<?php
/**
 * Ingo_Driver_ldap:: Implements the Sieve_Driver api to allow scripts to be
 * installed and set active via an LDAP server.
 *
 * $Horde: ingo/lib/Driver/ldap.php,v 1.8.2.3 2006/12/21 04:36:38 chuck Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jason M. Felice <jfelice@cronosys.com>
 * @since   Ingo 1.1
 * @package Ingo
 */
class Ingo_Driver_ldap extends Ingo_Driver {

    /**
     * Constructor.
     */
    function Ingo_Driver_ldap($params = array())
    {
        if (!Util::extensionExists('ldap')) {
            Horde::fatal(PEAR::raiseError(_("LDAP support is required but the LDAP module is not available or not loaded.")), __FILE__, __LINE__);
        }

        $default_params = array('hostspec' => 'localhost',
                                'port' => 389,
                                'script_attribute' => 'mailSieveRuleSource');
        $this->_params = array_merge($default_params, $params);
    }

    /**
     * Create a DN from a DN template.
     *
     * This is done by substituting the username for %u and the 'dc='
     * components for %d.
     *
     * @access private
     *
     * @param string $templ     The DN template (from the config).
     * @param string $username  The username to substitute.
     *
     * @return string  The resulting DN.
     */
    function _substUser($templ, $username)
    {
        $domain = '';
        if (strpos($username, '@') !== false) {
            list($username, $domain) = explode('@', $username);
        }
        $domain = implode(', dc=', explode('.', $domain));
        if (!empty($domain)) {
            $domain = 'dc=' . $domain;
        }

        if (preg_match('/^\s|\s$|\s\s|[,+="\r\n<>#;]/', $username)) {
            $username = '"' . str_replace('"', '\\"', $username) . '"';
        }

        return str_replace(array('%u', '%d'),
                           array($username, $domain),
                           $templ);
    }

