<?php
/**
 * IMP external API interface.
 *
 * This file defines IMP's external API interface. Other applications
 * can interact with IMP through this API.
 *
 * $Horde: imp/lib/api.php,v 1.94.10.14 2006/04/19 14:24:08 jan Exp $
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @package IMP
 */

$_services['perms'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray');

$_services['authenticate'] = array(
    'args' => array('userID' => 'string', 'credentials' => '{urn:horde}hash', 'params' => '{urn:horde}hash'),
    'checkperms' => false,
    'type' => 'boolean'
);

$_services['getStream'] = array(
    'args' => array('mailbox' => 'string', 'flags' => 'int'),
    'type' => 'resource'
);

$_services['compose'] = array(
    'args' => array('args' => '{urn:horde}hash', 'extra' => '{urn:horde}hash'),
    'type' => 'string'
);

$_services['batchCompose'] = array(
    'args' => array('args' => '{urn:horde}hash', 'extra' => '{urn:horde}hash'),
    'type' => 'string'
);

$_services['folderlist'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray'
);

$_services['createFolder'] = array(
    'args' => array('folder' => 'string'),
    'type' => 'string'
);

$_services['server'] = array(
    'args' => array(),
    'type' => 'string'
);

if (!empty($_SESSION['imp']['admin'])) {
    $_services['userList'] = array(
        'type' => '{urn:horde}stringArray'
    );

    $_services['addUser'] = array(
        'args' => array('userId' => 'string', 'credentials' => '{urn:horde}hash')
    );

    $_services['removeUser'] = array(
        'args' => array('userId' => 'string', 'credentials' => '{urn:horde}hash')
    );
}


function _imp_perms()
{
    $perms = array();

    $perms['tree']['imp']['create_folders'] = false;
    $perms['title']['imp:create_folders'] = _("Allow Folder Creation?");
    $perms['type']['imp:create_folders'] = 'boolean';
    $perms['tree']['imp']['max_folders'] = false;
    $perms['title']['imp:max_folders'] = _("Maximum Number of Folders");
    $perms['type']['imp:max_folders'] = 'int';

    return $perms;
}

/**
 * TODO
 *
 * @param string $userID       TODO
 * @param array  $credentials  TODO
 * @param array  $params       TODO
 *
 * @return boolean  Whether IMP authentication was successful.
 */
function _imp_authenticate($userID, $credentials, $params)
{
    $GLOBALS['authentication'] = 'none';
    require_once dirname(__FILE__) . '/base.php';

    if (!empty($params['server'])) {
        $server = $params['server'];
    } else {
        require IMP_BASE . '/config/servers.php';
        foreach ($servers as $key => $curServer) {
            if (!isset($server) && substr($key, 0, 1) != '_') {
                $server = $key;
            }
            if (IMP::isPreferredServer($curServer, $key)) {
                $server = $key;
                break;
            }
        }
    }

    require_once IMP_BASE . '/lib/Session.php';
    if (IMP_Session::createSession($userID, $credentials['password'], $server)) {
        global $imp;
        $entry = sprintf('Login success for %s [%s] to {%s:%s}', $imp['uniquser'], $_SERVER['REMOTE_ADDR'], $imp['server'], $imp['port']);
        Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_NOTICE);
        return true;
    }

    return false;
}

/**
 * Attempts to authenticate via IMP and return an IMAP stream.
 *
 * @param string $mailbox  The mailbox name.
 * @param int $flags       IMAP connection flags.
 *
 * @return mixed  An IMAP resource on success, false on failure.
 */
function _imp_getStream($mailbox = null, $flags = 0)
{
    $GLOBALS['authentication'] = 'none';
    require_once dirname(__FILE__) . '/base.php';

    if (IMP::checkAuthentication(OP_HALFOPEN, true) === true) {
        require_once IMP_BASE . '/lib/IMAP.php';
        $imap = &IMP_IMAP::singleton();
        if ($imap->changeMbox($mailbox, $flags)) {
            return $_SESSION['imp']['stream'];
        }
    }

    return false;
}

/**
 * Returns a compose window link.
 *
 * @param string|array $args   List of arguments to pass to compose.php.
 *                             If this is passed in as a string, it will be
 *                             parsed as a toaddress?subject=foo&cc=ccaddress
 *                             (mailto-style) string.
 * @param array $extra         Hash of extra, non-standard arguments to pass to
 *                             compose.php.
 *
 * @return string  The link to the message composition screen.
 */
function _imp_compose($args = array(), $extra = array())
{
    $link = _imp_batchCompose(array($args), array($extra));
    return $link[0];
}

