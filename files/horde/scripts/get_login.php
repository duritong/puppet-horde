<?php
/**
 * $Horde: horde/scripts/get_login.php,v 1.3.10.4 2007/01/02 13:55:15 jan Exp $
 *
 * Copyright 2004-2007 Joel Vandal <jvandal@infoteck.qc.ca>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('AUTH_HANDLER', true);
@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';

$auth = &Auth::singleton($conf['auth']['driver']);

// Check for GET auth.
if (empty($_GET['user']) || !$auth->authenticate($_GET['user'], array('password' => $_GET['pass']))) {
    header('Location: ' . Horde::applicationUrl('login.php?logout_reason=logout'));
    exit;
}

header('Location: ' . Horde::applicationUrl('index.php'));
