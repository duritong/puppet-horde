<?php
/**
 * Preferences storage implementation for PHP's session implementation.
 *
 * $Horde: framework/Prefs/Prefs/session.php,v 1.32.12.8 2007/01/02 13:54:35 jan Exp $
 *
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jon Parise <jon@horde.org>
 * @since   Horde 1.3.4
 * @package Horde_Prefs
 */
class Prefs_session extends Prefs {

    /**
     * Constructs a new session preferences object.
     *
     * @param string $user      The user who owns these preferences. (Unused)
     * @param string $password  The password associated with $user. (Unused)
     * @param string $scope     The current preferences scope.
     * @param array $params     A hash containing connection parameters.
     *                           (Unused)
     * @param boolean $caching  Should caching be used? (Unused)
     *
     */
    function Prefs_session($user, $password = '', $scope = '',
                           $params = null, $caching = true)
    {
        if (!Util::extensionExists('session')) {
            Horde::fatal(PEAR::raiseError(_("Prefs_session: Required session extension not found."), __FILE__, __LINE__));
        }

        $this->_scope = $scope;

        parent::Prefs();
    }

    /**
     * Retrieves the requested set of preferences from the current session.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function retrieve()
    {
        /* Load defaults to make sure we have all preferences. */
        parent::retrieve();

        $global_prefs = array();
        $local_prefs = array();

        /* Retrieve global and local preferences from the session variable. */
        if (isset($_SESSION['horde_prefs']['horde'])) {
            $global_prefs = $_SESSION['horde_prefs']['horde'];
        }
        if (isset($_SESSION['horde_prefs'][$this->_scope])) {
            $local_prefs = $_SESSION['horde_prefs'][$this->_scope];
        }

        /* Retrieve and store the local and global preferences. */
        $this->_prefs = array_merge($this->_prefs, $global_prefs, $local_prefs);

        /* Call hooks. */
        if (!isset($_SESSION['horde_prefs'])) {
            $this->_callHooks();
        }

        return true;
    }

    /**
     * Stores preferences in the current session.
     *
     * @param array $prefs  An array listing the preferences to be stored. If
     *                      not specified, store all the preferences listed in
     *                      the $prefs hash.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function store($prefs = array())
    {
        /* Create and register the preferences array, if necessary. */
        if (!isset($_SESSION['horde_prefs'])) {
            $_SESSION['horde_prefs'] = array();
        }

        /* Copy the current preferences into the session variable. */
        foreach ($this->_prefs as $name => $pref) {
            $scope = $this->getScope($name);
            $_SESSION['horde_prefs'][$scope][$name] = $pref;
        }

        return true;
    }

    /**
     * Perform cleanup operations.
     *
     * @param boolean $all  Cleanup all Horde preferences.
     */
    function cleanup($all = false)
    {
        /* Perform a Horde-wide cleanup? */
        if ($all) {
            unset($_SESSION['horde_prefs']);
        } else {
            unset($_SESSION['horde_prefs'][$this->_scope]);
            $_SESSION['horde_prefs']['_filled'] = false;
        }

        parent::cleanup($all);
    }

}
