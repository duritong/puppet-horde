<?php
/**
 * $Horde: imp/recompose.php,v 2.13.10.8 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2003-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

function _getFormData($val)
{
    static $fd;
    if (!isset($fd)) {
        global $formData;
        $fd = @unserialize($formData);
    }

    if (!empty($fd['post'][$val])) {
        return $fd['post'][$val];
    } elseif (!empty($fd['get'][$val])) {
        return $fd['get'][$val];
    } else {
        return '';
    }
}

@define('IMP_BASE', dirname(__FILE__));
$authentication = 'none';
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Session.php';
require IMP_BASE . '/config/servers.php';

// If we somehow get to this page with a valid session, go immediately
// to compose.php. No need to do other validity checks if the session
// already exists.
if (Auth::getAuth() && IMP::checkAuthentication(OP_HALFOPEN, true)) {
    $_SESSION['formData'] = serialize(array('get' => $_GET, 'post' => $_POST));
    header('Location: ' . Util::addParameter(Horde::applicationUrl('compose.php', true), 'actionID', 'recompose', false));
    exit;
}

// Check for a login attempt.
if (Util::getPost('imapuser')) {
    $imapuser = Util::getPost('imapuser');
    $pass = Util::getPost('pass');

    /* Create a new session if we're given the proper parameters. */
    if (!is_null($imapuser) && !is_null($pass)) {
        if (Auth::getProvider() == 'imp') {
            /* Destroy any existing session on login and make sure to
             * use a new session ID, to avoid session fixation
             * issues. */
            Horde::getCleanSession();
        }

        /* Read the required server parameters from the servers.php
         * file. */
        require_once IMP_BASE . '/config/servers.php';
        $server_key = Util::getFormData('server_key');
        if (!empty($servers[$server_key])) {
            $sessArray = $servers[$server_key];
        }

        foreach (array('server', 'port', 'protocol', 'folders') as $val) {
            $data = Util::getFormData($val);
            if (!empty($data)) {
                $sessArray[$val] = $data;
            }
        }

        if (IMP_Session::createSession($imapuser, $pass, $sessArray['server'], $sessArray)) {
            $entry = sprintf('Relogin success for %s [%s] to {%s:%s}', $imp['uniquser'], $_SERVER['REMOTE_ADDR'], $imp['server'], $imp['port']);
            Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_NOTICE);

            if (($horde_language = Util::getFormData('new_lang'))) {
                $_SESSION['horde_language'] = $horde_language;
            }

            // Store the user's message data in their session, so that
            // we can redirect to compose.php. This insures that
            // everything gets loaded with a proper session present,
            // which can affect things like the user's preferences.
            $_SESSION['formData'] = Util::getPost('formData');
            header('Location: ' . Util::addParameter(Horde::applicationUrl('compose.php', true), 'actionID', 'recompose', false));
            exit;
        }
    }
}

$actionID = null;
$imp_auth = (Auth::getProvider() == 'imp');
$formAction = 'recompose.php';
$formData = Util::getPost('formData', serialize(array('get' => $_GET, 'post' => $_POST)));

$reason = _("Please log in again to resume composing your message. If you are NOT using cookies AND you are composing messages in popup windows, you will have to log in again in your main window as well. This is to keep attackers from hijacking your session ID. We apologize for any inconvenience.");
$title = _("Resume your session");

require IMP_TEMPLATES . '/common-header.inc';
if (Auth::getProvider() == 'imp' || Auth::getAuth()) {
    $autologin = false;
    require_once 'Horde/Menu.php';
    require IMP_TEMPLATES . '/login/login.inc';
}
require IMP_TEMPLATES . '/compose/recompose.inc';
if (@is_readable(IMP_BASE . '/config/motd.php')) {
    require IMP_BASE . '/config/motd.php';
}
require $registry->get('templates', 'horde') . '/common-footer.inc';
