<?php
/**
 * Kronolith base inclusion file.
 *
 * This file brings in all of the dependencies that every Kronolith script
 * will need, and sets up objects that all scripts use.
 *
 * $Horde: kronolith/lib/base.php,v 1.117.8.10 2007/03/15 17:59:52 jan Exp $
 *
 * @package Kronolith
 */

/* Check for a prior definition of HORDE_BASE (perhaps by an auto_prepend_file
 * definition for site customization). */
if (!defined('HORDE_BASE')) {
    @define('HORDE_BASE', dirname(__FILE__) . '/../..');
}

/* Load the Horde Framework core, and set up inclusion paths. */
require_once HORDE_BASE . '/lib/core.php';

/* Registry. */
if (Util::nonInputVar('session_control') == 'none') {
    $registry = &Registry::singleton(HORDE_SESSION_NONE);
} else {
    $registry = &Registry::singleton();
}
if (is_a(($pushed = $registry->pushApp('kronolith', !defined('AUTH_HANDLER'))), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = &$GLOBALS['conf'];
@define('KRONOLITH_TEMPLATES', $registry->get('templates'));

/* Find the base file path of Kronolith. */
@define('KRONOLITH_BASE', dirname(__FILE__) . '/..');

/* Horde framework libraries. */
require_once 'Horde/Date.php';
require_once 'Horde/Help.php';
require_once 'Horde/History.php';

/* Notification system. */
$notification = &Notification::singleton();
$notification->attach('status');

/* Kronolith base library. */
require_once KRONOLITH_BASE . '/lib/Kronolith.php';

/* Categories. */
require_once 'Horde/Prefs/CategoryManager.php';
$GLOBALS['cManager'] = &new Prefs_CategoryManager();

/* PEAR Date_Calc. */
require_once 'Date/Calc.php';

/* Start compression, if requested. */
Horde::compressOutput();

/* Set the timezone variable, if available. */
NLS::setTimeZone();

/* Create a calendar backend object. */
$GLOBALS['kronolith'] = &Kronolith_Driver::factory();

/* Create a share instance. */
require_once 'Horde/Share.php';
$GLOBALS['kronolith_shares'] = &Horde_Share::singleton($registry->getApp());

/* Update preferences for which calendars to display. If the user
 * doesn't have any selected calendars to view then fall back to an
 * available calendar. */
$GLOBALS['display_calendars'] = unserialize($GLOBALS['prefs']->getValue('display_cals'));
$GLOBALS['display_remote_calendars'] = unserialize($GLOBALS['prefs']->getValue('display_remote_cals'));
if (($d_cal = Util::getFormData('display_cal')) !== null) {
    if (substr($d_cal, 0, 7) == 'remote_') {
        $d_cal = substr($d_cal, 7);
        if (in_array($d_cal, $GLOBALS['display_remote_calendars'])) {
            $key = array_search($d_cal, $GLOBALS['display_remote_calendars']);
            unset($GLOBALS['display_remote_calendars'][$key]);
        } else {
            $GLOBALS['display_remote_calendars'][] = $d_cal;
        }
    } else {
        if (in_array($d_cal, $GLOBALS['display_calendars'])) {
            $key = array_search($d_cal, $GLOBALS['display_calendars']);
            unset($GLOBALS['display_calendars'][$key]);
        } else {
            $GLOBALS['display_calendars'][] = $d_cal;
        }
    }
}

/* Make sure all shares exists now to save on checking later. */
$GLOBALS['all_calendars'] = Kronolith::listCalendars();
$calendar_keys = array_values($GLOBALS['display_calendars']);
$GLOBALS['display_calendars'] = array();
foreach ($calendar_keys as $id) {
    if (isset($GLOBALS['all_calendars'][$id])) {
        $GLOBALS['display_calendars'][] = $id;
    }
}

/* Make sure all the remote calendars still exist. */
$_temp = $GLOBALS['display_remote_calendars'];
$_all = unserialize($GLOBALS['prefs']->getValue('remote_cals'));
$GLOBALS['display_remote_calendars'] = array();
foreach ($_all as $id) {
    if (in_array($id['url'], $_temp)) {
        $GLOBALS['display_remote_calendars'][] = $id['url'];
    }
}
$GLOBALS['prefs']->setValue('display_remote_cals', serialize($GLOBALS['display_remote_calendars']));

if (count($GLOBALS['display_calendars']) == 0) {
    $cals = Kronolith::listCalendars(true);
    if (!Auth::getAuth()) {
        /* All calendars for guests. */
        $GLOBALS['display_calendars'] = array_keys($cals);
    } else {
        /* Make sure at least the default calendar is visible. */
        $default_calendar = Kronolith::getDefaultCalendar(PERMS_READ);
        if ($default_calendar) {
            $GLOBALS['display_calendars'] = array($default_calendar);
        }

        /* If the user's personal calendar doesn't exist, then create it. */
        if (!$GLOBALS['kronolith_shares']->exists(Auth::getAuth())) {
            require_once 'Horde/Identity.php';
            $identity = &Identity::singleton();
            $name = $identity->getValue('fullname');
            if (trim($name) == '') {
                $name = Auth::removeHook(Auth::getAuth());
            }
            $share = &$GLOBALS['kronolith_shares']->newShare(Auth::getAuth());
            $share->set('name', sprintf(_("%s's Calendar"), $name));
            $GLOBALS['kronolith_shares']->addShare($share);
            $GLOBALS['all_calendars'][Auth::getAuth()] = &$share;

            /* Make sure the personal calendar is displayed by default. */
            if (!in_array(Auth::getAuth(), $GLOBALS['display_calendars'])) {
                $GLOBALS['display_calendars'][] = Auth::getAuth();
            }

            /* Calendar auto-sharing with the user's groups */
            if ($conf['autoshare']['shareperms'] != 'none') {
                $perm_value = 0;
                switch ($conf['autoshare']['shareperms']) {
                case 'read':
                    $perm_value = PERMS_READ | PERMS_SHOW;
                    break;
                case 'edit':
                    $perm_value = PERMS_READ | PERMS_SHOW | PERMS_EDIT;
                    break;
                case 'full':
                    $perm_value = PERMS_READ | PERMS_SHOW | PERMS_EDIT | PERMS_DELETE;
                    break;
                }
                $perm = $share->getPermission();
                // Add the default perm, not added otherwise
                $perm->addUserPermission(Auth::getAuth(), PERMS_ALL, false);
                $groups = &Group::singleton();
                $group_list = $groups->getGroupMemberships(Auth::getAuth());
                if (!is_a($group_list, 'PEAR_Error')) {
                    foreach ($group_list as $group_id => $group_name) {
                        $perm->addGroupPermission($group_id, $perm_value, false);
                    }
                }
                $share->setPermission($perm);
                $share->save();
                $notification->push(sprintf(_("New calendar created and automatically shared with the following group(s): %s."), implode(', ', $group_list)), 'horde.success');
            }
        }
    }
}

$GLOBALS['prefs']->setValue('display_cals', serialize($GLOBALS['display_calendars']));
