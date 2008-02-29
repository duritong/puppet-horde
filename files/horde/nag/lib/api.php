<?php
/**
 * Nag external API interface.
 *
 * $Horde: nag/lib/api.php,v 1.100.10.16.2.3 2008/01/09 22:00:57 chuck Exp $
 *
 * This file defines Nag's external API interface. Other applications can
 * interact with Nag through this API.
 *
 * @package Nag
 */

$_services['perms'] = array(
    'args' => array(),
    'type' => '{urn:horde}hashHash'
);

$_services['show'] = array(
    'link' => '%application%/view.php?tasklist=|tasklist|&task=|task|&uid=|uid|',
);

$_services['browse'] = array(
    'args' => array('path' => 'string'),
    'type' => '{urn:horde}hashHash',
);

$_services['addTasklist'] = array(
    'args' => array('name' => 'string', 'description' => 'string'),
    'type' => 'string',
);

$_services['listTasklists'] = array(
    'args' => array('owneronly' => 'boolean', 'permission' => 'int'),
    'type' => '{urn:horde}stringArray',
);

$_services['listTasks'] = array(
    'args' => array('sortby' => 'string', 'sortdir' => 'int'),
    'type' => '{urn:horde}stringArray',
);

$_services['list'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray',
);

$_services['listBy'] = array(
    'args' => array('action' => 'string', 'timestamp' => 'int'),
    'type' => '{urn:horde}stringArray',
);

$_services['getActionTimestamp'] = array(
    'args' => array('uid' => 'string', 'timestamp' => 'int'),
    'type' => 'int',
);

$_services['import'] = array(
    'args' => array('content' => 'string', 'contentType' => 'string', 'tasklist' => 'string'),
    'type' => 'int',
);

$_services['export'] = array(
    'args' => array('uid' => 'string', 'contentType' => '{urn:horde}stringArray'),
    'type' => 'string',
);

$_services['delete'] = array(
    'args' => array('uid' => '{urn:horde}stringArray'),
    'type' => 'boolean',
);

$_services['replace'] = array(
    'args' => array('uid' => 'string', 'content' => 'string', 'contentType' => 'string'),
    'type' => 'boolean',
);


function _nag_perms()
{
    $perms = array();
    $perms['tree']['nag']['max_tasks'] = false;
    $perms['title']['nag:max_tasks'] = _("Maximum Number of Tasks");
    $perms['type']['nag:max_tasks'] = 'int';

    return $perms;
}

function _nag_listTasks($sortby = null, $sortdir = null)
{
    require_once dirname(__FILE__) . '/base.php';
    global $prefs;

    if (!isset($sortby)) {
        $sortby = $prefs->getValue('sortby');
    }
    if (!isset($sortdir)) {
        $sortdir = $prefs->getValue('sortdir');
    }

    return Nag::listTasks($sortby, $sortdir);
}

function _nag_addTasklist($name, $description = '')
{
    if (!Auth::getAuth()) {
        return PEAR::raiseError(_("Permission denied"));
    }

    require_once dirname(__FILE__) . '/base.php';
    global $nag_shares;

    $tasklistId = md5(microtime());
    $tasklist = $nag_shares->newShare($tasklistId);

    if (is_a($tasklist, 'PEAR_Error')) {
        return $tasklist;
    }

    $tasklist->set('name', $name, false);
    $tasklist->set('desc', $description, false);
    $result = $nag_shares->addShare($tasklist);

    if (is_a($result, 'PEAR_Error')) {
        return $result;
    }

    return $tasklistId;
}

function __nag_modified($uid)
{
    $modified = _nag_getActionTimestamp($uid, 'modify');
    if (empty($modified)) {
        $modified = _nag_getActionTimestamp($uid, 'add');
    }
    return $modified;
}

/**
 * Browse through Nag's object tree.
 *
 * @param string $path       The level of the tree to browse.
 * @param array $properties  The item properties to return. Defaults to 'name',
 *                           'icon', and 'browseable'.
 *
 * @return array  The contents of $path
 */
