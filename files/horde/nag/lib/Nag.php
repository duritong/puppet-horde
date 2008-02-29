<?php

/**
 * Sort by task name.
 */
define('NAG_SORT_NAME', 'name');

/**
 * Sort by priority.
 */
define('NAG_SORT_PRIORITY', 'priority');

/**
 * Sort by due date.
 */
define('NAG_SORT_DUE', 'due');

/**
 * Sort by completion.
 */
define('NAG_SORT_COMPLETION', 'completed');

/**
 * Sort by category.
 */
define('NAG_SORT_CATEGORY', 'category');

/**
 * Sort by owner.
 */
define('NAG_SORT_OWNER', 'tasklist_id');

/**
 * Sort in ascending order.
 */
define('NAG_SORT_ASCEND', 0);

/**
 * Sort in descending order.
 */
define('NAG_SORT_DESCEND', 1);

/**
 * Nag Base Class.
 *
 * $Horde: nag/lib/Nag.php,v 1.124.2.17 2006/08/03 13:33:45 jan Exp $
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Jon Parise <jon@horde.org>
 * @package Nag
 */
class Nag {

    function secondsToString($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = ($seconds / 60) % 60;

        if ($hours > 1) {
            if ($minutes == 0) {
                return sprintf(_("%d hours"), $hours);
            } elseif ($minutes == 1) {
                return sprintf(_("%d hours, %d minute"), $hours, $minutes);
            } else {
                return sprintf(_("%d hours, %d minutes"), $hours, $minutes);
            }
        } elseif ($hours == 1) {
            if ($minutes == 0) {
                return sprintf(_("%d hour"), $hours);
            } elseif ($minutes == 1) {
                return sprintf(_("%d hour, %d minute"), $hours, $minutes);
            } else {
                return sprintf(_("%d hour, %d minutes"), $hours, $minutes);
            }
        } else {
            if ($minutes == 0) {
                return _("no time");
            } elseif ($minutes == 1) {
                return sprintf(_("%d minute"), $minutes);
            } else {
                return sprintf(_("%d minutes"), $minutes);
            }
        }
    }

