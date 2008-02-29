<?php
/**
 * $Horde: kronolith/attendees.php,v 1.7.8.14 2007/11/16 13:32:10 jan Exp $
 *
 * Copyright 2004-2007 Code Fusion  <http://www.codefusion.co.za/>
 *                Stuart Binge <s.binge@codefusion.co.za>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';
require_once KRONOLITH_BASE . '/lib/FBView.php';
require_once 'Horde/Identity.php';
require_once 'Horde/UI/Tabs.php';
require_once 'Horde/Variables.php';

// Get the current attendees array from the session cache.
$attendees = (isset($_SESSION['attendees']) && is_array($_SESSION['attendees'])) ? $_SESSION['attendees'] : array();

// Get our Action ID & Value. This specifies what action the user initiated.
$actionID = Util::getFormData('actionID');
if (Util::getFormData('clearAll')) {
    $actionID =  'clear';
}
$actionValue = Util::getFormData('actionValue');

// Perform the specified action, if there is one.
switch ($actionID) {
case 'add':
    // Add new attendees. Multiple attendees can be seperated on a
    // single line by whitespace and/or commas.
    $new = preg_split('/[\s,]+/', Util::getFormData('newAttendees'), -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($new)) {
        foreach ($new as $newattendee) {
            // Avoid overwriting existing attendees with the default values
            if (!isset($attendees[$newattendee]))
                $attendees[$newattendee] = array('attendance' => KRONOLITH_PART_REQUIRED, 'response' => KRONOLITH_RESPONSE_NONE);
        }
    }

    $_SESSION['attendees'] = $attendees;

    if (Util::getFormData('addNewClose')) {
        Util::closeWindowJS();
        exit;
    }

    break;

case 'remove':
    // Remove the specified attendee.
    if (isset($attendees[$actionValue])) {
        unset($attendees[$actionValue]);
        $_SESSION['attendees'] = $attendees;
    }
    break;

case 'changeatt':
    // Change the attendance status of an attendee
    list($partval, $partname) = explode(' ', $actionValue, 2);
    if (isset($attendees[$partname])) {
        $attendees[$partname]['attendance'] = $partval;
        $_SESSION['attendees'] = $attendees;
    }
    break;

case 'dismiss':
    // Close the attendee window.
    if ($browser->hasFeature('javascript')) {
        Util::closeWindowJS();
    } else {
        $url = Util::getFormData('url');

        if (!empty($url)) {
            $location = Horde::applicationUrl($url, true);
        } else {
            $url = Util::addParameter($prefs->getValue('defaultview') . '.php', 'month', Util::getFormData('month'));
            $url = Util::addParameter($url, 'year', Util::getFormData('year'));
            $location = Horde::applicationUrl($url, true);
        }

        // Make sure URL is unique.
        $location = Util::addParameter($location, 'unique', md5(microtime()));

        header('Location: ' . $location);
    }
    break;

case 'expand':
    // Not implemented yet.
    break;

case 'clear':
    // Remove all the attendees.
    $attendees = array();
    $_SESSION['attendees'] = $attendees;
    break;
}

// Get the current Free/Busy view; default to the 'day' view if none specified.
$view = Util::getFormData('view', 'day');

// Pre-format our delete image/link.
$delimg = Horde::img('delete.png', _("Remove Attendee"), null, $registry->getImageDir('horde'));

$ident = &Identity::singleton();
$identities = $ident->getAll('id');
$vars = &Variables::getDefaultVariables();
$tabs = &new Horde_UI_Tabs(null, $vars);
$tabs->addTab(_("Day"), 'javascript:switchView(\'day\')', 'day');
$tabs->addTab(_("Work Week"), 'javascript:switchView(\'workweek\')', 'workweek');
$tabs->addTab(_("Week"), 'javascript:switchView(\'week\')', 'week');

$attendee_view = &Kronolith_FreeBusy_View::singleton($view);

// Add the creator as a required attendee in the Free/Busy display
$cal = @unserialize($prefs->getValue('fb_cals'));
if (!is_array($cal)) {
    $cal = null;
}

// If the free/busy calendars preference is empty, default to the user's
// default_share preference, and if that's empty, to their username.
if (!$cal) {
    $cal = $prefs->getValue('default_share');
    if (!$cal) {
        $cal = Auth::getAuth();
    }
    $cal = array($cal);
}
$vfb = Kronolith::generateFreeBusy($cal, null, null, true, Auth::getAuth());
if (!is_a($vfb, 'PEAR_Error')) {
    $attendee_view->addRequiredMember($vfb);
} else {
    $notification->push(sprintf(_("Error retrieving your free/busy information: %s"), $vfb->getMessage()));
}

// Add the Free/Busy information for each attendee.
foreach ($attendees as $email => $status) {
    if ($status['attendance'] == KRONOLITH_PART_REQUIRED ||
        $status['attendance'] == KRONOLITH_PART_OPTIONAL) {
        $vfb = Kronolith::getFreeBusy($email);
        if (!is_a($vfb, 'PEAR_Error')) {
            $organizer = $vfb->getAttribute('ORGANIZER');
            if (empty($organizer)) {
                $vfb->setAttribute('ORGANIZER', 'mailto:' . $email, array(), false);
            }
            if ($status['attendance'] == KRONOLITH_PART_REQUIRED) {
                $attendee_view->addRequiredMember($vfb);
            } else {
                $attendee_view->addOptionalMember($vfb);
            }
        } else {
            $notification->push(sprintf(_("Error retrieving free/busy information for %s: %s"), $email, $vfb->getMessage()));
        }
    }
}

$timestamp = (int)Util::getFormData('timestamp', time());
$vfb_html = $attendee_view->render($timestamp);

$title = _("Edit attendees");
require KRONOLITH_TEMPLATES . '/common-header.inc';
$notification->notify(array('status'));
require KRONOLITH_TEMPLATES . '/attendees/attendees.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
