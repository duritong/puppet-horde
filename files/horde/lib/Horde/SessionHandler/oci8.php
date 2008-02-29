<?php
/**
 * SessionHandler:: implementation for Oracle 8i (native).
 *
 * Required parameters:<pre>
 *   'hostspec'  The hostname of the database server.
 *   'username'  The username with which to connect to the database.
 *   'password'  The password associated with 'username'.
 *   'database'  The name of the database.
 *   'table'     The name of the sessiondata table in 'database'.</pre>
 *
 * Required for some configurations:<pre>
 *   'port'  The port on which to connect to the database.</pre>
 *
 * Optional parameters:<pre>
 *   'persistent'  Use persistent DB connections? (boolean)</pre>
 *
 * The table structure for the SessionHandler can be found in
 * horde/scripts/sql/horde_sessionhandler.oci8.sql.
 *
 * $Horde: framework/SessionHandler/SessionHandler/oci8.php,v 1.8.4.14 2007/03/14 09:45:37 jan Exp $
 *
 * Copyright 2003-2007 Liam Hoekenga <liamr@umich.edu>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Liam Hoekenga <liamr@umich.edu>
 * @since   Horde 2.2
 * @package Horde_SessionHandler
 */
class SessionHandler_oci8 extends SessionHandler {

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
        if ($this->_connected) {
            $this->_connected = false;
            return OCILogOff($this->_db);
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
        if (is_a(($result = $this->_connect()), 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return '';
        }

        $select_query = sprintf('SELECT session_data FROM %s WHERE session_id = %s FOR UPDATE',
                                $this->_params['table'], $this->_quote($id));

        Horde::logMessage(sprintf('SQL Query by SessionHandler_oci8::read(): query = "%s"', $select_query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $select_statement = OCIParse($this->_db, $select_query);
        OCIExecute($select_statement, OCI_DEFAULT);
        if (OCIFetchInto($select_statement, $result)) {
            $value = $result[0]->load();
        } else {
            $value = '';
        }

        OCIFreeStatement($select_statement);
        return $value;
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
        if (is_a(($result = $this->_connect()), 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        $select_query = sprintf('SELECT session_data FROM %s WHERE session_id = %s FOR UPDATE',
                                $this->_params['table'], $this->_quote($id));

        Horde::logMessage(sprintf('SQL Query by SessionHandler_oci8::write(): query = "%s"', $select_query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $select_statement = OCIParse($this->_db, $select_query);
        OCIExecute($select_statement, OCI_DEFAULT);
        if (OCIFetchInto($select_statement, $result)) {
            /* Discard the existing LOB contents. */
            if (!$result[0]->truncate()) {
                OCIRollback($this->_db);
                return false;
            }

            /* Save the session data. */
            if ($result[0]->save($session_data)) {
                OCICommit($this->_db);
                OCIFreeStatement($select_statement);
            } else {
                OCIRollback($this->_db);
                return false;
            }
        } else {
            $insert_query = sprintf('INSERT INTO %s (session_id, session_lastmodified, session_data) VALUES (%s, %s, EMPTY_BLOB()) RETURNING session_data INTO :blob',
                                    $this->_params['table'],
                                    $this->_quote($id),
                                    $this->_quote(time()));

            Horde::logMessage(sprintf('SQL Query by SessionHandler_oci8::read(): query = "%s"', $insert_query),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $insert_statement = OCIParse($this->_db, $insert_query);
            $lob = OCINewDescriptor($this->_db);
            OCIBindByName($insert_statement, ':blob', $lob, -1, SQLT_BLOB);
            OCIExecute($insert_statement, OCI_DEFAULT);
            if (!$lob->save($session_data)) {
                OCIRollback($this->_db);
                return false;
            }
            OCICommit($this->_db);
            OCIFreeStatement($insert_statement);
        }

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
        if (is_a(($result = $this->_connect()), 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        /* Build the SQL query. */
        $query = sprintf('DELETE FROM %s WHERE session_id = %s',
                         $this->_params['table'], $this->_quote($id));

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_oci8::destroy(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $statement = OCIParse($this->_db, $query);
        $result = OCIExecute($statement);
        if (!$result) {
            OCIFreeStatement($statement);
            Horde::logMessage('Failed to delete session (id = ' . $id . ')', __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        OCIFreeStatement($statement);
        return true;
    }

    /**
     * Garbage collect stale sessions from the SessionHandler backend.
     *
     * @param integer $maxlifetime  The maximum age of a session.
     *
     * @return boolean  True on success, false otherwise.
     */
    function gc($maxlifetime = 1)
    {
        /* Make sure we have a valid database connection. */
        if (is_a(($result = $this->_connect()), 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        /* Build the SQL query. */
        $query = sprintf('DELETE FROM %s WHERE session_lastmodified < %s',
                         $this->_params['table'], $this->_quote(time() - $maxlifetime));

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_oci8::gc(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $statement = OCIParse($this->_db, $query);
        $result = OCIExecute($statement);
        if (!$result) {
            OCIFreeStatement($statement);
            Horde::logMessage('Error garbage collecting old sessions', __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        OCIFreeStatement($statement);
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
        if (is_a(($result = $this->_connect()), 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        /* Session timeout, don't rely on garbage collection */
        $timeout = time() - ini_get('session.gc_maxlifetime');

        $query = sprintf('SELECT session_id FROM %s' .
                         ' WHERE session_lastmodified > %s',
                         $this->_params['table'],
                         $timeout);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_oci8::getSessionIDs(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute query */
        $statement = OCIParse($this->_db, $query);
        OCIExecute($statement);

        $sessions = array();
        while (OCIFetchInto($statement, $row)) {
            $sessions[] = $row[0];
        }

        OCIFreeStatement($statement);
        return $sessions;
    }

    /**
     * Escape a string for insertion. Stolen from PEAR::DB.
     * @access private
     *
     * @param string $value  The string to quote.
     *
     * @return string  The quoted string.
     */
    function _quote($value)
    {
        return ($value === null) ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Attempts to open a connection to the SQL server.
     *
     * @access private
     */
    function _connect()
    {
        if ($this->_connected) {
            return true;
        }

        Horde::assertDriverConfig($this->_params, 'sessionhandler',
            array('hostspec', 'username', 'password'),
            'session handler Oracle');

        if (!isset($this->_params['table'])) {
            $this->_params['table'] = 'horde_sessionhandler';
        }

        if (function_exists('oci_connect')) {
            if (empty($this->_params['persistent'])) {
                $connect = 'oci_connect';
            } else {
                $connect = 'oci_pconnect';
            }
        } else {
            if (empty($this->_params['persistent'])) {
                $connect = 'OCILogon';
            } else {
                $connect = 'OCIPLogon';
            }
        }

        if (!is_resource($this->_db = @$connect($this->_params['username'],
                                                $this->_params['password'],
                                                $this->_params['hostspec']))) {
            return PEAR::raiseError('Could not connect to database for SQL SessionHandler.');
        }

        $this->_connected = true;
        return true;
    }

}
