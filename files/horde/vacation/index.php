<?php
/**
 * $Horde: vacation/index.php,v 1.18.2.2 2007/01/02 13:55:21 jan Exp $
 *
 * Copyright 2001-2007 Eric Rostetter <eric.rostetter@physics.utexas.edu>
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

@define('VACATION_BASE', dirname(__FILE__));
$vacation_configured = (is_readable(VACATION_BASE . '/config/conf.php'));

if (!$vacation_configured) {
    require VACATION_BASE . '/../lib/Test.php';
    Horde_Test::configFilesMissing('Vacation', VACATION_BASE, 'conf.php');
}

require VACATION_BASE . '/main.php';
