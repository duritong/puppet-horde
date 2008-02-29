<?php
/**
 * $Horde: nag/index.php,v 1.16.10.4 2007/01/02 13:55:12 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

define('NAG_BASE', dirname(__FILE__));
$nag_configured = (@is_readable(NAG_BASE . '/config/conf.php') &&
                   @is_readable(NAG_BASE . '/config/prefs.php'));

if (!$nag_configured) {
    require NAG_BASE . '/../lib/Test.php';
    Horde_Test::configFilesMissing('Nag', NAG_BASE,
        array('conf.php', 'prefs.php'));
}

require NAG_BASE . '/list.php';
