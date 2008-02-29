<?php
/**
 * $Horde: kronolith/addevent.php,v 1.55.8.4 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you did
 * not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require KRONOLITH_BASE . '/lib/base.php';

/* Check permissions. */
if (Kronolith::hasPermission('max_events') !== true &&
    Kronolith::hasPermission('max_events') <= Kronolith::countEvents()) {
    $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d events."), Kronolith::hasPermission('max_events')), ENT_COMPAT, NLS::getCharset());
    if (!empty($conf['hooks']['permsdenied'])) {
        $message = Horde::callHook('_perms_hook_denied', array('kronolith:max_events'), 'horde', $message);
    }
    $notification->push($message, 'horde.error', array('content.raw'));
    $url = Util::addParameter($prefs->getValue('defaultview') . '.php', array('month' => Util::getFormData('month'),
                                                                              'year' => Util::getFormData('year')));
    header('Location: ' . Horde::applicationUrl($url, true));
    exit;
}

$calendar_id = Kronolith::getDefaultCalendar(PERMS_EDIT);
if (!$calendar_id) {
    $url = Util::addParameter($prefs->getValue('defaultview') . '.php', array('month' => Util::getFormData('month'),
                                                                              'year' => Util::getFormData('year')));
    header('Location: ' . Horde::applicationUrl($url, true));
}

$event = &$kronolith->getEvent();
$_SESSION['attendees'] = $event->getAttendees();

if (!$timestamp = Util::getFormData('timestamp')) {
    $month = Util::getFormData('month', date('n'));
    $day = Util::getFormData('mday', date('j'));
    $year = Util::getFormData('year', date('Y'));
    $hour = $prefs->getValue('twentyFour') ? 12 : 6;
    $timestamp = mktime($hour, 0, 0, $month, $day, $year);
}

$url = Util::getFormData('url');

$event->start = &new Horde_Date($timestamp);
// Default to a 1 hour duration.
$event->end = &new Horde_Date($timestamp + 3600);
$event->setRecurType(KRONOLITH_RECUR_NONE);
$month = $event->start->month;
$year = $event->start->year;

$buttons = array('<input type="submit" class="button" name="save" value="' . _("Save Event") . '" onclick="return checkCategory();" />');
if (isset($url)) {
    $cancelurl = $url;
} else {
    $cancelurl = Util::addParameter('month.php', 'month', $month);
    $cancelurl = Util::addParameter($cancelurl, 'year', $year);
    $cancelurl = Horde::applicationUrl($cancelurl);
}

$title = _("Add a new event");
$calendars = Kronolith::listCalendars(false, PERMS_EDIT);
Horde::addScriptFile('stripe.js', 'kronolith', true);
Horde::addScriptFile('open_attendees_win.js');
require KRONOLITH_TEMPLATES . '/common-header.inc';
require KRONOLITH_TEMPLATES . '/menu.inc';
require KRONOLITH_TEMPLATES . '/edit/javascript.inc';
require KRONOLITH_TEMPLATES . '/edit/edit.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
