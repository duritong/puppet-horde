<?php
/**
 * Login screen for IMP.
 *
 * $Horde: imp/login.php,v 2.222.2.10 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('AUTH_HANDLER', true);
@define('IMP_BASE', dirname(__FILE__));
$authentication = 'none';
require_once IMP_BASE . '/lib/base.php';
require IMP_BASE . '/config/servers.php';

/* Get an Auth object. */
$imp_auth = (Auth::getProvider() == 'imp');
$auth = &Auth::singleton($conf['auth']['driver']);
$logout_reason = $auth->getLogoutReason();

$autologin = Util::getFormData('autologin', false);
$actionID = (Util::getFormData('action') == 'compose') ? 'login_compose' : Util::getFormData('actionID');
$server_key = Util::getFormData('server_key');
$url_param = Util::getFormData('url');

/* Handle cases where we already have a session. */
if (!empty($_SESSION['imp']) && is_array($_SESSION['imp'])) {
    if ($logout_reason) {
        /* Log logout requests now. */
        if ($logout_reason == AUTH_REASON_LOGOUT) {
            $entry = sprintf('Logout for %s [%s] from {%s:%s}',
                             $_SESSION['imp']['uniquser'],
                             $_SERVER['REMOTE_ADDR'], $_SESSION['imp']['server'],
                             $_SESSION['imp']['port']);
        } else {
            $entry = $_SERVER['REMOTE_ADDR'] . ' ' . $auth->getLogoutReasonString();
        }
        Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_NOTICE);

        $language = (isset($prefs)) ? $prefs->getValue('language') : NLS::select();

        unset($_SESSION['imp']);

        /* Cleanup preferences. */
        if (isset($prefs)) {
            $prefs->cleanup($imp_auth);
        }

        if ($imp_auth) {
            Auth::clearAuth();
            @session_destroy();
            Horde::setupSessionHandler();
            @session_start();
        }

        NLS::setLang($language);

        /* Hook to preselect the correct language in the widget. */
        $_GET['new_lang'] = $language;

        $registry->loadPrefs('horde');
        $registry->loadPrefs();
    } else {
        require_once IMP_BASE . '/lib/Session.php';
        header('Location: ' . IMP_Session::getInitialUrl($actionID, false));
        exit;
    }
}

/* Log session timeouts. */
if ($logout_reason == AUTH_REASON_SESSION) {
    $entry = sprintf('Session timeout for client [%s]', $_SERVER['REMOTE_ADDR']);
    Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_NOTICE);

    /* Make sure everything is really cleared. */
    Auth::clearAuth();
    unset($_SESSION['imp']);
}

/* Redirect the user on logout if redirection is enabled. */
if ($logout_reason == AUTH_REASON_LOGOUT &&
    ($conf['user']['redirect_on_logout'] ||
     !empty($conf['auth']['redirect_on_logout']))) {
    if (!empty($conf['auth']['redirect_on_logout'])) {
        $url = Auth::addLogoutParameters($conf['auth']['redirect_on_logout'], AUTH_REASON_LOGOUT);
    } else {
        $url = Auth::addLogoutParameters($conf['user']['redirect_on_logout'], AUTH_REASON_LOGOUT);
    }
    if (!isset($_COOKIE[session_name()])) {
        $url = Util::addParameter($url, session_name(), session_id());
    }
    header('Location: ' . $url);
    exit;
}

/* Redirect the user if an alternate login page has been specified. */
if (!empty($conf['auth']['alternate_login'])) {
    $url = Auth::addLogoutParameters($conf['auth']['alternate_login']);
    if (!isset($_COOKIE[session_name()])) {
        $url = Util::addParameter($url, session_name(), session_id(), false);
    }
    if ($url_param) {
        $url = Util::addParameter($url, 'url', $url_param, false);
    }
    header('Location: ' . $url);
    exit;
} elseif ($conf['user']['alternate_login']) {
    $url = Auth::addLogoutParameters($conf['user']['alternate_login']);
    if (!isset($_COOKIE[session_name()])) {
        $url = Util::addParameter($url, session_name(), session_id(), false);
    }
    header('Location: ' . $url);
    exit;
}

/* Initialize the password key(s). If we are doing Horde auth as well,
 * make sure that the Horde auth key gets set. */
Secret::setKey('imp');
if ($imp_auth) {
    Secret::setKey('auth');
}

$used_servers = $servers;
if ($conf['server']['server_list'] != 'shown') {
    $server_key = Util::getFormData('server_key');
    if (is_null($server_key)) {
        /* Grab some default values from the first entry in
         * config/servers.php. */
        $server_key = IMP::getAutoLoginServer(true);
    }
    $used_servers = array($server_key => $servers[$server_key]);
    $autologin = Util::getFormData('autologin');
}

if (!$logout_reason && IMP::canAutoLogin($server_key, $autologin)) {
    $url = Horde::applicationUrl('redirect.php', true);
    $params = array('actionID' => 'login', 'autologin' => true);
    if (count($used_servers) == 1) {
        reset($used_servers);
        list($server_key, $curServer) = each($used_servers);
        $params['server_key'] = $server_key;
    }
    $url = Util::addParameter($url, $params, null, false);
    header('Location: ' . $url);
    exit;
}

$title = sprintf(_("Welcome to %s"), $registry->get('name', ($imp_auth) ? 'horde' : null));

if ($logout_reason && $imp_auth && $conf['menu']['always']) {
    $notification->push('setFocus();if (window.parent.frames.horde_menu) window.parent.frames.horde_menu.location.reload();', 'javascript');
} else {
    $notification->push('setFocus()', 'javascript');
}

$formAction = Horde::url('redirect.php', false, -1, true);
$formData = null;

$reason = $auth->getLogoutReasonString();

/* Add some javascript. */
Horde::addScriptFile('enter_key_trap.js', 'horde', true);

/* Do we need to do IE version detection? */
if (!Auth::getAuth() &&
    ($browser->getBrowser() == 'msie') &&
    ($browser->getMajor() >= 5)) {
    $ie_clientcaps = true;
}

require_once 'Horde/Menu.php';
require IMP_TEMPLATES . '/common-header.inc';
require IMP_TEMPLATES . '/login/login.inc';
if (@is_readable(IMP_BASE . '/config/motd.php')) {
    require IMP_BASE . '/config/motd.php';
}
require $registry->get('templates', 'horde') . '/common-footer.inc';
