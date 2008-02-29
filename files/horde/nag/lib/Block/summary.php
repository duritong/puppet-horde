<?php

$block_name = _("Tasks Summary");

/**
 * $Horde: nag/lib/Block/summary.php,v 1.51.8.10 2006/06/06 00:05:00 chuck Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_nag_summary extends Horde_Block {

    var $_app = 'nag';

    function _title()
    {
        global $registry;
        return Horde::link(Horde::applicationUrl($registry->getInitialPage(), true)) . $registry->get('name') . '</a> <small>' .
            Horde::link(Util::addParameter(Horde::applicationUrl('task.php', true), 'actionID', 'add_task')) . Horde::img('nag.png', _("New Task")) . ' ' . _("New Task") . '</a></small>';
    }

    function _params()
    {
        require_once dirname(__FILE__) . '/../base.php';
        require_once 'Horde/Prefs/CategoryManager.php';
        $cManager = &new Prefs_CategoryManager();
        $categories = array();
        foreach ($cManager->get() as $c) {
            $categories[$c] = $c;
        }
        $categories['unfiled'] = _("Unfiled");

        return array('show_pri' => array('type' => 'checkbox',
                                         'name' => _("Show priorities?"),
                                         'default' => 1),
                     'show_actions' => array('type' => 'checkbox',
                                             'name' => _("Show action buttons?"),
                                             'default' => 1),
                     'show_due' => array('type' => 'checkbox',
                                         'name' => _("Show due dates?"),
                                         'default' => 1),
                     'show_tasklist' => array('type' => 'checkbox',
                                              'name' => _("Show tasklist name?"),
                                              'default' => 1),
                     'show_alarms' => array('type' => 'checkbox',
                                            'name' => _("Show task alarms?"),
                                            'default' => 1),
                     'show_category' => array('type' => 'checkbox',
                                              'name' => _("Show task category?"),
                                              'default' => 1),
                     'show_overdue' => array('type' => 'checkbox',
                                             'name' => _("Always show overdue tasks?"),
                                             'default' => 1),
                     'show_completed' => array('type' => 'checkbox',
                                              'name' => _("Always show completed tasks?"),
                                              'default' => 1),
                     'show_categories' => array('type' => 'multienum',
                                                'name' => _("Show tasks from these categories"),
                                                'default' => array(),
                                                'values' => $categories)
               );
    }

    function _content()
    {
        global $registry, $prefs;
        require_once dirname(__FILE__) . '/../base.php';

        $now = time();
        $html = '';

        if (!empty($this->_params['show_alarms'])) {
            $messages = array();
            $alarmList = Nag::listAlarms($now);
            if (is_a($alarmList, 'PEAR_Error')) {
                return $alarmList->getMessage();
            }
            foreach ($alarmList as $task) {
                $differential = $task['due'] - $now;
                $key = $differential;
                while (isset($messages[$key])) {
                    $key++;
                }
                $viewurl = Util::addParameter('view.php', array('task' => $task['task_id'],
                                                                'tasklist' => $task['tasklist_id']));
                $link = Horde::link(htmlspecialchars(Horde::applicationUrl($viewurl, true))) .
                    (!empty($task['name']) ? htmlspecialchars($task['name']) : _("[none]")) . '</a>';
                if ($differential >= -60 && $differential < 60) {
                    $messages[$key] = sprintf(_("%s is due now."), $link);
                } elseif ($differential >= 60) {
                    $messages[$key] = sprintf(_("%s is due in %s"), $link, Nag::secondsToString($differential));
                }
            }

            ksort($messages);
            foreach ($messages as $message) {
                $html .= '<tr><td class="control">' . Horde::img('alarm_small.png') . '&nbsp;&nbsp;<strong>' . $message . '</strong></td></tr>';
            }

            if (!empty($messages)) {
                $html .= '</table><br /><table cellspacing="0" width="100%" class="linedRow">';
            }
        }

        $i = 0;
        $tasks = Nag::listTasks();
        foreach ($tasks as $id => $task) {
            // Only print tasks due in the past if the show_overdue
            // flag is on. Only display selected categories (possibly
            // unfiled).
            if (($task['due'] > 0 &&
                 $now > $task['due'] &&
                 empty($this->_params['show_overdue'])) ||
                (!empty($this->_params['show_categories']) &&
                 (!in_array($task['category'], $this->_params['show_categories']) &&
                  !(empty($task['category']) &&
                    in_array('unfiled', $this->_params['show_categories']))))) {
                continue;
            }

            // Only display completed tasks if the show_completed
            // parameter is on.
            if ($task['completed'] && empty($this->_params['show_completed'])) {
                continue;
            }

            if (!empty($task['completed'])) {
                $style = 'closed';
            } elseif (!empty($task['due']) && $task['due'] < $now) {
                $style = 'overdue';
            } else {
                $style = '';
            }

            $html .= '<tr class="' . $style . '">';

            if (!empty($this->_params['show_actions'])) {
                $taskurl = Util::addParameter('task.php', array('task' => $task['task_id'],
                                                                'tasklist' => $task['tasklist_id'],
                                                                'url' => Horde::selfUrl(true)));
                $label = sprintf(_("Edit \"%s\""), $task['name']);
                $html .= '<td width="1%">' . Horde::link(htmlspecialchars(Horde::applicationUrl(Util::addParameter($taskurl, 'actionID', 'modify_task'), true)), $label) . Horde::img('edit.png', $label, null, $registry->getImageDir('horde')) . '</a></td>';
                if ($task['completed']) {
                    $html .= '<td width="1%">' . Horde::img('checked.png', _("Completed")) . '</td>';
                } else {
                    $label = sprintf(_("Complete \"%s\""), $task['name']);
                    $html .= '<td width="1%">' . Horde::link(htmlspecialchars(Horde::applicationUrl(Util::addParameter($taskurl, 'actionID', 'complete_task'), true)), $label) . Horde::img('unchecked.png', $label) . '</a></td>';
                }
            }

            if (!empty($this->_params['show_pri'])) {
                $html .= '<td align="center">&nbsp;' . Nag::formatPriority($task['priority']) . '&nbsp;</td>';
            }

            if (!empty($this->_params['show_tasklist'])) {
                $owner = $task['tasklist_id'];
                $shares = &Horde_Share::singleton($registry->getApp());
                $share = $shares->getShare($owner);
                if (!is_a($share, 'PEAR_Error')) {
                    $owner = $share->get('name');
                }
                $html .= '<td width="1%" class="nowrap">' . htmlspecialchars($owner) . '&nbsp;</td>';
            }

            $html .= '<td>';

            $viewurl = Util::addParameter('view.php', array('task' => $task['task_id'],
                                                            'tasklist' => $task['tasklist_id']));
            $html .= Horde::link(htmlspecialchars(Horde::applicationUrl($viewurl, true))) .
                (!empty($task['name']) ? htmlspecialchars($task['name']) : _("[none]")) . '</a>';

            if ($task['due'] > 0 && empty($task['completed']) && !empty($this->_params['show_due'])) {
                $html .= ' (' . strftime($prefs->getValue('date_format'), $task['due']) . ')';
            }

            $html .= '</td>';

            if (!empty($this->_params['show_category'])) {
                $html .= '<td width="1%" class="category' . md5($task['category']) . '">&nbsp;' .
                    htmlspecialchars($task['category'] ? $task['category'] : _("Unfiled")) .
                    '&nbsp;</td>';
            }
            $html .= "</tr>\n";
        }

        if (empty($html)) {
            return '<em>' . _("No tasks to display") . '</em>';
        }

        return '<link href="' .
            htmlspecialchars(Horde::applicationUrl('themes/categoryCSS.php', true)) .
            '" rel="stylesheet" type="text/css" />' .
            '<table cellspacing="0" width="100%" class="linedRow">' .
            $html . '</table>';
    }

}
