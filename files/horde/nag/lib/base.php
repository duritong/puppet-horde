<?php
/**
 * Nag base inclusion file.
 *
 * $Horde: nag/lib/base.php,v 1.75.10.5 2006/06/28 22:02:33 jan Exp $
 *
 * This file brings in all of the dependencies that every Nag
 * script will need and sets up objects that all scripts use.
 */

// Check for a prior definition of HORDE_BASE (perhaps by an
// auto_prepend_file definition for site customization).
if (!defined('HORDE_BASE')) {
    @define('HORDE_BASE', dirname(__FILE__) . '/../..');
}

// Load the Horde Framework core, and set up inclusion paths.
require_once HORDE_BASE . '/lib/core.php';

// Registry.
$registry = &Registry::singleton();
if (is_a(($pushed = $registry->pushApp('nag', !defined('AUTH_HANDLER'))), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = &$GLOBALS['conf'];
@define('NAG_TEMPLATES', $registry->get('templates'));

// Find the base file path of Nag.
@define('NAG_BASE', dirname(__FILE__) . '/..');

// Notification system.
require_once NAG_BASE . '/lib/Notification/Listener/status.php';
$notification = &Notification::singleton();
$notification->attach('status', null, 'Notification_Listener_status_nag');

// Nag base libraries.
require_once NAG_BASE . '/lib/Nag.php';
require_once NAG_BASE . '/lib/Driver.php';

// Horde libraries.
require_once 'Horde/Help.php';
require_once 'Horde/History.php';

// Start compression.
Horde::compressOutput();

// Set the timezone variable.
NLS::setTimeZone();

// Create a share instance.
require_once 'Horde/Share.php';
$GLOBALS['nag_shares'] = &Horde_Share::singleton($registry->getApp());

// Update the preference for what task lists to display. If the user
// doesn't have any selected task lists for view then fall back to
// some available list.
$GLOBALS['display_tasklists'] = @unserialize($GLOBALS['prefs']->getValue('display_tasklists'));
if (!$GLOBALS['display_tasklists']) {
    $GLOBALS['display_tasklists'] = array();
}
if (($d_task = Util::getFormData('display_tasklist')) !== null) {
    if (in_array($d_task, $GLOBALS['display_tasklists'])) {
        $key = array_search($d_task, $GLOBALS['display_tasklists']);
        unset($GLOBALS['display_tasklists'][$key]);
    } else {
        $GLOBALS['display_tasklists'][] = $d_task;
    }
}

// Make sure all task lists exist now, to save on checking later.
$_temp = $GLOBALS['display_tasklists'];
$_all = Nag::listTasklists();
$GLOBALS['display_tasklists'] = array();
foreach ($_temp as $id) {
    if (isset($_all[$id])) {
        $GLOBALS['display_tasklists'][] = $id;
    }
}

if (count($GLOBALS['display_tasklists']) == 0) {
    $lists = Nag::listTasklists(true);
    if (!Auth::getAuth()) {
        /* All tasklists for guests. */
        $GLOBALS['display_tasklists'] = array_keys($lists);
    } else {
        /* Make sure at least the default tasklist is visible. */
        $default_tasklist = Nag::getDefaultTasklist(PERMS_READ);
        if ($default_tasklist) {
            $GLOBALS['display_tasklists'] = array($default_tasklist);
        }

        /* If the user's personal tasklist doesn't exist, then create it. */
        if (!$GLOBALS['nag_shares']->exists(Auth::getAuth())) {
            require_once 'Horde/Identity.php';
            $identity = &Identity::singleton();
            $name = $identity->getValue('fullname');
            if (trim($name) == '') {
                $name = Auth::removeHook(Auth::getAuth());
            }
            $share = &$GLOBALS['nag_shares']->newShare(Auth::getAuth());
            $share->set('name', sprintf(_("%s's Task List"), $name));
            $GLOBALS['nag_shares']->addShare($share);

            /* Make sure the personal tasklist is displayed by default. */
            if (!in_array(Auth::getAuth(), $GLOBALS['display_tasklists'])) {
                $GLOBALS['display_tasklists'][] = Auth::getAuth();
            }
        }
    }
}

$GLOBALS['prefs']->setValue('display_tasklists', serialize($GLOBALS['display_tasklists']));