    /**
     * Retrieves the current user's task list from storage.
     * This function will also sort the resulting list, if requested.
     *
     * @param Nag_Driver $storage  The current storage object.
     * @param constant $sortby     The field by which to sort
     *                             (NAG_SORT_PRIORITY, NAG_SORT_NAME
     *                             NAG_SORT_DUE, NAG_SORT_COMPLETION).
     * @param constant $sortdir    The direction by which to sort
     *                             (NAG_SORT_ASC, NAG_SORT_DESC).
     * @param constant $altsortby  The secondary sort field.
     * @param array $tasklists     An array of tasklist to display or
     *                             null/empty to display taskslists
     *                             $GLOBALS['display_tasklists'].
     * @param integer $completed   Which tasks to retrieve (1 = all tasks,
     *                             0 = incomplete tasks, 2 = complete tasks).
     *
     * @return array  A list of the requested tasks.
     *
     * @see Nag_Driver::listTasks()
     */
    function listTasks($sortby = null,
                       $sortdir = null,
                       $altsortby = null,
                       $tasklists = null,
                       $completed = null)
    {
        global $prefs, $registry;

        if (is_null($sortby)) {
            $sortby = $prefs->getValue('sortby');
        }
        if (is_null($sortdir)) {
            $sortdir = $prefs->getValue('sortdir');
        }
        if (is_null($altsortby)) {
            $altsortby = $prefs->getValue('altsortby');
        }
        if ($completed === null) {
            $completed = $prefs->getValue('show_completed');
        }

        if (is_null($tasklists)) {
            $tasklists = $GLOBALS['display_tasklists'];
        }
        if (!is_array($tasklists)) {
            $tasklists = array($tasklists);
        }
        
        /* Sorting criteria for the task list. */
        $sort_functions = array(
            NAG_SORT_PRIORITY => 'ByPriority',
            NAG_SORT_NAME => 'ByName',
            NAG_SORT_CATEGORY => 'ByCategory',
            NAG_SORT_DUE => 'ByDue',
            NAG_SORT_COMPLETION => 'ByCompletion',
            NAG_SORT_OWNER => 'ByOwner'
        );

        $tasks = array();
        $view_url = Horde::applicationUrl('view.php');
        $task_url = Horde::applicationUrl('task.php');
        foreach ($tasklists as $tasklist) {
            /* Create a Nag storage instance. */
            $storage = &Nag_Driver::singleton($tasklist);
            $storage->retrieve($completed);

            /* Retrieve the tasklist from storage. */
            $newtasks = $storage->listTasks();
            if (is_a($newtasks, 'PEAR_Error')) {
                return $newtasks;
            }

            $view_url_list = Util::addParameter($view_url, 'tasklist', $tasklist);
            $task_url_list = Util::addParameter($task_url, 'tasklist', $tasklist);
            foreach ($newtasks as $taskID => $task) {
                $newtasks[$taskID]['view_link'] = Util::addParameter($view_url_list, 'task', $task['task_id']);

                $task_url_task = Util::addParameter($task_url_list, 'task', $task['task_id']);
                $newtasks[$taskID]['complete_link'] = Util::addParameter($task_url_task, 'actionID', 'complete_task');
                $newtasks[$taskID]['edit_link'] = Util::addParameter($task_url_task, 'actionID', 'modify_task');
                $newtasks[$taskID]['delete_link'] = Util::addParameter($task_url_task, 'actionID', 'delete_tasks');
            }

            $tasks = array_merge($tasks, $newtasks);
        }

        /* We look for registered apis that support listAs(taskHash). */
        $apps = @unserialize($prefs->getValue('show_external'));
        if (is_array($apps)) {
            foreach ($apps as $app) {
                if ($app != 'nag' &&
                    $registry->hasMethod('getListTypes', $app)) {
                    $types = $registry->callByPackage($app, 'getListTypes');
                    if (!empty($types['taskHash'])) {
                        $newtasks = $registry->callByPackage($app, 'listAs', array('taskHash'));
                        if (is_a($newtasks, 'PEAR_Error')) {
                            Horde::logMessage($newtasks, __FILE__, __LINE__, PEAR_LOG_ERR);
                        } else {
                            $tasks = array_merge($tasks, $newtasks);
                        }
                    }
                }
            }
        }

        /* Sort the array if we have a sort function defined for this
         * field. */
        if (isset($sort_functions[$sortby])) {
            $prefix = ($sortdir == NAG_SORT_DESCEND) ? '_rsort' : '_sort';
            uasort($tasks, array('Nag', $prefix . $sort_functions[$sortby]));
            if (isset($sort_functions[$altsortby]) && $altsortby !== $sortby) {
                $task_buckets = array();
                foreach ($tasks as $id => $task) {
                    if (!isset($task_buckets[$task[$sortby]])) {
                        $task_buckets[$task[$sortby]] = array();
                    }
                    $task_buckets[$task[$sortby]][] = $task;
                }
                $tasks = array();
                foreach ($task_buckets as $task_bucket) {
                    uasort($task_bucket, array('Nag', $prefix . $sort_functions[$altsortby]));
                    $tasks = array_merge($tasks, $task_bucket);
                }
            }
        }

        return $tasks;
    }

    function getTask($tasklist, $task)
    {
        $storage = &Nag_Driver::singleton($tasklist);
        return $storage->get($task);
    }

    /**
     * Returns the number of taks in task lists that the current user owns.
     *
     * @return integer  The number of tasks that the user owns.
     */
    function countTasks()
    {
        static $count;
        if (isset($count)) {
            return $count;
        }

        $tasklists = Nag::listTasklists(true, PERMS_ALL);

        $count = 0;
        foreach (array_keys($tasklists) as $tasklist) {
            /* Create a Nag storage instance. */
            $storage = &Nag_Driver::singleton($tasklist);
            $storage->retrieve();

            /* Retrieve the task list from storage. */
            $count += count($storage->listTasks());
        }

        return $count;
    }

