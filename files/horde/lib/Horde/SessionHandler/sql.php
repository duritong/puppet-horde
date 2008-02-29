<?php
/**
 * SessionHandler implementation for PHP's PEAR database abstraction layer.
 *
 * Required parameters:<pre>
 *   'phptype'   The database type (e.g. 'pgsql', 'mysql', etc.).</pre>
 *
 * Required by some database implementations:<pre>
 *   'hostspec'  The hostname of the database server.
 *   'protocol'  The communication protocol ('tcp', 'unix', etc.).
 *   'database'  The name of the database.
 *   'username'  The username with which to connect to the database.
 *   'password'  The password associated with 'username'.
 *   'options'   Additional options to pass to the database.
 *   'tty'       The TTY on which to connect to the database.
 *   'port'      The port on which to connect to the database.</pre>
 *
 * Optional parameters:<pre>
 *   'persistent'  Use persistent DB connections? (boolean)
 *   'table'       The name of the sessiondata table in 'database'.  Default
 *                 is 'horde_sessionhandler'.</pre>
 *
 * The table structure for the SessionHandler can be found in
 * horde/scripts/sql/horde_sessionhandler.sql.
 *
 * $Horde: framework/SessionHandler/SessionHandler/sql.php,v 1.22.10.14 2007/01/02 13:54:38 jan Exp $
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
class SessionHandler_sql extends SessionHandler {

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
     * Close the SessionHandler backend.
     *
     * @return boolean  True on success, false otherwise.
     */
    function close()
    {
        /* Disconnect from database. */
        if ($this->_connected) {
            /* Close any open transactions. */
            $this->_db->commit();
            $this->_db->autoCommit(true);

            $this->_connected = false;
            return $this->_db->disconnect();
        }

        return true;
    }

    /**
     * Read the data for a particular session identifier from the
     * SessionHandler backend.
     *
     * @param string $id  The session identifier.
     *
     * @return string  The session data.
     */
    function read($id)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Begin a transaction. */
        $result = $this->_db->autocommit(false);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return '';
        }

        /* Execute the query. */
        require_once 'Horde/SQL.php';
        $result = Horde_SQL::readBlob($this->_db, $this->_params['table'], 'session_data',
                                      array('session_id' => $id));

        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return '';
        }

        return $result;
    }

    /**
     * Write session data to the SessionHandler backend.
     *
     * @param string $id            The session identifier.
     * @param string $session_data  The session data.
     *
     * @return boolean  True on success, false otherwise.
     */
    function write($id, $session_data)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = sprintf('SELECT session_id FROM %s WHERE session_id = ?',
                         $this->_params['table']);
        $values = array($id);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_sql::write(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->getOne($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        require_once 'Horde/SQL.php';
        if ($result) {
            $result = Horde_SQL::updateBlob($this->_db, $this->_params['table'], 'session_data',
                                            $session_data, array('session_id' => $id),
                                            array('session_lastmodified' => time()));
        } else {
            $result = Horde_SQL::insertBlob($this->_db, $this->_params['table'], 'session_data',
                                            $session_data, array('session_id' => $id,
                                                                 'session_lastmodified' => time()));
        }

        if (is_a($result, 'PEAR_Error')) {
            $this->_db->rollback();
            $this->_db->autoCommit(true);
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        $result = $this->_db->commit();
        if (is_a($result, 'PEAR_Error')) {
            $this->_db->autoCommit(true);
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        $this->_db->autoCommit(true);
        return true;
    }

    /**
     * Destroy the data for a particular session identifier in the
     * SessionHandler backend.
     *
     * @param string $id  The session identifier.
     *
     * @return boolean  True on success, false otherwise.
     */
    function destroy($id)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = sprintf('DELETE FROM %s WHERE session_id = ?',
                         $this->_params['table']);
        $values = array($id);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_sql::destroy(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        $result = $this->_db->commit();
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        return true;
    }

    /**
     * Garbage collect stale sessions from the SessionHandler backend.
     *
     * @param integer $maxlifetime  The maximum age of a session.
     *
     * @return boolean  True on success, false otherwise.
     */
    function gc($maxlifetime = 300)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = sprintf('DELETE FROM %s WHERE session_lastmodified < ?',
                         $this->_params['table']);
        $values = array(time() - $maxlifetime);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_sql::gc(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        return true;
    }

    /**
     * Get a list of the valid session identifiers.
     *
     * @return array  A list of valid session identifiers.
     */
    function getSessionIDs()
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        $timeout = time() - ini_get('session.gc_maxlifetime');

        /* Build the SQL query. */
        $query = 'SELECT session_id FROM ' . $this->_params['table'] .
                 ' WHERE session_lastmodified > ?';
        $values = array($timeout);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_sql::getSessionIDs(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->getCol($query, 0, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        return $result;
    }

    /**
     * Attempts to open a connection to the SQL server.
     *
     * @return boolean  True on success; exits (Horde::fatal()) on error.
     */
    function _connect()
    {
        if (!$this->_connected) {
            Horde::assertDriverConfig($this->_params, 'sessionhandler',
                array('phptype'),
                'session handler SQL');

            if (!isset($this->_params['database'])) {
                $this->_params['database'] = '';
            }
            if (!isset($this->_params['username'])) {
                $this->_params['username'] = '';
            }
            if (!isset($this->_params['hostspec'])) {
                $this->_params['hostspec'] = '';
            }
            if (empty($this->_params['table'])) {
                $this->_params['table'] = 'horde_sessionhandler';
            }

            /* Connect to the SQL server using the supplied
             * parameters. */
            include_once 'DB.php';
            $this->_db = &DB::connect($this->_params,
                                      array('persistent' => !empty($this->_params['persistent'])));
            if (is_a($this->_db, 'PEAR_Error')) {
                Horde::fatal($this->_db, __FILE__, __LINE__);
            }

            $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);

            $this->_connected = true;
        }

        return true;
    }

}
