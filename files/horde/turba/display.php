<?php
/**
 * $Horde: turba/display.php,v 1.64.2.10 2007/01/02 13:55:18 jan Exp $
 *
 * Copyright 2000-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('TURBA_BASE', dirname(__FILE__));
require_once TURBA_BASE . '/lib/base.php';
require_once TURBA_BASE . '/lib/Renderer.php';
require_once TURBA_BASE . '/lib/ObjectView.php';
require_once 'Horde/Form.php';
require_once 'Horde/Variables.php';

$renderer = &new Turba_Renderer();
$vars = &Variables::getDefaultVariables();

$source = $vars->get('source');
if (!isset($GLOBALS['cfgSources'][$source])) {
    $notification->push(_("The contact you requested does not exist."));
    header('Location: ' . Horde::applicationUrl($prefs->getValue('initial_page'), true));
    exit;
}

$driver = &Turba_Driver::singleton($source);

/* Set the contact from the key requested. */
$object = null;
$uid = $vars->get('uid');
if (!empty($uid)) {
    $search = $driver->search(array('__uid' => $uid));
    if (!is_a($search, 'PEAR_Error') && $search->count()) {
        $object = $search->next();
        $key = $object->getValue('__key');
    }
}
if (!$object || is_a($object, 'PEAR_Error')) {
    $key = $vars->get('key');
    $object = $driver->getObject($key);
    if (is_a($object, 'PEAR_Error')) {
        $notification->push($object->getMessage(), 'horde.error');
        header('Location: ' . Horde::applicationUrl($prefs->getValue('initial_page'), true));
        exit;
    }
}

/* Check permissions on this contact. */
if (!$object->hasPermission(PERMS_READ)) {
    $notification->push(_("You do not have permission to view this contact."), 'horde.error');
    header('Location: ' . Horde::applicationUrl($prefs->getValue('initial_page'), true));
    exit;
}

$renderer->setObject($object);
$view = &new Turba_ObjectView($object);

$values = array();
/* Get the values through the Turba_Object class. */
foreach ($object->driver->getCriteria() as $info_key => $info_val) {
    $values[$info_key] = $object->getValue($info_key);
}

/* Get the contact's history. */
if ($object->getValue('__uid')) {
    $history = &Horde_History::singleton();
    $log = $history->getHistory('turba:' . ($object->getValue('__owner') ? $object->getValue('__owner') : Auth::getAuth()) . ':' . $object->getValue('__uid'));
    if ($log && !is_a($log, 'PEAR_Error')) {
        foreach ($log->getData() as $entry) {
            switch ($entry['action']) {
            case 'add':
                $view->set('created', true);
                $values['__created'] = strftime($prefs->getValue('date_format'), $entry['ts']) . ' ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:i a', $entry['ts']);
                break;

            case 'modify':
                $view->set('modified', true);
                $values['__modified'] = strftime($prefs->getValue('date_format'), $entry['ts']) . ' ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:i a', $entry['ts']);
                break;
            }
        }
    }
}

$vars = new Variables(array('object' => $values));
$form = &Horde_Form::singleton('', $vars);
$form->addHidden('', 'url', 'text', false);
$form->addHidden('', 'source', 'text', true);
$form->addHidden('', 'key', 'text', false);
$view->setupForm($form);

if ($conf['documents']['type'] != 'none') {
    $files = $object->listFiles();
    if (is_a($files, 'PEAR_Error')) {
        $notification->push($files, 'horde.error');
    } else {
        $form->addVariable(_("Files"), '__vfs', 'html', false);
        $vars->set('__vfs', implode('<br />', array_map(array($object, 'vfsDisplayUrl'), $files)));
    }
}

$title = $vars->get('object[name]');
if (!empty($title) || $title == '0') {
    $form->setTitle($title);
}

if (!empty($conf['comments']['allow']) && $registry->hasMethod('forums/doComments')) {
    $comments = $registry->call('forums/doComments', array('turba', $source . '.' . $key, 'commentCallback'));
}

require TURBA_TEMPLATES . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';
$form->renderInactive($renderer, $vars);

if (!empty($comments['threads'])) {
    echo '<br />' . $comments['threads'];
}
if (!empty($comments['comments'])) {
    echo '<br />' . $comments['comments'];
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
