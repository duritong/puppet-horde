<?php
/**
 * Nag_Driver:: defines an API for implementing storage backends for Nag.
 *
 * $Horde: nag/lib/Driver.php,v 1.57.2.14 2006/05/05 16:01:10 jan Exp $
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Jon Parise <jon@horde.org>
 * @since   Nag 0.1
 * @package Nag
 */
class Nag_Driver {

    /**
     * Array holding the current task list. Each array entry is a hash
     * describing a task. The array is indexed by taskId.
     *
     * @var array
     */
    var $_tasks = array();

    /**
     * String containing the current tasklist.
     *
     * @var string
     */
    var $_tasklist = '';

    /**
     * Hash containing connection parameters.
     *
     * @var array
     */
    var $_params = array();

    /**
     * Lists tasks based on the given criteria. All tasks will be
     * returned by default.
     *
     * @return array  Returns a list of the requested tasks.
     */
    function listTasks()
    {
        return $this->_tasks;
    }

    /**
     * List all alarms near $date.
     *
     * @param integer $date  The unix epoch time to check for alarms.
     *
     * @return array  An array of tasks that have alarms that match.
     */
    function listAlarms($date)
    {
        if (!count($this->_tasks)) {
            $ret = $this->retrieve(0);
            if (is_a($ret,'PEAR_Error')) {
                return $ret;
            }
        }
        $now = time();
        $alarms = array();
        foreach ($this->_tasks as $task_id => $task) {
            if ($task['alarm'] &&
                ($task['due'] - ($task['alarm'] * 60)) <= $date) {
                $alarms[$task_id] = $task;
            }
        }
        return $alarms;
    }

    /**
     * Generate a universal / unique identifier for a task. This is
     * NOT something that we expect to be able to parse into a
     * tasklist and a taskId.
     *
     * @return string  A nice unique string (should be 255 chars or less).
     */
    function generateUID()
    {
        return date('YmdHis') . '.' .
            substr(base_convert(microtime(), 10, 36), -16) .
            '@' . $GLOBALS['conf']['server']['name'];
    }

    /**
     * Export this task in iCalendar format.
     *
     * @param array  $task      The task (hash array) to export.
     * @param object $calendar  A Horde_iCalendar object that acts as the container.
     *
     * @return object  Horde_iCalendar_vtodo object for this event.
     */
    function toiCalendar($task, &$calendar)
    {
        global $prefs;

        $vTodo = &Horde_iCalendar::newComponent('vtodo', $calendar);

        $vTodo->setAttribute('UID', $task['uid']);

        if (isset($task['name'])) {
            $vTodo->setAttribute('SUMMARY', $task['name']);
        }

        if (isset($task['desc'])) {
            $vTodo->setAttribute('DESCRIPTION', $task['desc']);
        }

        if (isset($task['priority'])) {
            $vTodo->setAttribute('PRIORITY', $task['priority']);
        }

        if (!empty($task['due']) && empty($task['completed'])) {
            $vTodo->setAttribute('DUE', $task['due']);
            if (!empty($task['alarm'])) {
                $vTodo->setAttribute('AALARM', $task['due'] - $task['alarm'] * 60);
            }
        }

        if (!empty($task['completed'])) {
            $vTodo->setAttribute('STATUS', 'COMPLETED');

            /* Some applications won't consider a task completed
             * unless it has a COMPLETED: date. Fill in today's date
             * for now, as we don't currently track completion date in
             * Nag. */
            $vTodo->setAttribute('COMPLETED', time());
        } else {
            // status values are defined in rfc2445
            $vTodo->setAttribute('STATUS', 'NEEDS-ACTION');
        }

        if (!empty($task['category'])) {
            $vTodo->setAttribute('CATEGORIES', $task['category']);
        }

        /* Get the task's history. */
        $history = &Horde_History::singleton();
        $log = $history->getHistory('nag:' . $task['tasklist_id'] . ':' . $task['uid']);
        if ($log && !is_a($log, 'PEAR_Error')) {
            foreach ($log->getData() as $entry) {
                switch ($entry['action']) {
                case 'add':
                    $created = $entry['ts'];
                    break;

                case 'modify':
                    $modified = $entry['ts'];
                    break;
                }
            }
        }
        if (!empty($created)) {
            $vTodo->setAttribute('DCREATED', $created);
        }
        if (!empty($modified)) {
            $vTodo->setAttribute('LAST-MODIFIED', $modified);
        }

        return $vTodo;
    }

