<?php
/**
 * The MIMP_IMAP:: class facilitates connections to the IMAP/POP3 server
 * via the c-client PHP extensions.
 *
 * $Horde: mimp/lib/IMAP.php,v 1.14.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 2003-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package MIMP
 */
class MIMP_IMAP {

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
     * Attempts to return a reference to a concrete MIMP_IMAP instance.
     * It will only create a new instance if no MIMP_IMAP instance currently
     * exists.
     *
     * This method must be invoked as:
     *   $mimp_imap = &MIMP_IMAP::singleton();
     *
     * @param array $params  Parameters needed.
     *
     * @return MIMP_IMAP  The concrete MIMP_IMAP reference, or false on error.
     */
    function &singleton($params = array())
    {
        static $instance;

        if (!isset($instance)) {
            $instance = new MIMP_IMAP($params);
        }

        return $instance;
    }

    /**
     * Constructor.
     *
     * @param array $params  Any additional parameters needed.
     */
    function MIMP_IMAP($params = array())
    {
        $this->_serverString = MIMP::serverString(null, $_SESSION['mimp']['protocol']);
        $this->_user = $_SESSION['mimp']['user'];
        $this->_pass = Secret::read(Secret::getKey('mimp'), $_SESSION['mimp']['pass']);
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
        while (($ret === false) &&
               !strstr(strtolower(imap_last_error()), 'login failure') &&
               (++$i < 3)) {
            if ($i != 0) {
                sleep(1);
            }
            $ret = @imap_open($this->_serverString . $mbox, $this->_user, $this->_pass, $flags);
        }
        return $ret;
    }

    /**
     * Change the currently active MIMP IMAP stream to a new mailbox
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
        if (empty($_SESSION['mimp']['stream'])) {
            if (($_SESSION['mimp']['stream'] = $this->openIMAPStream($mbox, $flags))) {
                $this->_openMbox = $mbox;
                $this->_mboxFlags = $flags;
                if (!empty($_SESSION['mimp']['timeout'])) {
                    foreach ($_SESSION['mimp']['timeout'] as $key => $val) {
                        imap_timeout($key, $val);
                    }
                }
                return true;
            } else {
                return false;
            }
        }

        if ($_SESSION['mimp']['base_protocol'] == 'pop3') {
            return true;
        }

        /* Only reopen mailbox if we need to - either we are changing
           mailboxes or the flags for the current mailbox has changed. */
        if (($this->_openMbox != $mbox) || ($this->_mboxFlags != $flags)) {
            $result = @imap_reopen($_SESSION['mimp']['stream'], $this->_serverString . $mbox, $flags);
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

    /**
     * Returns the list of default IMAP/POP3 protocol connection information.
     * This function can be called statically.
     *
     * @return array  The protocol configuration list.
     */
    function protocolList()
    {
        return array(
            'pop3' => array(
                'name' => _("POP3"),
                'string' => 'pop3',
                'port' => 110,
                'base' => 'POP3'
            ),
            'pop3notls' => array(
                'name' => _("POP3, no TLS"),
                'string' => 'pop3/notls',
                'port' => 110,
                'base' => 'POP3'
            ),
            'pop3sslvalid' => array(
                'name' => _("POP3 over SSL"),
                'string' => 'pop3/ssl',
                'port' => 995,
                'base' => 'POP3'
            ),
            'pop3ssl' => array(
                'name' => _("POP3 over SSL (self-signed certificate)"),
                'string' => 'pop3/ssl/novalidate-cert',
                'port' => 995,
                'base' => 'POP3'
            ),
            'imap' => array(
                'name' => _("IMAP"),
                'string' => 'imap',
                'port' => 143,
                'base' => 'IMAP'
            ),
            'imapnotls' => array(
                'name' => _("IMAP, no TLS"),
                'string' => 'imap/notls',
                'port' => 143,
                'base' => 'IMAP'
            ),
            'imapsslvalid' => array(
                'name' => _("IMAP over SSL"),
                'string' => 'imap/ssl',
                'port' => 993,
                'base' => 'IMAP'
            ),
            'imapssl' => array(
                'name' => _("IMAP over SSL (self-signed certificate)"),
                'string' => 'imap/ssl/novalidate-cert',
                'port' => 993,
                'base' => 'IMAP'
            )
        );
    }

}
