<?php

/**
 * Preference is administratively locked.
 */
define('_PREF_LOCKED', 1);

/**
 * Preference is shared amongst applications.
 */
define('_PREF_SHARED', 2);

/**
 * Preference value has been changed.
 */
define('_PREF_DIRTY', 4);

/**
 * Preference value is the application default.
 */
define('_PREF_DEFAULT', 8);

/**
 * The Prefs:: class provides a common abstracted interface into the
 * various preferences storage mediums.  It also includes all of the
 * functions for retrieving, storing, and checking preference values.
 *
 * TODO: document the format of the $_prefs hash here
 *
 * $_prefs[*pref name*] = array(
 *     'value'  => *Default value*,
 *     'locked' => *boolean*,
 *     'shared' => *boolean*,
 *     'type'   => 'checkbox'
 *                 'text'
 *                 'password'
 *                 'textarea'
 *                 'select'
 *                 'number'
 *                 'implicit'
 *                 'special'
 *                 'link' - There must be a field named either 'url'
 *                          (internal application link) or 'xurl'
 *                          (external application link) if this type is used.
 *                 'enum'
 *     'enum'   => TODO,
 *     'desc'   => _(*Description string*),
 *     'help'   => *Name of the entry in the XML help file*
 * );
 *
 * $Horde: framework/Prefs/Prefs.php,v 1.137.10.30 2007/06/20 11:24:21 jan Exp $
 *
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jon Parise <jon@horde.org>
 * @since   Horde 1.3
 * @package Horde_Prefs
 */
class Prefs {

    /**
     * Hash holding all of the user's preferences. Each preference is
     * itself a hash, so this will ultimately be multi-dimensional.
     *
     * [*pref name*] => Array(
     *     [d]  =>  *default value*
     *     [m]  =>  *pref mask*
     *     [v]  =>  *pref value*
     * )
     *
     * @access private
     *
     * @var array
     */
    var $_prefs = array();

    /**
     * String containing the name of this scope. This is used to
     * maintain the application scope between sets of preferences. By
     * default, all preferences belong to the "global" (Horde) scope.
     *
     * @var string
     */
    var $_scope = 'horde';

    /**
     * String containing the current username. This indicates the owner of the
     * preferences.
     *
     * @var string
     */
    var $_user = '';

    /**
     * Boolean indicating whether preference caching should be used.
     *
     * @var boolean
     */
    var $_caching = false;

    /**
     * Hash holding preferences with hook functions defined.
     *
     * @var array
     */
    var $_hooks = array();

    /**
     * Have we run hook functions yet?
     *
     * @var boolean
     */
    var $_hooksCalled = null;

    /**
     * Default constructor (must be called from each extending class in their
     * constructors via parent::Prefs()).
     */
    function Prefs()
    {
        $this->_shutdown();
    }

    /**
     * Returns the charset used by the concrete preference backend.
     *
     * @return string  The preference backend's charset.
     */
    function getCharset()
    {
        return NLS::getCharset();
    }

    /**
     * Updates the session-based preferences cache (if available).
     *
     * @param string $pref  The preference to update.  If empty, will update
     *                      the entire cache with the current set of prefs.
     */
    function cacheUpdate($pref = null)
    {
        /* Return immediately if caching is disabled. */
        if (!$this->_caching) {
            return;
        }

        if (isset($_SESSION['prefs_cache'])) {
            if (is_null($pref)) {
                $prefs = $this->_prefs;
            } else {
                $prefs = array($pref => $this->_prefs[$pref]);
            }

            /* Place each preference in the cache according to its
             * scope. */
            foreach ($prefs as $name => $pref) {
                $_SESSION['prefs_cache'][$this->getScope($name)][$name] = $pref;
            }
        }
    }

    /**
     * Tries to find the requested preferences in the cache. If they exist,
     * update the $prefs hash with the cached values.
     *
     * @return boolean  True on success, false on failure.
     */
    function cacheLookup()
    {
        /* Return immediately if caching is disabled. */
        if (!$this->_caching) {
            return false;
        }

        if (isset($_SESSION['prefs_cache']['horde']) &&
            isset($_SESSION['prefs_cache'][$this->_scope])) {

            /* Restore global preferences. */
            $this->_prefs = array_merge($this->_prefs, $_SESSION['prefs_cache']['horde'], $_SESSION['prefs_cache'][$this->_scope]);

            return true;
        }

        return false;
    }

