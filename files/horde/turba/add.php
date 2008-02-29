<?php
/**
 * The Turba script to add a new entry into an address book.
 *
 * $Horde: turba/add.php,v 1.54.4.12 2007/01/02 13:55:18 jan Exp $
 *
 * Copyright 2000-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you did
 * not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('TURBA_BASE', dirname(__FILE__));
require_once TURBA_BASE . '/lib/base.php';
require_once TURBA_BASE . '/lib/Renderer.php';
require_once TURBA_BASE . '/lib/ObjectView.php';
require_once TURBA_BASE . '/lib/Object.php';
require_once 'Horde/Variables.php';
require_once 'Horde/Form.php';
require_once 'Horde/Form/Action.php';

/* Get some variables. */
$vars = Variables::getDefaultVariables();
$source = $vars->get('source');
$url = $vars->get('url');

/* Exit with an error message if no sources to add to. */
if (empty($addSources)) {
    $notification->push(_("There are no writeable address books. None of the available address books are configured to allow you to add new entries to them. If you believe this is an error, please contact your system administrator."), 'horde.error');
    $url = (!empty($url) ? Horde::url($url, true) : Horde::applicationUrl('index.php', true));
    header('Location: ' . $url);
    exit;
}

/* Set up the form. */
$form = new Horde_Form($vars, _("New Contact"));
$form->setButtons(_("Save"), _("Reset to Defaults"));
$form->addHidden('', 'url', 'text', false);
$form->addHidden('', 'key', 'text', false);

/* Check if a source selection box is required. */
if (count($addSources) > 1) {
    /* Multiple sources, show a selection box. */
    $options = array();
    foreach ($addSources as $key => $config) {
        $options[$key] = $config['title'];
    }
    $v = &$form->addVariable(_("Choose an address book"), 'source', 'enum', true, false, null, array($options, true));
    $action = Horde_Form_Action::factory('submit');
    $v->setAction($action);
    $v->setOption('trackchange', true);
    if (is_null($vars->get('formname')) &&
        $vars->get($v->getVarName()) != $vars->get('__old_' . $v->getVarName())) {
        $notification->push(sprintf(_("Selected address book \"%s\"."), $addSources[$source]['title']), 'horde.message');
    }
    $form->setButtons(_("Add"));
} else {
    /* One source, no selection box but store the value in a hidden field. */
    $form->addHidden('', 'source', 'text', true);
    $source = key($addSources);
}

/* A source has been selected, connect and set up the fields. */
if ($source) {
    $driver = &Turba_Driver::singleton($source);
    if (is_a($driver, 'PEAR_Error')) {
        $notification->push(sprintf(_("Failed to access the address book: %s"), $driver->getMessage()), 'horde.error');
    } else {
        /* Check permissions. */
        $max_contacts = Turba::hasPermission($source . ':max_contacts', 'source');
        if ($max_contacts !== true &&
            $max_contacts <= $driver->countContacts()) {
            $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d contacts in \"%s\"."), $max_contacts, $cfgSources[$source]['title']), ENT_COMPAT, NLS::getCharset());
            if (!empty($conf['hooks']['permsdenied'])) {
                $message = Horde::callHook('_perms_hook_denied', array('turba:max_contacts'), 'horde', $message);
            }
            $notification->push($message, 'horde.error', array('content.raw'));
            $url = (!empty($url) ? Horde::url($url, true) : Horde::applicationUrl('index.php', true));
            header('Location: ' . $url);
            exit;
        }

        $object = new Turba_Object($driver);
        $view = new Turba_ObjectView($object);

        /* Get the form and set up the variables. */
        $view->setupForm($form);
        $vars->set('source', $source);
    }
}

/* Validate the form. */
if ($source && $form->validate($vars)) {
    /* Form valid, save data. */
    $form->getInfo($vars, $info);
    $source = $info['source'];
    foreach ($info['object'] as $info_key => $info_val) {
        $object->setValue($info_key, $info_val);
    }
    $object = $object->attributes;

    /* Get share information. */
    if ($driver->usingShares) {
        $object['__owner'] = $driver->share->get('uid');
    }

    /* Create Contact. */
    $key = $driver->add($object);
    if (!is_a($key, 'PEAR_Error')) {
        $vars->set('key', $key);
        $name = isset($object['name']) ? $object['name'] : _("Address book entry");
        $notification->push(sprintf(_("%s added."), $name), 'horde.success');
        if (empty($info['url'])) {
            $uri = Horde::applicationUrl('display.php', true);
            $uri = Util::addParameter($uri, array('source' => $info['source'], 'key' => $key), null, false);
        } else {
            $uri = $info['url'];
        }
        header('Location: ' . $uri);
        exit;
    }

    Horde::logMessage($key, __FILE__, __LINE__, PEAR_LOG_ERR);
    $notification->push(_("There was an error adding the new contact. Contact your system administrator for further help.") . $key->getMessage(), 'horde.error');
}

$title = _("New Contact");
require TURBA_TEMPLATES . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';

/* Render the form. */
$renderer = new Turba_Renderer();
$form->renderActive($renderer, $vars, 'add.php', 'post');

require $registry->get('templates', 'horde') . '/common-footer.inc';
