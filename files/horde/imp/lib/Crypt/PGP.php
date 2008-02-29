<?php

require_once 'Horde/Crypt/pgp.php';

/**
 * Name of PGP public key field in addressbook.
 */
define('IMP_PGP_PUBKEY_FIELD', 'pgpPublicKey');

/**
 * The IMP_PGP:: class contains all functions related to handling
 * PGP messages within IMP.
 *
 * $Horde: imp/lib/Crypt/PGP.php,v 1.90.2.19 2007/03/09 11:42:18 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 4.0
 * @package IMP
 */
class IMP_PGP extends Horde_Crypt_pgp {

    /**
     * The list of available sources to search for keys.
     *
     * @var array
     */
    var $_sources = array();

    /**
     * Constructor
     */
    function IMP_PGP()
    {
        /* Get the listing of all sources we search for public keys. */
        if (($sources = $GLOBALS['prefs']->getValue('search_sources'))) {
            $this->_sources = explode("\t", $sources);
            if ((count($this->_sources) == 1) && empty($this->_sources[0])) {
                $this->_sources = array();
            }
        }

        parent::Horde_Crypt_pgp(array('program' => $GLOBALS['conf']['utils']['gnupg'],
                                      'temp' => Horde::getTempDir()));
    }

    /**
     * Generate the personal Public/Private keypair and store in prefs.
     *
     * @param string $realname            See Horde_Crypt_pgp::
     * @param string $email               See Horde_Crypt_pgp::
     * @param string $passphrase          See Horde_Crypt_pgp::
     * @param string $comment             See Horde_Crypt_pgp::
     * @param string $keylength           See Horde_Crypt_pgp::
     *
     * @return PEAR_Error  Returns a PEAR_Error object on error.
     */
    function generatePersonalKeys($name, $email, $passphrase, $comment = '',
                                  $keylength = 1024)
    {
        $keys = $this->generateKey($name, $email, $passphrase, $comment, $keylength);
        if (is_a($keys, 'PEAR_Error')) return $keys;

        /* Store the keys in the user's preferences. */
        $this->addPersonalPublicKey($keys['public']);
        $this->addPersonalPrivateKey($keys['private']);
    }

    /**
     * Add the personal public key to the prefs.
     *
     * @param mixed $public_key  The public key to add (either string or
     *                           array).
     */
    function addPersonalPublicKey($public_key)
    {
        $GLOBALS['prefs']->setValue('pgp_public_key', (is_array($public_key)) ? implode('', $public_key) : $public_key);
    }

    /**
     * Add the personal private key to the prefs.
     *
     * @param mixed $private_key  The private key to add (either string or
     *                            array).
     */
    function addPersonalPrivateKey($private_key)
    {
        $GLOBALS['prefs']->setValue('pgp_private_key', (is_array($private_key)) ? implode('', $private_key) : $private_key);
    }

    /**
     * Get the personal public key from the prefs.
     *
     * @return string  The personal PGP public key.
     */
    function getPersonalPublicKey()
    {
        return $GLOBALS['prefs']->getValue('pgp_public_key');
    }

    /**
     * Get the personal private key from the prefs.
     *
     * @return string  The personal PGP private key.
     */
    function getPersonalPrivateKey()
    {
        return $GLOBALS['prefs']->getValue('pgp_private_key');
    }

    /**
     * Deletes the specified personal keys from the prefs.
     */
    function deletePersonalKeys()
    {
        $GLOBALS['prefs']->setValue('pgp_public_key', '');
        $GLOBALS['prefs']->setValue('pgp_private_key', '');

        $this->unsetPassphrase();
    }

