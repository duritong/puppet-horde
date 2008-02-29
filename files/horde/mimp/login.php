<?php
/**
 * $Horde: mimp/login.php,v 1.32.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('AUTH_HANDLER', true);
@define('MIMP_BASE', dirname(__FILE__));
$authentication = 'none';
require_once MIMP_BASE . '/lib/base.php';
require MIMP_BASE . '/config/servers.php';

/* Get an Auth object. */
$mimp_auth = (Auth::getProvider() == 'mimp');
$auth = &Auth::singleton($conf['auth']['driver']);

/* Get the actionID, checking for the action=compose variable. */
$actionID = Util::getFormData('actionID');
$url_param = Util::getFormData('url');

$logout_reason = $auth->getLogoutReason();

if (!empty($_SESSION['mimp']) && is_array($_SESSION['mimp'])) {
    if ($logout_reason) {
        if ($logout_reason == AUTH_REASON_LOGOUT) {
            $entry = sprintf('Logout for %s [%s] from {%s:%s}',
                             $_SESSION['mimp']['uniquser'],
                             $_SERVER['REMOTE_ADDR'], $_SESSION['mimp']['server'],
                             $_SESSION['mimp']['port']);
            Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_NOTICE);
        }

        $language = isset($prefs) ? $prefs->getValue('language') : NLS::select();

        unset($_SESSION['mimp']);

        if (isset($prefs)) {
            $prefs->cleanup($mimp_auth);
        }

        if ($mimp_auth) {
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
        $url = Horde::applicationUrl('mailbox.php', true);
        header('Location: ' . $url);
        exit;
    }
}

/* Log session timeouts. */
if ($logout_reason == AUTH_REASON_SESSION) {
    $entry = sprintf('Session timeout for client [%s]', $_SERVER['REMOTE_ADDR']);
    Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_INFO);

    /* Make sure everything is really cleared. */
    Auth::clearAuth();
    unset($_SESSION['mimp']);
}

/* Redirect the user on logout if redirection is enabled. */
if (($logout_reason == AUTH_REASON_LOGOUT) &&
    $conf['user']['redirect_on_logout']) {
    $url = Auth::addLogoutParameters($conf['user']['redirect_on_logout'], AUTH_REASON_LOGOUT);
    header('Location: ' . $url);
    exit;
}

/* Redirect the user if an alternate login page has been specified. */
if ($conf['user']['alternate_login']) {
    $url = Auth::addLogoutParameters($conf['user']['alternate_login']);
    header('Location: ' . $url);
    exit;
}

if ($logout_reason) {
    $notification->push($auth->getLogoutReasonString());
}

/* Initialize the password key(s). If we are doing Horde auth as well,
 * make sure that the Horde auth key gets set. */
Secret::setKey('mimp');
if ($mimp_auth) {
    Secret::setKey('auth');
}

/* Build the <select> widget for the servers list. */
if ($conf['server']['server_list'] == 'shown') {
    $server_select = &new Horde_Mobile_select('server');
    foreach ($servers as $key => $curServer) {
        $server_select->add($curServer['name'], $key, MIMP::isPreferredServer($curServer, $key));
    }
}

/* Build the <select> widget containing the available languages. */
if (!$prefs->isLocked('language')) {
    $lang_select = &new Horde_Mobile_select('new_lang');
    $_SESSION['horde_language'] = NLS::select();
    foreach ($nls['languages'] as $key => $val) {
        $lang_select->add($val, $key, ($key == $_SESSION['horde_language']));
    }
}

require MIMP_TEMPLATES . '/login/login.inc';
if (is_readable(MIMP_BASE . '/config/motd.php')) {
    require MIMP_BASE . '/config/motd.php';
}

if ($motd = Util::nonInputVar('motd')) {
    $t = &$c->add(new Horde_Mobile_text($motd));
    $t->set('linebreaks', true);
}

$m->display();
