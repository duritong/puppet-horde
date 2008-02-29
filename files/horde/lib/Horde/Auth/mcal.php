<?php
/**
 * The Auth_mcal class provides an MCAL implementation of the Horde
 * authentication system.
 *
 * MCAL Home Page: http://mcal.chek.com/
 *
 * Required parameters:<pre>
 *   'calendar'  The MCAL calendar name.</pre>
 *
 *
 * $Horde: framework/Auth/Auth/mcal.php,v 1.27.10.10 2007/01/02 13:54:07 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 1.3
 * @package Horde_Auth
 */
class Auth_mcal extends Auth {

    /**
     * An array of capabilities, so that the driver can report which
     * operations it supports and which it doesn't.
     *
     * @var array
     */
    var $capabilities = array('add'           => false,
                              'update'        => false,
                              'resetpassword' => false,
                              'remove'        => false,
                              'list'          => true,
                              'transparent'   => false);

    /**
     * Constructs a new MCAL authentication object.
     *
     * @param array $params  A hash containing connection parameters.
     */
    function Auth_mcal($params = array())
    {
        if (!Util::extensionExists('mcal')) {
            Horde::fatal(_("Auth_mcal: Required MCAL extension not found."), __FILE__, __LINE__);
        }

        if (empty($params['calendar'])) {
            Horde::fatal(_("No calendar name provided for MCAL authentication."), __FILE__, __LINE__);
        }

        $this->_params = $params;
    }

    /**
     * Find out if a set of login credentials are valid.
     *
     * @access private
     *
     * @param string $userId      The userId to check.
     * @param array $credentials  An array of login credentials. For MCAL,
     *                            this must contain a password entry.
     *
     * @return boolean  Whether or not the credentials are valid.
     */
    function _authenticate($userId, $credentials)
    {
        $mcal = @mcal_open($this->_params['calendar'], $userId, $credentials['password']);

        if ($mcal) {
            @mcal_close($mcal);
            return true;
        } else {
            @mcal_close($mcal);
            $this->_setAuthError(AUTH_REASON_BADLOGIN);
            return false;
        }
    }

    /**
     * List all users in the system.
     *
     * @return mixed  The array of userIds, or a PEAR_Error object on failure.
     */
    function listUsers()
    {
        $lines = @file('/etc/mpasswd');
        if (!$lines || !is_array($lines)) {
            return PEAR::raiseError('Unable to list users.');
        }

        $users = array();
        foreach ($lines as $line) {
            $users[] = substr($line, 0, strpos($line, ':'));
        }

        return $users;
    }

}
