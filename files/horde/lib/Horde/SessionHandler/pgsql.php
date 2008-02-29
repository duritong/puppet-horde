<?php
/**
 * PostgreSQL Session Handler for PHP (native).
 *
 * Copyright 2000-2007 Jon Parise <jon@csh.rit.edu>.  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions
 *  are met:
 *  1. Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 *  ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 *  ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 *  FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 *  DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 *  OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 *  LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 *  OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 *  SUCH DAMAGE.
 *
 * Required parameters:<pre>
 *   'database'  The name of the database.
 *   'password'  The password associated with 'username'.
 *   'protocol'  The communication protocol ('tcp', 'unix').
 *   'username'  The username with which to connect to the database.
 *
 * Required for some configurations (i.e. 'protocol' = 'tcp'):<pre>
 *   'hostspec'  The hostname of the database server.
 *   'port'      The port on which to connect to the database.</pre>
 *
 * Optional parameters:<pre>
 *   'persistent'  Use persistent DB connections? (boolean)
 *                 Default: NO
 *   'table'       The name of the sessiondata table in 'database'.
 *                 Default: 'horde_sessionhandler'</pre>
 *
 * The table structure for the SessionHandler can be found in
 * horde/scripts/sql/horde_sessionhandler.pgsql.sql.
 *
 * Contributors:<pre>
 *  Jason Carlson           Return an empty string on failed reads
 *  pat@pcprogrammer.com    Perform update in a single transaction
 *  Jonathan Crompton       Lock row for life of session</pre>
 *
 * $Horde: framework/SessionHandler/SessionHandler/pgsql.php,v 1.12.10.16 2007/01/02 13:54:38 jan Exp $
 *
 * @author  Jon Parise <jon@csh.rit.edu>
 * @since   Horde 3.0
 * @package Horde_SessionHandler
 */
class SessionHandler_pgsql extends SessionHandler {

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
            return @pg_close($this->_db);
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

        @pg_query($this->_db, 'BEGIN;');

        $timeout = time() - ini_get('session.gc_maxlifetime');
        $query = sprintf('SELECT session_data FROM %s WHERE ' .
                         'session_id = %s AND session_lastmodified >= %s ' .
                         'FOR UPDATE;',
                         $this->_params['table'],
                         $this->quote($id),
                         $timeout);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_pgsql::' .
                                  'read(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $result = @pg_query($this->_db, $query);
        $data = pg_fetch_result($result, 0, 'session_data');
        pg_free_result($result);

        return pack('H*', $data);
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

        $timeout = time() - ini_get('session.gc_maxlifetime');
        $query = sprintf('SELECT session_data FROM %s WHERE ' .
                         'session_id = %s AND session_lastmodified >= %s ' .
                         'FOR UPDATE',
                         $this->_params['table'],
                         $this->quote($id),
                         $timeout);
        $result = @pg_query($this->_db, $query);
        $rows = pg_num_rows($result);
        pg_free_result($result);

        if ($rows == 0) {
            $query = sprintf('INSERT INTO %s (session_id, ' .
                             'session_lastmodified, session_data) ' .
                             'VALUES (%s, %s, %s);',
                             $this->_params['table'],
                             $this->quote($id),
                             time(),
                             $this->quote(bin2hex($session_data)));
        } else {
            $query = sprintf('UPDATE %s SET session_lastmodified = %s, ' .
                             'session_data = %s WHERE session_id = %s;',
                             $this->_params['table'],
                             time(),
                             $this->quote(bin2hex($session_data)),
                             $this->quote($id));
        }

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_pgsql::' .
                                  'write(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $result = @pg_query($this->_db, $query);
        if (!$result) {
            Horde::logMessage('Error writing session data: ' . pg_last_error($this->_db), __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }
        $rows = pg_affected_rows($result);
        pg_free_result($result);

        @pg_query($this->_db, 'COMMIT;');

        if ($rows != 1) {
            Horde::logMessage('Error writing session data',
                              __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
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
        $this->_connect();

        /* Build the SQL query. */
        $query = sprintf('DELETE FROM %s WHERE session_id = %s;',
                         $this->_params['table'], $this->quote($id));

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_pgsql::' .
                                  'destroy(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = @pg_query($this->_db, $query);

        @pg_query($this->_db, 'COMMIT;');

        if (!$result) {
            pg_free_result($result);
            Horde::logMessage('Failed to delete session (id = ' . $id . ')',
                              __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        pg_free_result($result);
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
                         $this->_params['table'],
                         $this->quote(time() - $maxlifetime));

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_pgsql::' .
                                  'gc(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = @pg_query($this->_db, $query);
        if (!$result) {
            Horde::logMessage('Error garbage collecting old sessions',
                              __FILE__, __LINE__, PEAR_LOG_ERR);
        }

        pg_free_result($result);
        return $result;
    }

    /**
     * Escape a string for insertion into the database.
     * @access private
     *
     * @param string $value  The string to quote.
     *
     * @return string  The quoted string.
     */
    function quote($value)
    {
        return "'" . addslashes($value) . "'";
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

        /* Build the SQL query. */
        $query = 'SELECT session_id FROM ' . $this->_params['table'];

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL Query by SessionHandler_pgsql::' .
                                  'getSessionIDs(): query = "%s"', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = @pg_query($this->_db, $query);
        if (!$result) {
            pg_free_result($result);
            Horde::logMessage('Error getting session IDs',
                              __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        $sessions = array();
        while ($row = pg_fetch_row($result)) {
            $sessions[] = $row[0];
        }

        pg_free_result($result);

        return $sessions;
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
                                      array('hostspec', 'username', 'database', 'password'),
                                      'session handler pgsql');

            if (empty($this->_params['table'])) {
                $this->_params['table'] = 'horde_sessionhandler';
            }

            $connect = empty($this->_params['persistent']) ?
                'pg_connect' :'pg_pconnect';

            $paramstr = '';
            if (isset($this->_params['protocol']) &&
                $this->_params['protocol'] == 'tcp') {
                $paramstr .= ' host=' . $this->_params['hostspec'];
                if (isset($this->_params['port'])) {
                    $paramstr .= ' port=' . $this->_params['port'];
                }
            }
            $paramstr .= ' dbname=' . $this->_params['database'] .
                ' user=' . $this->_params['username'] .
                ' password=' . $this->_params['password'];

            if (!$this->_db = @$connect($paramstr)) {
                return false;
            }

            $this->_connected = true;
        }

        return true;
    }

}
