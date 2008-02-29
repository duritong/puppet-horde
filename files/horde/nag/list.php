<?php
/**
 * $Horde: nag/list.php,v 1.93.8.6 2007/01/02 13:55:12 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you did
 * not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('NAG_BASE', dirname(__FILE__));
require_once NAG_BASE . '/lib/base.php';
require_once 'Horde/Variables.php';

$vars = Variables::getDefaultVariables();

/* Get the current action ID. */
$actionID = Util::getFormData('actionID');

/* Sort out the sorting values and task filtering. */
if (($sortby = Util::getFormData('sortby')) !== null) {
    $prefs->setValue('sortby', $sortby);
}
if (($sortdir = Util::getFormData('sortdir')) !== null) {
    $prefs->setValue('sortdir', $sortdir);
}
if ($vars->exists('show_completed')) {
    $prefs->setValue('show_completed', $vars->get('show_completed'));
} else {
    $vars->set('show_completed', $prefs->getValue('show_completed'));
}

/* Get the full, sorted task list. */
$tasks = Nag::listTasks($prefs->getValue('sortby'),
                        $prefs->getValue('sortdir'),
                        $prefs->getValue('altsortby'));

/* Page variables. */
$title = _("My Tasks");

switch ($actionID) {
case 'search_tasks':
    /* Get the search parameters. */
    $search_pattern = Util::getFormData('search_pattern');
    $search_name = (Util::getFormData('search_name') == 'on');
    $search_desc = (Util::getFormData('search_desc') == 'on');
    $search_category = (Util::getFormData('search_category') == 'on');

    if (!empty($search_pattern) && ($search_name || $search_desc || $search_category)) {
        $pattern = '/' . preg_quote($search_pattern, '/') . '/i';
        $search_results = array();
        foreach ($tasks as $task) {
            if (($search_name && preg_match($pattern, $task['name'])) ||
                ($search_desc && preg_match($pattern, $task['desc'])) ||
                ($search_category && preg_match($pattern, $task['category']))) {
                $search_results[] = $task;
            }
        }

        /* Reassign $tasks to the search result. */
        $tasks = $search_results;
        $title = _("Search Results");
    }
    break;
}

$print_view = (bool)Util::getFormData('print');
if (!$print_view) {
    Horde::addScriptFile('popup.js', 'horde', true);
    Horde::addScriptFile('tooltip.js', 'horde', true);
    Horde::addScriptFile('prototype.js', 'nag', true);
    Horde::addScriptFile('tables.js', 'nag', true);
    $print_link = Horde::applicationUrl(Util::addParameter('list.php', array('print' => 1)));
}

require NAG_TEMPLATES . '/common-header.inc';

if ($print_view) {
    require_once $registry->get('templates', 'horde') . '/javascript/print.js';
} else {
    require NAG_TEMPLATES . '/menu.inc';

    if (!$prefs->isLocked('show_completed')) {
    	require_once 'Horde/UI/Tabs.php';
    	$listurl = Horde::applicationUrl('list.php');
    	$tabs = new Horde_UI_Tabs('show_completed', $vars);
    	$tabs->addTab(_("_All tasks"), $listurl, 1);
    	$tabs->addTab(_("Incom_plete tasks"), $listurl, 0);
    	$tabs->addTab(_("_Completed tasks"), $listurl, 2);
    	echo $tabs->render($prefs->getValue('show_completed'));
    }
}

require NAG_TEMPLATES . '/list/header.inc';

if ($tasks) {
    $sortby = $prefs->getValue('sortby');
    $sortdir = $prefs->getValue('sortdir');
    $dateFormat = $prefs->getValue('date_format');
    $showTasklist = $prefs->getValue('show_tasklist');

    $baseurl = 'list.php';
    if ($actionID == 'search_tasks') {
        $baseurl = Util::addParameter($baseurl, array('actionID' => 'search_tasks',
                                                      'search_pattern' => $search_pattern,
                                                      'search_name' => $search_name ? 'on' : 'off',
                                                      'search_desc' => $search_desc ? 'on' : 'off',
                                                      'search_category' => $search_category ? 'on' : 'off'));
    }

    require NAG_TEMPLATES . '/list/task_headers.inc';

    foreach ($tasks as $task) {
        if (!empty($task['completed'])) {
            $style = 'linedRow closed';
        } elseif (!empty($task['due']) && $task['due'] < time()) {
            $style = 'linedRow overdue';
        } else {
            $style = 'linedRow';
        }

        if ($task['tasklist_id'] == '**EXTERNAL**') {
            // Just use a new share that this user owns for tasks from
            // external calls - if the API gives them back, we'll
            // trust it.
            $share = $GLOBALS['nag_shares']->newShare('**EXTERNAL**');
        } else {
            $share = $GLOBALS['nag_shares']->getShare($task['tasklist_id']);
        }

        $owner = $task['tasklist_id'];
        if (!is_a($share, 'PEAR_Error')) {
            $owner = $share->get('name');
        }

        require NAG_TEMPLATES . '/list/task_summaries.inc';
    }

    require NAG_TEMPLATES . '/list/task_footers.inc';
} else {
    require NAG_TEMPLATES . '/list/empty.inc';
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
