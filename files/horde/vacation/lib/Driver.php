<?php
/**
 * Vacation_Driver:: defines an API for implementing vacation backends for the
 * vacation module.
 *
 * $Horde: vacation/lib/Driver.php,v 1.35.2.2 2007/01/02 13:55:21 jan Exp $
 *
 * Copyright 2001-2007 Eric Rostetter and Mike Cochrane
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @author  Eric Rostetter <eric.rostetter@physics.utexas.edu>
 * @package Vacation
 */
class Vacation_Driver {

    /**
     * Hash containing configuration data.
     *
     * @var array
     */
    var $_params;

    /**
     * Error string returned to user if an eror occurs.
     *
     * @var string
     */
    var $err_str;

    /**
     * Constructor.
     *
     * @param array $params  Configuration parameters for the backend.
     */
    function Vacation_Driver($params = array())
    {
        $this->_params = $params;
    }

    /**
     * Return a parameter value.
     *
     * @param string $param  The parameter to check in.
     * @param string $realm  The realm to retrieve the parameter from.
     *
     * @return mixed  The parameter value, or null if not found.
     */
    function getParam($param, $realm = 'default')
    {
        return isset($this->_params[$realm][$param]) ? $this->_params[$realm][$param] : null;
    }

    /**
     * Setup vacation notices for a user.
     *
     * @param string $user     The username to enable vacation notices for.
     * @param string $realm    The realm of the user.
     * @param string $pass     The password for the user.
     * @param string $message  The text of the vacation notice.
     * @param string $alias    Alias email address -- Not yet implemented in
     *                         backends.
     *
     * @return boolean  Returns true on success, false on error.
     */
    function setVacation($user, $realm = 'default', $pass = '',
                         $message, $alias = '')
    {
        return false;
    }

    /**
     * Disables vacation notices for a user.
     *
     * @param string $user   The user to disable vacation notices for.
     * @param string $realm  The realm of the user.
     * @param string $pass   The password of the user.
     *
     * @return boolean  Returns true on success, false on error.
     */
    function unsetVacation($user, $realm = 'default', $pass = '')
    {
        return false;
    }

    /**
     * Retrieves status of vacation for a user.
     *
     * @param string $user   The username of the user to check.
     * @param string $realm  The realm of the user to check.
     *
     * @return boolean  Returns true if vacation is enabled for the user
     *                  or false if vacation is currently disabled.
     */
    function isEnabled($user, $realm, $password)
    {
        // Get current details.
        $current_details = $this->_getUserDetails($user, $realm, $password);
        if ($current_details === false) {
            return false;
        }

        // Check vacation flag.
        if ($current_details['vacation'] == 'y' ||
            $current_details['vacation'] == 'Y' ||
            $current_details['vacation'] == '1') {
            return 'Y';
        } elseif ($current_details['vacation'] == 'n' ||
                  $current_details['vacation'] == 'N' ||
                  $current_details['vacation'] == '0') {
            return 'N';
        } else {
            return false;
        }
    }

    /**
     * Retrieves current vacation message.
     *
     * @param string $user   The username of the user.
     * @param string $realm  The realm of the user.
     *
     * @return string  The current vacation message, or false if none.
     */
    function currentMessage($user, $realm, $password)
    {
        $current_details = $this->_getUserDetails($user, $realm, $password);

        // Check current vacation message.
        return $current_details['message'];
    }

    /**
     * Retrieve the current vacation details for the user.
     *
     * @param string $user      The username for which to retrieve details.
     * @param string $realm     The realm (domain) for the user.
     * @param string $password  The password for user.
     *
     * @return  mixed        Vacation details or false.
     */
    function _getUserDetails($user, $realm, $password)
    {
        return false;
    }

    /**
     * Format a password using the current encryption.
     *
     * @param string $plaintext  The plaintext password to encrypt.
     *
     * @return string  The crypted password.
     */
    function encryptPassword($plaintext)
    {
        return Auth::getCryptedPassword($plaintext,
                                        '',
                                        $this->_params['encryption'],
                                        $this->_params['show_encryption']);
    }

    /**
     * Parse an email address list and return it in a known standard form.
     * This will attempt to add the domain (realm) to unqualified addresses
     * if the realm is non-blank and not 'default'.
     *
     * @param string $user   The email address.
     * @param string $realm  The domain/realm to add if none is present.
     *
     * @return string  The email address(es) on success, false on error.
     */
    function _makeEmailAddress($user, $realm)
    {
        $domain = ($realm != 'default') ? $realm : '';
        $email = '';

        if ($this->getParam('norealm', $realm)) {
            $domain = '';
        }

        require_once 'Mail/RFC822.php';
        $parser = &new Mail_RFC822();
        $parsed_email = $parser->parseAddressList($user, $domain, false, false);
        if (is_array($parsed_email) && count($parsed_email) > 0) {
            for ($i = 0; $i < count($parsed_email); $i++) {
               $email .= !empty($email) ? ',' : '';
               if (is_object($parsed_email[$i])) {
                 $email .= $parsed_email[$i]->mailbox;
                 $email .= !empty($parsed_email[$i]->host)
                        ? '@' . $parsed_email[$i]->host
                        : '';
              } else {
                 $email .= $parsed_email[$i];
              }
            }
        } else {
            $this->err_str = _("Can't parse your email address");
            $email = false;
        }

        return $email;
    }

    /**
     * Attempts to return a concrete Vacation_Driver instance based on $driver.
     *
     * @param string    $driver    The type of concrete Vacation_Driver subclass
     *                             to return.  The is based on the vacation
     *                             driver ($driver).  The code is dynamically
     *                             included.
     *
     * @param array     $params    A hash containing any additional
     *                             configuration or connection parameters a
     *                             subclass might need.
     *
     * @return mixed    The newly created concrete Vacation_Driver instance, or
     *                  false on an error.
     */
    function &factory($driver = null, $params = null)
    {
        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['server']['driver'];
        }

        $driver = basename($driver);

        if (is_null($params)) {
            $params = Horde::getDriverConfig('server', $driver);
        }

        require_once dirname(__FILE__) . '/Driver/' . $driver . '.php';
        $class = 'Vacation_Driver_' . $driver;
        if (class_exists($class)) {
            $vacation = &new $class($params);
        } else {
            $vacation = false;
        }

        return $vacation;
    }

    /**
     * Attempts to return a reference to a concrete Vacation_Driver instance
     * based on $driver.  It will only create a new instance if no
     * Vacation_Driver instance with the same parameters currently exists.
     *
     * This should be used if multiple storage sources are required.
     *
     * This method must be invoked as: $var = &Vacation_Driver::singleton()
     *
     * @param string    $driver    The type of concrete Vacation_Driver subclass
     *                             to return.  The is based on the vacation
     *                             driver ($driver).  The code is dynamically
     *                             included.
     *
     * @param array     $params    A hash containing any additional
     *                             configuration or connection parameters a
     *                             subclass might need.
     *
     * @return mixed    The created concrete Vacation_Driver instance, or false
     *                  on error.
     */
    function &singleton($driver = null, $params = null)
    {
        static $instances;

        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['server']['driver'];
        }

        if (is_null($params)) {
            $params = Horde::getDriverConfig('server', $driver);
        }

        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($driver, $params));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &Vacation_Driver::factory($driver, $params);
        }

        return $instances[$signature];
    }

}
