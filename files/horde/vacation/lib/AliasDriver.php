<?php
/**
 * Vacation_AliasDriver:: defines an API for implementing vacation backends
 * for the vacation module.
 *
 * $Horde: vacation/lib/AliasDriver.php,v 1.8.2.2 2007/01/02 13:55:21 jan Exp $
 *
 * Copyright 2004-2007 Cronosys, LLC <http://www.cronosys.com/>
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Jason M. Felice <jfelice@cronosys.com>
 * @package Vacation
 */
class Vacation_AliasDriver {

    /**
     * Hash containing configuration data.
     *
     * @var array
     */
    var $_params;

    /**
     * Constructor
     *
     * @param array $params  Configuration parameters for the backend.
     */
    function Vacation_AliasDriver($params = array())
    {
        $this->_params = $params;
    }

    /**
     * Retrieve the aliases.
     *
     * @return mixed A key/value array of aliases (the right-hand side is
     *               arrays), or PEAR_Error on failure.
     */
    function getAliases()
    {
        return PEAR::raiseError(_("Not implemented."));
    }

    /**
     * Figure out which aliases are for this user.
     *
     * Here we find all aliases which have only the specified user on the
     * right-hand-side (if there are more, it is assumed to be a list).
     *
     * @param string $name      The name of the user.
     * @return mixed An array of the aliases, or PEAR_Error on failure.
     */
    function getAliasesForUser($name)
    {
        $aliases = $this->getAliases();
        if (is_a($aliases, 'PEAR_Error')) {
            return $aliases;
        }

        $result = array();
        foreach ($aliases as $src => $targets) {
            if (count($targets) == 1 && $targets[0] == $name) {
                $result[] = $src;
            }
        }

        return $result;
    }

    /**
     * Create a concrete Vacation_AliasDriver:: instance
     *
     * @param string $driver  The type of concrete Vacation_AliasDriver
     *                        subclass to return.
     * @param array $params   A hash containing any additional configuration or
     *                        connection parameters a subclass might need.
     *
     * @return mixed  The newly created concrete Vacation_AliasDriver instance,
     *                or false on error.
     */
    function &factory($driver = null, $params = null)
    {
        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['aliases']['driver'];
        }

        $driver = basename($driver);

        if (is_null($params)) {
            $params = Horde::getDriverConfig('aliases', $driver);
        }

        require_once dirname(__FILE__) . '/AliasDriver/' . $driver . '.php';
        $class = 'Vacation_AliasDriver_' . $driver;
        if (class_exists($class)) {
            $alias = &new $class($params);
        } else {
            $alias = false;
        }

        return $alias;
    }

    /**
     * Get a concrete Vacation_AliasDriver instance.
     *
     * This method will only create a new instance if no Vacation_AliasDriver::
     * instance with the same parameters currently exists.
     *
     * This should be used if multiple storage sources are required.
     *
     * This method must be invoked as: $var = &Vacation_Driver::singleton()
     *
     * @param string $driver  The type of concrete Vacation_AliasDriver
     *                        subclass to return.
     * @param array $params   A hash containing any additional configuration or
     *                        connection parameters a subclass might need.
     *
     * @return mixed  The created concrete Vacation_AliasDriver instance, or
     *                false on error.
     */
    function &singleton($driver = null, $params = null)
    {
        static $instances;

        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['aliases']['driver'];
        }

        if (is_null($params)) {
            $params = Horde::getDriverConfig('aliases', $driver);
        }

        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($driver, $params));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &Vacation_AliasDriver::factory($driver, $params);
        }

        return $instances[$signature];
    }

}
