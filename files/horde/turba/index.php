<?php
/**
 * $Horde: turba/index.php,v 1.29.10.8 2007/01/02 13:55:18 jan Exp $
 *
 * Copyright 2000-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you did
 * not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('TURBA_BASE', dirname(__FILE__));
$turba_configured = (@is_readable(TURBA_BASE . '/config/conf.php') &&
                     @is_readable(TURBA_BASE . '/config/attributes.php') &&
                     @is_readable(TURBA_BASE . '/config/prefs.php') &&
                     @is_readable(TURBA_BASE . '/config/mime_drivers.php') &&
                     @is_readable(TURBA_BASE . '/config/sources.php'));

if (!$turba_configured) {
    require TURBA_BASE . '/../lib/Test.php';
    Horde_Test::configFilesMissing('Turba', TURBA_BASE,
        array('conf.php', 'prefs.php', 'mime_drivers.php', 'sources.php'),
        array('attributes.php' => 'This file defines the Turba global attribute names and types - names, email addresses, etc.'));
}

require_once TURBA_BASE . '/lib/base.php';
require TURBA_BASE . '/' . ($browse_source_count
                            ? basename($prefs->getValue('initial_page'))
                            : 'search.php');
