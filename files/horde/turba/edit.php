<?php
/**
 * $Horde: turba/edit.php,v 1.70.4.8 2007/01/02 13:55:18 jan Exp $
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
require_once TURBA_BASE . '/lib/List.php';
require_once TURBA_BASE . '/lib/ListView.php';
require_once 'Horde/Form.php';
require_once 'Horde/Variables.php';

$renderer = &new Turba_Renderer();
$vars = &Variables::getDefaultVariables();
$source = $vars->get('source');
$original_source = $vars->get('original_source');
$key = $vars->get('key');
$groupedit = $vars->get('actionID') == 'groupedit';
$objectkeys = $vars->get('objectkeys');
$url = $vars->get('url', Horde::applicationUrl($prefs->getValue('initial_page'), true));

/* Edit the first of a list of contacts? */
if ($groupedit && (!$key || $key == '**search')) {
    if (!count($objectkeys)) {
        $notification->push(_("You must select at least one contact first."), 'horde.warning');
        header('Location: ' . $url);
        exit;
    }
    if ($key == '**search') {
        $original_source = $key;
    }
    list($source, $key) = explode(':', $objectkeys[0], 2);
    if (strpos($key, ':')) {
        list($owner, $key) = explode(':', $key, 2);
        $source .= ':' . $owner;
    }
    if (empty($original_source)) {
        $original_source = $source;
    }
    $vars->set('original_source', $original_source);
}

if ($source === null || !isset($cfgSources[$source])) {
    $notification->push(_("The contact you requested does not exist."));
    header('Location: ' . $url);
    exit;
}

$driver = &Turba_Driver::singleton($source);

/* Set the contact from the key requested. */
if (strpos($key, ':')) {
    list($owner, $key) = explode(':', $key, 2);
}
$object = $driver->getObject($key);
if (is_a($object, 'PEAR_Error')) {
    $notification->push($object->getMessage(), 'horde.error');
    header('Location: ' . Horde::applicationUrl($prefs->getValue('initial_page'), true));
    exit;
}

/* Check permissions on this contact. */
$editdone = false;
if (!$object->hasPermission(PERMS_EDIT)) {
   if (!$object->hasPermission(PERMS_READ)) {
       $notification->push(_("You do not have permission to view this contact."), 'horde.error');
       header('Location: ' . Horde::applicationUrl($prefs->getValue('initial_page'), true));
       exit;
   } else {
       $notification->push(_("You only have permission to view this contact."), 'horde.error');
       $uri = Horde::applicationUrl('display.php', true);
       $uri = Util::addParameter($uri, array('source' => $original_source, 'key' => $key), null, false);
       header('Location: ' . $uri);
       exit;
   }
}

$title = sprintf(_("Edit entry for %s"), $object->getValue('name'));

/* Get the form object. */
$form = &Horde_Form::singleton('', $vars, $title);
if ($groupedit) {
    $form->addHidden('', 'objectkeys', 'text', false);
    $form->addHidden('', 'original_source', 'text', false);
    $form->addHidden('', 'actionID', 'text', false);
    if ($source . ':' . $key == $objectkeys[0]) {
        /* First contact */
        $form->setButtons(_("Next"), _("Undo Changes"));
    } elseif ($source . ':' . $key == $objectkeys[count($objectkeys) - 1]) {
        /* Last contact */
        $form->setButtons(_("Previous"), _("Undo Changes"));
    } else {
        /* Inbetween */
        $form->setButtons(_("Previous"), _("Undo Changes"));
        $form->appendButtons(_("Next"));
    }
    $form->appendButtons(_("Finish"));
} else {
    $form->setButtons(_("Save"), _("Undo Changes"));
}
$form->addHidden('', 'url', 'text', false);
$form->addHidden('', 'source', 'text', true);
$form->addHidden('', 'key', 'text', false);

$renderer->setObject($object);
$view = &new Turba_ObjectView($object);
$view->setupForm($form);

if ($conf['documents']['type'] != 'none') {
    if (Util::getFormData('actionID') == 'delete_vfs') {
        $file = Util::getFormData('file');
        $result = $object->deleteFile($file);
        if (is_a($result, 'PEAR_Error')) {
            $notification->push($result, 'horde.error');
        } else {
            $notification->push(sprintf(_("The file \"%s\" has successfully been deleted."), $file), 'horde.success');
        }
    }

    $files = $object->listFiles();
    if (is_a($files, 'PEAR_Error')) {
        $notification->push($files, 'horde.error');
    } else {
        $v = $form->addVariable(_("Files"), '__vfs', 'html', false);
        $v->disable();
        $vars->set('__vfs', implode('<br />', array_map(array($object, 'vfsEditUrl'), $files)));
    }
    $form->addVariable(_("Add file"), 'vfs', 'file', false);
}

