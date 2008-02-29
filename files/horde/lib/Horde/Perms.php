<?php

/** Existence of object is known - object is shown to user. */
define('PERMS_SHOW', 2);

/** Contents of the object can be read. */
define('PERMS_READ', 4);

/** Contents of the object can be edited. */
define('PERMS_EDIT', 8);

/** The object can be deleted. */
define('PERMS_DELETE', 16);

/**
 * A bitmask of all possible permission values. Useful for
 * removeXxxPermission(), unsetPerm(), etc.
 */
define('PERMS_ALL', PERMS_SHOW | PERMS_READ | PERMS_EDIT | PERMS_DELETE);

/**
 * The Perms:: class provides the Horde permissions system.
 *
 * $Horde: framework/Perms/Perms.php,v 1.80.10.10 2007/01/02 13:54:34 jan Exp $
 *
 * Copyright 2001-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 2.1
 * @package Horde_Perms
 */
class Perms {

    /**
     * Caches information about application permissions.
     *
     * @var array
     */
    var $_applicationPermissions;

    /**
     * Returns the available permissions for a given level.
     *
     * @param string $name  The permission's name.
     *
     * @return array  An array of available permissions and their titles or
     *                false if not sub permissions exist for this level.
     */
    function getAvailable($name)
    {
        global $registry;

        if (empty($name)) {
            /* No name passed, so top level permissions are requested. These
             * can only be applications. */
            $apps = $registry->listApps(array('notoolbar', 'active', 'hidden'), true);
            foreach (array_keys($apps) as $app) {
                $apps[$app] = $registry->get('name', $app) . ' (' . $app . ')';
            }
            asort($apps);
            return $apps;
        } else {
            /* Name has been passed, explode the name to get all the levels in
             * permission being requisted, with the app as the first level. */
            $levels = array();
            $levels = explode(':', $name);

            /* First level is always app. */
            $app = $levels[0];

            /* Return empty if no app defined API method for providing
             * permission information. */
            if (!$registry->hasMethod('perms', $app)) {
                return false;
            }

            /* Call the app's permission function to return the permissions
             * specific to this app. */
            $perms = $this->getApplicationPermissions($app);
            if (is_a($perms, 'PEAR_Error')) {
                return $perms;
            }

            require_once 'Horde/Array.php';
            /* Get the part of the app's permissions based on the permission
             * name requested. */
            $children = Horde_Array::getElement($perms['tree'], $levels);
            if ($children === false ||
                !is_array($children) ||
                !count($children)) {
                /* No array of children available for this permission name. */
                return false;
            }

            $perms_list = array();
            foreach ($children as $perm_key => $perm_val) {
                $perms_list[$perm_key] = $perms['title'][$name . ':' . $perm_key];
            }
            return $perms_list;
        }
    }

    /**
     * Returns the short name of an object, the last portion of the full name.
     *
     * @static
     *
     * @param string $name  The name of the object.
     *
     * @return string  The object's short name.
     */
    function getShortName($name)
    {
        /* If there are several components to the name, explode and
         * get the last one, otherwise just return the name. */
        if (strpos($name, ':') !== false) {
            $tmp = explode(':', $name);
            return array_pop($tmp);
        } else {
            return $name;
        }
    }

    /**
     * Given a permission name, returns the title for that permission by
     * looking it up in the applications's permission api.
     *
     * @param string $name  The permissions's name.
     *
     * @return string  The title for the permission.
     */
    function getTitle($name)
    {
        global $registry;

        $levels = explode(':', $name);
        if (count($levels) == 1) {
            return $registry->get('name', $name) . ' (' . $name . ')';
        }
        $perm = array_pop($levels);

        /* First level is always app. */
        $app = $levels[0];

        /* Return empty if no app defined API method for providing permission
         * information. */
        if (!$registry->hasMethod('perms', $app)) {
            return Perms::getShortName($name);
        }

        $app_perms = $this->getApplicationPermissions($app);

        return isset($app_perms['title'][$name])
            ? $app_perms['title'][$name] . ' (' . DataTree::getShortName($name) . ')'
            : DataTree::getShortName($name);
    }

    /**
     * Returns information about permissions implemented by an application.
     *
     * @since Horde 3.1
     *
     * @param string $app  An application name.
     *
     * @return array  Hash with permissions information.
     */
    function getApplicationPermissions($app)
    {
        if (!isset($this->_applicationPermissions[$app])) {
            $this->_applicationPermissions[$app] = $GLOBALS['registry']->callByPackage($app, 'perms');
        }

        return $this->_applicationPermissions[$app];
    }

    /**
     * Returns a new permissions object.
     *
     * @param string $name  The permission's name.
     *
     * @return Permissions  A new permissions object.
     */
    function &newPermission($name)
    {
        return PEAR::raiseError(_("The administrator needs to configure a permanent Permissions backend if you want to use Permissions."));
    }

    /**
     * Returns a Permission object corresponding to the named permission,
     * with the users and other data retrieved appropriately.
     *
     * @param string $name  The name of the permission to retrieve.
     */
    function &getPermission($name)
    {
        return PEAR::raiseError(_("The administrator needs to configure a permanent Permissions backend if you want to use Permissions."));
    }

