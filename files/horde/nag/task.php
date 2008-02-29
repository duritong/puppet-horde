<?php
/**
 * $Horde: nag/task.php,v 1.80.8.7 2007/01/02 13:55:12 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('NAG_BASE', dirname(__FILE__));
require_once NAG_BASE . '/lib/base.php';
require_once 'Horde/Prefs/CategoryManager.php';
$cManager = &new Prefs_CategoryManager();

/* Redirect to the task list if no action has been requested. */
$actionID = Util::getFormData('actionID');
if (is_null($actionID)) {
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
}

$url = Util::getFormData('url');

/* Run through the action handlers. */
switch ($actionID) {
case 'add_task':
    /* Check permissions. */
    if (Nag::hasPermission('max_tasks') !== true &&
        Nag::hasPermission('max_tasks') <= Nag::countTasks()) {
        $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d tasks."), Nag::hasPermission('max_tasks')), ENT_COMPAT, NLS::getCharset());
        if (!empty($conf['hooks']['permsdenied'])) {
            $message = Horde::callHook('_perms_hook_denied', array('nag:max_tasks'), 'horde', $message);
        }
        $notification->push($message, 'horde.error', array('content.raw'));
        header('Location: ' . Horde::applicationUrl('list.php', true));
        exit;
    }
    /* Set up the task attributes. */
    $tasklist_id = Nag::getDefaultTasklist(PERMS_EDIT);
    $task_id = null;
    $task_name = '';
    $task_due = 0;
    $task_desc = '';
    $task_priority = 3;
    $task_completed = 0;
    $task_category = '';

    $task_alarm = 0;
    $alarm_value = 15;
    $alarm_unit = 'min';
    $alarm_set = false;

    /* Set the initial due date to today. */
    require_once NAG_BASE . '/lib/Widgets.php';
    $initial_date = getdate();
    $javascript = 'onchange="document.task.due_type[1].checked = true;"';
    $day_widget = Widgets::buildDayWidget('due[day]', $initial_date['mday'], $javascript);
    $month_widget = Widgets::buildMonthWidget('due[month]', $initial_date['mon'], $javascript);
    $year_widget = Widgets::buildYearWidget('due[year]', 3, $initial_date['year'], $javascript);
    $hour_widget = Widgets::buildHourWidget('due_hour', 8, $javascript);
    $minute_widget = Widgets::buildMinuteWidget('due_minute', 15, null, $javascript);
    $am_pm_widget = Widgets::buildAmPmWidget('due_am_pm', $initial_date['hours'], $javascript, $javascript);

    /* Set up the radio buttons. */
    $none_checked = ($task_due == 0) ? 'checked="checked" ' : '';
    $specified_checked = ($task_due != 0) ? 'checked="checked" ' : '';

    $title = _("Adding A New Task");
    break;

case 'modify_task':
    $task_id = Util::getFormData('task');
    $tasklist_id = Util::getFormData('tasklist');
    $task = Nag::getTask($tasklist_id, $task_id);
    $alarm_value = 15;
    $alarm_unit = 'min';
    $alarm_set = false;

    if (isset($task) && isset($task['task_id'])) {
        /* Set up the task attributes. */
        $task_name = $task['name'];
        $task_due = $task['due'];
        $task_desc = $task['desc'];
        $task_priority = $task['priority'];
        $task_completed = $task['completed'];
        $task_category = $task['category'];
        $task_alarm = $task['alarm'];

        /* If the due date isn't set, set the widgets to the
         * default. */
        $due_date = getdate(($task_due > 0) ? $task_due : (time() + 604800));
        $javascript = 'onchange="document.task.due_type[1].checked = true;"';

        /* Set up alarm widget data. */
        if ($task_alarm) {
            $alarm_set = true;
            if ($task_alarm % 10080 == 0) {
                $alarm_value = $task_alarm / 10080;
                $alarm_unit = 'week';
            } elseif ($task_alarm % 1440 == 0) {
                $alarm_value = $task_alarm / 1440;
                $alarm_unit = 'day';
            } elseif ($task_alarm % 60 == 0) {
                $alarm_value = $task_alarm / 60;
                $alarm_unit = 'hour';
            } else {
                $alarm_value = $task_alarm;
                $alarm_unit = 'min';
            }
        }

        /* Set up the due date selection widgets. */
        require_once NAG_BASE . '/lib/Widgets.php';
        $day_widget = Widgets::buildDayWidget('due[day]', $due_date['mday'], $javascript);
        $month_widget = Widgets::buildMonthWidget('due[month]', $due_date['mon'], $javascript);
        $year_widget = Widgets::buildYearWidget('due[year]', 3, $due_date['year'], $javascript);
        $hour_widget = Widgets::buildHourWidget('due_hour', $due_date['hours'], $javascript);
        $minute_widget = Widgets::buildMinuteWidget('due_minute', 15, $due_date['minutes'], $javascript);
        $am_pm_widget = Widgets::buildAmPmWidget('due_am_pm', $due_date['hours'], $javascript, $javascript);

        /* Set up the radio buttons. */
        $none_checked = ($task_due == 0) ? 'checked="checked" ' : '';
        $specified_checked = ($task_due > 0) ? 'checked="checked" ' : '';

        $title = _("Modifying:") . ' ' . $task_name;
    } else {
        $notification->push(_("Task not found."), 'horde.error');
        header('Location: ' . Horde::applicationUrl('list.php', true));
        exit;
    }
    break;

case 'save_task':
    /* Get the form values. */
    $task_id = Util::getFormData('task');
    $tasklist_original = Util::getFormData('tasklist_original');
    $tasklist_target = Util::getFormData('tasklist_target');

    $share = $GLOBALS['nag_shares']->getShare($tasklist_target);
    if (is_a($share, 'PEAR_Error')) {
        $notification->push(sprintf(_("Access denied saving task: %s"), $share->getMessage()), 'horde.error');
    } elseif (!$share->hasPermission(Auth::getAuth(), PERMS_EDIT)) {
        $notification->push(sprintf(_("Access denied saving task to %s."), $share->get('name')), 'horde.error');
    } else {
        $task_name = Util::getFormData('task_name');
        $task_desc = Util::getFormData('task_desc');
        $task_priority = Util::getFormData('task_priority');
        $task_completed = Util::getFormData('task_completed') ? 1 : 0;
        $task_category = Util::getFormData('task_category');
        $due_type = Util::getFormData('due_type');
        $due = Util::getFormData('due');
        $due_day = !empty($due['day']) ? $due['day'] : null;
        $due_month = !empty($due['month']) ? $due['month'] : null;
        $due_year = !empty($due['year']) ? $due['year'] : null;
        $due_hour = Util::getFormData('due_hour');
        $due_minute = Util::getFormData('due_minute');
        if (!$prefs->getValue('twentyFour')) {
            $due_am_pm = Util::getFormData('due_am_pm');
            if ($due_am_pm == 'pm') {
                if ($due_hour < 12) {
                    $due_hour = $due_hour + 12;
                }
            } else {
                // Adjust 12:xx AM times.
                if ($due_hour == 12) {
                    $due_hour = 0;
                }
            }
        }
        $alarm_set = Util::getFormData('alarm');
        $alarm_unit = Util::getFormData('alarm_unit');
        $alarm_value = Util::getFormData('alarm_value');
        if ($alarm_set) {
            /* is_int() doesn't work here. */
            if ((string)(int)$alarm_value != $alarm_value) {
                $notification->push(_("The alarm field may only contain integers."), 'horde.warning');
                $task_alarm = 0;
            } else {
                $task_alarm = $alarm_value * $alarm_unit ;
            }
        } else {
            $task_alarm = 0;
        }

        if ($new_category = Util::getFormData('new_category')) {
            $new_category = $cManager->add($new_category);
            if ($new_category) {
                $task_category = $new_category;
            }
        }

        /* Convert the due date to Unix time. */
        $due_str = "$due_month/$due_day/$due_year $due_hour:$due_minute";

        /* Set the due date according to the $due_type toggle. */
        $task_due = strcasecmp($due_type, 'none') ? strtotime($due_str) : 0;

        /* If $task_id is set, we're modifying an existing task. Otherwise,
         * we're adding a new task with the provided attributes. */
        if ($task_id != null && !empty($tasklist_original)) {
            $storage = &Nag_Driver::singleton($tasklist_original);
            $result = $storage->modify($task_id, $task_name, $task_desc,
                                       $task_due, $task_priority,
                                       $task_completed, $task_category,
                                       $task_alarm);

            if (!is_a($result, 'PEAR_Error') &&
                $tasklist_original != $tasklist_target) {
                /* Moving the task to another tasklist. */
                $share = $GLOBALS['nag_shares']->getShare($tasklist_original);
                if (!is_a($share, 'PEAR_Error') &&
                    $share->hasPermission(Auth::getAuth(), PERMS_DELETE)) {
                    $share = $GLOBALS['nag_shares']->getShare($tasklist_original);
                    if (!is_a($share, 'PEAR_Error') &&
                        $share->hasPermission(Auth::getAuth(), PERMS_EDIT)) {
                        $result = $storage->move($task_id, $tasklist_target);
                    } else {
                        $notification->push(sprintf(_("Access denied moving the task to %s."), $share->get('name')), 'horde.error');
                    }
                } else {
                    $notification->push(sprintf(_("Access denied removing task from %s."), $share->get('name')), 'horde.error');
                }
            }
        } else {
            /* Check permissions. */
            if (Nag::hasPermission('max_tasks') !== true &&
                Nag::hasPermission('max_tasks') <= Nag::countTasks()) {
                header('Location: ' . Horde::applicationUrl('list.php', true));
                exit;
            }
            /* Creating a new task. */
            $storage = &Nag_Driver::singleton($tasklist_target);
            $result = $storage->add($task_name, $task_desc, $task_due,
                                    $task_priority, $task_completed,
                                    $task_category, $task_alarm);
        }

        // Check our results.
        if (is_a($result, 'PEAR_Error')) {
            $notification->push(sprintf(_("There was a problem saving the task: %s."), $result->getMessage()), 'horde.error');
        } else {
            $notification->push(sprintf(_("Saved %s."), $task_name), 'horde.success');
        }
    }

    /* Return to the task list. */
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
    break;

case 'delete_tasks':
    /* Delete the task if we're provided with a valid task ID. */
    $task_id = Util::getFormData('task');
    $tasklist_id = Util::getFormData('tasklist');
    if (isset($task_id)) {
        $share = $GLOBALS['nag_shares']->getShare($tasklist_id);
        $task = Nag::getTask($tasklist_id, $task_id);
        if (is_a($share, 'PEAR_Error') || !$share->hasPermission(Auth::getAuth(), PERMS_DELETE)) {
            $notification->push(sprintf(_("Access denied deleting %s."), $task['name']), 'horde.error');
        } else {
            $storage = &Nag_Driver::singleton($tasklist_id);
            $result = $storage->delete($task_id);
            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("There was a problem deleting %s: %s"),
                                            $task['name'], $result->getMessage()), 'horde.error');
            } else {
                $notification->push(sprintf(_("Deleted %s."), $task['name']), 'horde.success');
            }
        }
    }

    /* Return to the task list. */
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
    break;

