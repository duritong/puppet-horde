<?php
/**
 * $Horde: imp/index.php,v 2.73.10.4 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
$imp_configured = (@is_readable(IMP_BASE . '/config/conf.php') &&
                   @is_readable(IMP_BASE . '/config/mime_drivers.php') &&
                   @is_readable(IMP_BASE . '/config/prefs.php') &&
                   @is_readable(IMP_BASE . '/config/servers.php'));

if (!$imp_configured) {
    require IMP_BASE . '/../lib/Test.php';
    Horde_Test::configFilesMissing('IMP', IMP_BASE,
        array('conf.php', 'mime_drivers.php', 'prefs.php'),
        array('servers.php' => 'This file controls the default settings for IMP, and also defines the list of available servers if you are using the server list.'));
}

require IMP_BASE . '/redirect.php';
