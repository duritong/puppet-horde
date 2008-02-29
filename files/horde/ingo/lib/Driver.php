<?php
/**
 * Ingo_Driver:: defines an API to activate filter scripts on a server.
 *
 * $Horde: ingo/lib/Driver.php,v 1.10.10.7 2006/01/31 20:00:24 jan Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Driver {

    /**
     * Driver specific parameters
     *
     * @var array
     */
    var $_params;

    /**
     * Attempts to return a concrete Ingo_Driver instance based on $driver.
     *
     * @param string $driver  The type of concrete Ingo_Driver subclass to
     *                        return.
     * @param array $params   A hash containing any additional configuration or
     *                        connection parameters a subclass might need.
     *
     * @return mixed  The newly created concrete Ingo_Driver instance, or
     *                false on error.
     */
    function &factory($driver, $params = array())
    {
        $driver = basename($driver);
        require_once dirname(__FILE__) . '/Driver/' . $driver . '.php';
        $class = 'Ingo_Driver_' . $driver;
        if (class_exists($class)) {
            $ingo = &new $class($params);
        } else {
            $ingo = false;
        }

        return $ingo;
    }

    /**
     * Attempts to return a reference to a concrete Ingo_Driver instance
     * based on $driver.
     *
     * It will only create a new instance if no Ingo_Driver instance with the
     * same parameters currently exists.
     *
     * This should be used if multiple storage sources are required.
     *
     * This method must be invoked as: $var = &Ingo_Driver::singleton();
     *
     * @param string $driver  The type of concrete Ingo_Driver subclass to
     *                        return.
     * @param array $params   A hash containing any additional configuration or
     *                        connection parameters a subclass might need.
     *
     * @return mixed  The created concrete Ingo_Driver instance, or false
     *                on error.
     */
    function &singleton($driver, $params = array())
    {
        static $instances;

        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($driver, $params));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &Ingo_Driver::factory($driver, $params);
        }

        return $instances[$signature];
    }

    /**
     * Constructor.
     */
    function Ingo_Driver($params = array())
    {
        $this->_params = $params;
    }

    /**
     * Sets a script running on the backend.
     *
     * @param string $script  The filter script.
     *
     * @return mixed  True on success, false if script can't be activated.
     *                Returns PEAR_Error on error.
     */
    function setScriptActive($script)
    {
        return false;
    }

}