case 'complete_task':
    /* Complete the task if we're provided with a valid task ID. */
    $task_id = Util::getFormData('task');
    $tasklist_id = Util::getFormData('tasklist');
    if (isset($task_id)) {
        $share = $GLOBALS['nag_shares']->getShare($tasklist_id);
        $task = Nag::getTask($tasklist_id, $task_id);
        if (is_a($share, 'PEAR_Error') || !$share->hasPermission(Auth::getAuth(), PERMS_EDIT)) {
            $notification->push(sprintf(_("Access denied completing task %s."), $task['name']), 'horde.error');
        } else {
            $storage = &Nag_Driver::singleton($tasklist_id);
            $result = $storage->modify($task_id, $task['name'], $task['desc'], $task['due'],
                                       $task['priority'], 1, $task['category'], $task['alarm']);
            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("There was a problem completing %s: %s"),
                                            $task['name'], $result->getMessage()), 'horde.error');
            } else {
                $notification->push(sprintf(_("Completed %s."), $task['name']), 'horde.success');
            }
        }
    }

    if (isset($url)) {
        header('Location: ' . $url);
    } else {
        header('Location: ' . Horde::applicationUrl('list.php', true));
    }
    exit;
    break;

default:
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
}

$notification->push('document.task.task_name.focus()', 'javascript');
$tasklists = Nag::listTasklists(false, PERMS_EDIT);
Horde::addScriptFile('stripe.js', 'horde', true);
require NAG_TEMPLATES . '/common-header.inc';
require NAG_TEMPLATES . '/menu.inc';
require NAG_TEMPLATES . '/task/task.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