/**
 * Return a list of compose window links.
 *
 * @param mixed $args   List of lists of arguments to pass to compose.php. If
 *                      the lists are passed in as strings, they will be parsed
 *                      as toaddress?subject=foo&cc=ccaddress (mailto-style)
 *                      strings.
 * @param array $extra  List of hashes of extra, non-standard arguments to pass
 *                      to compose.php.
 *
 * @return string  The list of links to the message composition screen.
 */
function _imp_batchCompose($args = array(), $extra = array())
{
    global $prefs;

    $GLOBALS['authentication'] = 'none';
    require_once dirname(__FILE__) . '/base.php';

    $links = array();
    foreach ($args as $i => $arg) {
        $links[$i] = IMP::composeLink($arg, isset($extra[$i]) ? $extra[$i] : null);
    }

    return $links;
}

/**
 * Returns the list of folders.
 *
 * @return array  The list of IMAP folders.
 */
function _imp_folderlist()
{
    $GLOBALS['authentication'] = 'none';
    require_once dirname(__FILE__) . '/base.php';

    $result = false;

    if (IMP::checkAuthentication(OP_HALFOPEN, true) === true) {
        if ($_SESSION['imp']['base_protocol'] == 'pop3') {

            $result = array('INBOX' => array('val' => 'INBOX', 'label' => 'INBOX', 'abbrev' => 'INBOX'));
        } else {
            require_once IMP_BASE . '/lib/Folder.php';
            $imp_folder = &IMP_Folder::singleton();
            $result = $imp_folder->flist_IMP();
        }
    }

    return $result;
}

/**
 * Creates a new folder.
 *
 * @param string $folder  The UTF7-IMAP encoded name of the folder to create.
 *
 * @return string  The full folder name created on success, an empty string
 *                 on failure.
 */
function _imp_createFolder($folder)
{
    $GLOBALS['authentication'] = 'none';
    require_once dirname(__FILE__) . '/base.php';
    require_once IMP_BASE . '/lib/Folder.php';

    $result = false;

    if (IMP::checkAuthentication(OP_HALFOPEN, true) === true) {
        $imp_folder = &IMP_Folder::singleton();
        $result = $imp_folder->create(IMP::appendNamespace($folder), $GLOBALS['prefs']->getValue('subscribe'));
    }

    return (empty($result)) ? '' : $folder;
}

/**
 * Returns the currently logged on IMAP server.
 *
 * @return string  The server hostname.  Returns null if the user has not
 *                 authenticated into IMP yet.
 */
function _imp_server()
{
    $GLOBALS['authentication'] = 'none';
    require_once dirname(__FILE__) . '/base.php';
    return (IMP::checkAuthentication(OP_HALFOPEN, true) === true) ? $_SESSION['imp']['server'] : null;
}

/**
 * Adds a set of authentication credentials.
 *
 * @param string $userId       The userId to add.
 * @param array $credentials   The credentials to use.
 *
 * @return boolean  True on success or a PEAR_Error object on failure.
 */
function _imp_addUser($userId, $credentials)
{
    $params = $_SESSION['imp']['admin']['params'];
    $params['admin_user'] = $params['login'];
    $params['admin_password'] = Secret::read(Secret::getKey('imp'), $params['password']);
    require_once 'Horde/IMAP/Admin.php';
    $imap = &new IMAP_Admin($params);
    return $imap->addMailbox(String::convertCharset($userId, NLS::getCharset(), 'utf7-imap'));
}

/**
 * Deletes a set of authentication credentials.
 *
 * @param string $userId  The userId to delete.
 *
 * @return boolean  True on success or a PEAR_Error object on failure.
 */
function _imp_removeUser($userId)
{
    $params = $_SESSION['imp']['admin']['params'];
    $params['admin_user'] = $params['login'];
    $params['admin_password'] = Secret::read(Secret::getKey('imp'), $params['password']);
    require_once 'Horde/IMAP/Admin.php';
    $imap = &new IMAP_Admin($params);
    return $imap->removeMailbox(String::convertCharset($userId, NLS::getCharset(), 'utf7-imap'));
}

/**
 * Lists all users in the system.
 *
 * @return array  The array of userIds, or a PEAR_Error object on failure.
 */
function _imp_userList()
{
    $params = $_SESSION['imp']['admin']['params'];
    $params['admin_user'] = $params['login'];
    $params['admin_password'] = Secret::read(Secret::getKey('imp'), $params['password']);
    require_once 'Horde/IMAP/Admin.php';
    $imap = &new IMAP_Admin($params);
    return $imap->listMailboxes();
}
