<?php
/**
 * Vacation_Driver_ldap:: implements the Vacation_Driver API for
 * LDAP-compliant mail servers (such as Exim).
 *
 * Parameters:
 * (required)
 *   host       - hostname of the LDAP server
 *   port       - port number of the LDAP service
 *   basedn     - base DN of the user directory
 *   uid        - attribute to use for uid
 *   vacation   - attribute to use for storing the vacation message
 *   active     - attribute which determines if the vacation message is active
 * (optional)
 *   userdn     - another way of specifying the user DN (instead of
 *                constructing it from uid+basedn).
 *   version    - Protocol version for the LDAP server (PHP defaults
 *                to version 2. OpenLDAP >= 2.1.4 uses version 3, and
 *                so must be set explicitly).
 *
 * $Horde: vacation/lib/Driver/ldap.php,v 1.17.2.10 2007/01/02 13:55:22 jan Exp $
 *
 * Copyright 2001-2007 Eric Rostetter <eric.rostetter@physics.utexas.edu>
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Eric Rostetter <eric.rostetter@physics.utexas.edu>
 * @package Vacation
 */
class Vacation_Driver_ldap extends Vacation_Driver {

    /**
     * Pointer to the ldap connection.
     */
    var $_ds;

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
        if (is_a($current_details, 'PEAR_Error')) {
            return false;
        }

