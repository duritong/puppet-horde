<?php
/**
 * IMP base inclusion file. This file brings in all of the dependencies that
 * every IMP script will need, and sets up objects that all scripts use.
 *
 * The following variables, defined in the script that calls this one, are
 * used:
 *   $authentication   - The type of authentication to use
 *   $no_compress      - Controls whether the page should be compressed
 *   $session_control  - Sets special session control limitations
 *
 * $Horde: imp/lib/base.php,v 1.79.10.15 2007/01/02 13:54:56 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

// Check for a prior definition of HORDE_BASE (perhaps by an
// auto_prepend_file definition for site customization).
if (!defined('HORDE_BASE')) {
    @define('HORDE_BASE', dirname(__FILE__) . '/../..');
}

// Load the Horde Framework core, and set up inclusion paths.
require_once HORDE_BASE . '/lib/core.php';

$session_control = Util::nonInputVar('session_control');
switch ($session_control) {
case 'netscape':
    if ($browser->isBrowser('mozilla')) {
        session_cache_limiter('private, must-revalidate');
    }
    break;
}

// Registry.
if ($session_control == 'none') {
    $registry = &Registry::singleton(HORDE_SESSION_NONE);
} elseif ($session_control == 'readonly') {
    $registry = &Registry::singleton(HORDE_SESSION_READONLY);
} else {
    $registry = &Registry::singleton();
}

// We explicitly do not check application permissions for the compose
// and recompose pages, since those are handled below and need to fall
// through to IMP-specific code.
$auth_check = !(defined('AUTH_HANDLER') || strstr($_SERVER['PHP_SELF'], 'compose.php'));
if (is_a(($pushed = $registry->pushApp('imp', $auth_check)), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = &$GLOBALS['conf'];
@define('IMP_TEMPLATES', $registry->get('templates'));

// Find the base file path of IMP.
@define('IMP_BASE', dirname(__FILE__) . '/..');

// Notification system.
require_once IMP_BASE . '/lib/Notification/Listener/status.php';
$notification = &Notification::singleton();
$notification->attach('status', null, 'Notification_Listener_status_imp');
// BC check.
if (@include_once 'Horde/Notification/Listener/audio.php') {
    $notification->attach('audio');
}

// IMP base library.
require_once IMP_BASE . '/lib/IMP.php';

// If IMP isn't responsible for Horde auth, and no one is logged into
// Horde, redirect to the login screen. If this is a compose window
// that just timed out, give the user a chance to recover their
// message.
if (!(Auth::isAuthenticated() || (Auth::getProvider() == 'imp'))) {
    if (strstr($_SERVER['PHP_SELF'], 'recompose.php')) {
        // Let this fall through; otherwise we create an infinite
        // inclusion loop.
    } elseif (strstr($_SERVER['PHP_SELF'], 'compose.php')) {
        require IMP_BASE . '/recompose.php';
        exit;
    } else {
        Horde::authenticationFailureRedirect();
    }
}

// Horde libraries.
require_once 'Horde/Secret.php';

// Help.
require_once 'Horde/Help.php';

// Start compression.
if (!Util::nonInputVar('no_compress')) {
    Horde::compressOutput();
}

$authentication = Util::nonInputVar('authentication');
if ($authentication === null) {
    $authentication = 0;
}
if ($authentication !== 'none') {
    // If we've gotten to this point and have valid login credentials
    // but don't actually have an IMP session, then we need to go
    // through redirect.php to ensure that everything gets set up
    // properly. Single-signon and transparent authentication setups
    // are likely to trigger this case.
    if (empty($_SESSION['imp'])) {
        if (strstr($_SERVER['PHP_SELF'], 'compose.php')) {
            require IMP_BASE . '/recompose.php';
        } else {
            require IMP_BASE . '/redirect.php';
        }
        exit;
    }

    if (strstr($_SERVER['PHP_SELF'], 'compose.php')) {
        if (!IMP::checkAuthentication($authentication, true)) {
            require IMP_BASE . '/recompose.php';
            exit;
        }
    } else {
        IMP::checkAuthentication($authentication);
    }
}

if ((IMP::loginTasksFlag() === 2) &&
    !defined('AUTH_HANDLER') &&
    !strstr($_SERVER['PHP_SELF'], 'maintenance.php')) {
    require_once IMP_BASE . '/lib/Session.php';
    IMP_Session::loginTasks();
}

// Set default message character set, if necessary
if (isset($prefs) && ($def_charset = $prefs->getValue('default_msg_charset'))) {
    $GLOBALS['mime_structure']['default_charset'] = $def_charset;
    $GLOBALS['mime_headers']['default_charset'] = $def_charset;
}

// Catch c-client errors.
register_shutdown_function('imap_errors');
register_shutdown_function('imap_alerts');