    /**
     * Adds a new preference entry to the $prefs hash.
     *
     * @param string $pref   The name of the preference to add.
     * @param string $val    The initial value of the preference.
     * @param integer $mask  The initial bitmask of the preference.
     */
    function add($pref, $val = '', $mask = 0)
    {
        if (is_array($this->_prefs)) {
            $this->_prefs[$pref] = array('v' => $val, 'm' => $mask, 'd' => $val);
        }
    }

    /**
     * Removes a preference entry from the $prefs hash.
     *
     * @param string $pref  The name of the preference to remove.
     */
    function remove($pref)
    {
        if (is_array($this->_prefs)) {
            $scope = $this->getScope($pref);
            unset($this->_prefs[$pref]);
            unset($_SESSION['prefs_cache'][$scope][$pref]);
        }
    }

    /**
     * Sets the given preferences ($pref) to the specified value
     * ($val), if the preference is modifiable.
     *
     * @param string $pref      The name of the preference to modify.
     * @param string $val       The new value for this preference.
     * @param boolean $convert  If true the preference value gets converted
     *                          from the current charset to the backend's
     *                          charset.
     *
     * @return boolean  True if the value was successfully set, false on a
     *                  failure.
     */
    function setValue($pref, $val, $convert = true)
    {
        /* Exit early if this preference is locked or doesn't exist. */
        if (!isset($this->_prefs[$pref]) || $this->isLocked($pref)) {
            return false;
        }

        return $this->_setValue($pref, $val, true, $convert);
    }

    function __set($name, $value)
    {
        return $this->setValue($name, $value);
    }

    /**
     * Sets the given preferences ($pref) to the specified value
     * ($val), whether or not the preference is user-modifiable, unset
     * the default bit, and set the dirty bit.
     *
     * @access protected
     *
     * @param string  $pref     The name of the preference to modify.
     * @param string  $val      The new value for this preference.
     * @param boolean $dirty    True if we should mark the new value as
     *                          dirty (changed).
     * @param boolean $convert  If true the preference value gets converted
     *                          from the current charset to the backend's
     *                          charset.
     *
     * @return boolean  True if the value was successfully set, false on a
     *                  failure.
     */
    function _setValue($pref, $val, $dirty = true, $convert = true)
    {
        global $conf;

        if ($convert) {
            $val = $this->convertToDriver($val, NLS::getCharset());
        }

        /* If the preference's value is already equal to $val, don't
         * bother changing it. Changing it would set the "dirty" bit,
         * causing an unnecessary update later on in the storage
         * routine. */
        if (isset($this->_prefs[$pref]) &&
            (($this->_prefs[$pref]['v'] == $val) &&
             !$this->isDefault($pref))) {
            return true;
        }

        /* Check to see if the value exceeds the allowable storage
         * limit. */
        if (isset($conf['prefs']['maxsize'])) {
            if (strlen($val) > $conf['prefs']['maxsize']) {
                global $notification;
                if (isset($notification)) {
                    $notification->push(sprintf(_("The preference \"%s\" could not be saved because its data exceeded the maximum allowable size"), $pref), 'horde.error');
                    return false;
                }
            }
        }

        /* Assign the new value, unset the "default" bit, and set the
           "dirty" bit. */
        if (empty($this->_prefs[$pref]['m'])) {
            $this->_prefs[$pref]['m'] = 0;
        }
        $this->_prefs[$pref]['v'] = $val;
        $this->setDefault($pref, false);
        if ($dirty) {
            $this->setDirty($pref, true);
        }

        $this->cacheUpdate($pref);

        return true;
    }

    /**
     * Returns the value of the requested preference.
     *
     * @param string $pref      The name of the preference to retrieve.
     * @param boolean $convert  If true the preference value gets converted
     *                          from the backend's charset to the current
     *                          charset.
     *
     * @return string  The value of the preference, null if it doesn't exist.
     */
    function getValue($pref, $convert = true)
    {
        static $charset;
        if (!isset($charset)) {
            $charset = NLS::getCharset();
        }

        if (isset($this->_prefs[$pref]['v'])) {
            if ($convert) {
                if ($this->isDefault($pref)) {
                    /* Default values have the current UI charset. */
                    $value = String::convertCharset($this->_prefs[$pref]['v'], NLS::getCharset(), $charset);
                } else {
                    /* Stored values have the backend charset. */
                    $value = $this->convertFromDriver($this->_prefs[$pref]['v'], $charset);
                }
            } else {
                $value = $this->_prefs[$pref]['v'];
            }
        } else {
            $value = null;
        }

        return $value;
    }