    /**
     * Returns all the alarms active right on $date.
     *
     * @param object $date  The start of the time range.
     *
     * @return array  The alarms (alarmId) active on $date.
     */
    function listAlarms($date)
    {
        $tasks = array();

        foreach ($GLOBALS['display_tasklists'] as $tasklist) {
            /* Create a Nag storage instance. */
            $storage = &Nag_Driver::singleton($tasklist);

            /* Retrieve the alarms for the task list. */
            $newtasks = $storage->listAlarms($date);
            if (is_a($newtasks, 'PEAR_Error')) {
                return $newtasks;
            }

            /* Don't show an alarm for complete tasks. */
            foreach ($newtasks as $taskID => $task) {
                if (!empty($task['completed'])) {
                    unset($newtasks[$taskID]);
                }
            }

            $tasks = array_merge($tasks, $newtasks);
        }

        return $tasks;
    }

    /**
     * Lists all task lists a user has access to.
     *
     * @param boolean $owneronly  Only return tasklists that this user owns?
     *                            Defaults to false.
     * @param integer $permission The permission to filter tasklists by.
     *
     * @return array  The task lists.
     */
    function listTasklists($owneronly = false, $permission = PERMS_SHOW)
    {
        $tasklists = $GLOBALS['nag_shares']->listShares(Auth::getAuth(), $permission, $owneronly ? Auth::getAuth() : null);
        if (is_a($tasklists, 'PEAR_Error')) {
            Horde::logMessage($tasklists, __FILE__, __LINE__, PEAR_LOG_ERR);
            return array();
        }

        return $tasklists;
    }

    /**
     * Returns the default tasklist for the current user at the specified
     * permissions level.
     */
    function getDefaultTasklist($permission = PERMS_SHOW)
    {
        global $prefs;

        $default_tasklist = $prefs->getValue('default_tasklist');
        $tasklists = Nag::listTasklists(false, $permission);

        if (isset($tasklists[$default_tasklist])) {
            return $default_tasklist;
        } elseif ($prefs->isLocked('default_tasklist')) {
            return '';
        } elseif (count($tasklists)) {
            return key($tasklists);
        }

        return false;
    }

    /**
     * Builds the HTML for a priority selection widget.
     *
     * @param string $name       The name of the widget.
     * @param integer $selected  The default selected priority.
     *
     * @return string  The HTML <select> widget.
     */
    function buildPriorityWidget($name, $selected = -1)
    {
        $descs = array(1 => _("(highest)"), 5 => _("(lowest)"));

        $html = "<select id=\"$name\" name=\"$name\">";
        for ($priority = 1; $priority <= 5; $priority++) {
            $html .= "<option value=\"$priority\"";
            $html .= ($priority == $selected) ? ' selected="selected">' : '>';
            $html .= $priority . ' ' . @$descs[$priority] . '</option>';
        }
        $html .= "</select>\n";

        return $html;
    }

    /**
     * Builds the HTML for a task completion state widget.
     *
     * @param string $name      The name of the widget.
     * @param integer $checked  The default completion state.
     *
     * @return string  HTML for a checkbox representing the completion state.
     */
    function buildCompletionWidget($name, $checked = 0)
    {
        $name = htmlspecialchars($name);
        return "<input type=\"checkbox\" id=\"$name\" name=\"$name\"" .
            ($checked ? ' checked="checked"' : '') . ' />';
    }

    /**
     * Formats the given Unix-style date string.
     *
     * @param string $unixdate  The Unix-style date value to format.
     *
     * @return string  The formatted due date string.
     */
    function formatDate($unixdate = '')
    {
        global $prefs;

        if (empty($unixdate)) {
            return '';
        }

        return sprintf(_("%s at %s"),
                       strftime($prefs->getValue('date_format'), $unixdate),
                       strftime($prefs->getValue('twentyFour') ? '%H:%M' : '%I:%M %p', $unixdate));
    }

    /**
     * Returns the string representation of the given completion status.
     *
     * @param int $completed  The completion value.
     *
     * @return string  The HTML representation of $completed.
     */
    function formatCompletion($completed)
    {
        return $completed ?
            Horde::img('checked.png', _("Completed")) :
            Horde::img('unchecked.png', _("Not Completed"));
    }