    /**
     * Connect and bind to ldap server.
     *
     * @param string $username  The user to bind as.
     * @param string $password  The bind password.
     */
    function _connect($username, $password)
    {
        if (!($ldapcn = @ldap_connect($this->_params['hostspec'],
                                      $this->_params['port']))) {
            return PEAR::raiseError(_("Connection failure"));
        }

        /* Set the LDAP protocol version. */
        if (!empty($this->_params['version'])) {
            @ldap_set_option($ldapcn,
                             LDAP_OPT_PROTOCOL_VERSION,
                             $this->_params['version']);
        }

        /* Start TLS if we're using it. */
        if (!empty($this->_params['tls'])) {
            if (!@ldap_start_tls($ldapcn)) {
                return PEAR::raiseError(sprintf(_("STARTTLS failed: (%s) %s"),
                                                ldap_errno($ldapcn),
                                                ldap_error($ldapcn)));
            }
        }

        /* Bind to the server. */
        if (isset($this->_params['bind_dn'])) {
            $bind_dn = $this->_substUser($this->_params['bind_dn'], $username);
            if (is_a($bind_dn, 'PEAR_Error')) {
                return $bind_dn;
            }

            if (isset($this->_params['bind_password'])) {
                $password = $this->_params['bind_password'];
            }

            if (!@ldap_bind($ldapcn, $bind_dn, $password)) {
                return PEAR::raiseError(sprintf(_("Bind failed: (%s) %s"),
                                                ldap_errno($ldapcn),
                                                ldap_error($ldapcn)));
            }
        } elseif (!(@ldap_bind($ldapcn))) {
            return PEAR::raiseError(sprintf(_("Bind failed: (%s) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }

        return $ldapcn;
    }

    /**
     * Retrieve current user's scripts.
     *
     * @access private
     *
     * @param resource $ldapcn  The connection to the LDAP server.
     * @param string $username  The user's login.
     * @param string $userDN    Set to the user object's real DN.
     *
     * @return mixed  Array of script sources, or PEAR_Error on failure.
     */
    function _getScripts($ldapcn, $username, &$userDN)
    {
        $attrs = array($this->_params['script_attribute'], 'dn');
        $filter = $this->_substUser($this->_params['script_filter'], $username);

        /* Find the user object. */
        $sr = @ldap_search($ldapcn, $this->_params['script_base'], $filter,
                           $attrs);
        if ($sr === false) {
            return PEAR::raiseError(sprintf(_("Error retrieving current script: (%d) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }
        if (@ldap_count_entries($ldapcn, $sr) != 1) {
            return PEAR::raiseError(sprintf(_("Expected 1 object, got %d."),
                                            ldap_count_entries($ldapcn, $sr)));
        }
        $ent = @ldap_first_entry($ldapcn, $sr);
        if ($ent === false) {
            return PEAR::raiseError(sprintf(_("Error retrieving current script: (%d) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }

        /* Retrieve the user's DN. */
        $v = @ldap_get_dn($ldapcn, $ent);
        if ($v === false) {
            @ldap_free_result($sr);
            return PEAR::raiseError(sprintf(_("Error retrieving current script: (%d) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }
        $userDN = $v;

        /* Retrieve the user's scripts. */
        $attrs = @ldap_get_attributes($ldapcn, $ent);
        @ldap_free_result($sr);
        if ($attrs === false) {
            return PEAR::raiseError(sprintf(_("Error retrieving current script: (%d) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }

        /* Attribute can be in any case, and can have a ";binary"
         * specifier. */
        $regexp = '/^' . preg_quote($this->_params['script_attribute'], '/') .
                  '(?:;.*)?$/i';
        unset($attrs['count']);
        foreach ($attrs as $name => $values) {
            if (preg_match($regexp, $name)) {
                unset($values['count']);
                return array_values($values);
            }
        }

        return array();
    }

    /**
     * Sets a script running on the backend.
     *
     * @param string $script    The sieve script.
     * @param string $username  The backend username.
     * @param string $password  The backend password.
     *
     * @return mixed  True on success, PEAR_Error on error.
     */
    function setScriptActive($script, $username, $password)
    {
        $ldapcn = $this->_connect($username, $password);
        if (is_a($ldapcn, 'PEAR_Error')) {
            return $ldapcn;
        }

        $values = $this->_getScripts($ldapcn, $username, $userDN);
        if (is_a($values, 'PEAR_Error')) {
            return $values;
        }

        $found = false;
        foreach ($values as $i => $value) {
            if (strpos($value, "# Sieve Filter\n") !== false) {
                if (empty($script)) {
                    unset($values[$i]);
                } else {
                    $values[$i] = $script;
                }
                $found = true;
                break;
            }
        }
        if (!$found && !empty($script)) {
            $values[] = $script;
        }

        $replace = array(String::lower($this->_params['script_attribute']) => $values);
        if (empty($values)) {
            $r = @ldap_mod_del($ldapcn, $userDN, $replace);
        } else {
            $r = @ldap_mod_replace($ldapcn, $userDN, $replace);
        }
        if (!$r) {
            return PEAR::raiseError(sprintf(_("Activating the script for \"%s\" failed: (%d) %s"),
                                            $userDN,
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }

        @ldap_close($ldapcn);
        return true;
    }

    /**
     * Returns the content of the currently active script.
     *
     * @param string $username  The backend username.
     * @param string $password  The backend password.
     *
     * @return string  The complete ruleset of the specified user.
     */
    function getScript($username, $password)
    {
        $ldapcn = $this->_connect($username, $password);
        if (is_a($ldapcn, 'PEAR_Error')) {
            return $ldapcn;
        }

        $values = $this->_getScripts($ldapcn, $username, $userDN);
        if (is_a($values, 'PEAR_Error')) {
            return $values;
        }

        $script = '';
        foreach ($values as $value) {
            if (strpos($value, "# Sieve Filter\n") !== false) {
                $script = $value;
                break;
            }
        }

        ldap_close($ldapcn);
        return $script;
    }

}
