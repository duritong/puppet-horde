<?php
/**
 * $Horde: kronolith/addeventaction.php,v 1.50.2.4 2005/12/17 16:52:38 chuck Exp $
 *
 * Copyright 1999, 2000 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

if (!Util::getFormData('cancel')) {
    $calendar_id = Util::getFormData('targetcalendar');
    $share = &$kronolith_shares->getShare($calendar_id);
    if (is_a($share, 'PEAR_Error')) {
        $notification->push(sprintf(_("There was an error accessing the calendar: %s"), $share->getMessage()), 'horde.error');
    } elseif (!$share->hasPermission(Auth::getAuth(), PERMS_EDIT, Auth::getAuth())) {
        $notification->push(sprintf(_("You do not have permission to add events to %s."), $share->get('name')), 'horde.warning');
    } elseif (Kronolith::hasPermission('max_events') === true ||
              Kronolith::hasPermission('max_events') > Kronolith::countEvents()) {
        $kronolith->open($calendar_id);
        $event = &$kronolith->getEvent();
        $event->readForm();
        $result = $event->save();
        if (is_a($result, 'PEAR_Error')) {
            $userinfo = $result->getUserInfo();
            if (is_array($userinfo)) {
                $userinfo = implode(', ', $userinfo);
            }
            $message = $result->getMessage() . ($userinfo ? ' : ' . $userinfo : '');

            $notification->push(sprintf(_("There was an error adding the event: %s"), $message), 'horde.error');
        } else {
            if (Util::getFormData('sendupdates', false)) {
                $event = &$kronolith->getEvent($result);
                Kronolith::sendITipNotifications($event, $notification, KRONOLITH_ITIP_REQUEST);
            }
        }
    }
}

if ($url = Util::getFormData('url')) {
    header('Location: ' . $url);
} else {
    $url = Util::addParameter($prefs->getValue('defaultview') . '.php',
                              array('month' => Util::getFormData('month'),
                                    'year' => Util::getFormData('year')));
    header('Location: ' . Horde::applicationUrl($url, true));
}
