<?php
/**
 * The Auth_imp:: class provides an IMP implementation of the Horde
 * authentication system.
 *
 * Required parameters:<pre>
 *   None.</pre>
 *
 * Optional parameters:<pre>
 *   None.</pre>
 *
 *
 * $Horde: imp/lib/Auth/imp.php,v 1.16.6.16 2007/01/02 13:54:57 jan Exp $
 *
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Horde 3.0
 * @package Horde_Auth
 */
class Auth_imp extends Auth {

    /**
     * IMP specific authentication parameters.
     *
     * @var array
     */
    var $_impParams = array(
        'flags' => 0,
        'setup' => false
    );

    /**
     * Constructs a new IMP authentication object.
     *
     * @param array $params  A hash containing connection parameters.
     */
    function Auth_imp($params = array())
    {
        if (!Util::extensionExists('imap')) {
            Horde::fatal(PEAR::raiseError(_("Auth_imp: Required IMAP extension not found.")), __FILE__, __LINE__);
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
        // Check for for hordeauth.
        if (empty($_SESSION['imp']['uniquser'])) {
            if (IMP::canAutoLogin()) {
                $server_key = IMP::getAutoLoginServer();

                require IMP_BASE . '/config/servers.php';
                $ptr = &$servers[$server_key];
                if (isset($ptr['hordeauth'])) {

                    if (strcasecmp($ptr['hordeauth'], 'full') == 0) {
                        $imapuser = Auth::getAuth();
                    } else {
                        $imapuser = Auth::getBareAuth();
                    }
                    $pass = Auth::getCredential('password');

                    require_once IMP_BASE . '/lib/Session.php';
                    if (IMP_Session::createSession($imapuser, $pass,
                                                   $ptr['server'], $ptr)) {
                        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                           $entry = sprintf('Login success for %s [%s] (forwarded for [%s]) to {%s:%s}',
                                            $imapuser,
                                            $_SERVER['REMOTE_ADDR'],
                                            $_SERVER['HTTP_X_FORWARDED_FOR'],
                                            $ptr['server'],
                                            $ptr['port']);
                        } else {
                           $entry = sprintf('Login success for %s [%s] to {%s:%s}',
                                            $imapuser,
                                            $_SERVER['REMOTE_ADDR'],
                                            $ptr['server'],
                                            $ptr['port']);
                        }
                        Horde::logMessage($entry, __FILE__, __LINE__,
                                          PEAR_LOG_NOTICE);
                        return true;
                    }
                }
            }
        }

        if (empty($userID)) {
            if (empty($_SESSION['imp']['uniquser'])) {
                return false;
            }
            $userID = $_SESSION['imp']['uniquser'];
        }

        if (empty($credentials)) {
            if (empty($_SESSION['imp']['pass'])) {
                return false;
            }
            $credentials = array('password' => Secret::read(Secret::getKey('imp'), $_SESSION['imp']['pass']));
        }

        $login = ($login && ($this->getProvider() == 'imp'));

        return parent::authenticate($userID, $credentials, $login);
    }

