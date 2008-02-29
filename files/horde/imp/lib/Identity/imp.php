<?php

require_once 'Horde/Identity.php';

/**
 * This class provides an IMP-specific interface to all identities a
 * user might have. Its methods take care of any site-specific
 * restrictions configured in prefs.php and conf.php.
 *
 * $Horde: imp/lib/Identity/imp.php,v 1.44.2.11.2.1 2007/12/21 00:50:56 jan Exp $
 *
 * Copyright 2001-2007 Jan Schneider <jan@horde.org>
 * Copyright 2001-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   IMP 2.3.7
 * @package Horde_Identity
 */
class Identity_imp extends Identity {

    /**
     * Cached alias list.
     *
     * @var array
     */
    var $_aliases = array();

    /**
     * Cached from address list.
     *
     * @var array
     */
    var $_fromList = array();

    /**
     * Cached names list.
     *
     * @var array
     */
    var $_names = array();

    /**
     * Cached signature list.
     *
     * @var array
     */
    var $_signatures = array();

    /**
     * Reads all the user's identities from the prefs object or builds
     * a new identity from the standard values given in prefs.php.
     */
    function Identity_imp()
    {
        parent::Identity();
        $this->_properties = array_merge(
            $this->_properties,
            array('replyto_addr', 'alias_addr', 'tieto_addr', 'bcc_addr',
                  'mail_hdr', 'signature', 'sig_first', 'sig_dashes',
                  'save_sent_mail', 'sent_mail_folder'));
    }

    /**
     * Verifies and sanitizes all identity properties.
     *
     * @param integer $identity  The identity to verify.
     *
     * @return boolean|object  True if the properties are valid or a PEAR_Error
     *                         with an error description otherwise.
     */
    function verify($identity = null)
    {
        if (is_a($result = parent::verify($identity), 'PEAR_Error')) {
            return $result;
        }

        if (!isset($identity)) {
            $identity = $this->_default;
        }

        /* Clean up Alias, Tie-to, and BCC addresses. */
        require_once 'Horde/Array.php';
        foreach (array('alias_addr', 'tieto_addr', 'bcc_addr') as $val) {
            $data = $this->getValue($val, $identity);
            if (is_array($data)) {
                $data = implode("\n", $data);
            }
            $data = trim($data);
            $data = (empty($data)) ? array() : Horde_Array::prepareAddressList(preg_split("/[\n\r]+/", $data));
            $this->setValue($val, $data, $identity);
        }

        /* Split the list of headers by new lines and sort the list of headers
         * to make sure there are no duplicates. */
        $mail_hdr = $this->getValue('mail_hdr', $identity);
        if (is_array($mail_hdr)) {
            $mail_hdr = implode("\n", $mail_hdr);
        }
        $mail_hdr = trim($mail_hdr);
        if (!empty($mail_hdr)) {
            $mail_hdr = str_replace(':', '', $mail_hdr);
            $mail_hdr = preg_split("/[\n\r]+/", $mail_hdr);
            $mail_hdr = array_map('trim', $mail_hdr);
            $mail_hdr = array_unique($mail_hdr);
            natcasesort($mail_hdr);
        }
        $this->setValue('mail_hdr', $mail_hdr, $identity);
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
        global $imp;
        static $froms = array();

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
            $ob = imap_rfc822_parse_adrlist($address, $imp['maildomain']);
        }

        if (empty($name)) {
            if (!empty($ob[0]->personal)) {
                $name = $ob[0]->personal;
            } else {
                $name = $this->getFullname($ident);
            }
        }

        $from = MIME::trimEmailAddress(MIME::rfc822WriteAddress($ob[0]->mailbox, $ob[0]->host, $name));

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
     * box in the IMP compose window.
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
     * This function will search aliases for an identity automatically.
     *
     * @param string $address  The address to search for in the identities.
     *
     * @return boolean  True if the address was found.
     */
    function hasAddress($address)
    {
        static $list;

        $address = String::lower($address);
        if (!isset($list)) {
            $list = $this->getAllFromAddresses(true);
        }

        return isset($list[$address]);
    }

    /**
     * Returns the from address based on the chosen identity. If no
     * address can be found it is built from the current user name and
     * the specified maildomain.
     *
     * @param integer $ident  The identity to retrieve the address from.
     *
     * @return string  A valid from address.
     */
    function getFromAddress($ident = null)
    {
        global $imp;

        if (!empty($this->_fromList[$ident])) {
            return $this->_fromList[$ident];
        }

        $val = $this->getValue('from_addr', $ident);
        if (empty($val)) {
            $val = $imp['user'];
        }

        if (!strstr($val, '@')) {
            $val .= '@' . $imp['maildomain'];
        }

        $this->_fromList[$ident] = $val;

        return $val;
    }