    /**
     * Create a task (hash array) from a Horde_iCalendar_vtodo object.
     *
     * @param Horde_iCalendar_vtodo $vTodo  The iCalendar data to update from.
     *
     * @return array memo (hash array) created from vtodo
     */
    function fromiCalendar($vTodo)
    {
        $r = array();

        $name = $vTodo->getAttribute('SUMMARY');
        if (!is_array($name) && !is_a($name, 'PEAR_Error')) {
            $r['name'] = $name;
        }

        $uid = $vTodo->getAttribute('UID');
        if (!is_array($uid) && !is_a($uid, 'PEAR_Error')) {
            $r['uid'] = $uid;
        }

        $due = $vTodo->getAttribute('DUE');
        if (!is_array($due) && !is_a($due, 'PEAR_Error') && !empty($due)) {
            $r['due'] = $due;
        }

        $alarm = $vTodo->getAttribute('AALARM');
        if (!is_array($alarm) && !is_a($alarm, 'PEAR_Error') &&
            !empty($alarm) && !empty($r['due'])) {
            $r['alarm'] = intval(($r['due'] - $alarm) / 60);
        }

        $desc = $vTodo->getAttribute('DESCRIPTION');
        if (!is_array($desc) && !is_a($desc, 'PEAR_Error')) {
            $r['desc'] = $desc;
        }

        $priority = $vTodo->getAttribute('PRIORITY');
        if (!is_array($priority) && !is_a($priority, 'PEAR_Error')) {
            $r['priority'] = $priority;
        }

        $cat = $vTodo->getAttribute('CATEGORIES');
        if (!is_array($cat) && !is_a($cat, 'PEAR_Error')) {
            $r['category'] = $cat;
        }

        $status = $vTodo->getAttribute('STATUS');
        if (!is_array($status) && !is_a($status, 'PEAR_Error')) {
            if (!strcasecmp($status, 'COMPLETED')) {
                $r['completed'] = 1;
            } else {
                $r['completed'] = 0;
            }
        }

        return $r;
    }

    /**
     * Attempts to return a concrete Nag_Driver instance based on $driver.
     *
     * @param string    $tasklist   The name of the tasklist to load.
     *
     * @param string    $driver     The type of concrete Nag_Driver subclass
     *                              to return.  The is based on the storage
     *                              driver ($driver).  The code is dynamically
     *                              included.
     *
     * @param array     $params     (optional) A hash containing any additional
     *                              configuration or connection parameters a
     *                              subclass might need.
     *
     * @return mixed    The newly created concrete Nag_Driver instance, or
     *                  false on an error.
     */
    function &factory($tasklist = '', $driver = null, $params = null)
    {
        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['storage']['driver'];
        }

        $driver = basename($driver);

        if (is_null($params)) {
            $params = Horde::getDriverConfig('storage', $driver);
        }

        require_once dirname(__FILE__) . '/Driver/' . $driver . '.php';
        $class = 'Nag_Driver_' . $driver;
        if (class_exists($class)) {
            $nag = &new $class($tasklist, $params);
        } else {
            $nag = false;
        }

