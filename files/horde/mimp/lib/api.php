<?php
/**
 * MIMP external API interface.
 *
 * This file defines MIMP's external API interface. Other applications
 * can interact with MIMP through this API.
 *
 * $Horde: mimp/lib/api.php,v 1.6 2005/10/10 14:50:01 chuck Exp $
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @package MIMP
 */

$_services['authenticate'] = array(
    'args' => array('userID', 'credentials', 'params'),
    'checkperms' => false,
    'type' => 'boolean'
);

$_services['getStream'] = array(
    'args' => array('mailbox' => 'string', 'flags' => 'int'),
    'type' => 'resource'
);


/**
 * TODO
 *
 * @param string $userID       TODO
 * @param array  $credentials  TODO
 * @param array  $params       TODO
 *
 * @return boolean  Whether MIMP authentication was successful.
 */
function _mimp_authenticate($userID, $credentials, $params)
{
    $GLOBALS['authentication'] = 'none';
    require_once dirname(__FILE__) . '/base.php';

    if (!empty($params['server'])) {
        $server = $params['server'];
    } else {
        require MIMP_BASE . '/config/servers.php';
        foreach ($servers as $key => $curServer) {
            if (!isset($server) && substr($key, 0, 1) != '_') {
                $server = $key;
            }
            if (MIMP::isPreferredServer($curServer, $key)) {
                $server = $key;
                break;
            }
        }
    }

    require_once MIMP_BASE . '/lib/Session.php';
    if (MIMP_Session::createSession($userID, $credentials['password'], $server)) {
        $entry = sprintf('Login success for %s [%s] to {%s:%s}', $_SESSION['mimp']['uniquser'], $_SERVER['REMOTE_ADDR'], $_SESSION['mimp']['server'], $_SESSION['mimp']['port']);
        Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_NOTICE);
        return true;
    }

    return false;
}

/**
 * Attempts to authenticate via MIMP and return an IMAP stream.
 *
 * @param string $mailbox  The mailbox name.
 * @param int $flags       IMAP connection flags.
 *
 * @return mixed  An IMAP resource on success, false on failure.
 */
function _mimp_getStream($mailbox = null, $flags = 0)
{
    $GLOBALS['authentication'] = 'none';
    require_once dirname(__FILE__) . '/base.php';

    if (MIMP::checkAuthentication(OP_HALFOPEN, true) === true) {
        require_once MIMP_BASE . '/lib/IMAP.php';
        $imap = &MIMP_IMAP::singleton();
        if ($imap->changeMbox($mailbox, $flags)) {
            return $_SESSION['mimp']['stream'];
        }
    }

    return false;
}