    /**
     * Returns a colored representation of a priority.
     *
     * @param int $priority  The priority level.
     *
     * @return string  The HTML representation of $priority.
     */
    function formatPriority($priority)
    {
        return '<span class="pri-' . (int)$priority . '">' . (int)$priority .
            '</span>';
    }

    /**
     * Returns the string matching the given alarm value.
     *
     * @param int $value  The alarm value in minutes.
     *
     * @return string  The formatted alarm string.
     */
    function formatAlarm($value)
    {
        if ($value) {
            if ($value % 10080 == 0) {
                $alarm_value = $value / 10080;
                $alarm_unit = _("Week(s)");
            } elseif ($value % 1440 == 0) {
                $alarm_value = $value / 1440;
                $alarm_unit = _("Day(s)");
            } elseif ($value % 60 == 0) {
                $alarm_value = $value / 60;
                $alarm_unit = _("Hour(s)");
            } else {
                $alarm_value = $value;
                $alarm_unit = _("Minute(s)");
            }
            $alarm_text = "$alarm_value $alarm_unit";
        } else {
            $alarm_text = _("None");
        }
        return $alarm_text;
    }

    /**
     * Returns the specified permission for the current user.
     *
     * @since Nag 2.1
     *
     * @param string $permission  A permission, currently only 'max_tasks'.
     *
     * @return mixed  The value of the specified permission.
     */
    function hasPermission($permission)
    {
        global $perms;

        if (!$perms->exists('nag:' . $permission)) {
            return true;
        }

        $allowed = $perms->getPermissions('nag:' . $permission);
        if (is_array($allowed)) {
            switch ($permission) {
            case 'max_tasks':
                $allowed = array_reduce($allowed, create_function('$a, $b', 'return max($a, $b);'), 0);
                break;
            }
        }

        return $allowed;
    }

    /**
     * Build Nag's list of menu items.
     */
    function getMenu($returnType = 'object')
    {
        global $conf, $registry, $print_link;

        require_once 'Horde/Menu.php';

        $menu = &new Menu();
        $menu->add(Horde::applicationUrl('list.php'), _("_List Tasks"), 'nag.png', null, null, null, basename($_SERVER['PHP_SELF']) == 'index.php' ? 'current' : null);
        if (Nag::getDefaultTasklist(PERMS_EDIT) &&
            (!empty($conf['hooks']['permsdenied']) ||
             Nag::hasPermission('max_tasks') === true ||
             Nag::hasPermission('max_tasks') > Nag::countTasks())) {
            $menu->add(Horde::applicationUrl(Util::addParameter('task.php', 'actionID', 'add_task')), _("_New Task"), 'add.png', null, null, null, Util::getFormData('task') ? '__noselection' : null);
        }
        $menu->add(Horde::applicationUrl('search.php'), _("_Search"), 'search.png', $registry->getImageDir('horde'));

        if (Auth::getAuth()) {
            $menu->add(Horde::applicationUrl('tasklists.php'), _("_My Tasklists"), 'tasklists.png');
        }

        /* Import/Export. */
        if ($conf['menu']['import_export']) {
            $menu->add(Horde::applicationUrl('data.php'), _("_Import/Export"), 'data.png', $registry->getImageDir('horde'));
        }

        /* Print. */
        if ($conf['menu']['print'] && isset($print_link)) {
            $menu->add($print_link, _("_Print"), 'print.png', $registry->getImageDir('horde'), '_blank', 'popup(this.href); return false;');
        }

        if ($returnType == 'object') {
            return $menu;
        } else {
            return $menu->render();
        }
    }

