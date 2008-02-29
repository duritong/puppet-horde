<?php
/**
 * Ingo base inclusion file.
 * This file brings in all of the dependencies that every Ingo
 * script will need and sets up objects that all scripts use.
 *
 * The following global variables are declared by this script:
 *   $ingo_storage - The Ingo_Storage:: object to use for storing rules.
 *
 * $Horde: ingo/lib/base.php,v 1.56.10.2 2006/01/31 20:00:24 jan Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
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
if (is_a(($pushed = $registry->pushApp('ingo', !defined('AUTH_HANDLER'))), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = &$GLOBALS['conf'];
@define('INGO_TEMPLATES', $registry->get('templates'));

// Notification system.
$notification = &Notification::singleton();
$notification->attach('status');

// Redirect the user to the Horde login page if they haven't
// authenticated.
if (!Auth::isAuthenticated() && !defined('AUTH_HANDLER')) {
    Horde::authenticationFailureRedirect();
}

// Find the base file path of Ingo.
@define('INGO_BASE', dirname(__FILE__) . '/..');

// Ingo base library.
require_once INGO_BASE . '/lib/Ingo.php';

// Other Horde libraries needed.
require_once 'Horde/Help.php';

// Start compression.
Horde::compressOutput();

// Load the Ingo_Storage driver. It appears in the global variable
// $ingo_storage.
require_once INGO_BASE . '/lib/Storage.php';
$GLOBALS['ingo_storage'] = &Ingo_Storage::singleton();

// Create the ingo session (if needed).
if (!isset($_SESSION['ingo']) || !is_array($_SESSION['ingo'])) {
    require_once INGO_BASE . '/lib/Session.php';
    Ingo_Session::createSession();
}