    function __get($name)
    {
        return $this->getValue($name);
    }

    /**
     * Modifies the "locked" bit for the given preference.
     *
     * @param string $pref   The name of the preference to modify.
     * @param boolean $bool  The new boolean value for the "locked" bit.
     */
    function setLocked($pref, $bool)
    {
        $this->_setMask($pref, $bool, _PREF_LOCKED);
    }

    /**
     * Returns the state of the "locked" bit for the given preference.
     *
     * @param string $pref  The name of the preference to check.
     *
     * @return boolean  The boolean state of $pref's "locked" bit.
     */
    function isLocked($pref)
    {
        return $this->_getMask($pref, _PREF_LOCKED);
    }

    /**
     * Modifies the "shared" bit for the given preference.
     *
     * @param string $pref   The name of the preference to modify.
     * @param boolean $bool  The new boolean value for the "shared" bit.
     */
    function setShared($pref, $bool)
    {
        $this->_setMask($pref, $bool, _PREF_SHARED);
    }

    /**
     * Returns the state of the "shared" bit for the given preference.
     *
     * @param string $pref  The name of the preference to check.
     *
     * @return boolean  The boolean state of $pref's "shared" bit.
     */
    function isShared($pref)
    {
        return $this->_getMask($pref, _PREF_SHARED);
    }

    /**
     * Modifies the "dirty" bit for the given preference.
     *
     * @param string $pref      The name of the preference to modify.
     * @param boolean $bool     The new boolean value for the "dirty" bit.
     */
    function setDirty($pref, $bool)
    {
        $this->_setMask($pref, $bool, _PREF_DIRTY);
    }

    /**
     * Returns the state of the "dirty" bit for the given preference.
     *
     * @param string $pref  The name of the preference to check.
     *
     * @return boolean  The boolean state of $pref's "dirty" bit.
     */
    function isDirty($pref)
    {
        return $this->_getMask($pref, _PREF_DIRTY);
    }

    /**
     * Modifies the "default" bit for the given preference.
     *
     * @param string $pref   The name of the preference to modify.
     * @param boolean $bool  The new boolean value for the "default" bit.
     */
    function setDefault($pref, $bool)
    {
        $this->_setMask($pref, $bool, _PREF_DEFAULT);
    }

    /**
     * Returns the default value of the given preference.
     *
     * @param string $pref  The name of the preference to get the default for.
     *
     * @return string       The preference's default value.
     */
    function getDefault($pref)
    {
        return !empty($this->_prefs[$pref]['d']) ?
            $this->_prefs[$pref]['d'] :
            '';
    }

    /**
     * Determines if the current preference value is the default
     * value from prefs.php or a user defined value
     *
     * @param string $pref  The name of the preference to check.
     *
     * @return boolean  True if the preference is the application default
     *                  value.
     */
    function isDefault($pref)
    {
        return $this->_getMask($pref, _PREF_DEFAULT);
    }

    /**
     * Sets the value for a given mask.
     *
     * @access private
     *
     * @param string $pref   The name of the preference to modify.
     * @param boolean $bool  The new boolean value for the "default" bit.
     * @param integer $mask  The mask to add.
     */
    function _setMask($pref, $bool, $mask)
    {
        if (isset($this->_prefs[$pref])) {
            if ($bool != $this->_getMask($pref, $mask)) {
                if ($bool) {
                    $this->_prefs[$pref]['m'] |= $mask;
                } else {
                    $this->_prefs[$pref]['m'] &= ~$mask;
                }
            }
        }
    }

    /**
     * Gets the boolean state for a given mask.
     *
     * @access private
     *
     * @param string $pref   The name of the preference to modify.
     * @param integer $mask  The mask to get.
     *
     * @return boolean  The boolean state for the given mask.
     */
    function _getMask($pref, $mask)
    {
        return isset($this->_prefs[$pref]['m']) ? ($this->_prefs[$pref]['m'] & $mask) : false;
    }

    /**
     * Determines whether the current preference is empty.
     *
     * @param string $pref  The name of the preference to check.
     *
     * @return boolean  True if the preference is empty.
     */
    function isEmpty($pref)
    {
        return empty($this->_prefs[$pref]['v']);
    }

    /**
     * Returns the scope of the given preference.
     *
     * @param string $pref  The name of the preference to examine.
     *
     * @return string  The scope of the $pref.
     */
    function getScope($pref)
    {
        if ($this->isShared($pref)) {
            return 'horde';
        } else {
            return $this->_scope;
        }
    }