    function status()
    {
        global $notification;

        // Get any alarms in the next hour.
        $_now = time();
        $_alarmList = Nag::listAlarms($_now);
        if (is_a($_alarmList, 'PEAR_Error')) {
            Horde::logMessage($_alarmList, __FILE__, __LINE__, PEAR_LOG_ERR);
            $notification->push($_alarmList, 'horde.error');
        } else {
            $_messages = array();
            foreach ($_alarmList as $_task) {
                $differential = $_task['due'] - $_now;
                $_key = $differential;
                while (isset($_messages[$_key])) {
                    $_key++;
                }
                if ($differential >= -60 && $differential < 60) {
                    $_messages[$_key] = array(sprintf(_("%s is due now."), $_task['name']), 'nag.alarm');
                } elseif ($differential >= 60) {
                    $_messages[$_key] = array(sprintf(_("%s is due in %s"), $_task['name'],
                                                      Nag::secondsToString($differential)), 'nag.alarm');
                }
            }

            ksort($_messages);
            foreach ($_messages as $message) {
                $notification->push($message[0], $message[1]);
            }
        }

        // Check here for guest task lists so that we don't get multiple
        // messages after redirects, etc.
        if (!Auth::getAuth() && !count(Nag::listTasklists())) {
            $notification->push(_("No task lists are available to guests."));
        }

        // Display all notifications.
        $notification->notify(array('listeners' => 'status'));
    }

    /**
     * Sends email notifications that a task has been added, edited, or
     * deleted to users that want such notifications.
     *
     * @param string $action     The event action. One of "add", "edit", or
     *                           "delete".
     * @param string $tasklist   The tasklist of the task we are dealing with.
     * @param string $name       The name (short) of the task.
     * @param string $desc       The description (long) of the task.
     * @param integer $due       The due date of the task.
     * @param integer $priority  The priority of the task.
     */
    function sendNotification($action, $tasklist, $name, $desc, $due, $priority)
    {
        global $conf;

        switch ($action) {
        case 'add':
            $subject = _("Task added:");
            $notification_message = _("You requested to be notified when tasks are added to your tasklists.") . "\n\n" . _("The task \"%s\" has been added to \"%s\" tasklist, with a due date of: %s.");
            break;

        case 'edit':
            $subject = _("Task modified:");
            $notification_message = _("You requested to be notified when tasks are edited on your tasklists.") . "\n\n" . _("The task \"%s\" has been edited on \"%s\" tasklist, with a due date of: %s.");
            break;

        case 'delete':
            $subject = _("Task deleted:");
            $notification_message = _("You requested to be notified when tasks are deleted from your tasklists.") . "\n\n" . _("The task \"%s\" has been deleted from \"%s\" tasklist, with a due date of: %s.");
            break;

        default:
            return PEAR::raiseError('Unknown event action: ' . $action);
        }

        require_once 'Horde/Group.php';
        require_once 'Horde/Identity.php';
        require_once 'Horde/MIME.php';
        require_once 'Horde/MIME/Headers.php';
        require_once 'Horde/MIME/Message.php';

        $share = &$GLOBALS['nag_shares']->getShare($tasklist);
        if (is_a($share, 'PEAR_Error')) {
            return $share;
        }

        $groups = &Group::singleton();
        $recipients = array();
        $identity = &Identity::singleton();
        $from = $identity->getDefaultFromAddress(true);

        $owner = $share->get('owner');
        if (Nag::_notificationPref($owner, 'owner')) {
            $recipients[$owner] = true;
        }

        foreach ($share->listUsers(PERMS_READ) as $user) {
            if (!isset($recipients[$user])) {
                $recipients[$user] = Nag::_notificationPref($user, 'read', $tasklist);
            }
        }
        foreach ($share->listGroups(PERMS_READ) as $group) {
            $group = $groups->getGroupById($group);
            if (is_a($group, 'PEAR_Error')) {
                continue;
            }
            $group_users = $group->listAllUsers();
            if (is_a($group_users, 'PEAR_Error')) {
                Horde::logMessage($group_users, __FILE__, __LINE__, PEAR_LOG_ERR);
                continue;
            }
            foreach ($group_users as $user) {
                if (!isset($recipients[$user])) {
                    $recipients[$user] = Nag::_notificationPref($user, 'read', $tasklist);
                }
            }
        }

        $addresses = array();
        foreach ($recipients as $user => $send) {
            if ($send) {
                $identity = &Identity::singleton('none', $user);
                $email = $identity->getValue('from_addr');
                if (strstr($email, '@')) {
                    list($mailbox, $host) = explode('@', $email);
                    $addresses[] = MIME::rfc822WriteAddress($mailbox, $host, $identity->getValue('fullname'));
                }
            }
        }

        if (!count($addresses)) {
            return;
        }

        $msg_headers = &new MIME_Headers();
        $msg_headers->addMessageIdHeader();
        $msg_headers->addAgentHeader();
        $msg_headers->addHeader('Date', date('r'));
        $msg_headers->addHeader('From', $from);
        $msg_headers->addHeader('Subject', $subject . ' ' . $name);

        $message = "\n" . sprintf($notification_message, $name, $share->get('name'), $due ? strftime('%x %X', $due) : 'no due date' . "\n\n" . $desc);

        $mime = &new MIME_Message();
        $body = &new MIME_Part('text/plain', String::wrap($message, 76, "\n"), NLS::getCharset());

        $mime->addPart($body);
        $msg_headers->addMIMEHeaders($mime);

        $mail_driver = $conf['mailer']['type'];
        $mail_params = $conf['mailer']['params'];
        if ($mail_driver == 'smtp' && $mail_params['auth'] &&
            empty($mail_params['username'])) {
            $mail_params['username'] = Auth::getAuth();
            $mail_params['password'] = Auth::getCredential('password');
        }

        Horde::logMessage(sprintf('Sending event notifications for %s to %s', $name, implode(', ', $addresses)), __FILE__, __LINE__, PEAR_LOG_DEBUG);
        return $mime->send(implode(', ', $addresses), $msg_headers, $mail_driver, $mail_params);
    }

