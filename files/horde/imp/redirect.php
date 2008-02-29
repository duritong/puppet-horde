<?php
/**
 * $Horde: imp/redirect.php,v 1.116.2.16 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

function _framesetUrl($url)
{
    if (Util::getFormData('load_frameset')) {
        $full_url = Horde::applicationUrl($GLOBALS['registry']->get('webroot', 'horde') . '/index.php', true);
        $url = Util::addParameter($full_url, 'url', $url, false);
    }
    return $url;
}

function _newSessionUrl($actionID, $isLogin)
{
    $url = '';
    $addActionID = true;

    if (Util::getFormData('url')) {
        $url = Horde::url(Util::removeParameter(Util::getFormData('url'), session_name()), true);
    } elseif (Auth::getProvider() == 'imp') {
        $url = Horde::applicationUrl($GLOBALS['registry']->get('webroot', 'horde') . '/', true);
        /* Force the initial page to IMP if we're logging in to compose a
         * message. */
        if ($actionID == 'login_compose') {
            $url = Util::addParameter($url, 'url', IMP_Session::getInitialUrl('login_compose', false));
            $addActionID = false;
        }
    } else {
        $url = IMP_Session::getInitialUrl($actionID, false);
        if ($isLogin) {
            /* Don't show popup window in initial page. */
            $url = Util::addParameter($url, 'no_newmail_popup', 1, false);
        }
    }

    if ($addActionID && $actionID) {
        /* Preserve the actionID. */
        $url = Util::addParameter($url, 'actionID', $actionID, false);
    }

    return $url;
}

function _redirect($url)
{
    if ($GLOBALS['browser']->isBrowser('msie') &&
        $GLOBALS['conf']['use_ssl'] == 3 &&
        strlen($url) < 160) {
        header('Refresh: 0; URL=' . $url);
    } else {
        header('Location: ' . $url);
    }
    exit;
}

@define('AUTH_HANDLER', true);
@define('IMP_BASE', dirname(__FILE__));
$authentication = 'none';
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Session.php';
require_once 'Horde/Maintenance.php';

$actionID = (Util::getFormData('action') == 'compose') ? 'login_compose' : Util::getFormData('actionID');
$autologin = Util::getFormData('autologin');
$imapuser = empty($autologin) ? Util::getPost('imapuser') : Auth::getBareAuth();
$pass = empty($autologin) ? Util::getPost('pass') : Auth::getCredential('password');
$isLogin = IMP::loginTasksFlag();

/* If we are returning from Maintenance processing. */
if (Util::getFormData(MAINTENANCE_DONE_PARAM)) {
    /* Finish up any login tasks we haven't completed yet. */
    IMP_Session::loginTasks();

    _redirect(_framesetUrl(_newSessionUrl($actionID, $isLogin)));
}

/* If we already have a session: */
if (isset($_SESSION['imp']) && is_array($_SESSION['imp'])) {
    /* Make sure that if a username was specified, it is the current
     * username. */
    if (($imapuser !== null && ($imapuser != $_SESSION['imp']['user'])) ||
        ($pass !== null && ($pass != Secret::read(Secret::getKey('imp'), $_SESSION['imp']['pass'])))) {

        /* Disable the old session. */
        unset($_SESSION['imp']);
        _redirect(Auth::addLogoutParameters(IMP::logoutUrl(), AUTH_REASON_FAILED));
    }

    /* Finish up any login tasks we haven't completed yet. */
    IMP_Session::loginTasks();

    $url = Util::getFormData('url');
    if (empty($url)) {
        $url = IMP_Session::getInitialUrl($actionID, false);
    } elseif (!empty($actionID)) {
        $url = Util::addParameter($url, 'actionID', $actionID, false);
    }

    /* Don't show popup window in initial page. */
    if ($isLogin) {
        $url = Util::addParameter($url, 'no_newmail_popup', 1, false);
    }

    _redirect(_framesetUrl($url));
}

/* Create a new session if we're given the proper parameters. */
if ((!is_null($imapuser) && !is_null($pass))) {
    if (Auth::getProvider() == 'imp') {
        /* Destroy any existing session on login and make sure to use
         * a new session ID, to avoid session fixation issues. */
        Horde::getCleanSession();
    }

    /* Read the required server parameters from the servers.php
     * file. */
    require_once IMP_BASE . '/config/servers.php';
    $server_key = Util::getFormData('server_key', IMP::getAutoLoginServer(true));
    if (!empty($servers[$server_key])) {
        $sessArray = $servers[$server_key];
    }

    /* If we're not using hordeauth get parameters altered from the defaults
     * from the form data. */
    if (empty($autologin)) {
        foreach (array('server', 'port', 'protocol', 'smtphost', 'smtpport') as $val) {
            $data = Util::getFormData($val);
            if (!empty($data)) {
                $sessArray[$val] = $data;
            }
        }
    } else {
        if (!empty($sessArray['hordeauth'])) {
            if (strcasecmp($sessArray['hordeauth'], 'full') == 0) {
                $imapuser = Auth::getAuth();
            }
        } else {
            $entry = sprintf('Invalid server key "%s" from client [%s]', $server_key, $_SERVER['REMOTE_ADDR']);
            Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_INFO);
        }
    }

    if (!empty($sessArray) &&
        IMP_Session::createSession($imapuser, $pass, $sessArray['server'], $sessArray)) {
        $entry = sprintf('Login success for %s [%s] to {%s:%s}', $imp['uniquser'], $_SERVER['REMOTE_ADDR'], $imp['server'], $imp['port']);
        Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_NOTICE);

        $ie_version = Util::getFormData('ie_version');
        if ($ie_version) {
            $browser->setIEVersion($ie_version);
        }

        if (($horde_language = Util::getFormData('new_lang'))) {
            $_SESSION['horde_language'] = $horde_language;
        }

        IMP_Session::loginTasks();

        $url = _newSessionUrl($actionID, $isLogin);
    } else {
        $url = Auth::addLogoutParameters(IMP::logoutUrl());
    }

    _redirect(_framesetUrl($url));
}

/* No session, and no login attempt. Just go to the login page. */
require IMP_BASE . '/login.php';