    /**
     * Returns all aliases based on the chosen identity.
     *
     * @param integer $ident  The identity to retrieve the aliases from.
     *
     * @return array  Aliases for the identity.
     */
    function getAliasAddress($ident)
    {
        if (empty($this->_aliases[$ident])) {
            $this->_aliases[$ident] = @array_merge($this->getValue('alias_addr', $ident),
                                                   array($this->getValue('replyto_addr', $ident)));
        }

        return $this->_aliases[$ident];
    }

    /**
     * Returns an array with all identities' from addresses.
     *
     * @param boolean $alias  Include aliases?
     *
     * @return array  The array with
     *                KEY - address
     *                VAL - identity number
     */
    function getAllFromAddresses($alias = false)
    {
        $list = array();

        foreach ($this->_identities as $key => $identity) {
            /* Get From Addresses. */
            $addr = String::lower($this->getFromAddress($key));
            if (!isset($list[$addr])) {
                $list[$addr] = $key;
            }

            /* Get Aliases. */
            if ($alias) {
                $addrs = $this->getAliasAddress($key);
                if (!empty($addrs)) {
                    foreach ($addrs as $val) {
                        $val = String::lower($val);
                        if (!empty($val) && !isset($list[$val])) {
                            $list[$val] = $key;
                        }
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Get all 'tie to' address/identity pairs.
     *
     * @return array  The array with
     *                KEY - address
     *                VAL - identity number
     */
    function getAllTieAddresses()
    {
        $list = array();

        foreach ($this->_identities as $key => $identity) {
            $tieaddr = $this->getValue('tieto_addr', $key);
            if (!empty($tieaddr)) {
                foreach ($tieaddr as $val) {
                    if (!isset($list[$val])) {
                        $list[$val] = $key;
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Returns the BCC addresses for a given identity.
     *
     * @param integer $ident  The identity to retrieve the Bcc addresses from.
     *
     * @return array  The array of objects (IMAP addresses).
     */
    function getBccAddresses($ident = null)
    {
        $bcc = $this->getValue('bcc_addr', $ident);
        if (empty($bcc)) {
            return array();
        } else {
            if (!is_array($bcc)) {
                $bcc = array($bcc);
            }
            $addresses = implode(', ', $bcc);
            return imap_rfc822_parse_adrlist($addresses, '');
        }
    }

    /**
     * Returns the identity's id that matches the passed addresses.
     *
     * @param mixed $addresses      Either an array or a single string or a
     *                              comma-separated list of email addresses.
     * @param boolean $search_ties  Search for a matching identity in tied
     *                              addresses too?
     *
     * @return integer  The id of the first identity that from or alias
     *                  addresses match (one of) the passed addresses or
     *                  null if none matches.
     */
    function getMatchingIdentity($addresses, $search_ties = true)
    {
        static $tie_addresses, $own_addresses;

        if (!isset($tie_addresses)) {
            $tie_addresses = $this->getAllTieAddresses();
            $own_addresses = $this->getAllFromAddresses(true);
        }

        /* Normalize address list. */
        if (!is_array($addresses)) {
            $addresses = array($addresses);
        }
        $addresses = implode(', ', $addresses);
        $addresses = imap_rfc822_parse_adrlist($addresses, '');

        foreach ($addresses as $address) {
            if (empty($address->mailbox)) {
                continue;
            }
            $find_address = $address->mailbox;
            if (!empty($address->host)) {
                $find_address .= '@' . $address->host;
            }
            $find_address = String::lower($find_address);

            /* Search 'tieto' addresses first. */
            /* Check for this address explicitly. */
            if ($search_ties && isset($tie_addresses[$find_address])) {
                return $tie_addresses[$find_address];
            }

            /* If we didn't find the address, check for the domain. */
            if (!empty($address->host)) {
                $host = '@' . $address->host;
                if ($search_ties && $host != '@' && isset($tie_addresses[$host])) {
                    return $tie_addresses[$host];
                }
            }

            /* Next, search all from addresses. */
            if (isset($own_addresses[$find_address])) {
                return $own_addresses[$find_address];
            }
        }

        return null;
    }

    /**
     * Returns the user's full name.
     *
     * @param integer $ident  The identity to retrieve the name from.
     *
     * @return string  The user's full name.
     */
    function getFullname($ident = null)
    {
        if (isset($this->_names[$ident])) {
            return $this->_names[$ident];
        }

        $this->_names[$ident] = $this->getValue('fullname', $ident);

        return $this->_names[$ident];
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
        if (isset($this->_signatures[$ident])) {
            return $this->_signatures[$ident];
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

        if (!empty($GLOBALS['conf']['hooks']['signature'])) {
            include_once HORDE_BASE . '/config/hooks.php';
            if (function_exists('_imp_hook_signature')) {
                $val = call_user_func('_imp_hook_signature', $val);
            }
        }

        $this->_signatures[$ident] = $val;

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
            $folder = parent::getValue('sent_mail_folder', $identity);
            return strlen($folder) ? IMP::folderPref($folder, true) : '';
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
        $list = array();
        foreach ($this->_identities as $key => $identity) {
            if ($folder = $this->getValue('sent_mail_folder', $key)) {
                $list[$folder] = true;
            }
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