    /**
     * Returns whether a user wants email notifications for a tasklist.
     *
     * @access private
     *
     * @todo This method is causing a memory leak somewhere, noticeable if
     *       importing a large amount of events.
     *
     * @param string $user      A user name.
     * @param string $mode      The check "mode". If "owner", the method checks
     *                          if the user wants notifications only for
     *                          tasklists he owns. If "read", the method checks
     *                          if the user wants notifications for all
     *                          tasklists he has read access to, or only for
     *                          shown tasklists and the specified tasklist is
     *                          currently shown.
     * @param string $tasklist  The name of the tasklist if mode is "read".
     *
     * @return boolean  True if the user wants notifications for the tasklist.
     */
    function _notificationPref($user, $mode, $tasklist = null)
    {
        $prefs = &Prefs::singleton($GLOBALS['conf']['prefs']['driver'],
                                   'nag', $user, '', null,
                                   false);
        $prefs->retrieve();

        $notification = $prefs->getValue('task_notification');
        switch ($notification) {
        case 'owner':
            return $mode == 'owner';
        case 'read':
            return $mode == 'read';
        case 'show':
            if ($mode == 'read') {
                $display_tasklists = unserialize($prefs->getValue('display_tasklists'));
                return in_array($tasklist, $display_tasklists);
            }
        }

        return false;
    }

    /**
     * Comparison function for sorting tasks by priority.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  1 if task one is greater, -1 if task two is greater;
     *                  0 if they are equal.
     */
    function _sortByPriority($a, $b)
    {
        if ($a['priority'] == $b['priority']) {
            return 0;
        }
        return ($a['priority'] > $b['priority']) ? 1 : -1;
    }

    /**
     * Comparison function for reverse sorting tasks by priority.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  -1 if task one is greater, 1 if task two is greater;
     *                  0 if they are equal.
     */
    function _rsortByPriority($a, $b)
    {
        if ($a['priority'] == $b['priority']) {
            return 0;
        }
        return ($a['priority'] > $b['priority']) ? -1 : 1;
    }

    /**
     * Comparison function for sorting tasks by name.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  1 if task one is greater, -1 if task two is greater;
     *                  0 if they are equal.
     */
    function _sortByName($a, $b)
    {
        return strcasecmp($a['name'], $b['name']);
    }

    /**
     * Comparison function for reverse sorting tasks by name.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  -1 if task one is greater, 1 if task two is greater;
     *                  0 if they are equal.
     */
    function _rsortByName($a, $b)
    {
        return strcasecmp($b['name'], $a['name']);
    }