    /**
     * Returns a Permission object corresponding to the given unique ID, with
     * the users and other data retrieved appropriately.
     *
     * @param integer $cid  The unique ID of the permission to retrieve.
     */
    function &getPermissionById($cid)
    {
        return PEAR::raiseError(_("The administrator needs to configure a permanent Permissions backend if you want to use Permissions."));
    }

    /**
     * Adds a permission to the permissions system. The permission must first
     * be created with Perm::newPermission(), and have any initial users
     * added to it, before this function is called.
     *
     * @param Permission $perm  The new perm object.
     */
    function addPermission(&$perm)
    {
        return PEAR::raiseError(_("The administrator needs to configure a permanent Permissions backend if you want to use Permissions."));
    }

    /**
     * Removes a permission from the permissions system permanently.
     *
     * @param Permission $perm  The permission to remove.
     * @param boolean $force    Force to remove every child.
     */
    function removePermission(&$perm, $force = false)
    {
        return PEAR::raiseError(_("The administrator needs to configure a permanent Permissions backend if you want to use Permissions."));
    }

    /**
     * Finds out what rights the given user has to this object.
     *
     * @param mixed $permission  The full permission name of the object to
     *                           check the permissions of, or the Permission
     *                           object.
     * @param string $user       The user to check for. Defaults to the current
     *                           user.
     * @param string $creator    The user who created the event.
     *
     * @return mixed  A bitmask of permissions the user has, false if there
     *                are none.
     */
    function getPermissions($permission, $user = null, $creator = null)
    {
        return PEAR::raiseError(_("The administrator needs to configure a permanent Permissions backend if you want to use Permissions."));
    }

    /**
     * Returns the unique identifier of this permission.
     *
     * @param Permission $permission  The permission object to get the ID of.
     *
     * @return integer  The unique id.
     */
    function getPermissionId($permission)
    {
        return PEAR::raiseError(_("The administrator needs to configure a permanent Permissions backend if you want to use Permissions."));
    }

    /**
     * Finds out if the user has the specified rights to the given object.
     *
     * @param string  $permission  The permission to check.
     * @param string  $user        The user to check for.
     * @param integer $perm        The permission level needed for access.
     * @param string  $creator     The creator of the event
     *
     * @return boolean  True if the user has the specified permissions.
     */
    function hasPermission($permission, $user, $perm, $creator = null)
    {
        return false;
    }

    /**
     * Checks if a permission exists in the system.
     *
     * @param string $permission  The permission to check.
     *
     * @return boolean  True if the permission exists.
     */
    function exists($permission)
    {
        return false;
    }

    /**
     * Returns a list of parent permissions.
     *
     * @param string $child  The name of the child to retrieve parents for.
     *
     * @return array  A hash with all parents in a tree format.
     */
    function getParents($child)
    {
        return PEAR::raiseError(_("The administrator needs to configure a permanent Permissions backend if you want to use Permissions."));
    }

    /**
     * Returns all permissions of the system in a tree format.
     *
     * @return array  A hash with all permissions in a tree format.
     */
    function getTree()
    {
        return array();
    }

    /**
     * Returns an hash of the available permissions.
     *
     * @return array  The available permissions as a hash.
     */
    function getPermsArray()
    {
        return array(PERMS_SHOW => _("Show"),
                     PERMS_READ => _("Read"),
                     PERMS_EDIT => _("Edit"),
                     PERMS_DELETE => _("Delete"));
    }

    /**
     * Given an integer value of permissions returns an array
     * representation of the integer.
     *
     * @param integer $int  The integer representation of permissions.
     */
    function integerToArray($int)
    {
        static $array = array();
        if (isset($array[$int])) {
            return $array[$int];
        }

        $array[$int] = array();

        /* Get the available perms array. */
        $perms = Perms::getPermsArray();

        /* Loop through each perm and check if its value is included in the
         * integer representation. */
        foreach ($perms as $val => $label) {
            if ($int & $val) {
                $array[$int][$val] = true;
            }
        }

        return $array[$int];
    }

    /**
     * Attempts to return a concrete Perms instance based on $driver.
     *
     * @param string $driver  The type of the concrete Perms subclass
     *                        to return.  The class name is based on the
     *                        perms driver ($driver).  The code is
     *                        dynamically included.
     *
     * @return Perms|boolean  The newly created concrete Perms instance, or
     *                        false on an error.
     */
    function &factory($driver = null)
    {
        if (is_null($driver)) {
            $perms = &new Perms();
        } else {
            include_once 'Horde/Perms/' . $driver . '.php';
            $class = 'Perms_' . $driver;
            if (class_exists($class)) {
                $perms = &new $class();
            } else {
                $perms = false;
            }
        }

        return $perms;
    }

    /**
     * Attempts to return a reference to a concrete Perms instance.
     * It will only create a new instance if no Perms instance
     * currently exists.
     *
     * This method must be invoked as: $var = &Perms::singleton()
     *
     * @return Perms|boolean  The concrete Perm reference, or false on error.
     */
    function &singleton()
    {
        static $perm;

        if (!isset($perm)) {
            $perm = &Perms::factory(!empty($GLOBALS['conf']['datatree']['driver']) ? 'datatree' : null);
        }

        return $perm;
    }

}
