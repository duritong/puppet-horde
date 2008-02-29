<?php
/**
 * Mnemo base inclusion file.
 *
 * This file brings in all of the dependencies that every Mnemo
 * script will need and sets up objects that all scripts use.
 *
 * $Horde: mnemo/lib/base.php,v 1.46.10.9 2007/01/02 13:55:11 jan Exp $
 *
 * Copyright 2001-2007 Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @since   Mnemo 1.0
 * @package Mnemo
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
if (is_a(($pushed = $registry->pushApp('mnemo', !defined('AUTH_HANDLER'))), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = &$GLOBALS['conf'];
@define('MNEMO_TEMPLATES', $registry->get('templates'));

// Notification system.
$notification = &Notification::singleton();
$notification->attach('status');

// Find the base file path of Mnemo.
@define('MNEMO_BASE', dirname(__FILE__) . '/..');

// Mnemo libraries.
require_once MNEMO_BASE . '/lib/Mnemo.php';
require_once MNEMO_BASE . '/lib/Driver.php';

// Horde libraries.
require_once 'Horde/Text/Filter.php';
require_once 'Horde/Help.php';
require_once 'Horde/History.php';

// Start compression, if requested.
Horde::compressOutput();

// Create a share instance.
require_once 'Horde/Share.php';
$GLOBALS['mnemo_shares'] = &Horde_Share::singleton($registry->getApp());

// Update the preference for which notepads to display. If the user
// doesn't have any selected notepads for view then fall back to some
// available notepad.
$GLOBALS['display_notepads'] = unserialize($GLOBALS['prefs']->getValue('display_notepads'));
if (($d_note = Util::getFormData('display_notepad')) !== null) {
    if (in_array($d_note, $GLOBALS['display_notepads'])) {
        $key = array_search($d_note, $GLOBALS['display_notepads']);
        unset($GLOBALS['display_notepads'][$key]);
    } else {
        $GLOBALS['display_notepads'][] = $d_note;
    }
}

// Make sure all task lists exist now, to save on checking later.
$_temp = $GLOBALS['display_notepads'];
$_all = Mnemo::listNotepads();
$GLOBALS['display_notepads'] = array();
foreach ($_temp as $id) {
    if (isset($_all[$id])) {
        $GLOBALS['display_notepads'][] = $id;
    }
}

if (count($GLOBALS['display_notepads']) == 0) {
    $lists = Mnemo::listNotepads(true);
    if (!Auth::getAuth()) {
        /* All notepads for guests. */
        $GLOBALS['display_notepads'] = array_keys($lists);
    } else {
        /* Make sure at least the default notepad is visible. */
        $default_notepad = Mnemo::getDefaultNotepad(PERMS_READ);
        if ($default_notepad) {
            $GLOBALS['display_notepads'] = array($default_notepad);
        }

        /* If the user's personal notepad doesn't exist, then create it. */
        if (!$GLOBALS['mnemo_shares']->exists(Auth::getAuth())) {
            require_once 'Horde/Identity.php';
            $identity = &Identity::singleton();
            $name = $identity->getValue('fullname');
            if (trim($name) == '') {
                $name = Auth::removeHook(Auth::getAuth());
            }
            $share = &$GLOBALS['mnemo_shares']->newShare(Auth::getAuth());
            $share->set('name', sprintf(_("%s's Notepad"), $name));
            $GLOBALS['mnemo_shares']->addShare($share);

            /* Make sure the personal notepad is displayed by default. */
            if (!in_array(Auth::getAuth(), $GLOBALS['display_notepads'])) {
                $GLOBALS['display_notepads'][] = Auth::getAuth();
            }
        }
    }
}

$GLOBALS['prefs']->setValue('display_notepads', serialize($GLOBALS['display_notepads']));
