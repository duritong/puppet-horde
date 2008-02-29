<?php
/**
 * $Horde: kronolith/deleventaction.php,v 1.22.10.6 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

$kronolith->open(Util::getFormData('calendar'));
if ($eventID = Util::getFormData('eventID')) {
    $event = &$kronolith->getEvent($eventID);
    if (is_a($event, 'PEAR_Error')) {
        if (($url = Util::getFormData('url')) === null) {
            $url = Horde::applicationUrl($prefs->getValue('defaultview') . '.php', true);
        }
        header('Location: ' . $url);
        exit;
    }
    $share = &$kronolith_shares->getShare($event->getCalendar());
    if (!$share->hasPermission(Auth::getAuth(), PERMS_DELETE, $event->getCreatorID())) {
        $notification->push(_("You do not have permission to delete this event."), 'horde.warning');
    } else {
        if (Util::getFormData('sendupdates', false)) {
            Kronolith::sendITipNotifications($event, $notification, KRONOLITH_ITIP_CANCEL);
        }

        if (Util::getFormData('future')) {
            $recurEnd = &new Horde_Date(array('hour' => 1, 'min' => 1, 'sec' => 1,
                                              'month' => Util::getFormData('month', date('n')),
                                              'mday' => Util::getFormData('mday', date('j')) - 1,
                                              'year' => Util::getFormData('year', date('Y'))));
            $recurEnd->correct();
            if ($event->end->compareDate($recurEnd) > 0) {
                $kronolith->deleteEvent($event->getId());
            } else {
                $event->recurEnd = $recurEnd;
                $event->save();
            }
        } elseif (Util::getFormData('current')) {
            $event->addException(Util::getFormData('year'),
                                 Util::getFormData('month'),
                                 Util::getFormData('mday'));
            $event->save();
        }

        if ($event->hasRecurType(KRONOLITH_RECUR_NONE) || Util::getFormData('all') || !$event->hasActiveRecurrence()) {
            $kronolith->deleteEvent($event->getID());
        }
    }
}

if ($timestamp = Util::getFormData('timestamp')) {
    $month = date('n', $timestamp);
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
} else {
    $month = Util::getFormData('month', date('n'));
    $day = Util::getFormData('mday', date('j'));
    $year = Util::getFormData('year', date('Y'));
}

if ($url = Util::getFormData('url')) {
    $location = $url;
} else {
    $url = Util::addParameter($prefs->getValue('defaultview') . '.php', 'month', $month);
    $url = Util::addParameter($url, 'year', $year);
    $url = Util::addParameter($url, 'mday', $day);
    $location = Horde::applicationUrl($url, true);
}

$location = Util::addParameter($location, 'uq', md5(microtime()));
header('Location: ' . $location);
