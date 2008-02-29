<?php
/**
 * The LDAP class attempts to change a user's password stored in an LDAP
 * directory service.
 *
 * $Horde: passwd/lib/Driver/ldap.php,v 1.41.2.4 2007/01/02 13:55:14 jan Exp $
 *
 * Copyright 2000-2007 Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.php.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @author  Tjeerd van der Zee <admin@xar.nl>
 * @author  Mattias Webjörn Eriksson <mattias@webjorn.org>
 * @author  Eric Jon Rostetter <eric.rostetter@physics.utexas.edu>
 * @package Passwd
 */
class Passwd_Driver_ldap extends Passwd_Driver {

    /**
     * LDAP connection handle.
     *
     * @var resource
     */
    var $_ds;

    /**
     * Constructs a new Passwd_Driver_ldap object.
     *
     * @param array $params  A hash containing connection parameters.
     */
    function Passwd_Driver_ldap($params = array())
    {
        $this->_params = array_merge(array('host' => 'localhost',
                                           'sslhost' => '',
                                           'port' => 389,
                                           'encryption' => 'crypt',
                                           'show_encryption' => 'true',
                                           'uid' => 'uid',
                                           'basedn' => '',
                                           'admindn' => null,
                                           'adminpw' => null,
                                           'realm' => '',
                                           'tls' => null,
                                           'attribute' => 'userPassword',
                                           'shadowlastchange' => null,
                                           'shadowmin' => null),
                                     $params);
    }

    /**
     * Does an LDAP connect and binds as the guest user or as the optional
     * userdn.
     *
     * @param string $userdn       The dn to use when binding non-anonymously.
     * @param string $oldpassword  The password for $userdn.
     * @param boolean $ssl         The SSL trigger.
     *
     * @return boolean  True or False based on success of connect and bind.
     */
    function _connect($userdn = null, $password = null, $ssl = false)
    {
        if ($ssl && empty($this->_params['tls'])) {
            $this->_ds = ldap_connect('ldaps://' . $this->_params['sslhost']);
        } else {
            $this->_ds = ldap_connect($this->_params['host'], $this->_params['port']);
        }
        if (!$this->_ds) {
            return PEAR::raiseError(_("Could not connect to LDAP server"));
        }

        if (ldap_set_option($this->_ds, LDAP_OPT_PROTOCOL_VERSION, 3) &&
            $this->_params['tls']) {
            if (!ldap_start_tls($this->_ds)) {
                return PEAR::raiseError(_("Could not start TLS connection to LDAP server"));
            }
        }

        $result = false;
        if (!is_null($this->_params['admindn'])) {
            // If we have an admindn, try to bind as admin.
            $result = @ldap_bind($this->_ds, $this->_params['admindn'], $this->_params['adminpw']);
        } else {
            // Try to bind as the current userdn with password.
            if (!is_null($userdn)) {
                $result = @ldap_bind($this->_ds, $userdn, $password);
            } else {
                $result = @ldap_bind($this->_ds);
            }
        }

        // If none of the bind attempts succeed, return error.
        if (!$result) {
            return PEAR::raiseError(_("Could not bind to LDAP server"));
        }

        return true;
    }