    /**
     * Comparison function for sorting tasks by category.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  1 if task one is greater, -1 if task two is greater;
     *                  0 if they are equal.
     */
    function _sortByCategory($a, $b)
    {
        return strcasecmp($a['category'] ? $a['category'] : _("Unfiled"),
                          $b['category'] ? $b['category'] : _("Unfiled"));
    }

    /**
     * Comparison function for reverse sorting tasks by category.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  -1 if task one is greater, 1 if task two is greater;
     *                  0 if they are equal.
     */
    function _rsortByCategory($a, $b)
    {
        return strcasecmp($b['category'] ? $b['category'] : _("Unfiled"),
                          $a['category'] ? $a['category'] : _("Unfiled"));
    }

    /**
     * Comparison function for sorting tasks by due date.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  1 if task one is greater, -1 if task two is greater;
     *                  0 if they are equal.
     */
    function _sortByDue($a, $b)
    {
        if ($a['due'] == $b['due']) {
            return 0;
        }

        // Treat empty due dates as farthest into the future.
        if ($a['due'] == 0) {
            return 1;
        }
        if ($b['due'] == 0) {
            return -1;
        }

        return ($a['due'] > $b['due']) ? 1 : -1;
    }

    /**
     * Comparison function for reverse sorting tasks by due date.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  -1 if task one is greater, 1 if task two is greater,
     *                  0 if they are equal.
     */
    function _rsortByDue($a, $b)
    {
        if ($a['due'] == $b['due']) {
            return 0;
        }

        // Treat empty due dates as farthest into the future.
        if ($a['due'] == 0) {
            return -1;
        }
        if ($b['due'] == 0) {
            return 1;
        }

        return ($a['due'] < $b['due']) ? 1 : -1;
    }

    /**
     * Comparison function for sorting tasks by completion status.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  1 if task one is greater, -1 if task two is greater;
     *                  0 if they are equal.
     */
    function _sortByCompletion($a, $b)
    {
        if ($a['completed'] == $b['completed']) {
            return 0;
        }
        return ($a['completed'] > $b['completed']) ? -1 : 1;
    }

    /**
     * Comparison function for reverse sorting tasks by completion status.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  -1 if task one is greater, 1 if task two is greater;
     *                  0 if they are equal.
     */
    function _rsortByCompletion($a, $b)
    {
        if ($a['completed'] == $b['completed']) {
            return 0;
        }
        return ($a['completed'] < $b['completed']) ? -1 : 1;
    }

    /**
     * Comparison function for sorting tasks by owner.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  1 if task one is greater, -1 if task two is greater;
     *                  0 if they are equal.
     */
    function _sortByOwner($a, $b)
    {
        $ashare = $GLOBALS['nag_shares']->getShare($a['tasklist_id']);
        $bshare = $GLOBALS['nag_shares']->getShare($b['tasklist_id']);

        $aowner = $a['tasklist_id'];
        $bowner = $b['tasklist_id'];

        if (!is_a($ashare, 'PEAR_Error') && $aowner != $ashare->get('owner')) {
            $aowner = $ashare->get('name');
        }
        if (!is_a($bshare, 'PEAR_Error') && $bowner != $bshare->get('owner')) {
            $bowner = $bshare->get('name');
        }

        return strcasecmp($aowner, $bowner);
    }

    /**
     * Comparison function for reverse sorting tasks by owner.
     *
     * @param array $a  Task one.
     * @param array $b  Task two.
     *
     * @return integer  -1 if task one is greater, 1 if task two is greater;
     *                  0 if they are equal.
     */
    function _rsortByOwner($a, $b)
    {
        $ashare = $GLOBALS['nag_shares']->getShare($a['tasklist_id']);
        $bshare = $GLOBALS['nag_shares']->getShare($b['tasklist_id']);

        $aowner = $a['tasklist_id'];
        $bowner = $b['tasklist_id'];

        if (!is_a($ashare, 'PEAR_Error') && $aowner != $ashare->get('owner')) {
            $aowner = $ashare->get('name');
        }
        if (!is_a($bshare, 'PEAR_Error') && $bowner != $bshare->get('owner')) {
            $bowner = $bshare->get('name');
        }

        return strcasecmp($bowner, $aowner);
    }

}
