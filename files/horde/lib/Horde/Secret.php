<?php
/**
 * The Secret:: class provides an API for encrypting and decrypting
 * small pieces of data with the use of a shared key.
 *
 * The Secret:: functions use the Horde Cipher:: class if mcrypt is not
 * available.
 *
 * $Horde: framework/Secret/Secret.php,v 1.45.10.8 2007/01/02 13:54:37 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 1.3
 * @package Horde_Secret
 */
class Secret {

    /**
     * Take a small piece of data and encrypt it with a key.
     *
     * @param string $key      The key to use for encryption.
     * @param string $message  The plaintext message.
     *
     * @return string  The ciphertext message.
     */
    function write($key, $message)
    {
        if (Util::extensionExists('mcrypt')) {
            $td = @mcrypt_module_open(MCRYPT_GOST, '', MCRYPT_MODE_ECB, '');
            if ($td) {
                $iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
                @mcrypt_generic_init($td, $key, $iv);
                $encrypted_data = @mcrypt_generic($td, $message);
                @mcrypt_generic_deinit($td);

                return $encrypted_data;
            }
        }

        static $cipherCache = array();
        $cacheIdx = md5($key);

        if (!isset($cipherCache[$cacheIdx])) {
            require_once 'Horde/Cipher.php';

            $cipherCache[$cacheIdx] = &Horde_Cipher::factory('blowfish');
            $cipherCache[$cacheIdx]->setBlockMode('ofb64');
            $cipherCache[$cacheIdx]->setKey($key);
        }

        return $cipherCache[$cacheIdx]->encrypt($message);
    }

    /**
     * Decrypt a message encrypted with Secret::write().
     *
     * @param string $key      The key to use for decryption.
     * @param string $message  The ciphertext message.
     *
     * @return string  The plaintext message.
     */
    function read($key, $ciphertext)
    {
        if (Util::extensionExists('mcrypt')) {
            $td = @mcrypt_module_open(MCRYPT_GOST, '', MCRYPT_MODE_ECB, '');
            if ($td) {
                $iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
                @mcrypt_generic_init($td, $key, $iv);
                $decrypted_data = @mdecrypt_generic($td, $ciphertext);
                @mcrypt_generic_deinit($td);

                // Strip padding characters.
                return rtrim($decrypted_data, "\0");
            }
        }

        static $cipherCache;
        $cacheIdx = md5($key);

        if (!is_array($cipherCache) || !isset($cipherCache[$cacheIdx])) {
            require_once 'Horde/Cipher.php';

            $cipherCache[$cacheIdx] = &Horde_Cipher::factory('blowfish');
            $cipherCache[$cacheIdx]->setBlockMode('ofb64');
            $cipherCache[$cacheIdx]->setKey($key);
        }

        return $cipherCache[$cacheIdx]->decrypt($ciphertext);
    }

    /**
     * Generate a secret key (for encryption), either using a random
     * md5 string and storing it in a cookie if the user has cookies
     * enabled, or munging some known values if they don't.
     *
     * @param string $keyname  The name of the key to set.
     *
     * @return string  The secret key that has been generated.
     */
    function setKey($keyname = 'generic')
    {
        global $conf;

        $timeout = $conf['session']['timeout'] ? time() + $conf['session']['timeout'] : 0;

        if (isset($_COOKIE[$conf['session']['name']])) {
            if (isset($_COOKIE[$keyname . '_key'])) {
                $key = $_COOKIE[$keyname . '_key'];
            } else {
                $key = md5(mt_rand());
                $_COOKIE[$keyname . '_key'] = $key;
                @setcookie($keyname . '_key', $key, $timeout, $conf['cookie']['path'],
                           $conf['cookie']['domain'], $conf['use_ssl'] == 1 ? 1 : 0);
            }
        } else {
            $key = session_id();
            @setcookie($keyname . '_key', $key, $timeout, $conf['cookie']['path'],
                       $conf['cookie']['domain'], $conf['use_ssl'] == 1 ? 1 : 0);
        }

        return $key;
    }

    /**
     * Return a secret key, either from a cookie, or if the cookie
     * isn't there, assume we are using a munged version of a known
     * base value.
     *
     * @param string $keyname  The name of the key to get.
     *
     * @return string  The secret key.
     */
    function getKey($keyname = 'generic')
    {
        static $keycache = array();

        if (!isset($keycache[$keyname])) {
            if (isset($_COOKIE[$keyname . '_key'])) {
                $keycache[$keyname] = $_COOKIE[$keyname . '_key'];
            } else {
                global $conf;
                $keycache[$keyname] = session_id();
                @setcookie($keyname . '_key', $keycache[$keyname],
                           $conf['session']['timeout'] ? time() + $conf['session']['timeout'] : 0,
                           $conf['cookie']['path'], $conf['cookie']['domain'], $conf['use_ssl'] == 1 ? 1 : 0);
            }
        }

        return $keycache[$keyname];
    }

    /**
     * Clears a secret key entry from the current cookie.
     *
     * @param string $keyname  The name of the key to clear.
     *
     * @return boolean  True if key existed, false if not.
     */
    function clearKey($keyname = 'generic')
    {
        if (isset($_COOKIE[$GLOBALS['conf']['session']['name']]) &&
            isset($_COOKIE[$keyname . '_key'])) {
            unset($_COOKIE[$keyname . '_key']);
            return true;
        }
        return false;
    }

}