    /**
     * Looks up and returns the user's dn.
     *
     * @param string $user    The username of the user.
     * @param string $passw   The password of the user.
     * @param string $realm   The realm (domain) name of the user.
     * @param string $basedn  The ldap basedn.
     * @param string $uid     The ldap uid.
     *
     * @return string  The ldap dn for the user.
     */
    function _lookupdn($user, $passw)
    {
        // Construct username@realm to connect as if 'realm' parameter is set.
        $urealm = '';
        if (!empty($this->_params['realm'])) {
            $urealm = $user . '@' . $this->_params['realm'];
        }

        // Bind as current user. _connect will try as guest if no user realm
        // is found or auth error.
        $this->_connect($urealm, $passw);

        // Construct search.
        $search = $this->_params['uid'] . '=' . $user;

        // Get userdn.
        $result = ldap_search($this->_ds, $this->_params['basedn'], $search);
        $entry = ldap_first_entry($this->_ds, $result);
        if ($entry === false) {
            return PEAR::raiseError(_("User not found."));
        }

        // If we used admin bindings, we have to check the password here.
        if (!is_null($this->_params['admindn'])) {
            $ldappasswd = ldap_get_values($this->_ds, $entry,
                                          $this->_params['attribute']);
            $result = $this->comparePasswords($ldappasswd[0], $passw);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        return ldap_get_dn($this->_ds, $entry);
    }

    /**
     * Checks for shadowLastChange and shadowMin support and returns their
     * values.
     *
     * @param string $user   The username of the user.
     * @param string $passw  The password of the user.
     *
     * @return array  Hash with keys being "shadowlastchange" and "shadowmin"
     *                and containing their respective values or false for no
     *                support.
     */
    function _lookupShadow($user, $passw)
    {
        // Init the return array
        $lookupshadow = array('shadowlastchange' => false,
                              'shadowmin' => false);

        // Construct username@realm to connect as if 'realm' parameter is set.
        $urealm = $user;
        if (!empty($this->_params['realm'])) {
            $urealm .= '@' . $this->_params['realm'];
        }

        // Bind as current user. _connect will try as guest if no user realm
        // is found or auth error.
        $this->_connect($urealm, $passw);

        // Construct search.
        $search = $this->_params['uid'] . '=' . $user;

        // Get userdn.
        $result = ldap_search($this->_ds, $this->_params['basedn'], $search);
        $entry = ldap_first_entry($this->_ds, $result);
        if ($entry !== false) {
            $information = ldap_get_values($this->_ds, $entry,
                                           $this->_params['shadowlastchange']);
            if ($information) {
                $lookupshadow['shadowlastchange'] = $information[0];
            }

            $information = ldap_get_values($this->_ds, $entry,
                                           $this->_params['shadowmin']);
            if ($information) {
                $lookupshadow['shadowmin'] = $information[0];
            }
        }

        return $lookupshadow;
    }

    /**
     * Returns the user's DN.
     *
     * @access protected
     *
     * @param string $username      The user for which to change the password.
     * @param string $old_password  The old (current) user password.
     *
     * @return string  The user's DN or a PEAR_Error.
     */
    function _userDN($username, $old_password)
    {
        if ($GLOBALS['conf']['hooks']['userdn']) {
            $userdn = Horde::callHook('_passwd_hook_userdn',
                                      array(Auth::getAuth()));
        } else {
            $userdn = $this->_lookupdn($username, $old_password);
            if (is_a($userdn, 'PEAR_Error')) {
                return $userdn;
            }
        }

        // Construct username@realm to connect if 'realm' parameter is set.
        // Looks like the _passwd_hook_username hook, but here we use a
        // configurable parameter as realm.  This is a safeguard that if
        // unable to lookup the user's DN we still have a chance to
        // authenticate.
        if (empty($userdn) && !empty($this->_params['realm'])) {
            $userdn = $username . '@' . $this->_params['realm'];
        }

        return $userdn;
    }

    /**
     * Changes the user's password.
     *
     * @param string $username      The user for which to change the password.
     * @param string $old_password  The old (current) user password.
     * @param string $new_password  The new user password to set.
     *
     * @return boolean  True or PEAR_Error based on success of the change.
     */
    function changePassword($username, $old_password, $new_password)
    {
        // Get the user's dn.
        $userdn = $this->_userDN($username, $old_password);
        if (is_a($userdn, 'PEAR_Error')) {
            return $userdn;
        }

        if (!is_null($this->_params['shadowlastchange'])) {
            $lookupshadow = $this->_lookupShadow($username, $old_password);

            // Check if we may change the password
            if ($lookupshadow['shadowlastchange'] &&
                $lookupshadow['shadowmin'] &&
                ($lookupshadow['shadowlastchange'] + $lookupshadow['shadowmin'] > (time() / 86400))) {
                return PEAR::raiseError(_("Minimum password age has not yet expired"));
            }
        }

        // Connect as the user.
        $result = $this->_connect($userdn, $old_password,
                                  !empty($this->_params['sslhost']));
        if (is_a($result, 'PEAR_Error')) {
            if ($result->getMessage() == _("Could not bind to LDAP server")) {
                return PEAR::raiseError(_("Incorrect Password"));
            }
            return $result;
        }

        // Change the user's password and update lastchange
        $new_details[$this->_params['attribute']] = $this->encryptPassword($new_password);

        if (!is_null($this->_params['shadowlastchange']) &&
            $lookupshadow['shadowlastchange']) {
            $new_details[$this->_params['shadowlastchange']] = floor(time() / 86400);
        }

        if (!ldap_mod_replace($this->_ds, $userdn, $new_details)) {
            return PEAR::raiseError(ldap_error($this->_ds));
        }

        return true;
    }

}
