<?php

require_once 'Horde/Identity.php';

/**
 * This class provides an MIMP-specific interface to all identities a
 * user might have. Its methods take care of any site-specific
 * restrictions configured in prefs.php and conf.php.
 *
 * $Horde: mimp/lib/Identity/mimp.php,v 1.22.2.1 2007/01/02 13:55:10 jan Exp $
 *
 * Copyright 2001-2007 Jan Schneider <jan@horde.org>
 * Copyright 2001-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Horde_Identity
 */
class Identity_mimp extends Identity {

    /**
     * Reads all the user's identities from the prefs object or builds
     * a new identity from the standard values given in prefs.php.
     */
    function Identity_mimp()
    {
        parent::Identity();
        $this->_properties = array_merge(
            $this->_properties,
            array('replyto_addr', 'signature', 'sig_first', 'sig_dashes',
                  'save_sent_mail', 'sent_mail_folder'));
    }

    /**
     * Returns a complete From: header based on all relevent factors (fullname,
     * from address, input fields, locks etc.)
     *
     * @param integer $ident        The identity to retrieve the values from.
     * @param string $from_address  A default from address to use if no
     *                              identity is selected and the from_addr
     *                              preference is locked.
     *
     * @return string  A full From: header in the format
     *                 'Fullname <user@example.com>'.
     */
    function getFromLine($ident = null, $from_address = '')
    {
        /* Cache the value of $_from for future checks. */
        static $froms;
        if (isset($froms[$ident])) {
            return $froms[$ident];
        }

        if (!isset($ident)) {
            $address = $from_address;
        }

        if (empty($address) || $this->_prefs->isLocked('from_addr')) {
            $address = $this->getFromAddress($ident);
            $name = $this->getFullname($ident);
        }

        if (!empty($address)) {
            $ob = imap_rfc822_parse_adrlist($address, $_SESSION['mimp']['maildomain']);
        }

        if (empty($name)) {
            if (!empty($ob[0]->personal)) {
                $name = $ob[0]->personal;
            } else {
                $name = $this->getFullname($ident);
            }
        }

        $from = MIME::trimEmailAddress(imap_rfc822_write_address($ob[0]->mailbox, $ob[0]->host, $name));

        $froms[$ident] = $from;
        return $from;
    }

    /**
     * Returns an array with From: headers from all identities
     *
     * @return array  The From: headers from all identities
     */
    function getAllFromLines()
    {
        foreach ($this->_identities as $ident => $dummy) {
            $list[$ident] = $this->getFromAddress($ident);
        }
        return $list;
    }

    /**
     * Returns an array with the necessary values for the identity select
     * box in the MIMP compose window.
     *
     * @return array  The array with the necessary strings
     */
    function getSelectList()
    {
        $ids = $this->getAll('id');
        foreach ($ids as $key => $id) {
            $list[$key] = $this->getFromAddress($key) . ' (' . $id . ')';
        }
        return $list;
    }

    /**
     * Returns true if the given address belongs to one of the identities.
     *
     * @param string $address  The address to search for in the identities
     *
     * @return boolean  True if the address was found
     */
    function hasAddress($address)
    {
        static $list;

        $address = String::lower($address);
        if (!isset($list)) {
            $list = $this->getAllFromAddresses();
        }

        return isset($list[$address]);
    }

    /**
     * Returns the from address based on the chosen identity.
     *
     * If no address can be found it is built from the current user name and
     * the specified maildomain.
     *
     * @param integer  The identity to retrieve the address from.
     *
     * @return string  A valid from address.
     */
    function getFromAddress($ident = null)
    {
        static $froms;

        if (isset($froms[$ident])) {
            return $froms[$ident];
        }

        $val = $this->getValue('from_addr', $ident);
        if (empty($val)) {
            $val = $_SESSION['mimp']['user'];
        }

        if (!strstr($val, '@')) {
            $val .= '@' . $_SESSION['mimp']['maildomain'];
        }

        $froms[$ident] = $val;

        return $val;
    }

    /**
     * Returns an array with all identities' from addresses
     *
     * @return array  The array with the from addresses
     */
    function getAllFromAddresses()
    {
        static $list;

        if (isset($list)) {
            return $list;
        }

        foreach ($this->_identities as $key => $identity) {
            $addr = String::lower($this->getFromAddress($key));
            if (!isset($list[$addr])) {
                $list[$addr] = $key;
            }
        }

        return $list;
    }

    /**
     * Returns the users full name.
     *
     * @param integer $ident  The identity to retrieve the name from.
     *
     * @return string  The user's full name.
     */
    function getFullname($ident = null)
    {
        static $names;

        if (isset($names[$ident])) {
            return $names[$ident];
        }

        $val = $this->getValue('fullname', $ident);

        $names[$ident] = $val;
        return $val;
    }

    /**
     * Returns the full signature based on the current settings for the
     * signature itself, the dashes and the position.
     *
     * @param integer $ident  The identity to retrieve the signature from.
     *
     * @return string  The full signature.
     */
    function getSignature($ident = null)
    {
        static $signatures;

        if (isset($signatures[$ident])) {
            return $signatures[$ident];
        }

        $val = $this->getValue('signature', $ident);
        if (!empty($val)) {
            $sig_first = $this->getValue('sig_first', $ident);
            $sig_dashes = $this->getValue('sig_dashes', $ident);
            $val = str_replace("\r\n", "\n", $val);
            if ($sig_dashes) {
                $val = "-- \n$val";
            }
            if (isset($sig_first) && $sig_first) {
                $val = "\n" . $val . "\n\n\n";
            } else {
                $val = "\n" . $val;
            }
        }

        $signatures[$ident] = $val;
        return $val;
    }

    /**
     * Returns an array with the signatures from all identities
     *
     * @return array  The array with all the signatures.
     */
    function getAllSignatures()
    {
        static $list;

        if (isset($list)) {
            return $list;
        }

        foreach ($this->_identities as $key => $identity) {
            $list[$key] = $this->getSignature($key);
        }

        return $list;
    }

    /**
     * @see Identity::getValue()
     */
    function getValue($key, $identity = null)
    {
        if ($key == 'sent_mail_folder') {
            return MIMP::folderPref(parent::getValue('sent_mail_folder', $identity), true);
        }
        return parent::getValue($key, $identity);
    }

    /**
     * Returns an array with the sent-mail folder names from all the
     * identities.
     *
     * @return array  The array with the folder names.
     */
    function getAllSentmailFolders()
    {
        foreach ($this->_identities as $key => $identity) {
            $list[$this->getSentmailFolder($key)] = true;
        }
        return array_keys($list);
    }

    /**
     * Returns true if the mail should be saved and the user is allowed to.
     *
     * @param integer $ident  The identity to retrieve the setting from.
     *
     * @return boolean  True if the sent mail should be saved.
     */
    function saveSentmail($ident = null)
    {
        if (!$GLOBALS['conf']['user']['allow_folders']) {
            return false;
        }

        return $this->getValue('save_sent_mail', $ident);
    }

}
