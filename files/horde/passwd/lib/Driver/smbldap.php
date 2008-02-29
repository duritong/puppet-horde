<?php

require_once dirname(__FILE__) . '/ldap.php';

/**
 * The LDAP class attempts to change a user's LDAP password and Samba password
 * stored in an LDAP directory service.
 *
 * $Horde: passwd/lib/Driver/smbldap.php,v 1.7.2.3 2007/01/02 13:55:14 jan Exp $
 *
 * Copyright 2004-2007 Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.php.
 *
 * @author  Shane Boulter <sboulter@ariasolutions.com>
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @author  Tjeerd van der Zee <admin@xar.nl>
 * @author  Mattias Webjörn Eriksson <mattias@webjorn.org>
 * @author  Eric Jon Rostetter <eric.rostetter@physics.utexas.edu>
 * @since   Passwd 3.0
 * @package Passwd
 */
class Passwd_Driver_smbldap extends Passwd_Driver_ldap {

    /**
     * Constructs a new Passwd_Driver_smbldap object.
     *
     * @param array $params  A hash containing connection parameters.
     */
    function Passwd_Driver_smbldap($params = array())
    {
        $params = array_merge(array('lm_attribute' => 'sambaLMPassword',
                                    'nt_attribute' => 'sambaNTPassword',
                                    'pw_set_attribute' => 'sambaPwdLastSet',
                                    'pw_expire_attribute' => 'sambaPwdMustChange',
                                    'pw_expire_time' => 2147483647),
                              $params);
        parent::Passwd_Driver_ldap($params);
    }

    /**
     * Change the user's password.
     *
     * @param string $username      The user for which to change the password.
     * @param string $old_password  The old (current) user password.
     * @param string $new_password  The new user password to set.
     *
     * @return boolean  True or false based on success of the change.
     */
    function changePassword($username, $old_password, $new_password)
    {
        // Get the user's dn.
        $userdn = $this->_userDN($username, $old_password);
        if (is_a($userdn, 'PEAR_Error')) {
            return $userdn;
        }

        $result = parent::changePassword($username, $old_password, $new_password);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        require_once 'Crypt/CHAP.php';
        $hash = &new Crypt_CHAP_MSv2();
        $hash->password = $new_password;
        $lmpasswd = strtoupper(bin2hex($hash->lmPasswordHash()));
        $ntpasswd = strtoupper(bin2hex($hash->ntPasswordHash()));
        $settime = time();
        // 24 hours/day * 60 min/hour * 60 secs/min = 86400 seconds/day
        $expiretime = $settime + ($this->_params['pw_expire_time'] * 86400);

        $new_lm_passwd[$this->_params['lm_attribute']] = $lmpasswd;
        if (!ldap_mod_replace($this->_ds, $userdn, $new_lm_passwd)) {
            return PEAR::raiseError(ldap_error($this->_ds));
        }

        $new_nt_passwd[$this->_params['nt_attribute']] = $ntpasswd;
        if (!ldap_mod_replace($this->_ds, $userdn, $new_nt_passwd)) {
            return PEAR::raiseError(ldap_error($this->_ds));
        }

        $new_set_time[$this->_params['pw_set_attribute']] = $settime;
        if (!ldap_mod_replace($this->_ds, $userdn, $new_set_time)) {
            return PEAR::raiseError(ldap_error($this->_ds));
        }

        $new_set_time[$this->_params['pw_expire_attribute']] = $expiretime;
        if (!ldap_mod_replace($this->_ds, $userdn, $new_set_time)) {
            return PEAR::raiseError(ldap_error($this->_ds));
        }

        return true;
    }

}
