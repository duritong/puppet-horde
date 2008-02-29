<?php
/**
 * $Horde: mimp/index.php,v 1.14.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('MIMP_BASE', dirname(__FILE__));
$mimp_configured = (is_readable(MIMP_BASE . '/config/conf.php') &&
                    is_readable(MIMP_BASE . '/config/prefs.php') &&
                    is_readable(MIMP_BASE . '/config/mime_drivers.php') &&
                    is_readable(MIMP_BASE . '/config/servers.php'));

if (!$mimp_configured) {
    /* MIMP isn't configured. */
    require MIMP_BASE . '/templates/index/notconfigured.inc';
    exit;
}

require MIMP_BASE . '/redirect.php';