        return $nag;
    }

    /**
     * Attempts to return a reference to a concrete Nag_Driver
     * instance based on $driver. It will only create a new instance
     * if no Nag_Driver instance with the same parameters currently
     * exists.
     *
     * This should be used if multiple storage sources are required.
     *
     * This method must be invoked as: $var = &Nag_Driver::singleton()
     *
     * @param string    $tasklist   The name of the tasklist to load.
     *
     * @param string    $driver     The type of concrete Nag_Driver subclass
     *                              to return.  The is based on the storage
     *                              driver ($driver).  The code is dynamically
     *                              included.
     *
     * @param array     $params     (optional) A hash containing any additional
     *                              configuration or connection parameters a
     *                              subclass might need.
     *
     * @return mixed    The created concrete Nag_Driver instance, or false
     *                  on error.
     */
    function &singleton($tasklist = '', $driver = null, $params = null)
    {
        static $instances;

        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['storage']['driver'];
        }

        if (is_null($params)) {
            $params = Horde::getDriverConfig('storage', $driver);
        }

        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($tasklist, $driver, $params));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &Nag_Driver::factory($tasklist, $driver, $params);
        }

        return $instances[$signature];
    }

    /**
     * Adds a task and handles notification.
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
     * @return array              array(ID,UID) of new task
     */

    function add($name, $desc, $due = 0, $priority = 0, $completed = 0,
                 $category = '', $alarm = 0, $uid = null)
    {
        if ($uid === null) {
            $uid = $this->generateUID();
        }

        $taskId = $this->_add($name, $desc, $due, $priority, $completed,
                              $category, $alarm, $uid);
        if (is_a($taskId, 'PEAR_Error')) {
            return $taskId;
        }

        /* Log the creation of this item in the history log. */
        $history = &Horde_History::singleton();
        $history->log('nag:' . $this->_tasklist . ':' . $uid, array('action' => 'add'), true);

        /* Notify users about the new event. */
        $result = Nag::sendNotification('add', $this->_tasklist, $name, $desc, $due, $priority);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
        }

        return array($taskId, $uid);
    }

    /**
     * Modifies an existing task and handles notification.
     *
     * @param string $taskId      The task to modify.
     * @param string $name        The name (short) of the task.
     * @param string $desc        The description (long) of the task.
     * @param integer $due        The due date of the task.
     * @param integer $priority   The priority of the task.
     * @param integer $completed  The completion state of the task.
     * @param string $category    The category of the task.
     * @param integer $alarm      The alarm associated with the task.
     */
    function modify($taskId, $name, $desc, $due = 0, $priority = 0,
                    $completed = 0, $category = '', $alarm = 0)
    {
        $modify = $this->_modify($taskId, $name, $desc, $due, $priority,
                                 $completed, $category, $alarm);
        if (is_a($modify, 'PEAR_Error')) {
            return $modify;
        }

        /* Log the modification of this item in the history log. */
        $task = $this->get($taskId);
        if (!empty($task['uid'])) {
            $history = &Horde_History::singleton();
            $history->log('nag:' . $this->_tasklist . ':' . $task['uid'], array('action' => 'modify'), true);
        }

        /* Notify users about the new event. */
        $result = Nag::sendNotification('edit', $this->_tasklist, $name, $desc, $due, $priority);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
        }

        return true;
    }

    /**
     * Deletes a task and handles notification.
     *
     * @param string $taskId  The task to delete.
     */
    function delete($taskId)
    {
        /* Get the task's details for use later. */
        $task = $this->get($taskId);

        $delete = $this->_delete($taskId);
        if (is_a($delete, 'PEAR_Error')) {
            return $delete;
        }

        /* Log the deletion of this item in the history log. */
        if (!empty($task['uid'])) {
            $history = &Horde_History::singleton();
            $history->log('nag:' . $this->_tasklist . ':' . $task['uid'], array('action' => 'delete'), true);
        }

        /* Notify users about the new event. */
        $result = Nag::sendNotification('delete', $this->_tasklist, $task['name'], $task['desc'], $task['due'], $task['priority']);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
        }

        return true;
    }

}
