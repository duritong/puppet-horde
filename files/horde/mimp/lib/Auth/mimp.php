<?php
/**
 * The Auth_mimp:: class provides an MIMP implementation of the Horde
 * authentication system.
 *
 * Required parameters:<pre>
 *   None.</pre>
 *
 * Optional parameters:<pre>
 *   None.</pre>
 *
 * $Horde: mimp/lib/Auth/mimp.php,v 1.17.2.1 2007/01/02 13:55:09 jan Exp $
 *
 * Copyright 2004-2007 Michael Slusarz <slusarz@curecanti.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Michael Slusarz <slusarz@curecanti.org>
 * @package Horde_Auth
 */
class Auth_mimp extends Auth {

    /**
     * MIMP specific authentication parameters.
     *
     * @var array
     */
    var $_mimpParams = array(
        'flags' => 0,
        'setup' => false
    );

    /**
     * Constructs a new MIMP authentication object.
     *
     * @param array $params  A hash containing connection parameters.
     */
    function Auth_mimp($params = array())
    {
        if (!Util::extensionExists('imap')) {
            Horde::fatal(PEAR::raiseError(_("Auth_mimp: Required IMAP extension not found.")), __FILE__, __LINE__);
        }
    }

    /**
     * Find out if a set of login credentials are valid, and if
     * requested, mark the user as logged in in the current session.
     *
     * @param string $userID      The userID to check.
     * @param array $credentials  The credentials to check.
     * @param boolean $login      Whether to log the user in. If false, we'll
     *                            only test the credentials and won't modify
     *                            the current session.
     *
     * @return boolean  Whether or not the credentials are valid.
     */
    function authenticate($userID = null, $credentials = array(),
                          $login = false)
    {
        if (empty($userID)) {
            if (empty($_SESSION['mimp']['uniquser'])) {
                return false;
            }
            $userID = $_SESSION['mimp']['uniquser'];
        }

        if (empty($credentials)) {
            if (empty($_SESSION['mimp']['pass'])) {
                return false;
            }
            $credentials = array('password' => Secret::read(Secret::getKey('mimp'), $_SESSION['mimp']['pass']));
        }

        $login = ($login && ($this->getProvider() == 'mimp'));

        return parent::authenticate($userID, $credentials, $login);
    }

    /**
     * Set MIMP-specific authentication options.
     *
     * @param array $params  The params to set.
     * <pre>
     * Keys:
     * -----
     * 'flags'  --  (integer) Flags to pass to imap_open().
     *              DEAFULT: 0
     *   OP_READONLY  : Open the mailbox read-only.
     *   OP_ANONYMOUS : (NNTP only) Don't use or update a .newrc file.
     *   OP_HALFOPEN  : (IMAP and NNTP only) Open a connection, but not a
     *                  specific mailbox.
     *   CL_EXPUNGE   : Expunge the mailbox automatically when the stream is
     *                  closed.
     * </pre>
     */
    function authenticateOptions($params = array())
    {
        $this->_mimpParams = array_merge($this->_mimpParams, $params);
    }

    /**
     * Find out if a set of login credentials are valid.
     *
     * @access private
     *
     * @param string $userID      The userID to check.
     * @param array $credentials  An array of login credentials.
     *
     * @return boolean  Whether or not the credentials are valid.
     */
    function _authenticate($userID, $credentials)
    {
        global $conf, $prefs;

        if (!(isset($_SESSION['mimp']) && is_array($_SESSION['mimp']))) {
            if (isset($prefs)) {
                $prefs->cleanup(true);
            }
            $this->_setAuthError(AUTH_REASON_SESSION);
            return false;
        }

        $connstr = null;
        $flags = $this->_mimpParams['flags'];

        switch ($_SESSION['mimp']['base_protocol']) {
        case 'pop3':
            $flags &= ~OP_ANONYMOUS;
            $flags &= ~OP_HALFOPEN;
            $_SESSION['mimp']['mailbox'] = 'INBOX';

            /* Turn some options off if we are working with POP3. */
            $conf['user']['allow_folders'] = false;
            $prefs->setValue('save_sent_mail', false);
            $prefs->setLocked('save_sent_mail', true);
            $prefs->setLocked('sent_mail_folder', true);
            $prefs->setLocked('trash_folder', true);
            break;

        default:
            $mailbox = Util::getFormData('mailbox');
            if ($mailbox != null) {
                $_SESSION['mimp']['mailbox'] = $mailbox;
            } elseif (empty($_SESSION['mimp']['mailbox'])) {
                $_SESSION['mimp']['mailbox'] = 'INBOX';
            }
            $connstr = $_SESSION['mimp']['mailbox'];
            break;
        }

        /* Open an IMAP stream. */
        require_once MIMP_BASE . '/lib/IMAP.php';
        $mimp_imap = &MIMP_IMAP::singleton();
        $mimp_imap->changeMbox($connstr, $flags);

        if (!$_SESSION['mimp']['stream']) {
            if (!empty($_SESSION['mimp']['server']) &&
                !empty($_SESSION['mimp']['port']) &&
                !empty($_SESSION['mimp']['protocol']) &&
                !empty($_SESSION['mimp']['user'])) {
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $entry = sprintf('FAILED LOGIN %s (forwarded for [%s]) to %s:%s[%s] as %s',
                                     $_SERVER['REMOTE_ADDR'],
                                     $_SERVER['HTTP_X_FORWARDED_FOR'],
                                     $_SESSION['mimp']['server'],
                                     $_SESSION['mimp']['port'],
                                     $_SESSION['mimp']['protocol'],
                                     $_SESSION['mimp']['user']);
                } else {
                    $entry = sprintf('FAILED LOGIN %s to %s:%s[%s] as %s',
                                     $_SERVER['REMOTE_ADDR'],
                                     $_SESSION['mimp']['server'],
                                     $_SESSION['mimp']['port'],
                                     $_SESSION['mimp']['protocol'],
                                     $_SESSION['mimp']['user']);
                }
                Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_ERR);
            }

            unset($_SESSION['mimp']);
            if (isset($prefs)) {
                $prefs->cleanup(true);
            }
            $this->_setAuthError(AUTH_REASON_FAILED);
            return false;
        }

        return true;
    }

    /**
     * Somewhat of a hack to allow MIMP to set an authentication error message
     * that may occur outside of this file.
     *
     * @param string $msg  The error message to set.
     */
    function MIMPsetAuthErrorMsg($msg)
    {
        $this->_setAuthError(AUTH_REASON_MESSAGE, $msg);
    }

}