    /**
     * Return a list of "dirty" preferences.
     *
     * @access private
     *
     * @return array  The list of "dirty" preferences in $this->_prefs.
     */
    function _dirtyPrefs()
    {
        $dirty_prefs = array();

        foreach (array_keys($this->_prefs) as $pref) {
            if ($this->isDirty($pref)) {
                $dirty_prefs[] = $pref;
            }
        }

        return $dirty_prefs;
    }

    /**
     * Retrieves the default preferences.
     */
    function retrieve()
    {
        /* Load defaults to make sure we have all preferences. */
        $this->_setDefaults('horde');
        $this->_setDefaults($this->_scope);

        return true;
    }

    /**
     * This function will be run at the end of every request as a shutdown
     * function (registered by the Prefs:: constructor).  All prefs with the
     * dirty bit set will be saved to the storage backend at this time; thus,
     * there is no need to manually call $prefs->store() every time a
     * preference is changed.
     *
     * @abstract
     */
    function store()
    {
        return true;
    }

    /**
     * This function provides common cleanup functions for all of the driver
     * implementations.
     *
     * @param boolean $all  Clean up all Horde preferences.
     */
    function cleanup($all = false)
    {
        /* Remove this scope from the preferences cache, if it
           exists. */
        if (isset($_SESSION['prefs_cache'][$this->_scope])) {
            unset($_SESSION['prefs_cache'][$this->_scope]);
        }

        /* Perform a Horde-wide cleanup? */
        if ($all) {
            /* Destroy the contents of the preferences hash. */
            $this->_prefs = array();

            /* Destroy the contents of the preferences cache. */
            if (isset($_SESSION['prefs_cache'])) {
                unset($_SESSION['prefs_cache']);
            }
        }
    }

    /**
     * Clears all preferences from the backend.
     */
    function clear()
    {
        $this->cleanup(true);
    }

    /**
     * Converts a value from the driver's charset to the specified charset.
     *
     * @param mixed $value     A value to convert.
     * @param string $charset  The charset to convert to.
     *
     * @return mixed  The converted value.
     */
    function convertFromDriver($value, $charset)
    {
        return $value;
    }

    /**
     * Converts a value from the specified charset to the driver's charset.
     *
     * @param mixed $value  A value to convert.
     * @param string $charset  The charset to convert from.
     *
     * @return mixed  The converted value.
     */
    function convertToDriver($value, $charset)
    {
        return $value;
    }

    /**
     * Populates the $prefs hash with new entries and externally defined
     * default values.
     *
     * @param string $app  The application to load defaults for.
     */
    function _setDefaults($app)
    {
        global $registry;
        $filename = $registry->get('fileroot', $app) . '/config/prefs.php';

        /* Ensure that the defaults from this file are only read once.
           Also, make sure we can read this file. */
        if (!@is_readable($filename)) {
            return;
        }

        /* Read the configuration file. The $_prefs array, which will be
           in local scope, is assumed to hold the default values. */
        include $filename;
        foreach ($_prefs as $pref => $pvals) {
            if (isset($pvals['value']) &&
                isset($pvals['locked']) &&
                isset($pvals['shared']) &&
                ($pvals['type'] != 'link') &&
                ($pvals['type'] != 'special')) {
                $pref = str_replace('.', '_', $pref);
                $mask = 0;
                if ($pvals['locked']) {
                    $mask |= _PREF_LOCKED;
                }
                if ($pvals['shared'] || $app == 'horde') {
                    $mask |= _PREF_SHARED;
                }
                $mask &= ~_PREF_DIRTY;
                $mask |= _PREF_DEFAULT;

                $this->add($pref, $pvals['value'], $mask);
                if (!empty($pvals['hook'])) {
                    $this->_setHook($pref);
                }
            }
        }

        /* Update the preferences cache with the defaults. */
        $this->cacheUpdate();
    }

    /**
     * Performs shutdown activities.
     *
     * @access private
     */
    function _shutdown()
    {
        register_shutdown_function(array(&$this, 'store'));
    }

    /**
     * Add $pref to the list of preferences with hook functions.
     *
     * @param string $pref  The preference with a hook.
     */
    function _setHook($pref)
    {
        $this->_hooks[] = $pref;
    }

