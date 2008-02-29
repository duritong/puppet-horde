<?php
/**
 * @package Horde_Prefs
 */

/**
 * PEAR DB layer.
 */
require_once 'DB.php';

/**
 * Horde_String class.
 */
require_once 'Horde/String.php';

/**
 * Preferences storage implementation for PHP's PEAR database
 * abstraction layer.
 *
 * Required parameters:<pre>
 *   'phptype'   The database type (ie. 'pgsql', 'mysql', etc.).
 *   'charset'   The database's internal charset.</pre>
 *
 * Optional parameters:<pre>
 *   'table'     The name of the preferences table in 'database'.
 *               DEFAULT: 'horde_prefs'</pre>
 *
 * Required by some database implementations:<pre>
 *   'hostspec'  The hostname of the database server.
 *   'protocol'  The communication protocol ('tcp', 'unix', etc.).
 *   'database'  The name of the database.
 *   'username'  The username with which to connect to the database.
 *   'password'  The password associated with 'username'.
 *   'options'   Additional options to pass to the database.
 *   'port'      The port on which to connect to the database.
 *   'tty'       The TTY on which to connect to the database.</pre>
 *
 * The table structure for the Prefs system is in
 * scripts/sql/horde_prefs.sql.
 *
 * $Horde: framework/Prefs/Prefs/sql.php,v 1.91.10.21 2007/01/02 13:54:35 jan Exp $
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
class Prefs_sql extends Prefs {

    /**
     * Hash containing connection parameters.
     *
     * @var array
     */
    var $_params = array();

    /**
     * Handle for the current database connection.
     *
     * @var DB
     */
    var $_db;

    /**
     * Boolean indicating whether or not we're connected to the SQL server.
     *
     * @var boolean
     */
    var $_connected = false;

    /**
     * Constructs a new SQL preferences object.
     *
     * @param string $user      The user who owns these preferences.
     * @param string $password  The password associated with $user. (Unused)
     * @param string $scope     The current preferences scope.
     * @param array $params     A hash containing connection parameters.
     * @param boolean $caching  Should caching be used?
     */
    function Prefs_sql($user, $password = '', $scope = '',
                       $params = array(), $caching = false)
    {
        $this->_user = $user;
        $this->_scope = $scope;
        $this->_params = $params;
        $this->_caching = $caching;

        parent::Prefs();
    }

    /**
     * Returns the charset used by the concrete preference backend.
     *
     * @return string  The preference backend's charset.
     */
    function getCharset()
    {
        return $this->_params['charset'];
    }

    /**
     * Retrieves the requested set of preferences from the user's database
     * entry.
     *
     * @param array $prefs  An array listing the preferences to retrieve. If
     *                      not specified, retrieve all of the preferences
     *                      listed in the $prefs hash.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function retrieve($prefs = array())
    {
        /* Attempt to pull the values from the session cache first. */
        if ($this->cacheLookup()) {
            return true;
        }

        /* Load defaults to make sure we have all preferences. */
        parent::retrieve();

        /* Make sure we're connected. */
        $this->_connect();

        /* Build the SQL query. */
        $query = 'SELECT pref_scope, pref_name, pref_value FROM ';
        $query .= $this->_params['table'] . ' ';
        $query .= 'WHERE pref_uid = ?';
        $query .= ' AND (pref_scope = ?';
        $query .= " OR pref_scope = 'horde') ORDER BY pref_scope";

        $values = array($this->_user, $this->_scope);

        Horde::logMessage(sprintf('SQL Query by Prefs_sql::retrieve(): %s', $query), __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->query($query, $values);

        if (isset($result) && !is_a($result, 'PEAR_Error')) {
            $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
            if (is_a($row, 'PEAR_Error')) {
                Horde::logMessage($row, __FILE__, __LINE__, PEAR_LOG_ERR);
                return;
            }

            /* Set the requested values in the $this->_prefs hash
             * based on the contents of the SQL result.
             *
             * Note that Prefs::setValue() can't be used here because
             * of the check for the "changeable" bit. We want to
             * override that check when populating the $this->_prefs
             * hash from the SQL server. */
            while ($row && !is_a($row, 'PEAR_Error')) {
                $name = trim($row['pref_name']);
                if (isset($this->_prefs[$name])) {
                    $this->_setValue($name, $row['pref_value'], false, false);
                    $this->setDirty($name, false);
                } else {
                    $this->add($name, $row['pref_value'], $row['pref_scope'] == 'horde' ? _PREF_SHARED : 0);
                }
                $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
            }

            /* Call hooks. */
            $this->_callHooks();
        } else {
            Horde::logMessage('No preferences were retrieved.', __FILE__, __LINE__, PEAR_LOG_DEBUG);
            return;
        }

        /* Update the session cache. */
        $this->cacheUpdate();

        return true;
    }

    /**
     * Stores preferences to SQL server.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function store()
    {
        /* Check for any "dirty" preferences. If no "dirty"
         * preferences are found, there's no need to update the SQL
         * server. Exit successfully. */
        $dirty_prefs = $this->_dirtyPrefs();
        if (!count($dirty_prefs)) {
            return true;
        }

        /* Make sure we're connected. */
        $this->_connect();

        /* Loop through the "dirty" preferences.  If a row already
         * exists for this preference, attempt to update it.
         * Otherwise, insert a new row. */
        foreach ($dirty_prefs as $name) {
            // Don't store locked preferences.
            if ($this->isLocked($name)) {
                continue;
            }

            $scope = $this->getScope($name);

            /* Does an entry already exist for this preference? */
            $query = 'SELECT 1 FROM ';
            $query .= $this->_params['table'] . ' ';
            $query .= 'WHERE pref_uid = ?';
            $query .= ' AND pref_name = ?';
            $query .= ' AND (pref_scope = ?';
            $query .= " OR pref_scope = 'horde')";

            $values = array($this->_user, $name, $scope);

            /* Execute the query. */
            $check = $this->_db->getOne($query, $values);

            /* Return an error if the query fails. */
            if (is_a($check, 'PEAR_Error')) {
                Horde::logMessage('Failed retrieving prefs for ' . $this->_user, __FILE__, __LINE__, PEAR_LOG_ERR);
                return PEAR::raiseError(_("Failed retrieving preferences."));
            }

            /* Is there an existing row for this preference? */
            if (!empty($check)) {
                /* Update the existing row. */
                $query = 'UPDATE ' . $this->_params['table'] . ' ';
                $query .= 'SET pref_value = ?';
                $query .= ' WHERE pref_uid = ?';
                $query .= ' AND pref_name = ?';
                $query .= ' AND pref_scope = ?';

                $values = array((string)$this->getValue($name, false),
                                $this->_user,
                                $name,
                                $scope);

                $result = $this->_db->query($query, $values);

                /* Return an error if the update fails. */
                if (is_a($result, 'PEAR_Error')) {
                    Horde::fatal($result, __FILE__, __LINE__);
                }
            } else {
                /* Insert a new row. */
                $query  = 'INSERT INTO ' . $this->_params['table'] . ' ';
                $query .= '(pref_uid, pref_scope, pref_name, pref_value) VALUES';
                $query .= '(?, ?, ?, ?)';

                $values = array($this->_user,
                                $scope,
                                $name,
                                (string)$this->getValue($name, false));

                $result = $this->_db->query($query, $values);

                /* Return an error if the insert fails. */
                if (is_a($result, 'PEAR_Error')) {
                    Horde::fatal($result, __FILE__, __LINE__);
                }
            }

            /* Mark this preference as "clean" now. */
            $this->setDirty($name, false);
        }

        /* Update the session cache. */
        $this->cacheUpdate();

        return true;
    }

    /**
     * Perform cleanup operations.
     *
     * @param boolean $all  Cleanup all Horde preferences.
     */
    function cleanup($all = false)
    {
        /* Close the database connection. */
        $this->_disconnect();

        parent::cleanup($all);
    }

    /**
     * Clears all preferences from the backend.
     */
    function clear()
    {
        /* Make sure we're connected. */
        $this->_connect();

        /* Build the SQL query. */
        $query = 'DELETE FROM ' . $this->_params['table'];
        $query .= ' WHERE pref_uid = ?';

        $values = array($this->_user);

        Horde::logMessage(sprintf('SQL Query by Prefs_sql::clear(): %s', $query), __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->query($query, $values);

        /* Cleanup. */
        parent::clear();

        return $result;
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
        static $converted = array();

        if (is_array($value)) {
            return String::convertCharset($value, $this->_params['charset'], $charset);
        }

        if (is_bool($value)) {
            return $value;
        }

        if (!isset($converted[$charset][$value])) {
            $converted[$charset][$value] = String::convertCharset($value, $this->_params['charset'], $charset);
        }

        return $converted[$charset][$value];
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
        return String::convertCharset($value, $charset, $this->_params['charset']);
    }

    /**
     * Attempts to open a persistent connection to the SQL server.
     *
     * @access private
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function _connect()
    {
        /* Check to see if we are already connected. */
        if ($this->_connected) {
            return true;
        }

        Horde::assertDriverConfig($this->_params, 'prefs',
            array('phptype', 'charset'),
            'preferences SQL');

        if (!isset($this->_params['database'])) {
            $this->_params['database'] = '';
        }
        if (!isset($this->_params['username'])) {
            $this->_params['username'] = '';
        }
        if (!isset($this->_params['password'])) {
            $this->_params['password'] = '';
        }
        if (!isset($this->_params['hostspec'])) {
            $this->_params['hostspec'] = '';
        }
        if (!isset($this->_params['table'])) {
            $this->_params['table'] = 'horde_prefs';
        }

        /* Connect to the SQL server using the supplied parameters. */
        $this->_db = &DB::connect($this->_params,
                                  array('persistent' => !empty($this->_params['persistent'])));
        if (is_a($this->_db, 'PEAR_Error')) {
            Horde::fatal($this->_db, __FILE__, __LINE__);
        }

        // Set DB portability options.
        switch ($this->_db->phptype) {
        case 'mssql':
            $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
            break;
        default:
            $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
        }

        $this->_connected = true;

        return true;
    }

    /**
     * Disconnect from the SQL server and clean up the connection.
     *
     * @access private
     *
     * @return boolean  True on success, false on failure.
     */
    function _disconnect()
    {
        if ($this->_connected) {
            $this->_connected = false;
            return $this->_db->disconnect();
        } else {
            return true;
        }
    }

}
