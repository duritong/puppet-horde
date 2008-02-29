<?php
/**
 * Mnemo storage implementation for PHP's PEAR database abstraction
 * layer.
 *
 * Required parameters:<pre>
 *      'phptype'       The database type (e.g. 'pgsql', 'mysql', etc.).
 *      'charset'       The database's internal charset.</pre>
 *
 * Optional values:<pre>
 *      'table'         The name of the memos table in 'database'. Defaults
 *                      to 'mnemo_memos'</pre>
 *
 * Required by some database implementations:<pre>
 *      'hostspec'      The hostname of the database server.
 *      'protocol'      The communication protocol ('tcp', 'unix', etc.).
 *      'database'      The name of the database.
 *      'username'      The username with which to connect to the database.
 *      'password'      The password associated with 'username'.
 *      'options'       Additional options to pass to the database.
 *      'tty'           The TTY on which to connect to the database.
 *      'port'          The port on which to connect to the database.</pre>
 *
 * The table structure is defined in scripts/drivers/mnemo_memos.sql.
 *
 * $Horde: mnemo/lib/Driver/sql.php,v 1.28.2.14 2007/01/02 13:55:11 jan Exp $
 *
 * Copyright 2001-2007 Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Mnemo 1.0
 * @package Mnemo
 */
class Mnemo_Driver_sql extends Mnemo_Driver {

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
     * Are we connected to the SQL server?
     *
     * @var boolean
     */
    var $_connected = false;

    /**
     * Construct a new SQL storage object.
     *
     * @param string $notepad   The name of the notepad to load/save notes from.
     * @param array  $params    A hash containing connection parameters.
     */
    function Mnemo_Driver_sql($notepad, $params = array())
    {
        $this->_notepad = $notepad;
        $this->_params = $params;
    }

    /**
     * Retrieve one note from the database.
     *
     * @param string $noteId  The ID of the note to retrieve.
     *
     * @return array  The array of note attributes.
     */
    function get($noteId)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = 'SELECT * FROM ' . $this->_params['table'] .
                 ' WHERE memo_owner = ? AND memo_id = ?';
        $values = array($this->_notepad, $noteId);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Mnemo_Driver_sql::get(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->query($query, $values);

        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        if (is_a($row, 'PEAR_Error')) {
            return $row;
        }

        return $this->_buildNote($row);
    }

    /**
     * Retrieve one note from the database by UID.
     *
     * @param string $uid  The UID of the note to retrieve.
     *
     * @return array  The array of note attributes.
     */
    function getByUID($uid)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = 'SELECT * FROM ' . $this->_params['table'] .
                 ' WHERE memo_uid = ?';
        $values = array($uid);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Mnemo_Driver_sql::getByUID(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        if (is_a($row, 'PEAR_Error')) {
            return $row;
        }
        if ($row === null) {
            return PEAR::raiseError(_("Not found"));
        }

        /* Decode and return the task. */
        $this->_notepad = $row['memo_owner'];
        return $this->_buildNote($row);
    }

    /**
     * Add a note to the backend storage.
     *
     * @param string $desc      The first line of the note.
     * @param string $body      The whole note body.
     * @param string $category  The category of the note.
     * @param string $uid       A Unique Identifier for the note.
     *
     * @return string  The unique ID of the new note.
     */
    function add($desc, $body, $category = '', $uid = null)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        $noteId = md5(uniqid(mt_rand(), true));
        if (is_null($uid)) {
            $uid = $this->generateUID();
        }

        $query = 'INSERT INTO ' . $this->_params['table'] .
                 ' (memo_owner, memo_id, memo_desc, memo_body, memo_category, memo_uid)' .
                 ' VALUES (?, ?, ?, ?, ?, ?)';
        $values = array($this->_notepad,
                        $noteId,
                        String::convertCharset($desc, NLS::getCharset(), $this->_params['charset']),
                        String::convertCharset($body, NLS::getCharset(), $this->_params['charset']),
                        String::convertCharset($category, NLS::getCharset(), $this->_params['charset']),
                        String::convertCharset($uid, NLS::getCharset(), $this->_params['charset']));

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Mnemo_Driver_sql::add(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the insertion query. */
        $result = $this->_db->query($query, $values);

        /* Return an error immediately if the query failed. */
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        /* Log the creation of this item in the history log. */
        $history = &Horde_History::singleton();
        $history->log('mnemo:' . $this->_notepad . ':' . $uid, array('action' => 'add'), true);

        return $noteId;
    }

    /**
     * Modify an existing note.
     *
     * @param string $noteId    The note to modify.
     * @param string $desc      The description (long) of the note.
     * @param string $body      The description (long) of the note.
     * @param string $category  The category of the note.
     */
    function modify($noteId, $desc, $body, $category = null)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        $query  = 'UPDATE ' . $this->_params['table'] .
                  ' SET memo_desc = ?, memo_body = ?';
        $values = array(String::convertCharset($desc, NLS::getCharset(), $this->_params['charset']),
                        String::convertCharset($body, NLS::getCharset(), $this->_params['charset']));

