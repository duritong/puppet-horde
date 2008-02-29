<?php
/**
 * $Horde: mimp/redirect.php,v 1.33.2.1 2007/01/02 13:55:08 jan Exp $
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
require_once MIMP_BASE . '/lib/Session.php';

$actionID = Util::getFormData('actionID');
$imapuser = Util::getPost('imapuser');
$pass = Util::getPost('pass');

/* If we already have a session... */
if (isset($_SESSION['mimp']) && is_array($_SESSION['mimp'])) {
    /* Make sure that if a username was specified, it is the current
     * username */
    if ((is_null($imapuser) || ($imapuser == $_SESSION['mimp']['user'])) &&
        (is_null($pass) || ($pass == Secret::read(Secret::getKey('mimp'), $_SESSION['mimp']['pass'])))) {

        $url = Util::getFormData('url');
        if (empty($url)) {
            $url = MIMP::getInitialUrl($actionID);
        } elseif (!empty($actionID)) {
            $url = Util::addParameter($url, 'actionID', $actionID, false);
        }

        header('Location: ' . $url);
        exit;
    } else {
        /* Disable the old session. */
        unset($_SESSION['mimp']);
        header('Location: ' . Auth::addLogoutParameters(MIMP::logoutUrl(), AUTH_REASON_FAILED));
        exit;
    }
}

/* Create a new session if we're given the proper parameters. */
if (!is_null($imapuser) && !is_null($pass)) {
    if (Auth::getProvider() == 'mimp') {
        /* Destroy any existing session on login and make sure to use
         * a new session ID, to avoid session fixation issues. */
        Horde::getCleanSession();
    }

    /* Read the required server parameters from the servers.php
     * file. */
    require_once MIMP_BASE . '/config/servers.php';
    $server_key = Util::getFormData('server');
    if (empty($server_key)) {
        /* Iterate through the servers in an attempt to locate a preferred
         * server for this web server/virtualhost. If none are found, we
         * default to the first entry in the $servers array that isn't a
         * prompt (key begins with '_'). */
        foreach ($servers as $key => $curServer) {
            if (empty($server_key) && substr($key, 0, 1) != '_') {
                $server_key = $key;
            }
            if (MIMP::isPreferredServer($curServer, $key)) {
                $server_key = $key;
                break;
            }
        }
    }

    if (!empty($servers[$server_key])) {
        $sessArray = $servers[$server_key];
    }

    /* Get parameters altered from the defaults from the form data. */
    foreach (array('port', 'protocol') as $val) {
        $data = Util::getFormData($val);
        if (!empty($data)) {
            $sessArray[$val] = $data;
        }
    }

    if (!empty($sessArray) &&
        MIMP_Session::createSession($imapuser, $pass, $sessArray['server'], $sessArray)) {
        $entry = sprintf('Login success for %s [%s] to {%s:%s}', $_SESSION['mimp']['uniquser'], $_SERVER['REMOTE_ADDR'], $_SESSION['mimp']['server'], $_SESSION['mimp']['port']);
        Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_NOTICE);

        if (($horde_language = Util::getFormData('new_lang'))) {
            $_SESSION['horde_language'] = $horde_language;
        }

        $url = Util::getFormData('url');
        if ($url) {
            $url = Horde::url(Util::removeParameter($url, session_name()));
            if (!empty($actionID)) {
                $url = Util::addParameter($url, 'actionID', $actionID, false);
            }
        } elseif (Auth::getProvider() == 'mimp') {
            $url = Horde::url($registry->get('webroot', 'horde') . '/index.php', true);
        } else {
            $url = MIMP::getInitialUrl($actionID);
        }
    } else {
        $url = Auth::addLogoutParameters(MIMP::logoutUrl());
    }

    header('Location: ' . $url);
    exit;
}

/* No session, and no login attempt. Just go to the login page. */
require MIMP_BASE . '/login.php';
