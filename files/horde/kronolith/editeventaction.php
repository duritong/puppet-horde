<?php
/**
 * $Horde: kronolith/editeventaction.php,v 1.53.12.9 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

$url = Util::getFormData('url');

if (!Util::getFormData('cancel')) {
    if (Util::getFormData('delete')) {
        $delurl = 'delevent.php';
        Util::addParameter($delurl, 'eventID', Util::getFormData('eventID'));
        Util::addParameter($delurl, 'calendar', Util::getFormData('existingcalendar'));
        $location = Horde::applicationUrl($delUrl, true);
        if (!empty($url)) {
            $location = Util::addParameter($location, 'url', $url, false);
        }
        if ($timestamp = Util::getFormData('timestamp')) {
            $location = Util::addParameter($location, 'timestamp', $timestamp, false);
        }
        header('Location: ' . $location);
    } else {
        $source = Util::getFormData('existingcalendar');
        $target = Util::getFormData('targetcalendar');
        $share = &$kronolith_shares->getShare($target);

        if (is_a($share, 'PEAR_Error')) {
            $notification->push(sprintf(_("There was an error accessing the calendar: %s"), $share->getMessage()), 'horde.error');
        } else {
            $event = false;

            if (Util::getFormData('saveAsNew')) {
                if (Kronolith::hasPermission('max_events') === true ||
                    Kronolith::hasPermission('max_events') > Kronolith::countEvents()) {
                    $kronolith->open($target);
                    $event = &$kronolith->getEvent();
                } else {
                    $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d events."), Kronolith::hasPermission('max_events')), ENT_COMPAT, NLS::getCharset());
                    if (!empty($conf['hooks']['permsdenied'])) {
                        $message = Horde::callHook('_perms_hook_denied', array('kronolith:max_events'), 'horde', $message);
                    }
                    $notification->push($message, 'horde.error', array('content.raw'));
                }
            } else {
                if ($target != $source) {
                    // Only delete the event from the source calendar if this
                    // user has permissions to do so.
                    $sourceShare = &$kronolith_shares->getShare($source);
                    if (!is_a($share, 'PEAR_Error') && !is_a($sourceShare, 'PEAR_Error') &&
                        $sourceShare->hasPermission(Auth::getAuth(), PERMS_DELETE) &&
                        $share->hasPermission(Auth::getAuth(), PERMS_EDIT)) {
                        $kronolith->open($source);
                        $res = $kronolith->move(Util::getFormData('eventID'), $target);
                        if (is_a($res, 'PEAR_Error')) {
                            $notification->push(sprintf(_("There was an error moving the event: %s"), $res->getMessage()), 'horde.error');
                        }
                        $kronolith->close();
                    }

                    $kronolith->open($target);
                    $event = &$kronolith->getEvent(Util::getFormData('eventID'));
                } else {
                    $kronolith->open($source);
                    $event = &$kronolith->getEvent(Util::getFormData('eventID'));
                }
            }

            if ($event && !is_a($event, 'PEAR_Error')) {
                if (!$share->hasPermission(Auth::getAuth(), PERMS_EDIT, $event->getCreatorID())) {
                    $notification->push(_("You do not have permission to edit this event."), 'horde.warning');
                } else {
                    $event->readForm();
                    $res = $event->save();
                    if (is_a($res, 'PEAR_Error')) {
                        $notification->push(sprintf(_("There was an error editing the event: %s"), $res->getMessage()), 'horde.error');
                    } elseif (Util::getFormData('sendupdates', false)) {
                        Kronolith::sendITipNotifications($event, $notification, KRONOLITH_ITIP_REQUEST);
                    }
                }
            }
        }
    }
}

if (!empty($url)) {
    $location = $url;
} else {
    $url = Util::addParameter($prefs->getValue('defaultview') . '.php',
                              array('month' => Util::getFormData('month'),
                                    'year' => Util::getFormData('year')));
    $location = Horde::applicationUrl($url, true);
}

// Make sure URL is unique.
$location = Util::addParameter($location, 'unique', md5(microtime()), false);

header('Location: ' . $location);