function _nag_browse($path = '', $properties = array())
{
    require_once dirname(__FILE__) . '/base.php';
    global $registry;

    // Default properties.
    if (!$properties) {
        $properties = array('name', 'icon', 'browseable');
    }

    if (substr($path, 0, 3) == 'nag') {
        $path = substr($path, 3);
    }
    if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1);
    }
    if (substr($path, -1) == '/') {
        $path = substr($path, 0, -1);
    }

    if (empty($path)) {
        $tasklists = Nag::listTasklists(false, PERMS_SHOW);
        $results = array();
        foreach ($tasklists as $tasklistId => $tasklist) {
            $results['nag/' . $tasklistId] =
                array('name' => $tasklist->get('name'),
                      'icon' => $registry->getImageDir() . '/nag.png',
                      'browseable' => $tasklist->hasPermission(Auth::getAuth(), PERMS_READ));
        }
        return $results;
    } elseif (array_key_exists($path, Nag::listTasklists(false, PERMS_READ))) {
        /* Create a Nag storage instance. */
        $storage = &Nag_Driver::singleton($path);
        $storage->retrieve();

        $tasks = $storage->listTasks();
        if (is_a($tasks, 'PEAR_Error')) {
            return $tasks;
        }

        $results = array();
        foreach ($tasks as $taskId => $task) {
            $key = 'nag/' . $task['tasklist_id'] . '/' . $taskId;
            if (in_array('name', $properties)) {
                $results[$key]['name'] = $task['name'];
            }
            if (in_array('icon', $properties)) {
                $results[$key]['icon'] = $registry->getImageDir() . '/nag.png';
            }
            if (in_array('browseable', $properties)) {
                $results[$key]['browseable'] = false;
            }
            if (in_array('contenttype', $properties)) {
                $results[$key]['contenttype'] = 'text/x-vtodo';
            }
            if (in_array('contentlength', $properties)) {
                $data = _nag_export($task['uid'], 'text/x-vtodo');
                if (is_a($data, 'PEAR_Error')) {
                    $data = '';
                }
                $results[$key]['contentlength'] = strlen($data);
            }
            if (in_array('modified', $properties)) {
                $results[$key]['modified'] = __nag_modified($task['uid']);
            }
            if (in_array('created', $properties)) {
                $results[$key]['created'] = _nag_getActionTimestamp($task['uid'], 'add');
            }
        }
        return $results;
    } else {
        $parts = explode('/', $path);
        if (count($parts) == 2 &&
            array_key_exists($parts[0], Nag::listTasklists(false, PERMS_READ))) {
            /* Create a Nag storage instance. */
            $storage = &Nag_Driver::singleton($parts[0]);
            if (is_a($storage, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $storage->getMessage()));
            }
            $storage->retrieve();

            $task = $storage->get($parts[1]);
            if (is_a($task, 'PEAR_Error')) {
                return $task;
            }

            $result = array('data' => _nag_export($task['uid'], 'text/x-vtodo'),
                            'mimetype' => 'text/x-vtodo');
            $modified = __nag_modified($task['uid']);
            if (!empty($modified)) {
                $result['mtime'] = $modified;
            }
            return $result;
        }
    }

    return PEAR::raiseError($path . ' does not exist or permission denied');
}

/**
 * @param boolean $owneronly   Only return tasklists that this user owns?
 *                             Defaults to false.
 * @param integer $permission  The permission to filter tasklists by.
 *
 * @return array  The task lists.
 */
function _nag_listTasklists($owneronly, $permission)
{
    require_once dirname(__FILE__) . '/base.php';

    return Nag::listTasklists($owneronly, $permission);
}

/**
 * Returns an array of UIDs for all tasks that the current user is authorized
 * to see.
 *
 * @param variant $tasklist  The tasklist or an array of taskslists to list.
 *
 * @return array             An array of UIDs for all tasks
 *                           the user can access.
 */
