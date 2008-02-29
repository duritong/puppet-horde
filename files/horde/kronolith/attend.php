<?php
/**
 * $Horde: kronolith/attend.php,v 1.2.2.4 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 2005-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you did
 * not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('AUTH_HANDLER', true);
@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

$uid = Util::getFormData('i');
$user = Util::getFormData('u');

switch (Util::getFormData('a')) {
case 'accept':
    $action = KRONOLITH_RESPONSE_ACCEPTED;
    $msg = _("You have successfully accepted attendence to this event.");
    break;

case 'decline':
    $action = KRONOLITH_RESPONSE_DECLINED;
    $msg = _("You have successfully declined attendence to this event.");
    break;

case 'tentative':
    $action = KRONOLITH_RESPONSE_TENTATIVE;
    $msg = _("You have tentatively accepted attendence to this event.");
    break;

default:
    $action = KRONOLITH_RESPONSE_NONE;
    $msg = '';
    break;
}

if (empty($uid) || empty($user)) {
    $notification->push(_("The request was incomplete. Some parameters that are necessary to accept or decline an event are missing."), 'horde.error');
}

$event = $kronolith->getByUID($uid);
if (is_a($event, 'PEAR_Error')) {
    $notification->push($event, 'horde.error');
    $title = '';
} elseif (!$event->hasAttendee($user)) {
    $notification->push(_("You are not an attendee of the specified event."), 'horde.error');
    $title = $event->getTitle();
} else {
    $event->addAttendee($user, KRONOLITH_PART_IGNORE, $action);
    $result = $event->save();
    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result, 'horde.error');
    } elseif (!empty($msg)) {
        $notification->push($msg, 'horde.success');
    }
    $title = $event->getTitle();
}

require KRONOLITH_TEMPLATES . '/common-header.inc';

?>
<div id="menu"><h1>&nbsp;<?php echo htmlspecialchars($title) ?></h1></div>
<?php

$notification->notify(array('listeners' => 'status'));
require $registry->get('templates', 'horde') . '/common-footer.inc';
