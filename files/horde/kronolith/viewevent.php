<?php
/**
 * $Horde: kronolith/viewevent.php,v 1.49.2.15 2007/10/05 13:55:43 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';
require_once 'Horde/Text.php';

if (Util::getFormData('calendar') == '**remote') {
    $event = Kronolith::getRemoteEventObject(Util::getFormData('remoteCal'),
                                             Util::getFormData('eventID'));
} elseif ($uid = Util::getFormData('uid')) {
    $event = &$kronolith->getByUID($uid);
} else {
    $kronolith->open(Util::getFormData('calendar'));
    $event = &$kronolith->getEvent(Util::getFormData('eventID'));
}
if (!$event || is_a($event, 'PEAR_Error')) {
    if (($url = Util::getFormData('url')) === null) {
        $url = Horde::applicationUrl($prefs->getValue('defaultview') . '.php',
                                     true);
    }
    header('Location: ' . $url);
    exit;
}

/* Get the event's history. */
if ($event->getUID()) {
    $history = &Horde_History::singleton();
    $log = $history->getHistory('kronolith:' . $event->getCalendar() . ':' .
                                $event->getUID());
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

$category = $event->getCategory();
$description = $event->getDescription();
$location = $event->getLocation();
$status = Kronolith::statusToString($event->getStatus());
$attendees = $event->getAttendees();

if ($conf['metadata']['keywords']) {
    include KRONOLITH_BASE . '/config/keywords.php';
    $keyword_list = array();
    foreach ($keywords as $cat => $list) {
        $sub_list = array();
        foreach ($list as $entry) {
            if ($event->hasKeyword($entry)) {
                $sub_list[] = htmlspecialchars($entry);
            }
        }
        if (count($sub_list)) {
            $keyword_list[$cat] = $sub_list;
        }
    }
}

if ($timestamp = (int)Util::getFormData('timestamp')) {
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
} else {
    $month = (int)Util::getFormData('month', date('n'));
    $year = (int)Util::getFormData('year', date('Y'));
}

$title = $event->getTitle();
$print_view = (bool)Util::getFormData('print');
if (!$print_view) {
    Horde::addScriptFile('popup.js', 'horde', true);
    Horde::addScriptFile('stripe.js', 'kronolith', true);
}
require KRONOLITH_TEMPLATES . '/common-header.inc';

if ($print_view) {
    require_once $registry->get('templates', 'horde') . '/javascript/print.js';
} else {
    $print_link = Util::addParameter(Horde::selfUrl(true), 'print', 'true');
    require KRONOLITH_TEMPLATES . '/menu.inc';
}

$mylinks = array();
$isRemote = Util::getFormData('calendar') == '**remote';

if (!$isRemote &&
    $all_calendars[$event->getCalendar()]->hasPermission(Auth::getAuth(),
                                                         PERMS_DELETE,
                                                         $event->getCreatorID())) {
    $delurl = Util::addParameter('delevent.php',
                                 array('eventID' => $event->getID(),
                                       'calendar' => $event->getCalendar(),
                                       'timestamp' => $timestamp));
    $delurl = Horde::applicationUrl($delurl);
    if ($url = Util::getFormData('url')) {
        $delurl = Util::addParameter($delurl, 'url', $url);
    }
    $mylinks[] = Horde::widget($delurl, '', '', '', '', _("De_lete"));
}

if ($isRemote ||
    $all_calendars[$event->getCalendar()]->hasPermission(Auth::getAuth(),
                                                         PERMS_EDIT,
                                                         $event->getCreatorID())) {
    $editurl = Util::addParameter('editevent.php',
                                  array('eventID' => $event->getID(),
                                        'calendar' => $isRemote ? '**remote' : $event->getCalendar(),
                                        'timestamp' => $timestamp));
    if ($isRemote) {
        $editurl = Util::addParameter($editurl, 'remoteCal', $event->remoteCal);
    }
    if ($url = Util::getFormData('url')) {
        $editurl = Util::addParameter($editurl, 'url', $url);
    } else {
        $editurl = Util::addParameter($editurl, 'url', Horde::selfUrl(true, true, true));
    }
    $editurl = Horde::applicationUrl($editurl);

    if ($isRemote) {
        $mylinks[] = Horde::widget($editurl, '', '', '', '', _("Save As New"));
    } else {
        $mylinks[] = Horde::widget($editurl, '', '', '', '', _("_Edit"));
    }
}

// Determine owner's name.
$owner = Kronolith::getUserName($event->getCreatorID());

require KRONOLITH_TEMPLATES . '/view/view.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
