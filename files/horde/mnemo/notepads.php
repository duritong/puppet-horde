<?php
/**
 * $Horde: mnemo/notepads.php,v 1.31.2.7 2007/12/19 18:26:23 chuck Exp $
 *
 * Copyright 2002-2007 Joel Vandal <jvandal@infoteck.qc.ca>
 * Copyright 2002-2007 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Joel Vandal <jvandal@infoteck.qc.ca>
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   Mnemo 2.0
 * @package Mnemo
 */

@define('MNEMO_BASE', dirname(__FILE__));
require_once MNEMO_BASE . '/lib/base.php';

/* Exit if this isn't an authenticated user. */
if (!Auth::getAuth()) {
    require MNEMO_BASE . '/list.php';
    exit;
}

/* Run through the action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'save':
    $to_edit = Util::getFormData('edit_share');
    $id = Util::getFormData('id');
    if (empty($id)) {
        $notification->push(_("Notepads must have a name."), 'horde.error');
        break;
    }

    $notepad = '';
    if (!strlen($to_edit)) {
        /* Create New Share. */
        $notepad = $GLOBALS['mnemo_shares']->newShare(md5(microtime()));
        if (is_a($notepad, 'PEAR_Error')) {
            $result = $notepad;
        } else {
            $notepad->set('name', $id);
            $notepad->set('desc', Util::getFormData('description', ''));
            $result = $GLOBALS['mnemo_shares']->addShare($notepad);
        }

        if (is_a($result, 'PEAR_Error')) {
            $notification->push(sprintf(_("The notepad \"%s\" couldn't be created: %s"), $id, $result->getMessage()), 'horde.error');
        } else {
            $notification->push(sprintf(_("The notepad \"%s\" has been created."), $id), 'horde.success');
        }
    } else {
        $notepad = &$GLOBALS['mnemo_shares']->getShare($to_edit);
        if (is_a($notepad, 'PEAR_Error')) {
            $notification->push($notepad, 'horde.error');
        } elseif ($notepad->get('owner') != Auth::getAuth()) {
            $notification->push(_("You are not allowed to change this notepad."), 'horde.error');
        } else {
            $notepad->set('name', $id);
            $notepad->set('desc', Util::getFormData('description', ''));
            $result = $notepad->save();
            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("The notepad \"%s\" couldn't be saved: %s"), $id, $result->getMessage()), 'horde.error');
            } else {
                $notification->push(sprintf(_("The notepad \"%s\" has been saved."), $id), 'horde.success');
            }
        }
    }

    unset($to_edit);
    break;

case 'delete':
    $to_delete = Util::getFormData('edit_share');
    if (!$to_delete) {
        $notification->push(_("You must select a notepad to be deleted."), 'horde.warning');
        break;
    }

    $share = $GLOBALS['mnemo_shares']->getShare($to_delete);
    if (is_a($share, 'PEAR_Error')) {
        $notification->push($share, 'horde.error');
        break;
    }
    if ($share->get('owner') != Auth::getAuth()) {
        $notification->push(("You are not allowed to delete this notepad."), 'horde.error');
        break;
    }

    /* Delete the notepad. */
    require_once MNEMO_BASE . '/lib/Driver.php';
    $storage = &Mnemo_Driver::singleton($to_delete);
    $result = $storage->deleteAll();
    if (is_a($result, 'PEAR_Error')) {
        $notification->push(sprintf(_("Unable to delete \"%s\": %s"), $share->get('name'), $result->getMessage()), 'horde.error');
    } else {
        /* Remove share and all groups/permissions. */
        $GLOBALS['mnemo_shares']->removeShare($share);
        $notification->push(sprintf(_("The notepad \"%s\" has been deleted."), $share->get('name')), 'horde.success');
    }

    /* Make sure we still own at least one notepad. */
    if (!count(Mnemo::listNotepads(true))) {
        /* If this share doesn't exist then create it. */
        if (!$GLOBALS['mnemo_shares']->exists(Auth::getAuth())) {
            require_once 'Horde/Identity.php';
            $identity = &Identity::singleton();
            $name = $identity->getValue('fullname');
            if (trim($name) == '') {
                $name = Auth::removeHook(Auth::getAuth());
            }
            $share = $GLOBALS['mnemo_shares']->newShare(Auth::getAuth());
            $share->set('name', sprintf(_("%s's Notepad"), $name));
            $GLOBALS['mnemo_shares']->addShare($share);
        }
    }
    break;
}

/* Personal Note Lists */
$personal_notepads = Mnemo::listNotepads(true);

Horde::addScriptFile('popup.js', 'horde', true);
$title = _("Note Lists");
require MNEMO_TEMPLATES . '/common-header.inc';
require MNEMO_TEMPLATES . '/menu.inc';
require MNEMO_TEMPLATES . '/notepads/notepads.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
