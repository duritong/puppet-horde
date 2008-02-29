<?php
/**
 * $Horde: horde/scripts/cookie_login.php,v 1.2.2.3 2007/01/02 13:55:15 jan Exp $
 *
 * Copyright 2005-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('AUTH_HANDLER', true);
@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';

$auth = &Auth::singleton($conf['auth']['driver']);

// Check for COOKIE auth.
if (empty($_COOKIE['user']) || empty($_COOKIE['password']) ||
    !$auth->authenticate($_COOKIE['user'], array('password' => $_COOKIE['password']))) {
    header('Location: ' . Horde::applicationUrl('login.php?logout_reason=' . AUTH_REASON_BADLOGIN, true));
    exit;
}

$url = Util::getFormData('url');
if (empty($url)) {
    $url = Horde::applicationUrl('index.php', true);
}
header('Location: ' . $url);
