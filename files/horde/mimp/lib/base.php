<?php
/**
 * $Horde: mimp/lib/base.php,v 1.37.2.1 2007/01/02 13:55:09 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * MIMP base inclusion file.
 *
 * This file brings in all of the dependencies that every MIMP script will
 * need, and sets up objects that all scripts use.
 */

// Check for a prior definition of HORDE_BASE (perhaps by an auto_prepend_file
// definition for site customization).
if (!defined('HORDE_BASE')) {
    @define('HORDE_BASE', dirname(__FILE__) . '/../..');
}

// Load the Horde Framework core, and set up inclusion paths.
require_once HORDE_BASE . '/lib/core.php';

// Registry.
$registry = &Registry::singleton();
if (is_a(($pushed = $registry->pushApp('mimp', !defined('AUTH_HANDLER'))), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = &$GLOBALS['conf'];
@define('MIMP_TEMPLATES', $registry->get('templates'));

// Notification system.
require_once 'Horde/Notification/Listener/mobile.php';
$notification = &Notification::singleton();
$GLOBALS['l'] = &$notification->attach('status', null, 'Notification_Listener_mobile');

// If MIMP isn't responsible for Horde auth, and no one is logged into Horde,
// redirect to the login screen.
if (!(Auth::isAuthenticated() || (Auth::getProvider() == 'mimp'))) {
    Horde::authenticationFailureRedirect();
}

// Find the base file path of MIMP.
@define('MIMP_BASE', dirname(__FILE__) . '/..');

// MIMP base library.
require_once MIMP_BASE . '/lib/MIMP.php';

// Horde libraries.
require_once 'Horde/Secret.php';

// Mobile markup renderer.
require_once 'Horde/Mobile.php';
$GLOBALS['m'] = new Horde_Mobile(null, Util::getFormData('debug'));
if (Util::getFormData('debug')) {
    $GLOBALS['m']->set('debug', true);
}

// Start compression.
if (!Util::nonInputVar('no_compress')) {
    Horde::compressOutput();
}

$authentication = Util::nonInputVar('authentication');
if ($authentication === null) {
    $authentication = 0;
}
if ($authentication !== 'none') {
    MIMP::checkAuthentication($authentication);
}

// Catch c-client errors.
register_shutdown_function('imap_alerts');
register_shutdown_function('imap_errors');