        // Don't change the category if it isn't provided.
        if (!is_null($category)) {
            $query .= ', memo_category = ?';
            $values[] = String::convertCharset($category, NLS::getCharset(), $this->_params['charset']);
        }
        $query .= ' WHERE memo_owner = ? AND memo_id = ?';
        array_push($values, $this->_notepad, $noteId);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Mnemo_Driver_sql::modify(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the update query. */
        $result = $this->_db->query($query, $values);

        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        /* Log the modification of this item in the history log. */
        $note = $this->get($noteId);
        if (!empty($note['uid'])) {
            $history = &Horde_History::singleton();
            $history->log('mnemo:' . $this->_notepad . ':' . $note['uid'], array('action' => 'modify'), true);
        }

        return true;
    }

    /**
     * Move a note to a new notepad.
     *
     * @param string $noteId      The note to move.
     * @param string $newNotepad  The new notepad.
     */
    function move($noteId, $newNotepad)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        $query = 'UPDATE ' . $this->_params['table'] .
                 ' SET memo_owner = ?' .
                 ' WHERE memo_owner = ? AND memo_id = ?';
        $values = array($newNotepad, $this->_notepad, $noteId);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Mnemo_Driver_sql::move(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the move query. */
        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return true;
    }

    function delete($noteId)
    {
        $this->_connect();

        /* Get the note's details for use later. */
        $note = $this->get($noteId);

        $query = 'DELETE FROM ' . $this->_params['table'] .
                 ' WHERE memo_owner = ? AND memo_id = ?';
        $values = array($this->_notepad, $noteId);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Mnemo_Driver_sql::delete(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the delete query. */
        $result = $this->_db->query($query, $values);

        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        /* Log the deletion of this item in the history log. */
        if (!empty($note['uid'])) {
            $history = &Horde_History::singleton();
            $history->log('mnemo:' . $this->_notepad . ':' . $note['uid'], array('action' => 'delete'), true);
        }

        return true;
    }

    function deleteAll()
    {
        $this->_connect();

        $query = 'DELETE FROM ' . $this->_params['table'] .
                 ' WHERE memo_owner = ?';
        $values = array($this->_notepad);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Mnemo_Driver_sql::deleteAll(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the delete query. */
        $result = $this->_db->query($query, $values);

        return is_a($result, 'PEAR_Error') ? $result : true;
    }

    /**
     * Retrieves all of the notes from $this->_notepad from the
     * database.
     *
     * @return mixed  True on success, PEAR_Error on failure.
     */
    function retrieve()
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = 'SELECT * FROM ' . $this->_params['table'] .
                 ' WHERE memo_owner = ?';
        $values = array($this->_notepad);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Mnemo_Driver_sql::retrieve(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Execute the query. */
        $result = $this->_db->query($query, $values);

        if (!is_a($result, 'PEAR_Error')) {
            $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
            if (is_a($row, 'PEAR_Error')) {
                return $row;
            }

            /* Store the retrieved values in a fresh $memos list. */
            $this->_memos = array();
            while ($row && !is_a($row, 'PEAR_Error')) {
                /* Add this new memo to the $memos list. */
                $this->_memos[$row['memo_id']] = $this->_buildNote($row);

                /* Advance to the new row in the result set. */
                $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
            }
            $result->free();
        } else {
            return $result;
        }

        return true;
    }

    /**
     * Attempts to open a persistent connection to the SQL server.
     *
     * @return boolean  True on success; exits (Horde::fatal()) on error.
     */
    function _connect()
    {
        if (!$this->_connected) {
            Horde::assertDriverConfig($this->_params, 'storage',
                array('phptype', 'charset'));

            if (!isset($this->_params['database'])) {
                $this->_params['database'] = '';
            }
            if (!isset($this->_params['username'])) {
                $this->_params['username'] = '';
            }
            if (!isset($this->_params['hostspec'])) {
                $this->_params['hostspec'] = '';
            }

            if (!isset($this->_params['table'])) {
                $this->_params['table'] = 'mnemo_memos';
            }

            /* Connect to the SQL server using the supplied parameters. */
            require_once 'DB.php';
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
        }

        return true;
    }

    function _buildNote($row)
    {
        /* Make sure notes always have a UID. */
        if (empty($row['memo_uid'])) {
            $row['memo_uid'] = $this->generateUID();

            $query = 'UPDATE ' . $this->_params['table'] .
                ' SET memo_uid = ?' .
                ' WHERE memo_owner = ? AND memo_id = ?';
            $values = array($row['memo_uid'], $row['memo_owner'], $row['memo_id']);

            /* Log the query at a DEBUG log level. */
            Horde::logMessage(sprintf('Mnemo_Driver_sql adding missing UID: %s', $query),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $this->_db->query($query, $values);
        }

        /* Create a new task based on $row's values. */
        return array('memolist_id' => $row['memo_owner'],
                     'memo_id' => $row['memo_id'],
                     'uid' => String::convertCharset($row['memo_uid'], $this->_params['charset']),
                     'desc' => String::convertCharset($row['memo_desc'], $this->_params['charset']),
                     'body' => String::convertCharset($row['memo_body'], $this->_params['charset']),
                     'category' => String::convertCharset($row['memo_category'], $this->_params['charset']));
    }

}