function _nag_list($tasklist = null)
{
    require_once dirname(__FILE__) . '/base.php';
    global $conf;

    if (!isset($conf['storage']['driver']) ||
        !isset($conf['storage']['params'])) {
        return PEAR::raiseError('Not configured');
    }

    if ($tasklist === null) {
        $tasklist = Nag::getDefaultTasklist();
    }

    if (!array_key_exists($tasklist,
                          Nag::listTasklists(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $tasks = Nag::listTasks(null, null, null, $tasklist);
    if (is_a($tasks, 'PEAR_Error')) {
        return $tasks;
    }

    $uids = array();
    foreach ($tasks as $task) {
        $uids[] = $task['uid'];
    }

    return $uids;
}

/**
 * Returns an array of UIDs for tasks that have had $action happen since
 * $timestamp.
 *
 * @param string  $action     The action to check for - add, modify, or delete.
 * @param integer $timestamp  The time to start the search.
 * @param string  $tasklist   The tasklist to be used. If 'null', the
 *                            user's default tasklist will be used.
 *
 * @return array  An array of UIDs matching the action and time criteria.
 */
function _nag_listBy($action, $timestamp, $tasklist = null)
{
    require_once dirname(__FILE__) . '/base.php';

    if ($tasklist === null) {
        $tasklist = Nag::getDefaultTasklist();
    }

    if (!array_key_exists($tasklist,
                          Nag::listTasklists(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $history = &Horde_History::singleton();
    $histories = $history->getByTimestamp('>', $timestamp, array(array('op' => '=', 'field' => 'action', 'value' => $action)), 'nag:' . $tasklist);
    if (is_a($histories, 'PEAR_Error')) {
        return $histories;
    }

    // Strip leading nag:username:.
    return preg_replace('/^([^:]*:){2}/', '', array_keys($histories));
}

/**
 * Returns the timestamp of an operation for a given uid an action.
 *
 * @param string $uid      The uid to look for.
 * @param string $action   The action to check for - add, modify, or delete.
 * @param string $tasklist The tasklist to be used. If 'null', the
 *                         user's default tasklist will be used.
 *
 * @return integer  The timestamp for this action.
 */
function _nag_getActionTimestamp($uid, $action, $tasklist = null)
{
    require_once dirname(__FILE__) . '/base.php';

    if ($tasklist === null) {
        $tasklist = Nag::getDefaultTasklist();
    }

    if (!array_key_exists($tasklist,
                          Nag::listTasklists(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $history = &Horde_History::singleton();
    return $history->getActionTimestamp('nag:' . $tasklist . ':' . $uid, $action);
}

/**
 * Imports one or more tasks represented in the specified content type.
 *
 * If a UID is present in the content and the task is already in the
 * database, a replace is performed rather than an add.
 *
 * @param string $content      The content of the task.
 * @param string $contentType  What format is the data in? Currently supports:
 *                             text/x-icalendar
 *                             text/x-vcalendar
 *                             text/x-vtodo
 * @param string $tasklist     The tasklist into which the task will be
 *                             imported.  If 'null', the user's default
 *                             tasklist will be used.
 *
 * @return string  The new UID on one import, an array of UIDs on multiple imports,
 *                 or PEAR_Error on failure.
 */
function _nag_import($content, $contentType, $tasklist = null)
{
    require_once dirname(__FILE__) . '/base.php';

    global $prefs;

    if ($tasklist === null) {
        $tasklist = Nag::getDefaultTasklist(PERMS_EDIT);
    }

    if (!array_key_exists($tasklist, Nag::listTasklists(false, PERMS_EDIT))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    require_once 'Horde/iCalendar.php';

    /* Create a Nag_Driver instance. */
    require_once NAG_BASE . '/lib/Driver.php';

    $storage = &Nag_Driver::singleton($tasklist);

    switch ($contentType) {
    case 'text/x-icalendar':
    case 'text/x-vcalendar':
    case 'text/x-vtodo':
    case 'text/calendar':
        $iCal = &new Horde_iCalendar();
        if (!is_a($content, 'Horde_iCalendar_vtodo')) {
            if (!$iCal->parsevCalendar($content)) {
                return PEAR::raiseError(_("There was an error importing the iCalendar data."));
            }
        } else {
            $iCal->addComponent($content);
        }

        $components = $iCal->getComponents();
        if (count($components) == 0) {
            return PEAR::raiseError(_("No iCalendar data was found."));
        }

        $ids = array();
        foreach ($components as $content) {
            if (is_a($content, 'Horde_iCalendar_vtodo')) {
                $task = $storage->fromiCalendar($content);
                if (isset($task['uid']) && !is_a(($existing = $storage->getByUID($task['uid'])), 'PEAR_Error')) {
                    $taskId = $existing['task_id'];
                    $result = $storage->modify($taskId,
                        isset($task['name']) ? $task['name'] : $existing['name'],
                        isset($task['desc']) ? $task['desc'] : $existing['desc'],
                        isset($task['due']) ? $task['due'] : $existing['due'],
                        isset($task['priority']) ? $task['priority'] : $existing['priority'],
                        isset($task['completed']) ? (int)$task['completed'] : $existing['completed'],
                        isset($task['category']) ? $task['category'] : $existing['category'],
                        isset($task['alarm']) ? $task['alarm'] : $existing['alarm']
                        );

                    if (is_a($result, 'PEAR_Error')) {
                        return $result;
                    }
                    $ids[] = $task['uid'];
                } else {
                    $newTask = $storage->add(
                        isset($task['name']) ? $task['name'] : '',
                        isset($task['desc']) ? $task['desc'] : '',
                        isset($task['due']) ? $task['due'] : 0,
                        isset($task['priority']) ? $task['priority'] : 3,
                        !empty($task['completed']) ? 1 : 0,
                        isset($task['category']) ? $task['category'] : '',
                        isset($task['alarm']) ? $task['alarm'] : 0,
                        isset($task['uid']) ? $task['uid'] : null
                        );

                    if (is_a($newTask, 'PEAR_Error')) {
                        return $newTask;
                    }
                    $ids[] = $newTask[1]; // use UID rather than ID
                }

            }
        }
        if (count($ids) == 0) {
            return PEAR::raiseError(_("No iCalendar data was found."));
        } else if (count($ids) == 1) {
            return $ids[0];
        }
        return $ids;
    }

    return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));

}

/**
 * Exports a task, identified by UID, in the requested content type.
 *
 * @param string $uid         Identify the task to export.
 * @param string $contentType  What format should the data be in?
 *                            A string with one of:
 *                            <pre>
 *                             text/calendar (VCALENDAR 2.0. Recommended as this is specified in rfc2445)
 *                             text/x-vtodo (seems to be used by horde only. Do we need this?)
 *                             text/x-vcalendar (old VCALENDAR 1.0 format. Still in wide use)
 *                             text/x-icalendar
 *                            </pre>
 *
 * @return string  The requested data.
 */
function _nag_export($uid, $contentType)
{
    require_once dirname(__FILE__) . '/base.php';

    $storage = &Nag_Driver::singleton();
    $task = $storage->getByUID($uid);
    if (is_a($task, 'PEAR_Error')) {
        return $task;
    }

    if (!array_key_exists($task['tasklist_id'], Nag::listTasklists(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $version = '2.0';
    switch ($contentType) {
    case 'text/x-vcalendar':
        $version = '1.0';
    case 'text/calendar':
    case 'text/x-icalendar':
    case 'text/x-vtodo':
        require_once dirname(__FILE__) . '/version.php';
        require_once 'Horde/iCalendar.php';

        // Create the new iCalendar container.
        $iCal = &new Horde_iCalendar($version);
        $iCal->setAttribute('PRODID', '-//The Horde Project//Nag ' . NAG_VERSION . '//EN');
        $iCal->setAttribute('METHOD', 'PUBLISH');

        // Create new vTodo object.
        $vTodo = $storage->toiCalendar($task, $iCal);
        $vTodo->setAttribute('VERSION', $version);

        $iCal->addComponent($vTodo);

        return $iCal->exportvCalendar();

    default:
        return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));
    }
}

/**
 * Deletes a task identified by UID.
 *
 * @param string|array $uid  Identify the task to delete, either a single UID
 *                           or an array.
 *
 * @return boolean  Success or failure.
 */
function _nag_delete($uid)
{
    // Handle an arrray of UIDs for convenience of deleting multiple
    // tasks at once.
    if (is_array($uid)) {
        foreach ($uid as $g) {
            $result = _nag_delete($g);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        return true;
    }

    require_once dirname(__FILE__) . '/base.php';

    $storage = &Nag_Driver::singleton();
    $task = $storage->getByUID($uid);
    if (is_a($task, 'PEAR_Error')) {
        return $task;
    }

    if (!array_key_exists($task['tasklist_id'], Nag::listTasklists(false, PERMS_DELETE))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    return $storage->delete($task['task_id']);
}

/**
 * Replaces the task identified by UID with the content represented in the
 * specified content type.
 *
 * If you want to replace multiple tasks with the UID specified in the
 * VCALENDAR data, you may use _nag_import instead. This automatically
 * does a replace if existings UIDs are found.
 *
 *
 * @param string $uid          Identify the task to replace.
 * @param string $content      The content of the task.
 * @param string $contentType  What format is the data in? Currently supports:
 *                             text/x-icalendar
 *                             text/x-vcalendar
 *                             text/x-vtodo
 *                             text/calendar
 *
 * @return boolean  Success or failure.
 */
function _nag_replace($uid, $content, $contentType)
{
    require_once dirname(__FILE__) . '/base.php';

    $storage = &Nag_Driver::singleton();
    $existing = $storage->getByUID($uid);
    if (is_a($existing, 'PEAR_Error')) {
        return $existing;
    }
    $taskId = $existing['task_id'];

    if (!array_key_exists($existing['tasklist_id'], Nag::listTasklists(false, PERMS_EDIT))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    switch ($contentType) {
    case 'text/calendar':
    case 'text/x-icalendar':
    case 'text/x-vcalendar':
    case 'text/x-vtodo':
        if (!is_a($content, 'Horde_iCalendar_vtodo')) {
            require_once 'Horde/iCalendar.php';
            $iCal = &new Horde_iCalendar();
            if (!$iCal->parsevCalendar($content)) {
                return PEAR::raiseError(_("There was an error importing the iCalendar data."));
            }

            $components = $iCal->getComponents();
            switch (count($components)) {
            case 0:
                return PEAR::raiseError(_("No iCalendar data was found."));

            case 1:
                $content = $components[0];
                break;

            default:
                return PEAR::raiseError(_("Multiple iCalendar components found; only one vTodo is supported."));
            }
        }

        $task = $storage->fromiCalendar($content);
        $result = $storage->modify($taskId,
                                    isset($task['name']) ? $task['name'] : $existing['name'],
                                    isset($task['desc']) ? $task['desc'] : $existing['desc'],
                                    isset($task['due']) ? $task['due'] : $existing['due'],
                                    isset($task['priority']) ? $task['priority'] : $existing['priority'],
                                    isset($task['completed']) ? (int)$task['completed'] : $existing['completed'],
                                    isset($task['category']) ? $task['category'] : $existing['category'],
                                    isset($task['alarm']) ? $task['alarm'] : $existing['alarm']
                                    );

        break;

    default:
        return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));
    }

    return $result;
}