    /**
     * Set IMP-specific authentication options.
     *
     * @param array $params  The params to set.
     * <pre>
     * Keys:
     * -----
     * 'flags'  --  (integer) Flags to pass to imap_open().
     *              DEFAULT: 0
     * </pre>
     */
    function authenticateOptions($params = array())
    {
        $this->_impParams = array_merge($this->_impParams, $params);
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

        if (!(isset($_SESSION['imp']) && is_array($_SESSION['imp']))) {
            if (isset($prefs)) {
                $prefs->cleanup(true);
            }
            $this->_setAuthError(AUTH_REASON_SESSION);
            return false;
        }

        /* Set the maildomain. */
        $maildomain = preg_replace('/[^-\.a-z0-9]/i', '',
                                   $prefs->getValue('mail_domain'));
        if (!empty($maildomain)) {
            $_SESSION['imp']['maildomain'] = $maildomain;
        } elseif (!empty($_SESSION['imp']['maildomain'])) {
            $prefs->setValue('mail_domain', $_SESSION['imp']['maildomain']);
        }


        if (!isset($GLOBALS['imp'])) {
            $GLOBALS['imp'] = &$_SESSION['imp'];
        }

        $connstr = null;
        $flags = $this->_impParams['flags'];
        $flags &= ~OP_ANONYMOUS;

        /* Process the mailbox parameter (if present). */
        $mailbox = Util::getFormData('mailbox');
        if (!is_null($mailbox)) {
            $_SESSION['imp']['mailbox'] = $mailbox;
        } elseif (!isset($_SESSION['imp']['mailbox'])) {
            $_SESSION['imp']['mailbox'] = 'INBOX';
        }
        $_SESSION['imp']['thismailbox'] = Util::getFormData('thismailbox', $_SESSION['imp']['mailbox']);

        /* Is this a search mailbox? */
        $imp_search_params = null;
        if (strpos($_SESSION['imp']['mailbox'], IMP_SEARCH_MBOX) === 0) {
            $imp_search_params = array('id' => $_SESSION['imp']['mailbox']);
        }
        require_once IMP_BASE . '/lib/Search.php';
        $GLOBALS['imp_search'] = new IMP_Search($imp_search_params);

        switch ($_SESSION['imp']['base_protocol']) {
        case 'pop3':
            $connstr = 'INBOX';
            $flags &= ~OP_HALFOPEN;

            /* Turn some options off if we are working with POP3. */
            $conf['user']['allow_folders'] = false;
            $prefs->setValue('save_sent_mail', false);
            $prefs->setLocked('save_sent_mail', true);
            $prefs->setLocked('sent_mail_folder', true);
            $prefs->setLocked('drafts_folder', true);
            $prefs->setLocked('trash_folder', true);
            break;

        case 'imap':
            if ($flags ^ OP_HALFOPEN) {
                $connstr = $_SESSION['imp']['thismailbox'];
                if ($GLOBALS['imp_search']->isSearchMbox($_SESSION['imp']['thismailbox'])) {
                    if (strstr(Util::getFormData('index'), ':')) {
                        $tmp = explode(':', Util::getFormData('index'));
                        $connstr = $tmp[1];
                        $flags |= OP_HALFOPEN;
                    } else {
                        $aindex = Util::getFormData('array_index');
                        if ($aindex !== null) {
                            $tmp = explode(IMP_MSG_SEP, $_SESSION['imp']['msgl']);
                            $mbox = substr($tmp[$aindex], strpos($tmp[$aindex], IMP_IDX_SEP) + 1);
                            $connstr = $mbox;
                            $flags |= OP_HALFOPEN;
                        }
                    }
                }
            }
            break;
        }

        /* Open an IMAP stream. */
        require_once IMP_BASE . '/lib/IMAP.php';
        $imp_imap = &IMP_IMAP::singleton();
        $imp_imap->changeMbox($connstr, $flags);

        if (!$_SESSION['imp']['stream']) {
            if (!empty($_SESSION['imp']['server']) &&
                !empty($_SESSION['imp']['port']) &&
                !empty($_SESSION['imp']['protocol']) &&
                !empty($_SESSION['imp']['user'])) {
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $entry = sprintf('FAILED LOGIN %s (forwarded for [%s]) to %s:%s[%s] as %s',
                                     $_SERVER['REMOTE_ADDR'],
                                     $_SERVER['HTTP_X_FORWARDED_FOR'],
                                     $_SESSION['imp']['server'],
                                     $_SESSION['imp']['port'],
                                     $_SESSION['imp']['protocol'],
                                     $_SESSION['imp']['user']);
                } else {
                    $entry = sprintf('FAILED LOGIN %s to %s:%s[%s] as %s',
                                     $_SERVER['REMOTE_ADDR'],
                                     $_SESSION['imp']['server'],
                                     $_SESSION['imp']['port'],
                                     $_SESSION['imp']['protocol'],
                                     $_SESSION['imp']['user']);
                }
                Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_ERR);
            }

            unset($_SESSION['imp']);
            if (isset($prefs)) {
                $prefs->cleanup(true);
            }
            $this->_setAuthError(AUTH_REASON_FAILED);
            return false;
        }

        return true;
    }

    /**
     * Somewhat of a hack to allow IMP to set an authentication error message
     * that may occur outside of this file.
     *
     * @param string $msg  The error message to set.
     */
    function IMPsetAuthErrorMsg($msg)
    {
        $this->_setAuthError(AUTH_REASON_MESSAGE, $msg);
    }

}
