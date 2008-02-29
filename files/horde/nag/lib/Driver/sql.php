<?php
/**
 * Nag storage implementation for PHP's PEAR database abstraction layer.
 *
 * Required parameters:<pre>
 *   'phptype'   The database type (e.g. 'pgsql', 'mysql', etc.).
 *   'charset'   The database's internal charset.</pre>
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
 *   'table'     The name of the tasks table in 'database'.  Default is
 *               'nag_tasks'.</pre>
 *
 * The table structure can be created by the scripts/sql/nag.sql script.
 *
 * $Horde: nag/lib/Driver/sql.php,v 1.60.2.19 2007/03/13 10:14:09 jan Exp $
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Jon Parise <jon@horde.org>
 * @since   Nag 0.1
 * @package Nag
 */
class Nag_Driver_sql extends Nag_Driver {

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
     * Constructs a new SQL storage object.
     *
     * @param string $tasklist  The tasklist to load.
     * @param array $params     A hash containing connection parameters.
     */
    function Nag_Driver_sql($tasklist, $params = array())
    {
        $this->_tasklist = $tasklist;
        $this->_params = $params;
    }

    /**
     * Retrieves one task from the database.
     *
     * @param string $taskId  The id of the task to retrieve.
     *
     * @return array  The array of task attributes.
     */
    function get($taskId)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = sprintf('SELECT * FROM %s WHERE task_owner = ? and task_id = ?',
                         $this->_params['table']);
        $values = array($this->_tasklist, $taskId);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Nag_Driver_sql::get(): %s', $query),
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
        return $this->_buildTask($row);
    }

    /**
     * Retrieves one task from the database by UID.
     *
     * @param string $uid  The UID of the task to retrieve.
     *
     * @return array  The array of task attributes.
     */
    function getByUID($uid)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = sprintf('SELECT * FROM %s WHERE task_uid = ?',
                         $this->_params['table']);
        $values = array($uid);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Nag_Driver_sql::getByUID(): %s', $query),
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
        $this->_tasklist = $row['task_owner'];
        return $this->_buildTask($row);
    }

    /**
     * Adds a task to the backend storage.
     *
     * @param string $name        The name (short) of the task.
     * @param string $desc        The description (long) of the task.
     * @param integer $due        The due date of the task.
     * @param integer $priority   The priority of the task.
     * @param integer $completed  The completion state of the task.
     * @param string $category    The category of the task.
     * @param integer $completed  The alarm associated with the task.
     * @param string $uid         A Unique Identifier for the task.
     *
     * @return string  The Nag ID of the new task.
     */
    function _add($name, $desc, $due = 0, $priority = 0, $completed = 0,
                  $category = '', $alarm = 0, $uid = null)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        $taskId = md5(uniqid(mt_rand(), true));
        if ($uid === null) {
            $uid = $this->generateUID();
        }

        $query = sprintf(
            'INSERT INTO %s (task_owner, task_id, task_name, task_uid, ' .
            'task_desc, task_due, task_priority, task_completed, ' .
            'task_category, task_alarm) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $this->_params['table']);
        $values = array($this->_tasklist,
                        $taskId,
                        String::convertCharset($name, NLS::getCharset(), $this->_params['charset']),
                        String::convertCharset($uid, NLS::getCharset(), $this->_params['charset']),
                        String::convertCharset($desc, NLS::getCharset(), $this->_params['charset']),
                        (int)$due,
                        (int)$priority,
                        (int)$completed,
                        String::convertCharset($category, NLS::getCharset(), $this->_params['charset']),
                        (int)$alarm);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Nag_Driver_sql::_add(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the insertion query. */
        $result = $this->_db->query($query, $values);

        /* Return an error immediately if the query failed. */
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return $taskId;
    }

    /**
     * Modifies an existing task.
     *
     * @param string $taskId      The task to modify.
     * @param string $name        The name (short) of the task.
     * @param string $desc        The description (long) of the task.
     * @param integer $due        The due date of the task.
     * @param integer $priority   The priority of the task.
     * @param integer $completed  The completion state of the task.
     * @param string $category    The category of the task.
     * @param integer $completed  The alarm associated with the task.
     */
    function _modify($taskId, $name, $desc, $due = 0, $priority = 0,
                     $completed = 0, $category = '', $alarm = 0)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        $query = sprintf('UPDATE %s SET' .
                         ' task_name = ?, ' .
                         ' task_desc = ?, ' .
                         ' task_due = ?, ' .
                         ' task_priority = ?, ' .
                         ' task_completed = ?, ' .
                         ' task_category = ?, ' .
                         ' task_alarm = ? ' .
                         'WHERE task_owner = ? AND task_id = ?',
                         $this->_params['table']);
        $values = array(String::convertCharset($name, NLS::getCharset(), $this->_params['charset']),
                        String::convertCharset($desc, NLS::getCharset(), $this->_params['charset']),
                        (int)$due,
                        (int)$priority,
                        (int)$completed,
                        String::convertCharset($category, NLS::getCharset(), $this->_params['charset']),
                        (int)$alarm,
                        $this->_tasklist,
                        $taskId);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Nag_Driver_sql::modify(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the update query. */
        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return true;
    }

    /**
     * Moves a task to a different tasklist.
     *
     * @param string $taskId       The task to move.
     * @param string $newTasklist  The new tasklist.
     */
    function move($taskId, $newTasklist)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        $query = sprintf('UPDATE %s SET task_owner = ? WHERE task_owner = ? AND task_id = ?',
                         $this->_params['table']);
        $values = array($newTasklist, $this->_tasklist, $taskId);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Nag_Driver_sql::move(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the move query. */
        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return true;
    }

    /**
     * Deletes a task from the backend.
     *
     * @param string $taskId  The task to delete.
     */
    function _delete($taskId)
    {
        $this->_connect();

        /* Get the task's details for use later. */
        $task = $this->get($taskId);

        $query = sprintf('DELETE FROM %s WHERE task_owner = ? AND task_id = ?',
                         $this->_params['table']);
        $values = array($this->_tasklist, $taskId);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Nag_Driver_sql::delete(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the delete query. */
        $result = $this->_db->query($query, $values);

        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return true;
    }

    /**
     * Deletes all tasks from the backend.
     */
    function deleteAll()
    {
        $this->_connect();

        $query = sprintf('DELETE FROM %s WHERE task_owner = ?',
                         $this->_params['table']);
        $values = array($this->_tasklist);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Nag_Driver_sql::deleteAll(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the delete query. */
        $result = $this->_db->query($query, $values);

        return is_a($result, 'PEAR_Error') ? $result : true;
    }

    /**
     * Retrieves tasks from the database.
     *
     * @param integer $completed  Which tasks to retrieve (1 = all tasks,
     *                            0 = incomplete tasks, 2 = complete tasks).
     *
     * @return mixed  True on success, PEAR_Error on failure.
     */
    function retrieve($completed = 1)
    {
        /* Make sure we have a valid database connection. */
        $this->_connect();

        /* Build the SQL query. */
        $query = sprintf('SELECT * FROM %s WHERE task_owner = ?',
                         $this->_params['table']);
        $values = array($this->_tasklist);
        if ($completed != 1) {
            $query .= ' AND task_completed = ?';
            $values[] = $completed ? 1 : 0;
        }

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Nag_Driver_sql::retrieve(): %s', $query),
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

        /* Store the retrieved values in a fresh $tasks list. */
        $this->_tasks = array();
        while ($row && !is_a($row, 'PEAR_Error')) {
            /* Add this new task to the $tasks list. */
            $this->_tasks[$row['task_id']] = $this->_buildTask($row);

            /* Advance to the new row in the result set. */
            $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        }
        $result->free();

        return true;
    }

    /**
     * Lists all alarms near $date.
     *
     * @param integer $date  The unix epoch time to check for alarms.
     *
     * @return array  An array of tasks that have alarms that match.
     */
    function listAlarms($date)
    {
        $this->_connect();

        $q  = 'SELECT * FROM ' . $this->_params['table'];
        $q .= ' WHERE task_owner = ?';
        $q .= ' AND task_alarm > 0';
        $q .= ' AND (task_due - (task_alarm * 60) <= ?)';
        $q .= ' AND task_completed = 0';
        $values = array($this->_tasklist, $date);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('SQL alarms list by %s: table = %s; query = "%s"',
                                  Auth::getAuth(), $this->_params['table'], $q),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Run the query. */
        $qr = $this->_db->getAll($q, $values, DB_FETCHMODE_ASSOC);
        if (is_a($qr, 'PEAR_Error')) {
            return $qr;
        }

        $tasks = array();
        foreach ($qr as $row) {
            $tasks[$row['task_id']] = $this->_buildTask($row);
        }
        return $tasks;
    }

    function _buildTask($row)
    {
        /* Make sure tasks always have a UID. */
        if (empty($row['task_uid'])) {
            $row['task_uid'] = $this->generateUID();

            $query = 'UPDATE ' . $this->_params['table'] .
                ' SET task_uid = ?' .
                ' WHERE task_owner = ? AND task_id = ?';
            $values = array($row['task_uid'], $row['task_owner'], $row['task_id']);

            /* Log the query at a DEBUG log level. */
            Horde::logMessage(sprintf('Nag_Driver_sql adding missing UID: %s', $query),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $this->_db->query($query, $values);
        }

        /* Create a new task based on $row's values. */
        return array('tasklist_id' => $row['task_owner'],
                     'task_id' => $row['task_id'],
                     'uid' => String::convertCharset($row['task_uid'], $this->_params['charset']),
                     'name' => String::convertCharset($row['task_name'], $this->_params['charset']),
                     'desc' => String::convertCharset($row['task_desc'], $this->_params['charset']),
                     'category' => String::convertCharset($row['task_category'], $this->_params['charset']),
                     'due' => $row['task_due'],
                     'priority' => $row['task_priority'],
                     'completed' => $row['task_completed'],
                     'alarm' => $row['task_alarm'],
                     'flags' => 0);
    }

    /**
     * Attempts to open a persistent connection to the SQL server.
     *
     * @return boolean    True on success; exits (Horde::fatal()) on error.
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
                $this->_params['table'] = 'nag_tasks';
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

}
