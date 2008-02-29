<?php
/**
 * $Horde: kronolith/editevent.php,v 1.52.8.3 2005/12/15 05:06:38 chuck Exp $
 *
 * Copyright 1999, 2000 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

if (Util::getFormData('calendar') == '**remote') {
    $event = Kronolith::getRemoteEventObject(Util::getFormData('remoteCal'), Util::getFormData('eventID'));
} else {
    $kronolith->open(Util::getFormData('calendar'));
    $event = &$kronolith->getEvent(Util::getFormData('eventID'));
}

$calendar_id = $event->getCalendar();
$_SESSION['attendees'] = $event->getAttendees();

if ($timestamp = Util::getFormData('timestamp')) {
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
} else {
    $month = Util::getFormData('month', date('n'));
    $year = Util::getFormData('year', date('Y'));
}

$url = Util::getFormData('url');
$title = sprintf(_("Edit %s"), $event->getTitle());
$calendars = Kronolith::listCalendars(false, PERMS_EDIT);

Horde::addScriptFile('stripe.js', 'kronolith', true);
Horde::addScriptFile('open_attendees_win.js');
require KRONOLITH_TEMPLATES . '/common-header.inc';
require KRONOLITH_TEMPLATES . '/menu.inc';
require KRONOLITH_TEMPLATES . '/edit/javascript.inc';

$buttons = array();
$share = isset($all_calendars[Util::getFormData('calendar')]) ? $all_calendars[Util::getFormData('calendar')] : PEAR::raiseError('not found');
if (Util::getFormData('calendar') == '**remote' ||
    !$share->hasPermission(Auth::getAuth(), PERMS_EDIT, $event->getCreatorID())) {
    if (!empty($conf['hooks']['permsdenied']) ||
        Kronolith::hasPermission('max_events') === true ||
        Kronolith::hasPermission('max_events') > Kronolith::countEvents()) {
        $buttons[] = '<input type="submit" class="button" name="saveAsNew" value="' . _("Save As New") . '" onclick="return checkCategory();" />';
    }
} else {
    $buttons[] = '<input type="submit" class="button" name="save" value="' . _("Save Event") . '" onclick="return checkCategory();" />';
    if ($event->isInitialized()) {
        if (!empty($conf['hooks']['permsdenied']) ||
            Kronolith::hasPermission('max_events') === true ||
            Kronolith::hasPermission('max_events') > Kronolith::countEvents()) {
            $buttons[] = '<input type="submit" class="button" name="saveAsNew" value="' . _("Save As New") . '" onclick="return checkCategory();" />';
        }
        if ($share->hasPermission(Auth::getAuth(), PERMS_DELETE, $event->getCreatorID())) {
            $delurl = Util::addParameter('delevent.php',
                                         array('eventID' => $event->getID(),
                                               'calendar' => $event->getCalendar(),
                                               'month' => $month,
                                               'year', $year));
            if (!empty($url)) {
                $delurl = Util::addParameter($delurl, 'url', $url);
            }
            if (isset($timestamp)) {
                $delurl = Util::addParameter($delurl, 'timestamp', $timestamp);
            }
            $delurl = Horde::applicationUrl($delurl);
            $buttons[] = '<input type="submit" class="button" name="delete" value="' . _("Delete Event") . '" onclick="self.location = \'' . $delurl . '\'; return false;" />';
        }
    }
}

if (isset($url)) {
    $cancelurl = $url;
} else {
    $cancelurl = Util::addParameter('month.php', array('month' => $month,
                                                       'year', $year));
    $cancelurl = Horde::applicationUrl($cancelurl);
}

require KRONOLITH_TEMPLATES . '/edit/edit.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
