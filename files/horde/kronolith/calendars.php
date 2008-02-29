<?php
/**
 * $Horde: kronolith/calendars.php,v 1.46.2.7 2007/12/19 18:26:23 chuck Exp $
 *
 * Copyright 2002-2007 Joel Vandal <jvandal@infoteck.qc.ca>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

// Exit if this isn't an authenticated user.
if (!Auth::getAuth()) {
    header('Location: ' . Horde::applicationUrl($prefs->getValue('defaultview') . '.php'));
    exit;
}

// Run through the action handlers.
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'save':
    $to_edit = Util::getFormData('edit_share');
    $id = Util::getFormData('id');
    if (empty($id)) {
        $notification->push(_("Calendars must have a name."), 'horde.error');
        break;
    }

    if (!strlen($to_edit)) {
        // Create new share.
        $cal = $kronolith_shares->newShare(md5(microtime()));
        if (is_a($cal, 'PEAR_Error')) {
            $result = $cal;
        } else {
            $cal->set('name', $id);
            $cal->set('desc', Util::getFormData('description', ''));
            $result = $kronolith_shares->addShare($cal);
        }

        if (is_a($result, 'PEAR_Error')) {
            $notification->push($result, 'horde.error');
        } else {
            $notification->push(sprintf(_("The calendar \"%s\" has been created."), $id), 'horde.success');
        }
    } else {
        $cal = &$kronolith_shares->getShare($to_edit);
        if (is_a($cal, 'PEAR_Error')) {
            $notification->push($cal, 'horde.error');
            break;
        }
        if ($cal->get('owner') != Auth::getAuth()) {
            $notification->push(_("You are not allowed to change this calendar."), 'horde.error');
            break;
        }

        $original_name = $cal->get('name');
        $cal->set('name', $id);
        $cal->set('desc', Util::getFormData('description', ''));

        if ($original_name != $id) {
            $result = $kronolith->rename($original_name, $id);
            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("Unable to rename \"%s\": %s"), $original_name, $result->getMessage()), 'horde.error');
                break;
            }
            $notification->push(sprintf(_("The calendar \"%s\" has been renamed to \"%s\"."), $original_name, $id), 'horde.success');
        } else {
            $notification->push(sprintf(_("The calendar \"%s\" has been saved."), $id), 'horde.success');
        }

        $cal->save();
    }

    header('Location: ' . Horde::applicationUrl('calendars.php', true));
    exit;

case 'delete':
    $to_delete = Util::getFormData('edit_share');
    $id = Util::getFormData('id');

    if ($to_delete == Auth::getAuth()) {
        $notification->push(sprintf(_("The calendar \"%s\" cannot be deleted."), $id), 'horde.warning');
        break;
    }

    if (isset($to_delete)) {
        $share = $kronolith_shares->getShare($to_delete);
        if (is_a($share, 'PEAR_Error')) {
            $notification->push(sprintf(_("Share not found: %s"), $share->getMessage()), 'horde.error');
            break;
        }
        if ($share->get('owner') != Auth::getAuth()) {
            $notification->push(_("You are not allowed to delete this calendar."), 'horde.error');
            break;
        }

        // Delete the calendar.
        $result = $kronolith->delete($to_delete);
        if (is_a($result, 'PEAR_Error')) {
            $notification->push(sprintf(_("Unable to delete \"%s\": %s"), $share->get('name'), $result->getMessage()), 'horde.error');
        } else {
            // Remove share and all groups/permissions.
            $kronolith_shares->removeShare($share);
            $notification->push(sprintf(_("The calendar \"%s\" has been deleted."), $share->get('name')), 'horde.success');
        }
    } else {
        $notification->push(_("You must select a calendar to be deleted."), 'horde.warning');
    }

    // Make sure we still own at least one calendar.
    if (count(Kronolith::listCalendars(true)) == 0) {
        // If this share doesn't exist then create it.
        if (!$kronolith_shares->exists(Auth::getAuth())) {
            require_once 'Horde/Identity.php';
            $identity = &Identity::singleton();
            $name = $identity->getValue('fullname');
            if (trim($name) == '') {
                $name = Auth::removeHook(Auth::getAuth());
            }
            $share = &$kronolith_shares->newShare(Auth::getAuth());
            $share->set('name', sprintf(_("%s's Calendar"), $name));
            $kronolith_shares->addShare($share);
            $all_calendars[Auth::getAuth()] = $share;
        }
    }

    header('Location: ' . Horde::applicationUrl('calendars.php', true));
    exit;
}

$remote_calendars = unserialize($prefs->getValue('remote_cals'));
$current_user = Auth::getAuth();
$my_calendars = array();
$shared_calendars = array();
foreach (Kronolith::listCalendars() as $id => $cal) {
    if ($cal->get('owner') == $current_user) {
        $my_calendars[$id] = $cal;
    } else {
        $shared_calendars[$id] = $cal;
    }
}

Horde::addScriptFile('popup.js', 'horde', true);
$title = _("My Calendars");
require KRONOLITH_TEMPLATES . '/common-header.inc';
require KRONOLITH_TEMPLATES . '/menu.inc';
require KRONOLITH_TEMPLATES . '/calendars/calendars.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