        // Check vacation flag.
        if ($current_details['vacation'] == 'y' ||
            $current_details['vacation'] == 'Y' ||
            $current_details['vacation'] == $this->_params[$realm]['enabled']) {
            return 'Y';
        } elseif ($current_details['vacation'] == 'n' ||
                  $current_details['vacation'] == 'N' ||
                  $current_details['vacation'] == $this->_params[$realm]['disabled']) {
            return 'N';
        } else {
            return false;
        }
    }

    /**
     * Connects to the LDAP server and binds as the guest user or as the
     * optional userdn.
     *
     * @param string $userdn    The DN to use when binding non-anonymously.
     * @param string $password  The password for $userdn.
     * @param string $realm     The name of the realm.
     *
     * @return boolean  True on success, false otherwise.
     *
     */
    function _connect($userdn = null, $password = null, $realm = 'default')
    {
        $this->_ds = ldap_connect($this->_params[$realm]['host'], $this->_params[$realm]['port']);
        if (!$this->_ds) {
            return PEAR::raiseError(_("Could not connect to ldap server"));
        }
        if (isset($this->_params[$realm]['version'])) {
            ldap_set_option($this->_ds, LDAP_OPT_PROTOCOL_VERSION,
                            $this->_params[$realm]['version']);
        }

        if (!empty($this->_params[$realm]['binddn'])) {
            $result = @ldap_bind($this->_ds, $this->_params[$realm]['binddn'], $this->_params[$realm]['bindpw']);
        } elseif (!is_null($userdn)) {
            $result = @ldap_bind($this->_ds, $userdn, $password);
        } else {
            $result = @ldap_bind($this->_ds);
        }

        if (!$result) {
            return PEAR::raiseError(_("Could not bind to ldap server"));
        }

        return true;
    }

    /**
     * Close the ldap connection.
     */
    function _disconnect()
    {
        @ldap_close($this->_ds);
    }

    /**
     * Check if the realm has a specific configuration.  If not, try to fall
     * back on the default configuration.  If still not a valid configuration
     * then exit with an error.
     *
     * @param string    $realm      The realm of the user, or "default" if none.
     *                              Note: passed by reference so we can change
     *                              it's value!
     *
     */
    function checkConfig(&$realm)
    {
        // If no realm passed in, or no host config for the realm passed in,
        // then we fall back to the default realm
        if (empty($realm) || empty($this->_params[$realm]['server'])) {
            $realm = 'default';
        }

        // If still no host/port, then we have a misconfigured module.
        if (empty($this->_params[$realm]['host']) ||
            empty($this->_params[$realm]['port']) ) {
            $this->err_str = _("The module is not properly configured!");
            return false;
        }
        return true;
    }

    /**
     * Lookup and return the user's dn.
     *
     * @param string $user    The username of the user.
     * @param string $realm   The realm (domain) name of the user.
     *
     * @return string    The ldap dn for the user.
     */
    function _lookupdn($user, $realm)
    {
        // Bind as guest.
        $this->_connect();

        // Construct search.
        $search = $this->_params[$realm]['uid'] . '=' . $user;
        if (!empty($this->_params[$realm]['realm'])) {
            $search .= '@' . $this->_params[$realm]['realm'];
        }

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('LDAP query by Vacation_Driver_ldap::_lookupdn(): root = "%s"; filter = "%s"; timelimit = %d',
                                  $this->_params[$realm]['basedn'], $search, $this->_params[$realm]['timeout']),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        // Get userdn.
        $result = @ldap_search($this->_ds, $this->_params[$realm]['basedn'], $search, array(), 0, 0, $this->_params[$realm]['timeout']);
        if (!$result ||
            !($entry = ldap_first_entry($this->_ds, $result))) {
            $this->_disconnect();
            return PEAR::raiseError(_("User not found."));
        }
        $userdn = ldap_get_dn($this->_ds, $entry);

        // Disconnect.
        $this->_disconnect();

        return $userdn;
    }

    /**
     * Set the vacation notice up.
     *
     * @param string $user     The username to enable vacation for.
     * @param string $realm    The realm of the user.
     * @param string $pass     The password of the user.
     * @param string $message  The message to install.
     *
     * @return boolean  Returns true on success, false on error.
     */
    function setVacation($user, $realm, $pass, $message)
    {
        // Make sure the configuration file is correct.
        if (!$this->checkConfig($realm)) {
            return false;
        }

        // Get the user's DN.
        if (isset($this->_params[$realm]['userdn'])) {
            $userdn = $this->_params[$realm]['userdn'];
        } else {
            $userdn = $this->_lookupdn($user, $realm);
            if (is_a($userdn, 'PEAR_Error')) {
                $this->err_str = $userdn->getMessage();
                return false;
            }
        }

        // Connect as the user.
        $res = $this->_connect($userdn, $pass, $realm);
        if (is_a($res, 'PEAR_Error')) {
            $this->err_str = $res->getMessage();
            $this->err_str .= ' - ' .  _("Check your password");
            return false;
        }

        // Prepare the message. \n->\n\n and UTF-8 encode.
        $message = str_replace("\r\n", "\\n", $message);
        $message = String::convertCharset($message, NLS::getCharset(), 'UTF-8');

        // Change the user's vacation.
        $newDetails[$this->_params[$realm]['vacation']] = $message;
        $newDetails[$this->_params[$realm]['active']] = explode("|", $this->_params[$realm]['enabled']);
        $res = ldap_mod_replace($this->_ds, $userdn, $newDetails);
        if (!$res) {
            $res = PEAR::raiseError(ldap_error($this->_ds));
            $this->_disconnect();
            return false;
        }

        $res = $this->_setVacationAlias($user, $realm, $userdn);

        // Disconnect.
        $this->_disconnect();

        return $res;
    }

    /**
     * Set/create vacation mail alias.
     *
     * Some mta/ldap/vacation implementations requires an extra mail alias
     * (ex. user@example.org -> user@example.org, user@autoreply.exmaple.org)
     *
     * You should override this method in your extended ldap driver class,
     * if you need this feature.
     *
     * @access private
     *
     * @param string $user   The username to enable vacation for.
     * @param string $realm  The realm for the current user.
     * @param string $userdn The LDAP DN for the current user.
     *
     * @return boolean  Success or failure.
     */
    function _setVacationAlias($user, $realm, $userdn)
    {
        return true;
    }

    /**
     * Unset/remove vacation mail alias.
     *
     * @access private
     *
     * @param string $user   The username to enable vacation for.
     * @param string $realm  The realm for the current user.
     * @param string $userdn The LDAP DN for the current user.
     *
     * @return boolean  Success or failure.
     *
     * @see _setVacationAlias()
     */
    function _unsetVacationAlias($user, $realm, $userdn)
    {
        return true;
    }

    function _getUserDetails($user, $realm = 'default', $pass)
    {
        // Make sure the configuration file is correct.
        if (!$this->checkConfig($realm)) {
            return PEAR::raiseError('config check failed');
        }

        // Get the user's DN.
        if (isset($this->_params[$realm]['userdn'])) {
            $userdn = $this->_params[$realm]['userdn'];
        } else {
            $userdn = $this->_lookupdn($user, $realm);
            if (is_a($userdn, 'PEAR_Error')) {
                return $userdn;
            }
        }

        // Connect as the user.
        $result = $this->_connect($userdn, $pass, $realm);
        if (is_a($result, 'PEAR_Error')) {
            $this->_disconnect();
            if ($result->getMessage() == _("Could not bind to ldap server")) {
                return PEAR::raiseError(_("Incorrect Password"));
            }
            return $result;
        }

        $vac = $this->_getVacation($userdn, $realm, $user);

        // Prepare the message. \n->\n\n and UTF-8 decode.
        $vac['message'] = str_replace("\\\\n", "\r\n", $vac['message']);
        $vac['message'] = String::convertCharset($vac['message'], 'UTF-8');

        return $vac;
    }

    function _getVacation($userdn, $realm, $user)
    {
        $filter = $this->_params[$realm]['uid'] . '=' . $user;
        $searchAttrs = array($this->_params[$realm]['vacation'], $this->_params[$realm]['active']);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('LDAP query by Vacation_Driver_ldap::_getVacation(): root = "%s"; filter = "%s"; attributes = "%s"; timelimit = %d',
                                  $userdn, $filter, implode(', ', $searchAttrs), $this->_params[$realm]['timeout']),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        // Get the vacation message and the vacation status.
        $sr = ldap_search($this->_ds, $userdn, $filter, $searchAttrs, 0, 0, $this->_params[$realm]['timeout']);

        $entry = ldap_first_entry($this->_ds, $sr);
        if (!$entry) {
            return false;
        }

        $retAttrs = ldap_get_attributes($this->_ds, $entry);
        if (!$retAttrs) {
            return false;
        }

        // Set default values.
        $vacationInfo['message']  = '';
        $vacationInfo['vacation'] = $this->_params[$realm]['disabled'];

        // Are there any returned attributes / values?
        $messageAttr = String::lower($this->_params[$realm]['vacation']);
        if (isset($retAttrs[$messageAttr])) {
            $vacationInfo['message'] = $retAttrs[$messageAttr][0];
        }

        $vacationAttr = String::lower($this->_params[$realm]['active']);
        if (isset($retAttrs[$vacationAttr])) {
            unset($retAttrs[$vacationAttr]['count']);
            $vacationInfo['vacation'] = implode("|", $retAttrs[$vacationAttr]);
        }

        return $vacationInfo;
    }

    /**
     * Deactivate the vacation notice.
     * NB: This does not delete the vacation message, just marks it as
     * disabled.
     *
     * @param string $user   The user to disable vacation notices for.
     * @param string $realm  The realm of the user.
     * @param string $pass   The password of the user.
     *
     * @return boolean  Returns true on success, false on error.
     */
    function unsetVacation($user, $realm, $pass)
    {
        // Make sure the configuration file is correct.
        if (!$this->checkConfig($realm)) {
            return false;
        }

        // Get the user's dn.
        if (isset($this->_params[$realm]['userdn'])) {
            $userdn = $this->_params[$realm]['userdn'];
        } else {
            $userdn = $this->_lookupdn($user, $realm);
            if (is_a($userdn, 'PEAR_Error')) {
                $this->err_str = $userdn->getMessage();
                return false;
            }
        }

        // Connect as the user.
        $result = $this->_connect($userdn, $pass, $realm);
        if (is_a($result, 'PEAR_Error')) {
            $this->_disconnect();
            if ($result->getMessage() == _("Could not bind to ldap server")) {
                $this->err_str = _("Incorrect Password");
                return false;
            }
            $this->err_str = $result->getMessage();
            return false;
        }

        // Set the vacation message to inactive.
        $newDetails[$this->_params[$realm]['active']] = $this->_params[$realm]['disabled'];
        $result = ldap_mod_replace($this->_ds, $userdn, $newDetails);
        if (!$result) {
            $this->err_str = ldap_error($this->_ds);
            return false;
        }

        // Delete the unnecessary vacation alias (if present).
        $result = $this->_unsetVacationAlias($user, $realm, $userdn);

        // Disconnect.
        $this->_disconnect();

        return $result;
    }

}
