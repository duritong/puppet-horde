<?php
/**
 * $Horde: horde/scripts/http_login_refer.php,v 1.3.12.4 2007/01/02 13:55:15 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

require_once '../lib/base.php';

$auth = &Auth::singleton($conf['auth']['driver']);

// Check for HTTP auth.
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    !$auth->authenticate($_SERVER['PHP_AUTH_USER'],
                         array('password' => $_SERVER['PHP_AUTH_PW']))) {

    header('WWW-Authenticate: Basic realm="' . $auth->getParam('realm') . '"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Forbidden');
}

if ($url = Util::getFormData('url')) {
    header('Location: ' . $url);
} else {
    header('Location: ' . Horde::applicationUrl('login.php'));
}