    /**
     * After preferences have been loaded, set any locked or empty
     * preferences that have hooks to the result of the hook.
     */
    function _callHooks()
    {
        if (!is_null($this->_hooksCalled)) {
            return;
        }

        $this->_hooksCalled = true;

        if (!count($this->_hooks)) {
            return;
        }

        global $registry;
        include_once $registry->get('fileroot', 'horde') . '/config/hooks.php';
        foreach ($this->_hooks as $pref) {
            if ($this->isLocked($pref) ||
                !$this->getValue($pref) ||
                $this->isDefault($pref)) {
                $func = '_prefs_hook_' . $pref;
                if (function_exists($func)) {
                    $this->_setValue($pref, $func($this->_user));
                }
            }
        }
    }

    /**
     * Attempts to return a concrete Prefs instance based on $driver.
     *
     * @param mixed $driver     The type of concrete Prefs subclass to return.
     *                          If $driver is an array, then we will look in
     *                          $driver[0]/lib/Prefs/ for the subclass
     *                          implementation named $driver[1].php.
     * @param string $scope     The scope for this set of preferences.
     * @param string $user      The name of the user who owns this set of
     *                          preferences.
     * @param string $password  The password associated with $user.
     * @param array $params     A hash containing any additional configuration
     *                          or connection parameters a subclass might need.
     * @param boolean $caching  Should caching be used?
     *
     * @return Prefs  The newly created concrete Prefs instance, or false on
     *                error.
     */
    function &factory($driver, $scope = 'horde', $user = '', $password = '',
                      $params = null, $caching = true)
    {
        if (is_array($driver)) {
            $app = $driver[0];
            $driver = $driver[1];
        }

        /* Attempt to register (cache) the $prefs hash in session storage. */
        if ($caching) {
            if (!isset($_SESSION['prefs_cache'])) {
                $_SESSION['prefs_cache'] = array();
            }
        }

        $driver = basename($driver);
        if (empty($driver) || $driver == 'none') {
            $driver = 'session';
        }

        if (is_null($params)) {
            $params = Horde::getDriverConfig('prefs', $driver);
        }

        /* If $params['user_hook'] is defined, use it to retrieve the value to
         * use for the username ($this->_user). Otherwise, just use the value
         * passed in the $user parameter. */
        if (!empty($params['user_hook']) &&
            function_exists($params['user_hook'])) {
            $user = call_user_func($params['user_hook'], $user);
        }

        if (!empty($app)) {
            require_once $GLOBALS['registry']->get('fileroot', $app) . '/lib/Prefs/' . $driver . '.php';
        } elseif (file_exists(dirname(__FILE__) . '/Prefs/' . $driver . '.php')) {
            require_once dirname(__FILE__) . '/Prefs/' . $driver . '.php';
        } else {
            include_once 'Horde/Prefs/' . $driver . '.php';
        }

        $class = 'Prefs_' . $driver;
        if (class_exists($class)) {
            $prefs = &new $class($user, $password, $scope, $params, $caching);
        } else {
            $prefs = PEAR::raiseError('Class definition of ' . $class . ' not found.');
        }

        return $prefs;
    }

    /**
     * Attempts to return a reference to a concrete Prefs instance based on
     * $driver. It will only create a new instance if no Prefs instance
     * with the same parameters currently exists.
     *
     * This should be used if multiple preference sources (and, thus,
     * multiple Prefs instances) are required.
     *
     * This method must be invoked as: $var = &Prefs::singleton()
     *
     * @param mixed $driver     The type of concrete Prefs subclass to return.
     *                          If $driver is an array, then we will look in
     *                          $driver[0]/lib/Prefs/ for the subclass
     *                          implementation named $driver[1].php.
     * @param string $scope     The scope for this set of preferences.
     * @param string $user      The name of the user who owns this set of
     *                          preferences.
     * @param string $password  The password associated with $user.
     * @param array $params     A hash containing any additional configuration
     *                          or connection parameters a subclass might need.
     * @param boolean $caching  Should caching be used?
     *
     * @return Prefs  The concrete Prefs reference, or false on an error.
     */
    function &singleton($driver, $scope = 'horde', $user = '', $password = '',
                        $params = null, $caching = true)
    {
        static $instances = array();

        if (is_null($params)) {
            $params = Horde::getDriverConfig('prefs', $driver);
        }

        $signature = serialize(array($driver, $user, $params, $caching));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &Prefs::factory($driver, $scope, $user, $password, $params, $caching);
        }

        return $instances[$signature];
    }

}