if ($form->validate($vars)) {
    /* Form valid, save data. */
    $form->getInfo($vars, $info);

    /* Update Contact. */
    foreach ($info['object'] as $info_key => $info_val) {
        if ($info_key != '__key') {
            $object->setValue($info_key, $info_val);
        }
    }
    $success = $object->store();
    $key = $object->getValue('__key');
    if (!is_a($success, 'PEAR_Error')) {
        if ($conf['documents']['type'] != 'none' && isset($info['vfs'])) {
            $success = $object->addFile($info['vfs']);
            if (is_a($success, 'PEAR_Error')) {
                $notification->push(sprintf(_("Entry for %s updated, but saving the uploaded file failed: %s"), $object->getValue('name'), $success->getMessage()), 'horde.warning');
            } else {
                $notification->push(sprintf(_("Entry for %s updated."), $object->getValue('name')), 'horde.success');
            }
        } else {
            $notification->push(sprintf(_("Entry for %s updated."), $object->getValue('name')), 'horde.success');
        }
        $form->setTitle(sprintf(_("Edit entry for %s"), $object->getValue('name')));
        $editdone = true;
    } else {
        Horde::logMessage($key, __FILE__, __LINE__, PEAR_LOG_ERR);
        $notification->push(sprintf(_("There was an error updating this entry: %s"), $success->getMessage()), 'horde.error');
    }
} else {
    $object_values = $vars->get('object');
    $object_keys = array_keys($object->attributes);
    foreach ($object_keys as $info_key) {
        if (!isset($object_values[$info_key])) {
            $object_values[$info_key] = $object->getValue($info_key);
        }
    }
    $vars->set('object', $object_values);
    $vars->set('url', $url);
    $vars->set('source', $source);
    $vars->set('key', $key);
    if ($groupedit) {
        $vars->set('objectkeys', $objectkeys);
        $vars->set('actionID', 'groupedit');
    }
}

if ($groupedit && $editdone) {
    $next_page = Horde::applicationUrl('edit.php', true);
    $next_page = Util::addParameter($next_page,
                                    array('source' => $source,
                                          'original_source' => $original_source,
                                          'objectkeys' => $objectkeys,
                                          'url' => $url,
                                          'actionID' => 'groupedit'),
                                    null, false);
    $objectkey = array_search($source . ':' . $key, $objectkeys);
    if ($vars->get('submitbutton') == _("Finish")) {
        $next_page = Horde::url('browse.php', true);
        if ($original_source == '**search') {
            $next_page = Util::addParameter($next_page, 'key', $original_source, false);
        } else {
            $next_page = Util::addParameter($next_page, 'source', $original_source, false);
        }
    } elseif ($vars->get('submitbutton') == _("Previous") && $source . ':' . $key != $objectkeys[0]) {
        /* Previous contact */
        $form->setButtons(_("Undo Changes"));
        list(, $previous_key) = explode(':', $objectkeys[$objectkey - 1]);
        $next_page = Util::addParameter($next_page, 'key', $previous_key, false);
        if ($form->getOpenSection()) {
            $next_page = Util::addParameter($next_page, '__formOpenSection', $form->getOpenSection(), false);
        }
    } elseif ($vars->get('submitbutton') == _("Next") &&
              $source . ':' . $key != $objectkeys[count($objectkeys) - 1]) {
        /* Next contact */
        list(, $next_key) = explode(':', $objectkeys[$objectkey + 1]);
        $next_page = Util::addParameter($next_page, 'key', $next_key, false);
        if ($form->getOpenSection()) {
            $next_page = Util::addParameter($next_page, '__formOpenSection', $form->getOpenSection(), false);
        }
    }
    header('Location: ' . $next_page);
    exit;
}

if ($editdone) {
    if (empty($info['url'])) {
        $uri = Util::addParameter('display.php', array('source' => $info['source'], 'key' => $key));
        $uri = Horde::applicationUrl($uri, true);
    } else {
        $uri = $info['url'];
    }

    header('Location: ' . $uri);
    exit;
}

if ($groupedit) {
    /* Read the columns to display from the preferences. */
    $sources = Turba::getColumns();
    $columns = isset($sources[$source]) ? $sources[$source] : array();
    $results = &new Turba_List($objectkeys);
    $listView = &new Turba_ListView($results, array('Group' => true));
}

require TURBA_TEMPLATES . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';
$form->renderActive($renderer, $vars, 'edit.php', 'post');

if (isset($listView) && is_object($listView)) {
    echo '<br />';
    $listView->displayPage();
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
