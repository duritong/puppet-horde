<?php

require_once('Net/Sieve.php');

/**
 * Ingo_Driver_timsieved:: Implements the Sieve_Driver api to allow scripts to
 * be installed and set active via a Cyrus timsieved server.
 *
 * $Horde: ingo/lib/Driver/timsieved.php,v 1.15.10.7 2006/10/09 15:52:52 jan Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   Ingo 0.1
 * @package Ingo
 */
class Ingo_Driver_timsieved extends Ingo_Driver {

    /**
     * Constructor.
     */
    function Ingo_Driver_timsieved($params = array())
    {
        $default_params = array(
            'hostspec'   => 'localhost',
            'logintype'  => 'PLAIN',
            'port'       => 2000,
            'scriptname' => 'ingo',
            'admin'      => '',
            'usetls'     => true
        );
        $this->_params = array_merge($default_params, $params);
    }

    /**
     * Sets a script running on the backend.
     *
     * @param string $script    The sieve script.
     * @param string $username  The backend username.
     * @param string $password  The backend password.
     *
     * @return mixed  True on success.
     *                Returns PEAR_Error on error.
     */
    function setScriptActive($script, $username, $password)
    {
        if (empty($this->_params['admin'])) {
            $auth = $username;
            $user = '';
        } else {
            $auth = $this->_params['admin'];
            $user = $username;
        }
        $sieve = &new Net_Sieve($auth,
                                $password,
                                $this->_params['hostspec'],
                                $this->_params['port'],
                                $this->_params['logintype'],
                                $user,
                                false,
                                false,
                                $this->_params['usetls']);

        if (is_a($res = $sieve->getError(), 'PEAR_Error')) {
            return $res;
        }

        return $sieve->installScript($this->_params['scriptname'], $script, true);
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
        if (empty($this->_params['admin'])) {
            $auth = $username;
            $user = '';
        } else {
            $auth = $this->_params['admin'];
            $user = $username;
        }
        $sieve = &new Net_Sieve($auth,
                                $password,
                                $this->_params['hostspec'],
                                $this->_params['port'],
                                $this->_params['logintype'],
                                $user);

        if (is_a($res = $sieve->getError(), 'PEAR_Error')) {
            return $res;
        }

        return $sieve->getScript($sieve->getActive());
    }
}
