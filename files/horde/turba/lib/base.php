<?php
/**
 * Turba base inclusion file.
 *
 * $Horde: turba/lib/base.php,v 1.62.10.17 2007/02/28 22:14:59 jan Exp $
 *
 * This file brings in all of the dependencies that every Turba script will
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
if (is_a(($pushed = $registry->pushApp('turba', !defined('AUTH_HANDLER'))), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = $GLOBALS['conf'];
@define('TURBA_TEMPLATES', $registry->get('templates'));

// Horde framework libraries.
require_once 'Horde/Help.php';
require_once 'Horde/History.php';

// Notification system.
$notification = &Notification::singleton();
$notification->attach('status');

// Find the base file path of Turba.
@define('TURBA_BASE', dirname(__FILE__) . '/..');

// Turba base library.
require_once TURBA_BASE . '/lib/Turba.php';
require_once TURBA_BASE . '/lib/Driver.php';

// Turba source and attribute configuration.
include TURBA_BASE . '/config/attributes.php';
include TURBA_BASE . '/config/sources.php';

// Turba's Horde_Share object.
require_once 'Horde/Share.php';
$GLOBALS['turba_shares'] = &Horde_Share::singleton($registry->getApp());

// See if any of our sources are configured to use Horde_Share.
$haveShare = false;
foreach ($cfgSources as $key => $cfg) {
    if (!empty($cfg['use_shares'])) {
        $haveShare = true;
    }
}
if ($haveShare) {
    $GLOBALS['cfgSources'] = Turba::getConfigFromShares($cfgSources);
} else {
    $GLOBALS['cfgSources'] = $cfgSources;
}
$GLOBALS['cfgSources'] = Turba::permissionsFilter($GLOBALS['cfgSources'], 'source');
$GLOBALS['attributes'] = $attributes;

// Build the directory sources select widget.
$default_source = Util::nonInputVar('source');
if (empty($default_source)) {
    $default_source = empty($_SESSION['turba']['source']) ? Turba::getDefaultAddressBook() : $_SESSION['turba']['source'];
    $default_source = Util::getFormData('source', $default_source);
}
$browse_source_options = '';
$browse_source_count = 0;
foreach (Turba::getAddressBooks() as $key => $curSource) {
    if (!empty($curSource['browse'])) {
        $selected = ($key == $default_source) ? ' selected="selected"' : '';
        $browse_source_options .= '<option value="' . htmlspecialchars($key) . '" ' . $selected . '>' .
            htmlspecialchars($curSource['title']) . '</option>';

        $browse_source_count++;

        if (empty($default_source)) {
            $default_source = $key;
        }
    }
}
$_SESSION['turba']['source'] = empty($cfgSources[$default_source]['browse']) ? Turba::getDefaultAddressBook() : $default_source;

// Only set $add_source_options if there is at least one editable address book
// that is not the current address book.
$addSources = Turba::getAddressBooks(PERMS_EDIT);
$copymove_source_options = '';
$copymoveSources = $addSources;
unset($copymoveSources[$default_source]);
foreach ($copymoveSources as $key => $curSource) {
    if ($key != $default_source) {
        $copymove_source_options .= '<option value="' . htmlspecialchars($key) . '">' .
            htmlspecialchars($curSource['title']) . '</option>';
    }
}

// Start compression, if requested.
Horde::compressOutput();
