<?php
/**
 * The Horde_Crypt:: class provides an API for various cryptographic
 * systems used by Horde applications.
 *
 * $Horde: framework/Crypt/Crypt.php,v 1.27.10.10 2007/01/02 13:54:11 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Horde 3.0
 * @package Horde_Crypt
 */
class Horde_Crypt {

    /**
     * The temporary directory to use.
     *
     * @var string
     */
    var $_tempdir;

    /**
     * Attempts to return a concrete Horde_Crypt instance based on $driver.
     *
     * @param mixed $driver  The type of concrete Horde_Crypt subclass to
     *                       return. If $driver is an array, then we will look
     *                       in $driver[0]/lib/Crypt/ for the subclass
     *                       implementation named $driver[1].php.
     * @param array $params  A hash containing any additional configuration or
     *                       parameters a subclass might need.
     *
     * @return Horde_Crypt  The newly created concrete Horde_Crypt instance, or
     *                      false on an error.
     */
    function &factory($driver, $params = array())
    {
        if (is_array($driver)) {
            list($app, $driver) = $driver;
        }

        /* Return a base Horde_Crypt object if no driver is specified. */
        $driver = basename($driver);
        if (empty($driver) || (strcmp($driver, 'none') == 0)) {
            $crypt = &new Horde_Crypt();
            return $crypt;
        }

        if (!empty($app)) {
            require_once $GLOBALS['registry']->get('fileroot', $app) . '/lib/Crypt/' . $driver . '.php';
        } elseif (@file_exists(dirname(__FILE__) . '/Crypt/' . $driver . '.php')) {
            require_once dirname(__FILE__) . '/Crypt/' . $driver . '.php';
        } else {
            @include_once 'Horde/Crypt/' . $driver . '.php';
        }
        $class = 'Horde_Crypt_' . $driver;
        if (class_exists($class)) {
            $crypt = &new $class($params);
        } else {
            $crypt = PEAR::raiseError('Class definition of ' . $class . ' not found.');
        }

        return $crypt;
    }

    /**
     * Attempts to return a reference to a concrete Horde_Crypt instance
     * based on $driver. It will only create a new instance if no
     * Horde_Crypt instance with the same parameters currently exists.
     *
     * This should be used if multiple crypto backends (and, thus,
     * multiple Horde_Crypt instances) are required.
     *
     * This method must be invoked as: $var = &Horde_Crypt::singleton()
     *
     * @param mixed $driver  The type of concrete Horde_Crypt subclass to
     *                       return. If $driver is an array, then we will look
     *                       in $driver[0]/lib/Crypt/ for the subclass
     *                       implementation named $driver[1].php.
     * @param array $params  A hash containing any additional configuration or
     *                       connection parameters a subclass might need.
     *
     * @return Horde_Crypt  The concrete Horde_Crypt reference, or false on an
     *                      error.
     */
    function &singleton($driver, $params = array())
    {
        static $instances;

        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($driver, $params));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &Horde_Crypt::factory($driver, $params);
        }

        return $instances[$signature];
    }

    /**
     * Outputs error message if we are not using a secure connection.
     *
     * @return PEAR_Error  Returns a PEAR_Error object if there is no secure
     *                     connection.
     */
    function requireSecureConnection()
    {
        global $browser;

        if (!$browser->usingSSLConnection()) {
            return PEAR::raiseError(_("The encryption features require a secure web connection."));
        }
    }

    /**
     * Encrypt the requested data.
     * This method should be provided by all classes that extend Horde_Crypt.
     *
     * @param string $data   The data to encrypt.
     * @param array $params  An array of arguments needed to encrypt the data.
     *
     * @return array  The encrypted data.
     */
    function encrypt($data, $params = array())
    {
        return $data;
    }

    /**
     * Decrypt the requested data.
     * This method should be provided by all classes that extend Horde_Crypt.
     *
     * @param string $data   The data to decrypt.
     * @param array $params  An array of arguments needed to decrypt the data.
     *
     * @return array  The decrypted data.
     */
    function decrypt($data, $params = array())
    {
        return $data;
    }

    /**
     * Create a temporary file that will be deleted at the end of this
     * process.
     *
     * @access private
     *
     * @param string  $descrip  Description string to use in filename.
     * @param boolean $delete   Delete the file automatically?
     *
     * @return string  Filename of a temporary file.
     */
    function _createTempFile($descrip = 'horde-crypt', $delete = true)
    {
        return Util::getTempFile($descrip, $delete, $this->_tempdir, true);
    }

}
