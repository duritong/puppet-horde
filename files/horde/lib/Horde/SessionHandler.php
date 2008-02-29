<?php
/**
 * SessionHandler:: defines an API for implementing custom session
 * handlers for PHP.
 *
 * $Horde: framework/SessionHandler/SessionHandler.php,v 1.13.10.13 2007/12/05 18:57:35 slusarz Exp $
 *
 * Copyright 2002-2007 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   Horde 3.0
 * @package Horde_SessionHandler
 */
class SessionHandler {

    /**
     * Hash containing connection parameters.
     *
     * @var array
     */
    var $_params = array();

    /**
     * Constructs a new SessionHandler object.
     *
     * @param array $params  A hash containing connection parameters.
     */
    function SessionHandler($params = array())
    {
        $this->_params = $params;
    }

    /**
     * Attempts to return a concrete SessionHandler instance based on
     * $driver.
     *
     * @param string $driver  The type of concrete SessionHandler subclass to
     *                        return.
     * @param array $params   A hash containing any additional configuration or
     *                        connection parameters a subclass might need.
     *
     * @return mixed  The newly created concrete SessionHandler instance, or
     *                false on an error.
     */
    function &factory($driver, $params = null)
    {
        if (is_array($driver)) {
            $app = $driver[0];
            $driver = $driver[1];
        }

        $driver = basename($driver);
        if ($driver == 'memcached') {
            // Trap for old driver name.
            $driver = 'memcache';
        }

        $class = 'SessionHandler_' . $driver;
        if (!class_exists($class)) {
            if (!empty($app)) {
                include_once $GLOBALS['registry']->get('fileroot', $app) . '/lib/SessionHandler/' . $driver . '.php';
            } else {
                include_once 'Horde/SessionHandler/' . $driver . '.php';
            }
        }

        if (class_exists($class)) {
            if (is_null($params)) {
                include_once 'Horde.php';
                $params = Horde::getDriverConfig('sessionhandler', $driver);
            }
            $handler = new $class($params);
        } else {
            $handler = PEAR::raiseError('Class definition of ' . $class . ' not found.');
        }

        return $handler;
    }

    /**
     * Attempts to return a reference to a concrete SessionHandler
     * instance based on $driver. It will only create a new instance
     * if no SessionHandler instance with the same parameters
     * currently exists.
     *
     * This method must be invoked as: $var = &SessionHandler::singleton()
     *
     * @param string $driver  See SessionHandler::factory().
     * @param array $params   See SessionHandler::factory().
     *
     * @return mixed  The created concrete SessionHandler instance, or false
     *                on error.
     */
    function &singleton($driver, $params = null)
    {
        static $instances = array();

        $signature = serialize(array($driver, $params));
        if (empty($instances[$signature])) {
            $instances[$signature] = &SessionHandler::factory($driver, $params);
        }

        return $instances[$signature];
    }

    /**
     * Open the SessionHandler backend.
     *
     * @abstract
     *
     * @param string $save_path     The path to the session object.
     * @param string $session_name  The name of the session.
     *
     * @return boolean  True on success, false otherwise.
     */
    function open($save_path, $session_name)
    {
        return true;
    }

    /**
     * Close the SessionHandler backend.
     *
     * @abstract
     *
     * @return boolean  True on success, false otherwise.
     */
    function close()
    {
        return true;
    }

    /**
     * Read the data for a particular session identifier from the
     * SessionHandler backend.
     *
     * @abstract
     *
     * @param string $id  The session identifier.
     *
     * @return string  The session data.
     */
    function read($id)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Write session data to the SessionHandler backend.
     *
     * @abstract
     *
     * @param string $id            The session identifier.
     * @param string $session_data  The session data.
     *
     * @return boolean  True on success, false otherwise.
     */
    function write($id, $session_data)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Destroy the data for a particular session identifier in the
     * SessionHandler backend.
     *
     * @abstract
     *
     * @param string $id  The session identifier.
     *
     * @return boolean  True on success, false otherwise.
     */
    function destroy($id)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Garbage collect stale sessions from the SessionHandler backend.
     *
     * @abstract
     *
     * @param integer $maxlifetime  The maximum age of a session.
     *
     * @return boolean  True on success, false otherwise.
     */
    function gc($maxlifetime = 300)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Determines if a session belongs to an authenticated user.
     *
     * @access private
     *
     * @param string $session_data  The session data itself.
     * @param boolean $return_user  If true, return the user session data.
     *
     * @return boolean|string  True or the user's session data if the session
     *                         belongs to an authenticated user.
     */
    function _isAuthenticated($session_data, $return_data = false)
    {
        if (empty($session_data)) {
            return false;
        }

        while ($session_data) {
            $vars = explode('|', $session_data, 2);
            $data = unserialize($vars[1]);
            if ($vars[0] == '__auth') {
                if (empty($data)) {
                    return false;
                }
                if (!empty($data['authenticated'])) {
                    return $return_data ? $data : true;
                }
                return false;
            }
            $session_data = substr($session_data, strlen($vars[0]) + strlen(serialize($data)) + 1);
        }
    }

    /**
     * Get a list of the valid session identifiers.
     *
     * @abstract
     *
     * @return array  A list of valid session identifiers.
     */
    function getSessionIDs()
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Determine the number of currently logged in users.
     *
     * @return integer  A count of logged in users.
     */
    function countAuthenticatedUsers()
    {
        $count = 0;

        $sessions = $this->getSessionIDs();
        if (is_a($sessions, 'PEAR_Error')) {
            return $sessions;
        }

        foreach ($sessions as $id) {
            $data = $this->read($id);
            if (!is_a($data, 'PEAR_Error') &&
                $this->_isAuthenticated($data)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Returns a list of currently logged in users.
     *
     * @return array  A list of logged in users.
     */
    function listAuthenticatedUsers($date = false)
    {
        $users = array();

        $sessions = $this->getSessionIDs();
        if (is_a($sessions, 'PEAR_Error')) {
            return $sessions;
        }

        foreach ($sessions as $id) {
            $data = $this->read($id);
            if (is_a($data, 'PEAR_Error')) {
                continue;
            }

            $data = $this->_isAuthenticated($data, true);
            if ($data !== false && isset($data['userId'])) {
                $user = $data['userId'];
                if ($date && isset($data['timestamp'])) {
                    $user = date('r', $data['timestamp']) . '  ' . $user;
                }
                $users[] = $user;
            }
        }

        return $users;
    }

}
