<?php
/**
 * Vacation base inclusion file.
 *
 * This file brings in all of the dependencies that every Vacation
 * script will need, and sets up objects that all scripts use.
 *
 * $Horde: vacation/lib/base.php,v 1.35.2.3 2007/01/02 13:55:21 jan Exp $
 *
 * Copyright 2001-2007 Eric Rostetter <eric.rostetter@physics.utexas.edu>
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
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
if (is_a(($pushed = $registry->pushApp('vacation', !defined('AUTH_HANDLER'))), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = &$GLOBALS['conf'];
@define('VACATION_TEMPLATES', $registry->get('templates'));

// Notification system.
$notification = &Notification::singleton();
$notification->attach('status');

// Find the base file path of Vacation.
@define('VACATION_BASE', dirname(__FILE__) . '/..');

// Help.
require_once 'Horde/Help.php';

/**
 * Default strings for vacation messages, just to have them in the source for
 * localization reasons.
 */
_("On vacation message");
_("I'm on vacation and will not be reading my mail for a while.");
_("Your mail will be dealt with when I return.");
