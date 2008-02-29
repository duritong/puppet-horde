<?php
/**
 * SessionHandler:: implementation for MySQL (native).
 *
 * Required parameters:<pre>
 *   'hostspec'   - The hostname of the database server.
 *   'protocol'   - The communication protocol ('tcp', 'unix', etc.).
 *   'username'   - The username with which to connect to the database.
 *   'password'   - The password associated with 'username'.
 *   'database'   - The name of the database.
 *   'table'      - The name of the sessiondata table in 'database'.
 *   'rowlocking' - Whether to use row-level locking and transactions (InnoDB)
 *                  or table-level locking (MyISAM).</pre>
 *
 * Required for some configurations:<pre>
 *   'port'  The port on which to connect to the database.</pre>
 *
 * Optional parameters:<pre>
 *   'persistent'  Use persistent DB connections? (boolean)</pre>
 *
 * The table structure for the SessionHandler can be found in
 * horde/scripts/sql/horde_sessionhandler.sql.
 *
 * $Horde: framework/SessionHandler/SessionHandler/mysql.php,v 1.16.12.16 2007/04/10 15:55:56 jan Exp $
 *
 * Copyright 2002-2007 Mike Cochrane <mike@graftonhall.co.nz>
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2006-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Mike Cochrame <mike@graftonhall.co.nz>
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 3.0
 * @package Horde_SessionHandler
 */
class SessionHandler_mysql extends SessionHandler {

    /**
     * Handle for the current database connection.
     *
     * @var resource
     */
    var $_db;

    /**
     * Are we connected to the SQL server.
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
            $this->_connected = false;
            return @mysql_close($this->_db);
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

        /* Session timeout, don't rely on garbage collection. */
        $timeout = time() - ini_get('session.gc_maxlifetime');

        $query = sprintf('SELECT session_data FROM %s WHERE session_id = %s' .
                         ' AND session_lastmodified > %s',
                         $this->_params['table'],
                         $this->_quote($id),
                         $timeout);

        if (!empty($this->_params['rowlocking'])) {
            /* Start a transaction. */
            $result = @mysql_query('START TRANSACTION', $this->_db);
            $query .= ' FOR UPDATE';
        } else {
            $result = @mysql_query('LOCK TABLES ' . $this->_params['table'] . ' WRITE', $this->_db);
        }
        if (!$result) {
            return '';
        }

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_mysql::read(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $result = @mysql_query($query, $this->_db);
        if (!$result) {
            Horde::logMessage('Error retrieving session data (id = ' . $id . '): ' . mysql_error($this->_db),
                              __FILE__, __LINE__, PEAR_LOG_ERR);
            return '';
        }

        return @mysql_result($result, 0, 0);
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
        $query = sprintf('REPLACE INTO %s (session_id, session_data, session_lastmodified)' .
                         ' VALUES (%s, %s, %s)',
                         $this->_params['table'],
                         $this->_quote($id),
                         $this->_quote($session_data),
                         time());

        $result = @mysql_query($query, $this->_db);
        if (!$result) {
            $error = mysql_error($this->_db);
        }
        if (empty($this->_params['rowlocking'])) {
            @mysql_query('UNLOCK TABLES ' . $this->_params['table'], $this->_db);
        }
        if (!$result) {
            @mysql_query('ROLLBACK', $this->_db);
            Horde::logMessage('Error writing session data: ' . $error, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        @mysql_query('COMMIT', $this->_db);

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
        $query = sprintf('DELETE FROM %s WHERE session_id = %s',
                         $this->_params['table'], $this->_quote($id));

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_mysql::destroy(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = @mysql_query($query, $this->_db);
        if (!$result) {
            $error = mysql_error($this->_db);
        }
        if (empty($this->_params['rowlocking'])) {
            @mysql_query('UNLOCK TABLES ' . $this->_params['table'], $this->_db);
        }
        if (!$result) {
            @mysql_query('ROLLBACK', $this->_db);
            Horde::logMessage('Failed to delete session (id = ' . $id . '): ' . $error, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        @mysql_query('COMMIT', $this->_db);

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
        $query = sprintf('DELETE FROM %s WHERE session_lastmodified < %s',
                         $this->_params['table'], (int)(time() - $maxlifetime));

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_mysql::gc(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = @mysql_query($query, $this->_db);
        if (!$result) {
            Horde::logMessage('Error garbage collecting old sessions: ' . mysql_error($this->_db), __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        return @mysql_affected_rows($this->_db);
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

        /* Session timeout, don't rely on garbage collection */
        $timeout = time() - ini_get('session.gc_maxlifetime');

        $query = sprintf('SELECT session_id FROM %s' .
                         ' WHERE session_lastmodified > %s',
                         $this->_params['table'],
                         $timeout);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_mysql::getSessionIDs(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $result = @mysql_query($query, $this->_db);
        if (!$result) {
            Horde::logMessage('Error getting session IDs: ' . mysql_error($this->_db),
                              __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        $sessions = array();

        while ($row = mysql_fetch_row($result))
            $sessions[] = $row[0];

        return $sessions;
    }

    /**
     * Escape a mysql string.
     *
     * @access private
     *
     * @param string $value  The string to quote.
     *
     * @return string  The quoted string.
     */
    function _quote($value)
    {
        switch (strtolower(gettype($value))) {
        case 'null':
            return 'NULL';

        case 'integer':
            return $value;

        case 'string':
        default:
            return "'" . @mysql_real_escape_string($value, $this->_db) . "'";
        }
    }

    /**
     * Attempts to open a connection to the SQL server.
     *
     * @access private
     */
    function _connect()
    {
        if ($this->_connected) {
            return;
        }

        Horde::assertDriverConfig($this->_params, 'sessionhandler',
            array('hostspec', 'username', 'database'),
            'session handler MySQL');

        if (empty($this->_params['password'])) {
            $this->_params['password'] = '';
        }

        if (empty($this->_params['table'])) {
            $this->_params['table'] = 'horde_sessionhandler';
        }

        if (empty($this->_params['persistent'])) {
            $connect = 'mysql_connect';
        } else {
            $connect = 'mysql_pconnect';
        }

        if (!$this->_db = @$connect($this->_params['hostspec'],
                                    $this->_params['username'],
                                    $this->_params['password'])) {
            Horde::fatal(PEAR::raiseError('Could not connect to database for SQL SessionHandler.'), __FILE__, __LINE__);
        }

        if (!@mysql_select_db($this->_params['database'], $this->_db)) {
            Horde::fatal(PEAR::raiseError(sprintf('Could not connect to table %s for SQL SessionHandler.', $this->_params['database']), __FILE__, __LINE__));
        }

        $this->_connected = true;
    }

}