    /**
     * Add a public key to an address book.
     *
     * @param string $public_key  An PGP public key.
     *
     * @return array  See Horde_Crypt_pgp::pgpPacketInformation()
     *                Returns PEAR_Error or error.
     */
    function addPublicKey($public_key)
    {
        /* Make sure the key is valid. */
        $key_info = $this->pgpPacketInformation($public_key);
        if (!isset($key_info['signature'])) {
            return PEAR::raiseError(_("Not a valid public key."), 'horde.error');
        }

        /* Remove the '_SIGNATURE' entry. */
        unset($key_info['signature']['_SIGNATURE']);

        /* Store all signatures that appear in the key. */
        foreach ($key_info['signature'] as $id => $sig) {
            /* Check to make sure the key does not already exist in ANY
               address book and removes the id from the key_info for a
               correct output. */
            $result = $this->getPublicKey($sig['email'], null, true);
            if (!is_a($result, 'PEAR_Error') && !empty($result)) {
                unset($key_info['signature'][$id]);
                continue;
            }

            /* Add key to the user's address book. */
            $result = $GLOBALS['registry']->call('contacts/addField', array($sig['email'], $sig['name'], IMP_PGP_PUBKEY_FIELD, $public_key, $GLOBALS['prefs']->getValue('add_source')));
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        return $key_info;
    }

	/**
	 * Retrieves the public key from the preference storage.
	 */
    function _getPublicKeyFromPrefs($address)
    {
        global $conf;
        $userprefs = &Prefs::singleton($conf['prefs']['driver'],'imp', $address, '', null, false);
        $userprefs->retrieve();
        return $pk = $userprefs->getValue('pgp_public_key');
    }


    /**
     * Retrieves a public key by e-mail.
     * First, the key will be attempted to be retrieved from a user's
     * address book(s).
     * Second, if unsuccessful, the key is attempted to be retrieved via
     * a public PGP keyserver.
     *
     * @param string $address      The e-mail address to search by.
     * @param string $fingerprint  The fingerprint of the user's key.
     *
     * @return string  The PGP public key requested. Returns PEAR_Error object
     *                 on error.
     */
    function getPublicKey($address, $fingerprint = null)
    {
       /* 1. try users database */
        $prefs_key = $this->_getPublicKeyFromPrefs($address);
        if(strlen($prefs_key) > 100 &&
            preg_match('/-----BEGIN PGP ([^-]+)-----/', $prefs_key)){
            return $prefs_key;
        }

        /* 2. try retrieving from Contacts */
        $result = $GLOBALS['registry']->call('contacts/getField', array($address, IMP_PGP_PUBKEY_FIELD, $this->_sources, false, true));

        /* TODO: Retrieve by ID. */
		 if($fingerprint == null){
            $fingerprint = $address;
        }

        /* Try retrieving via a PGP public keyserver. */
        if (is_a($result, 'PEAR_Error') && !empty($fingerprint)) {
            $result = $this->getFromPublicKeyserver($fingerprint);
        }

        /* See if the address points to the user's public key. */
        if (is_a($result, 'PEAR_Error')) {
            require_once 'Horde/Identity.php';
            $identity = &Identity::singleton(array('imp', 'imp'));
            $personal_pubkey = $this->getPersonalPublicKey();
            if (!empty($personal_pubkey) && $identity->hasAddress($address)) {
                return $personal_pubkey;
            }
        }

        /* If more than one public key is returned, just return the first in
         * the array. There is no way of knowing which is the "preferred" key,
         * if the keys are different. */
        if (is_array($result)) {
            return reset($result);
        }

        return $result;
    }

    /**
     * Retrieves all public keys from a user's address book(s).
     *
     * @return array  All PGP public keys available. Returns PEAR_Error object
     *                on error.
     *
     */
    function listPublicKeys()
    {
        if (empty($this->_sources)) {
            return array();
        } else {
            return $GLOBALS['registry']->call('contacts/getAllAttributeValues', array(IMP_PGP_PUBKEY_FIELD, $this->_sources));
        }
    }

    /**
     * Deletes a public key from a user's address book(s) by e-mail.
     *
     * @param string $email  The e-mail address to delete.
     *
     * @return PEAR_Error  Returns PEAR_Error object on error.
     */
    function deletePublicKey($email)
    {
        return $GLOBALS['registry']->call('contacts/deleteField', array($email, IMP_PGP_PUBKEY_FIELD, $this->_sources));
    }

    /**
     * Parse a message into its PGP components.
     *
     * @param string $text  See Horde_Crypt_pgp::parsePGPData()
     *
     * @return array  Returns an array of MIME_Part objects.
     *                If there was no PGP data, returns false.
     */
    function &parseMessage($text)
    {
        $result = $this->parsePGPData($text);
        if (empty($result) ||
            ((count($result) == 1) && ($result[0]['type'] == PGP_ARMOR_TEXT))) {
            $result = false;
            return $result;
        }

        include_once 'Horde/MIME/Part.php';

        $return_array = array();

        reset($result);
        do {
            $block = current($result);
            $temp_part = &new MIME_Part();
            $temp_part->setContents(implode("\n", $block['data']));

            /* Since private keys should NEVER be sent across email (in fact,
               there is no MIME type to handle them) we will render them, if
               someone is foolish enough to send one, in simple text. */
            if (($block['type'] == PGP_ARMOR_TEXT) ||
                ($block['type'] == PGP_ARMOR_PRIVATE_KEY)) {
                $temp_part->setType('text/plain');
            } elseif ($block['type'] == PGP_ARMOR_PUBLIC_KEY) {
                $temp_part->setType('application/pgp-keys');
            } elseif ($block['type'] == PGP_ARMOR_MESSAGE) {
                $temp_part->setType('application/pgp-encrypted');
            } elseif ($block['type'] == PGP_ARMOR_SIGNED_MESSAGE) {
                $temp_part->setType('application/pgp-signature');
                if (($block = next($result))) {
                    if (!empty($block) && ($block['type'] == PGP_ARMOR_SIGNATURE)) {
                        $temp_part->appendContents("\n" . implode("\n", $block['data']));
                    }
                }
            } elseif ($block['type'] == PGP_ARMOR_SIGNATURE) {
                continue;
            }

            $return_array[] = $temp_part;
        } while (next($result));

        return $return_array;
    }

    /**
     * Renders a text message with PGP components.
     *
     * @param MIME_Part &$part          The MIME_Part containing the data to
     *                                  render.
     * @param MIME_Contents &$contents  The MIME_Contents:: module to use to
     *                                  output the text.
     *
     * @return string  Returns the rendered text.
     *                 If there was no PGP data, returns false.
     */
    function parseMessageOutput(&$part, &$contents)
    {
        if (!($parts = &$this->parseMessage($part->getContents()))) {
            return false;
        }

        $text = '';

        require_once 'Horde/MIME/Message.php';

        $base_ob = &$contents->getBaseObjectPtr();
        $addr = $base_ob->getFromAddress();

        $message = &new MIME_Message();
        foreach ($parts as $val) {
            $message->addPart($val);
        }

        $mc = &new MIME_Contents($message, array('download' => 'download_attach', 'view' => 'view_attach'), array(&$contents));
        $message->buildMessage();

        foreach ($message->getParts() as $val) {
            /* If the part appears to be nothing but empty space, don't
               display it. */
            if (($val->getBytes() < 5) &&
                !(rtrim($val->getContents()))) {
                continue;
            }
            $v = &$mc->getMIMEViewer($val);
            if (!is_a($v, 'IMP_MIME_Viewer_pgp')) {
                $text .= $mc->formatStatusMsg(_("The message below has not been digitally signed or encrypted with PGP."), Horde::img('alerts/warning.png', _("Warning"), '', $GLOBALS['registry']->getImageDir('horde')));
            }
            $text .= $mc->renderMIMEPart($val);
        }

        return $text;
    }

    /**
     * Returns the signed data only for a plaintext signed MIME_Part.
     *
     * @param MIME_Part $mime_part  The MIME_Part object with a plaintext PGP
     *                              signed message in the contents.
     *
     * @return string  The contents of the signed message.
     */
    function getSignedMessage(&$mime_part)
    {
        $msg = '';

        /* Just output signed data - remove all PGP headers. */
        $result = $this->parsePGPData($mime_part->getContents());
        foreach ($result as $block) {
            if ($block['type'] == PGP_ARMOR_SIGNED_MESSAGE) {
                $headerSeen = false;
                $headerDone = false;
                foreach ($block['data'] as $line) {
                    if ($headerDone) {
                        $msg .= $line . "\n";
                    } elseif (strpos($line, "-----") === 0) {
                        $headerSeen = true;
                        continue;
                    } elseif ($headerSeen) {
                        /* There are some versions of GnuPG (like Version:
                           GnuPG v1.2.1 (MingW32)) which separate headers from
                           content with a line containing a blank, but this
                           isn't RFC conforming, so this isn't handled.
                           It results in a good signature with an empty
                           message.
                           The wrong code would be:
                           elseif (empty($line) || (strcmp($line, ' ') == 0))
                         */
                        $line = trim($line);
                        if (empty($line)) {
                            $headerDone = true;
                        }
                    }
                }
            }
        }

        return rtrim($msg);
    }

    /**
     * Get a public key via a public PGP keyserver.
     *
     * @param string $fingerprint  The fingerprint of the requested key.
     *
     * @return string  See Horde_Crypt_pgp::getPublicKeyserver()
     */
    function getFromPublicKeyserver($fingerprint)
    {
        return $this->_keyserverConnect($fingerprint, 'get');
    }

    /**
     * Send a public key to a public PGP keyserver.
     *
     * @param string $pubkey  The PGP public key.
     *
     * @return string  See Horde_Crypt_pgp::putPublicKeyserver()
     */
    function sendToPublicKeyserver($pubkey)
    {
        return $this->_keyserverConnect($pubkey, 'put');
    }

    /**
     * Connect to the keyservers
     *
     * @access private
     *
     * @param string $data    The data to send to the keyserver.
     * @param string $method  The method to use - either 'get' or 'put'.
     *
     * @return string  See Horde_Crypt_pgp::getPublicKeyserver()  -or-
     *                     Horde_Crypt_pgp::putPublicKeyserver().
     */
    function _keyserverConnect($data, $method)
    {
        global $conf;

        if (!empty($conf['utils']['gnupg_keyserver'])) {
            $timeout = (empty($conf['utils']['gnupg_timeout'])) ? PGP_KEYSERVER_TIMEOUT : $conf['utils']['gnupg_timeout'];
            if ($method == 'get') {
                foreach ($conf['utils']['gnupg_keyserver'] as $server) {
                    $result = $this->getPublicKeyserver($data, $server, $timeout);
                    if (!is_a($result, 'PEAR_Error')) {
                        return $result;
                    }
                }
                return $result;
            } else {
                return $this->putPublicKeyserver($data, $conf['utils']['gnupg_keyserver'][0], $timeout);
            }
        } else {
            return PEAR::raiseError(_("Public PGP keyserver support has been disabled."), 'horde.warning');
        }
    }

    /**
     * Verifies a signed message with a given public key.
     *
     * @param string $text       The text to verify.
     * @param string $address    E-mail address of public key.
     * @param string $signature  A PGP signature block.
     *
     * @return string  See Horde_Crypt_pgp::decryptSignature()
     *                 -OR-
     *                 Horde_Crypt_pgp::decryptDetachedSignature()
     */
    function verifySignature($text, $address, $signature = '')
    {
        $fingerprint = null;

        /* Get fingerprint of key. */
        if (!empty($signature)) {
            $packet_info = $this->pgpPacketInformation($signature);
            if (isset($packet_info['fingerprint'])) {
                $fingerprint = $packet_info['fingerprint'];
            }
        } else {
            $fingerprint = $this->getSignersFingerprint($text);
        }

        $public_key = $this->getPublicKey($address, $fingerprint);
        if (is_a($public_key, 'PEAR_Error')) {
            return $public_key;
        }

        if (!empty($signature)) {
            $options = array('type' => 'detached-signature', 'signature' => $signature);
        } else {
            $options = array('type' => 'signature');
        }
        $options['pubkey'] = $public_key;

        /* decrypt() returns a PEAR_Error object on error. */
        return $this->decrypt($text, $options);
    }


    /**
     * Decrypt a message with user's public/private keypair.
     *
     * @param string $text         The text to decrypt.
     * @param boolean $passphrase  Whether a passphrase has to be used.
     *
     * @return string  The decrypted message. Returns PEAR_Error object on
     *                 error.
     */
    function decryptMessage($text, $passphrase = true)
    {
        /* decrypt() returns a PEAR_Error object on error. */
        if (!$passphrase) {
            return $this->decrypt($text, array('type' => 'message', 'no_passphrase' => true));
        } else {
            return $this->decrypt($text, array('type' => 'message', 'pubkey' => $this->getPersonalPublicKey(), 'privkey' => $this->getPersonalPrivateKey(), 'passphrase' => $this->getPassphrase()));
        }
    }

    /**
     * Gets the user's passphrase from the session cache.
     *
     * @return string  The passphrase, if set.
     */
    function getPassphrase()
    {
        global $imp;

        if (isset($imp['pgp_passphrase'])) {
            return Secret::read(Secret::getKey('imp'), $imp['pgp_passphrase']);
        }
    }

    /**
     * Store's the user's passphrase in the session cache.
     *
     * @param string $passphrase  The user's passphrase.
     *
     * @return boolean  Returns true if correct passphrase, false if incorrect.
     */
    function storePassphrase($passphrase)
    {
        if ($this->verifyPassphrase($this->getPersonalPublicKey(), $this->getPersonalPrivateKey(), $passphrase) === false) {
            return false;
        }

        $GLOBALS['imp']['pgp_passphrase'] = Secret::write(Secret::getKey('imp'), $passphrase);

        return true;
    }

    /**
     * Clear the passphrase from the session cache.
     */
    function unsetPassphrase()
    {
        unset($GLOBALS['imp']['pgp_passphrase']);
    }

    /**
     * Generates the javascript code for saving public keys.
     *
     * @param MIME_Part &$mime_part  The MIME_Part containing the public key.
     * @param string $cache          The MIME_Part identifier.
     *
     * @return string  The URL for saving public keys.
     */
    function savePublicKeyURL(&$mime_part, $cache = null)
    {
        if (empty($cache)) {
            require_once 'Horde/SessionObjects.php';
            $cacheSess = &Horde_SessionObjects::singleton();
            $oid = $cacheSess->storeOid($mime_part);
        }

        return $this->getJSOpenWinCode('save_attachment_public_key', false, array('mimecache' => $oid));
    }

    /**
     * Print out the link for the javascript PGP popup.
     *
     * @param string $actionid  The ActionID to perform.
     * @param mixed $reload     If true, reload base window on close. If text,
     *                          run this JS on close. If false, don't do
     *                          anything on close.
     * @param array $params     Additional parameters needed for the reload
     *                          page.
     *
     * @return string  The javascript link.
     */
    function getJSOpenWinCode($actionid, $reload = true, $params = null)
    {
        $popup_url = Horde::applicationUrl('pgp.php');
        $popup_url = Util::addParameter($popup_url, 'actionID', $actionid, false);
        if (!empty($reload)) {
            if (is_bool($reload)) {
                $popup_url = Util::addParameter($popup_url, 'reload', Util::removeParameter(Horde::selfUrl(true), array('actionID')), false);
            } else {
                require_once 'Horde/SessionObjects.php';
                $cacheSess = &Horde_SessionObjects::singleton();
                $popup_url = Util::addParameter($popup_url, 'passphrase_action', $cacheSess->storeOid($reload, false), false);
            }
        }

        if (is_array($params)) {
            foreach ($params as $key => $val) {
                $popup_url = Util::addParameter($popup_url, $key, $val, false);
            }
        }

        return "popup_imp('" . $popup_url . "',450,200);";
    }

    /**
     * Provide the list of parameters needed for signing a message.
     *
     * @access private
     *
     * @return array  The list of parameters needed by encrypt().
     */
    function _signParameters()
    {
        return array('pubkey' => $this->getPersonalPublicKey(), 'privkey' => $this->getPersonalPrivateKey(), 'passphrase' => $this->getPassphrase());
    }

    /**
     * Provide the list of parameters needed for encrypting a message.
     *
     * @access private
     *
     * @param array $addresses  The e-mail address of the keys to use for
     *                          encryption.
     *
     * @return array  The list of parameters needed by encrypt().
     *                Returns PEAR_Error on error.
     */
    function _encryptParameters($addresses)
    {
        $addr_list = array();

        foreach ($addresses as $val) {
            /* We can only encrypt if we are sending to a single person. */
            $addrOb = IMP::bareAddress($val, true);
            $key_addr = array_pop($addrOb);

            /* Get the public key for the address. */
            $public_key = $this->getPublicKey($key_addr);
            if (is_a($public_key, 'PEAR_Error')) {
                return $public_key;
            }
            $addr_list[$key_addr] = $public_key;
        }

        if (!empty($this->multipleRecipientEncryption)) {
            return array('recips' => $addr_list);
        } else {
            return array('pubkey' => $public_key, 'email' => $key_addr);
        }
    }

    /**
     * Sign a MIME_Part using PGP using IMP default parameters.
     *
     * @param MIME_Part $mime_part  The MIME_Part object to sign.
     *
     * @return MIME_Part  See Horde_Crypt_pgp::signMIMEPart(). Returns
     *                    PEAR_Error object on error.
     */
    function IMPsignMIMEPart($mime_part)
    {
        return $this->signMIMEPart($mime_part, $this->_signParameters());
    }

    /**
     * Encrypt a MIME_Part using PGP using IMP default parameters.
     *
     * @param MIME_Part $mime_part  The MIME_Part object to encrypt.
     * @param array $addresses      The e-mail address of the keys to use for
     *                              encryption.
     *
     * @return MIME_Part  See Horde_Crypt_pgp::encryptMIMEPart(). Returns
     *                    PEAR_Error object on error.
     */
    function IMPencryptMIMEPart($mime_part, $addresses)
    {
        $params = $this->_encryptParameters($addresses);
        if (is_a($params, 'PEAR_Error')) {
            return $params;
        }
        return $this->encryptMIMEPart($mime_part, $params);
    }

    /**
     * Sign and Encrypt a MIME_Part using PGP using IMP default parameters.
     *
     * @param MIME_Part $mime_part  The MIME_Part object to sign and encrypt.
     * @param array $addresses      The e-mail address of the keys to use for
     *                              encryption.
     *
     * @return MIME_Part  See Horde_Crypt_pgp::signAndencryptMIMEPart().
     *                    Returns PEAR_Error object on error.
     */
    function IMPsignAndEncryptMIMEPart($mime_part, $addresses)
    {
        $encrypt_params = $this->_encryptParameters($addresses);
        if (is_a($encrypt_params, 'PEAR_Error')) {
            return $encrypt_params;
        }
        return $this->signAndEncryptMIMEPart($mime_part, $this->_signParameters(), $encrypt_params);
    }

    /**
     * Generate a MIME_Part object, in accordance with RFC 2015/3156, that
     * contains the user's public key.
     *
     * @return MIME_Part  See Horde_Crypt_pgp::publicKeyMIMEPart().
     */
    function publicKeyMIMEPart()
    {
        return parent::publicKeyMIMEPart($this->getPersonalPublicKey());
    }

}
