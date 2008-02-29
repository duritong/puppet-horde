<?php
/**
 * $Horde: nag/view.php,v 1.55.2.7 2007/01/02 13:55:12 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('NAG_BASE', dirname(__FILE__));
require_once NAG_BASE . '/lib/base.php';
require_once 'Horde/Text/Filter.php';

/* We can either have a UID or a taskId and a tasklist. Check for
 * UID first. */
if ($uid = Util::getFormData('uid')) {
    $storage = &Nag_Driver::singleton();
    $task = $storage->getByUID($uid);
    if (is_a($task, 'PEAR_Error')) {
        header('Location: ' . Horde::applicationUrl('list.php', true));
        exit;
    }

    $task_id = $task['task_id'];
    $tasklist_id = $task['tasklist_id'];
} else {
    /* If we aren't provided with a task and tasklist, redirect to
     * list.php. */
    $task_id = Util::getFormData('task');
    $tasklist_id = Util::getFormData('tasklist');
    if (!isset($task_id) || !$tasklist_id) {
        header('Location: ' . Horde::applicationUrl('list.php', true));
        exit;
    }

    /* Get the current task. */
    $task = Nag::getTask($tasklist_id, $task_id);
}

/* If the requested task doesn't exist, display an error message. */
if (!isset($task) || !isset($task['task_id'])) {
    $notification->push(_("Task not found."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
}

/* Check permissions on $tasklist_id. */
$share = $GLOBALS['nag_shares']->getShare($tasklist_id);
if (is_a($share, 'PEAR_Error') || !$share->hasPermission(Auth::getAuth(), PERMS_READ)) {
    $notification->push(_("You do not have permission to view this tasklist."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
}

/* Get the task's history. */
if (!empty($task['uid'])) {
    $history = &Horde_History::singleton();
    $log = $history->getHistory('nag:' . $tasklist_id . ':' . $task['uid']);
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
}

$title = $task['name'];
$print_view = (bool)Util::getFormData('print');
if (!$print_view) {
    Horde::addScriptFile('popup.js', 'horde', true);
    Horde::addScriptFile('stripe.js', 'horde', true);
}
require NAG_TEMPLATES . '/common-header.inc';

if ($print_view) {
    require_once $registry->get('templates', 'horde') . '/javascript/print.js';
} else {
    $print_link = Util::addParameter('view.php', array('task' => $task_id,
                                                       'tasklist' => $tasklist_id,
                                                       'print' => 1));
    $print_link = Horde::url($print_link);
    require NAG_TEMPLATES . '/menu.inc';
}

/* Set up alarm units and value. */
$task_alarm = $task['alarm'];
if (!$task['due']) {
    $task_alarm = 0;
}
$alarm_text = Nag::formatAlarm($task_alarm);
require NAG_TEMPLATES . '/view/task.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
