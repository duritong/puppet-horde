<?php

require_once 'Horde/Kolab.php';

/**
 * Horde Nag driver for the Kolab IMAP server.
 *
 * $Horde: nag/lib/Driver/kolab.php,v 1.6.10.11 2007/01/02 13:55:13 jan Exp $
 *
 * Copyright 2004-2007 Horde Project (http://horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Stuart Binge <omicron@mighty.co.za>
 * @package Nag
 */
class Nag_Driver_kolab extends Nag_Driver {

    /**
     * Our Kolab server connection.
     *
     * @var Kolab
     */
    var $_kolab = null;

    /**
     * Constructs a new Kolab storage object.
     *
     * @param string $tasklist  The tasklist to load.
     * @param array $params     A hash containing connection parameters.
     */
    function Nag_Driver_kolab($tasklist, $params = array())
    {
        $this->_tasklist = $tasklist;
        $this->_params = $params;
    }

    function _connect()
    {
        if (isset($this->_kolab)) {
            return true;
        }

        $this->_kolab = new Kolab();

        return $this->_kolab->open($this->_tasklist);
    }

    function _disconnect()
    {
        $this->_kolab->close();
        $this->_kolab = null;
    }

    function _buildTask()
    {
        return array(
            'tasklist_id' => $this->_tasklist,
            'task_id' => $this->_kolab->getUID(),
            'uid' => $this->_kolab->getUID(),
            'name' => $this->_kolab->getStr('summary'),
            'desc' => $this->_kolab->getStr('body'),
            'category' => $this->_kolab->getStr('categories'),
            'due' => Kolab::decodeDateOrDateTime($this->_kolab->getVal('due-date')),
            'priority' => $this->_kolab->getVal('priority'),
            'completed' => Kolab::percentageToBoolean($this->_kolab->getVal('completed')),
            'alarm' => $this->_kolab->getVal('alarm'),
            'flags' => 0,
        );
    }

    /**
     * Retrieves one task from the store.
     *
     * @param string $taskId  The id of the task to retrieve.
     *
     * @return array  The array of task attributes.
     */
    function get($taskId)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $result = $this->_kolab->loadObject($taskId);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_buildTask();
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
        return PEAR::raiseError("Not supported");
    }

    function _setObject($name, $desc, $due = 0, $priority = 0, $completed = 0,
                        $category = 0, $alarm = 0, $uid = null)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        if ($uid !== null) {
            $result = $this->_kolab->loadObject($uid);
        } else {
            $uid = $this->generateUID();
            $result = $this->_kolab->newObject($uid);
        }
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        if ($due == 0) {
            $alarm = 0;
        }

        $this->_kolab->setStr('summary', $name);
        $this->_kolab->setStr('body', $desc);
        $this->_kolab->setStr('categories', $category);
        $this->_kolab->setVal('priority', $priority);
        $this->_kolab->setVal('completed', Kolab::booleanToPercentage($completed));
        $this->_kolab->setVal('due-date', Kolab::encodeDateTime($due));
        $this->_kolab->setVal('alarm', $alarm);

        $result = $this->_kolab->saveObject();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $uid;
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
     * @param integer $alarm      The alarm associated with the task.
     * @param string $uid         A Unique Identifier for the task.
     *
     * @return string  The Nag ID of the new task.
     */
    function _add($name, $desc, $due = 0, $priority = 0, $completed = 0,
                  $category = 0, $alarm = 0, $uid = null)
    {
        return $this->_setObject($name, $desc, $due, $priority, $completed,
                                 $category, $alarm);
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
                     $completed = 0, $category = 0, $alarm = 0)
    {
        $result = $this->_setObject($name, $desc, $due, $priority, $completed,
                                    $category, $alarm, $taskId);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $result == $taskId;
    }

    /**
     * Moves a task to a different tasklist.
     *
     * @param string $taskId       The task to move.
     * @param string $newTasklist  The new tasklist.
     */
    function move($taskId, $newTasklist)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_kolab->moveObject($taskId, $newTasklist);
    }

    /**
     * Deletes a task from the backend.
     *
     * @param string $taskId  The task to delete.
     */
    function _delete($taskId)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_kolab->removeObjects($taskId);
    }

    /**
     * Deletes all tasks from the backend.
     */
    function deleteAll()
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_kolab->removeAllObjects();
    }

    /**
     * Retrieves tasks from the Kolab server.
     *
     * @param integer $completed  Which tasks to retrieve (1 = all tasks,
     *                            0 = incomplete tasks, 2 = complete tasks).
     *
     * @return mixed  True on success, PEAR_Error on failure.
     */
    function retrieve($completed = 1)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $this->_tasks = array();

        $msg_list = $this->_kolab->listObjects();
        if (is_a($msg_list, 'PEAR_Error')) {
            return $msg_list;
        }

        if (empty($msg_list)) {
            return true;
        }

        foreach ($msg_list as $msg) {
            $result = &$this->_kolab->loadObject($msg, true);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
            if (($completed = 0 &&
                 Kolab::percentageToBoolean($this->_kolab->getVal('completed'))) ||
                ($completed == 2 &&
                 !Kolab::percentageToBoolean($this->_kolab->getVal('completed')))) {
                continue;
            }
            $this->_tasks[$this->_kolab->getUID()] = $this->_buildTask();
        }

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
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $tasks = array();

        $msg_list = $this->_kolab->listObjects();
        if (is_a($msg_list, 'PEAR_Error')) {
            return $msg_list;
        }

        if (empty($msg_list)) {
            return $tasks;
        }

        foreach ($msg_list as $msg) {
            $result = &$this->_kolab->loadObject($msg, true);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }

            $task = $this->_buildTask();

            if ($task['alarm'] > 0 && $task['due'] >= time() && $task['due'] - ($task['alarm'] * 60) <= $date) {
                $tasks[$this->_kolab->getUID()] = $task;
            }
        }

        return $tasks;
    }

}
