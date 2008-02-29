<?php
/**
 * The IMP_IMAP:: class facilitates connections to the IMAP/POP3 server
 * via the c-client PHP extensions.
 *
 * $Horde: imp/lib/IMAP.php,v 1.11.10.15 2007/01/02 13:54:56 jan Exp $
 *
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 4.0
 * @package IMP
 */
class IMP_IMAP {

    /**
     * The server string.
     *
     * @var string
     */
    var $_serverString;

    /**
     * The username for the server.
     *
     * @var string
     */
    var $_user;

    /**
     * The password for the mail server.
     *
     * @var string
     */
    var $_pass;

    /**
     * The currently open mailbox.
     *
     * @var string
     */
    var $_openMbox = null;

    /**
     * The IMAP flags set in the currently open mailbox.
     *
     * @var integer
     */
    var $_mboxFlags = null;

    /**
     * Attempts to return a reference to a concrete IMP_IMAP instance.
     * It will only create a new instance if no IMP_IMAP instance currently
     * exists.
     *
     * This method must be invoked as:
     *   $imp_imap = &IMP_IMAP::singleton();
     *
     * @param array $params  Parameters needed.
     *
     * @return IMP_IMAP  The concrete IMP_IMAP reference, or false on error.
     */
    function &singleton($params = array())
    {
        static $instance;

        if (!isset($instance)) {
            $instance = new IMP_IMAP($params);
        }

        return $instance;
    }

    /**
     * Constructor.
     *
     * @param array $params  Any additional parameters needed.
     */
    function IMP_IMAP($params = array())
    {
        $this->_serverString = IMP::serverString(null, $_SESSION['imp']['protocol']);
        $this->_user = $_SESSION['imp']['user'];
        $this->_pass = Secret::read(Secret::getKey('imp'), $_SESSION['imp']['pass']);
    }

    /**
     * Open an IMAP stream.
     *
     * @param string $mbox    A mailbox to open.
     * @param integer $flags  Any flags that need to be passed to imap_open().
     *
     * @return resource  The return from the imap_open() call.
     */
    function openIMAPStream($mbox = null, $flags = 0)
    {
        $i = -1;
        $ret = false;
        if (empty($mbox)) {
            $flags |= OP_HALFOPEN;
        }

        while (($ret === false) &&
               !strstr(strtolower(imap_last_error()), 'login failure') &&
               (++$i < $_SESSION['imp']['login_tries'])) {
            if ($i != 0) {
                sleep(1);
            }
            $ret = @imap_open($this->_serverString . $mbox, $this->_user, $this->_pass, $flags);
        }
        return $ret;
    }

    /**
     * Change the currently active IMP IMAP stream to a new mailbox
     * (if necessary).
     *
     * @param string $mbox    The new mailbox.
     * @param integer $flags  Any flags that need to be passed to
     *                        imap_reopen().
     *
     * @return boolean  True on success, false on error.
     */
    function changeMbox($mbox, $flags = 0)
    {
        /* Open a connection if none exists. */
        if (empty($_SESSION['imp']['stream'])) {
            if (($_SESSION['imp']['stream'] = $this->openIMAPStream($mbox, $flags))) {
                $this->_openMbox = $mbox;
                $this->_mboxFlags = $flags;
                if (empty($mbox)) {
                    $this->_mboxFlags |= OP_HALFOPEN;
                }

                if (!empty($_SESSION['imp']['imap_server']['timeout'])) {
                    foreach ($_SESSION['imp']['imap_server']['timeout'] as $key => $val) {
                        imap_timeout($key, $val);
                    }
                }
                return true;
            } else {
                return false;
            }
        }

        if ($_SESSION['imp']['base_protocol'] == 'pop3') {
            return true;
        }

        /* Only reopen mailbox if we need to - either we are changing
           mailboxes or the flags for the current mailbox has changed. */
        if (($this->_openMbox != $mbox) || ($this->_mboxFlags != $flags)) {
            $result = @imap_reopen($_SESSION['imp']['stream'], $this->_serverString . $mbox, $flags);
            if ($result) {
                $this->_openMbox = $mbox;
                $this->_mboxFlags = $flags;
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

}
