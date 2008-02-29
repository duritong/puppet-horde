<?php
/**
 * Kronolith external API interface.
 *
 * $Horde: kronolith/lib/api.php,v 1.126.2.26.2.4 2008/01/09 21:56:32 chuck Exp $
 *
 * This file defines Kronolith's external API interface. Other applications
 * can interact with Kronolith through this API.
 *
 * @package Kronolith
 */

$_services['perms'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray'
);

$_services['show'] = array(
    'link' => '%application%/viewevent.php?calendar=|calendar|' .
              '&eventID=|event|&uid=|uid|'
);

$_services['browse'] = array(
    'args' => array('path' => 'string'),
    'type' => '{urn:horde}hashHash',
);

$_services['getFreeBusy'] = array(
    'args' => array('startstamp' => 'int', 'endstamp' => 'int', 'calendar' => 'string'),
    'type' => '{urn:horde}stringArray'
);

$_services['listCalendars'] = array(
    'args' => array('owneronly' => 'boolean', 'permission' => 'int'),
    'type' => '{urn:horde}stringArray'
);

$_services['listEvents'] = array(
    'args' => array('startstamp' => 'int', 'endstamp' => 'int', 'calendar' => 'string', 'showRecurrence' => 'string'),
    'type' => '{urn:horde}stringArray'
);

$_services['list'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray'
);

$_services['listBy'] = array(
    'args' => array('action' => 'string', 'timestamp' => 'int'),
    'type' => '{urn:horde}stringArray'
);

$_services['getActionTimestamp'] = array(
    'args' => array('uid' => 'string', 'timestamp' => 'int'),
    'type' => 'int',
);

$_services['import'] = array(
    'args' => array('content' => 'string', 'contentType' => 'string', 'calendar' => 'string'),
    'type' => 'int'
);

$_services['export'] = array(
    'args' => array('uid' => 'string', 'contentType' => 'string'),
    'type' => 'string'
);

$_services['exportCalendar'] = array(
    'args' => array('calendar' => 'string', 'contentType' => 'string'),
    'type' => 'string'
);


$_services['delete'] = array(
    'args' => array('uid' => 'string'),
    'type' => 'boolean'
);

$_services['replace'] = array(
    'args' => array('uid' => 'string', 'content' => 'string', 'contentType' => 'string'),
    'type' => 'boolean'
);

// FIXME: create complex type definition for SOAP calls.
$_services['eventFromUID'] = array(
    'args' => array('uid' => 'string'),
    'type' => 'object'
);

// FIXME: create complex type definition for SOAP calls.
$_services['updateAttendee'] = array(
    'args' => array('response' => 'object'),
    'type' => 'boolean'
);


function _kronolith_perms()
{
    $perms = array();
    $perms['tree']['kronolith']['max_events'] = false;
    $perms['title']['kronolith:max_events'] = _("Maximum Number of Events");
    $perms['type']['kronolith:max_events'] = 'int';

    return $perms;
}

function __kronolith_modified($uid)
{
    $modified = _kronolith_getActionTimestamp($uid, 'modify');
    if (empty($modified)) {
        $modified = _kronolith_getActionTimestamp($uid, 'add');
    }
    return $modified;
}

/**
 * Browse through Kronolith's object tree.
 *
 * @param string $path       The level of the tree to browse.
 * @param array $properties  The item properties to return. Defaults to 'name',
 *                           'icon', and 'browseable'.
 *
 * @return array  The contents of $path
 */
function _kronolith_browse($path = '', $properties = array())
{
    require_once dirname(__FILE__) . '/base.php';
    global $registry;

    // Default properties.
    if (!$properties) {
        $properties = array('name', 'icon', 'browseable');
    }

    if (substr($path, 0, 9) == 'kronolith') {
        $path = substr($path, 9);
    }
    if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1);
    }
    if (substr($path, -1) == '/') {
        $path = substr($path, 0, -1);
    }

    if (empty($path)) {
        $calendars = Kronolith::listCalendars(false, PERMS_SHOW);
        $results = array();
        foreach ($calendars as $calendarId => $calendar) {
            if (in_array('name', $properties)) {
                $results['kronolith/' . $calendarId]['name'] = $calendar->get('name');
            }
            if (in_array('icon', $properties)) {
                $results['kronolith/' . $calendarId]['icon'] = $registry->getImageDir() . '/kronolith.png';
            }
            if (in_array('browseable', $properties)) {
                $results['kronolith/' . $calendarId]['browseable'] = $calendar->hasPermission(Auth::getAuth(), PERMS_READ);
            }
            if (in_array('contenttype', $properties)) {
                $results['kronolith/' . $calendarId]['contenttype'] = 'httpd/unix-directory';
            }
            if (in_array('contentlength', $properties)) {
                $results['kronolith/' . $calendarId]['contentlength'] = 0;
            }
            if (in_array('modified', $properties)) {
                $results['kronolith/' . $calendarId]['modified'] = time();
            }
            if (in_array('created', $properties)) {
                $results['kronolith/' . $calendarId]['created'] = 0;
            }
        }
        return $results;
    } elseif (array_key_exists($path, Kronolith::listCalendars(false, PERMS_READ))) {
        $events = Kronolith::listEvents(0, new Horde_Date(array('year' => 9999, 'month' => 12, 'day' => 31)), array($path), false);
        if (is_a($events, 'PEAR_Error')) {
            return $events;
        }

        $results = array();
        foreach ($events as $timestamp => $timeevents) {
            foreach ($timeevents as $eventId => $event) {
                $key = 'kronolith/' . $path . '/' . $eventId;
                if (in_array('name', $properties)) {
                    $results[$key]['name'] = $event->getTitle();
                }
                if (in_array('icon', $properties)) {
                    $results[$key]['icon'] = $registry->getImageDir('horde') . '/mime/icalendar.png';
                }
                if (in_array('browseable', $properties)) {
                    $results[$key]['browseable'] = false;
                }
                if (in_array('contenttype', $properties)) {
                    $results[$key]['contenttype'] = 'text/calendar';
                }
                if (in_array('contentlength', $properties)) {
                    $data = _kronolith_export($event->getUID(), 'text/calendar');
                    if (is_a($data, 'PEAR_Error')) {
                        $data = '';
                    }
                    $results[$key]['contentlength'] = strlen($data);
                }
                if (in_array('modified', $properties)) {
                    $results[$key]['modified'] = __kronolith_modified($event->getUID());
                }
                if (in_array('created', $properties)) {
                    $results[$key]['created'] = _kronolith_getActionTimestamp($event->getUID(), 'add');
                }
            }
        }
        return $results;
    } else {
        $parts = explode('/', $path);
        if (count($parts) == 2 &&
            array_key_exists($parts[0], Kronolith::listCalendars(false, PERMS_READ))) {
            global $kronolith;
            if ($kronolith->getCalendar() != $parts[0]) {
                $kronolith->close();
                $kronolith->open($parts[0]);
            }
            $event = &$kronolith->getEvent($parts[1]);
            if (is_a($event, 'PEAR_Error')) {
                return $event;
            }

            $result = array('data' => _kronolith_export($event->getUID(), 'text/calendar'),
                            'mimetype' => 'text/calendar');
            $modified = __kronolith_modified($event->getUID());
            if (!empty($modified)) {
                $result['mtime'] = $modified;
            }
            return $result;
        }
    }

    return PEAR::raiseError($path . ' does not exist or permission denied');
}

function _kronolith_listCalendars($owneronly = false, $permission = null)
{
    require_once dirname(__FILE__) . '/base.php';
    if (!isset($permission)) {
        $permission = PERMS_SHOW;
    }
    return array_keys(Kronolith::listCalendars($owneronly, $permission));
}

function _kronolith_list($calendar = null)
{
    require_once dirname(__FILE__) . '/base.php';

    if (empty($calendar)) {
        $calendar = Kronolith::getDefaultCalendar();
    }
    if (!array_key_exists($calendar,
                          Kronolith::listCalendars(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $ids = Kronolith::listEventIds(0,
                                   new Horde_Date(array('year' => 9999, 'month' => 12, 'day' => 31)),
                                   $calendar);
    if (is_a($ids, 'PEAR_Error')) {
        return $ids;
    }

    $uids = array();
    foreach ($ids as $cal) {
        $uids = array_merge($uids, array_keys($cal));
    }

    return $uids;
}

/**
 * Returns an array of UIDs for events that have had $action happen since
 * $timestamp.
 *
 * @param string  $action     The action to check for - add, modify, or delete.
 * @param integer $timestamp  The time to start the search.
 * @param string  $calendar   The calendar to search in.
 *
 * @return array  An array of UIDs matching the action and time criteria.
 */
function _kronolith_listBy($action, $timestamp, $calendar = null)
{
    require_once dirname(__FILE__) . '/base.php';

    if (empty($calendar)) {
        $calendar = Kronolith::getDefaultCalendar();
    }

    if (!array_key_exists($calendar,
                          Kronolith::listCalendars(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $history = &Horde_History::singleton();
    $histories = $history->getByTimestamp('>', $timestamp, array(array('op' => '=', 'field' => 'action', 'value' => $action)), 'kronolith:' . $calendar);
    if (is_a($histories, 'PEAR_Error')) {
        return $histories;
    }

    // Strip leading kronolith:username:.
    return preg_replace('/^([^:]*:){2}/', '', array_keys($histories));
}

/**
 * Returns the timestamp of an operation for a given uid an action
 *
 * @param string $uid      The uid to look for.
 * @param string $action   The action to check for - add, modify, or delete.
 * @param string $calendar The calendar to search in.
 *
 * @return integer  The timestamp for this action.
 */
function _kronolith_getActionTimestamp($uid, $action, $calendar = null)
{
    require_once dirname(__FILE__) . '/base.php';

    if (empty($calendar)) {
        $calendar = Kronolith::getDefaultCalendar();
    }

    if (!array_key_exists($calendar,
                          Kronolith::listCalendars(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $history = &Horde_History::singleton();
    return $history->getActionTimestamp('kronolith:' . $calendar . ':' .
                                        $uid, $action);
}

/**
 * Imports an event represented in the specified content type.
 *
 * @param string $content      The content of the event.
 * @param string $contentType  What format is the data in? Currently supports:
 *                             <pre>
 *                             text/calendar
 *                             text/x-icalendar
 *                             text/x-vcalendar
 *                             text/x-vevent
 *                             </pre>
 * @param string $calendar     What calendar should the event be added to?
 *
 * @return mixed  The event's UID, or a PEAR_Error on failure.
 */
function _kronolith_import($content, $contentType, $calendar = null)
{
    require_once dirname(__FILE__) . '/base.php';
    global $kronolith;

    if (!isset($calendar)) {
        $calendar = Kronolith::getDefaultCalendar(PERMS_EDIT);
    }
    if (!array_key_exists($calendar,
                          Kronolith::listCalendars(false, PERMS_EDIT))) {
        return PEAR::raiseError(_("Permission Denied"));
    }
    $kronolith->open($calendar);

    switch ($contentType) {
    case 'text/calendar':
    case 'text/x-icalendar':
    case 'text/x-vcalendar':
    case 'text/x-vevent':
        require_once 'Horde/iCalendar.php';
        $iCal = new Horde_iCalendar();
        if (!is_a($content, 'Horde_iCalendar_vevent')) {
            if (!$iCal->parsevCalendar($content)) {
                return PEAR::raiseError(_("There was an error importing the iCalendar data."));
            }
        } else {
            $iCal->addComponent($content);
        }

        $components = $iCal->getComponents();
        if (count($components) == 0) {
            return PEAR::raiseError(_("No iCalendar data was found."));
        }

        $ids = array();
        foreach ($components as $content) {
            if (is_a($content, 'Horde_iCalendar_vevent')) {
                $event = &$kronolith->getEvent();
                $event->fromiCalendar($content);
                $eventId = $event->save();
                if (is_a($eventId, 'PEAR_Error')) {
                    return $eventId;
                }
                $ids[] = $event->getUID();
            }
        }
        if (count($ids) == 0) {
            return PEAR::raiseError(_("No iCalendar data was found."));
        } else if (count($ids) == 1) {
            return $ids[0];
        }
        return $ids;
    }

    return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));

}

/**
 * Exports an event, identified by UID, in the requested content type.
 *
 * @param string $uid         Identify the event to export.
 * @param string $contentType  What format should the data be in?
 *                            A string with one of:
 *                            <pre>
 *                             text/calendar (VCALENDAR 2.0. Recommended as
 *                                            this is specified in rfc2445)
 *                             text/x-vtodo    Seems to be used by horde only.
 *                                             Do we need this?
 *                             text/x-vcalendar (old VCALENDAR 1.0 format.
 *                                              Still in wide use)
 *                             text/x-icalendar
 *                            </pre>
 *
 * @return string  The requested data.
 */
function _kronolith_export($uid, $contentType)
{
    require_once dirname(__FILE__) . '/base.php';
    global $kronolith, $kronolith_shares;

    $event = $kronolith->getByUID($uid);
    if (is_a($event, 'PEAR_Error')) {
        return $event;
    }

    if (!array_key_exists($event->getCalendar(),
                          Kronolith::listCalendars(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $version = '2.0';
    switch ($contentType) {
    case 'text/x-vcalendar':
        $version = '1.0';
    case 'text/calendar':
    case 'text/x-icalendar':
    case 'text/x-vtodo':
        $share = &$kronolith_shares->getShare($event->getCalendar());

        require_once 'Horde/iCalendar.php';
        $iCal = new Horde_iCalendar($version);
        $iCal->setAttribute('X-WR-CALNAME', $share->get('name'));

        // Create a new vEvent.
        $vEvent = &$event->toiCalendar($iCal);
        $iCal->addComponent($vEvent);

        return $iCal->exportvCalendar();

    }

    return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));;

}

/**
 * Exports a calendar in the requested content type.
 *
 * @param string $calendar    The calendar to export.
 * @param string $contentType  What format should the data be in?
 *                             A string with one of:
 *                             <pre>
 *                             text/calendar (VCALENDAR 2.0. Recommended as
 *                                            this is specified in rfc2445)
 *                             text/x-vtodo    Seems to be used by horde only.
 *                                             Do we need this?
 *                             text/x-vcalendar (old VCALENDAR 1.0 format.
 *                                              Still in wide use)
 *                             text/x-icalendar
 *                             </pre>
 *
 * @return string  The iCalendar representation of the calendar.
 */
function _kronolith_exportCalendar($calendar, $contentType)
{
    require_once dirname(__FILE__) . '/base.php';
    global $kronolith, $kronolith_shares;

    if (!array_key_exists($calendar,
                          Kronolith::listCalendars(false, PERMS_READ))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    if ($kronolith->getCalendar() != $calendar) {
        $kronolith->close();
        $kronolith->open($calendar);
    }

    $events = $kronolith->listEvents(null, null);

    $version = '2.0';
    switch ($contentType) {
    case 'text/x-vcalendar':
        $version = '1.0';
    case 'text/calendar':
    case 'text/x-icalendar':
    case 'text/x-vtodo':
        $share = &$kronolith_shares->getShare($calendar);

        require_once 'Horde/iCalendar.php';
        $iCal = new Horde_iCalendar($version);
        $iCal->setAttribute('X-WR-CALNAME', String::convertCharset($share->get('name'), NLS::getCharset(), 'utf-8'));

        foreach ($events as $id) {
            $event = &$kronolith->getEvent($id);
            $vEvent = &$event->toiCalendar($iCal);
            $iCal->addComponent($vEvent);
        }

        return $iCal->exportvCalendar();
    }

    return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));

}

/**
 * Deletes an event identified by UID.
 *
 * @param string|array $uid  A single UID or an array identifying the event(s)
 *                           to delete.
 *
 * @return boolean  Success or failure.
 */
function _kronolith_delete($uid)
{
    /* Handle an array of UIDs for convenience of deleting multiple events at
     * once. */
    if (is_array($uid)) {
        foreach ($uid as $g) {
            $result = _kronolith_delete($g);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        return true;
    }

    require_once dirname(__FILE__) . '/base.php';
    global $kronolith;

    $events = $kronolith->getByUID($uid, true);
    if (is_a($events, 'PEAR_Error')) {
        return $events;
    }

    /* First try the user's own calendars. */
    $ownerCalendars = Kronolith::listCalendars(true, PERMS_DELETE);
    $event = null;
    foreach ($events as $ev) {
        if (isset($ownerCalendars[$ev->getCalendar()])) {
            $event = $ev;
            break;
        }
    }

    /* If not successful, try all calendars the user has access too. */
    if (empty($event)) {
        $deletableCalendars = Kronolith::listCalendars(false, PERMS_DELETE);
        foreach ($events as $ev) {
            if (isset($deletableCalendars[$ev->getCalendar()])) {
                $event = $ev;
                break;
            }
        }
    }

    if (empty($event)) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    return $kronolith->deleteEvent($event->getID());
}

/**
 * Replaces the event identified by UID with the content represented in the
 * specified contentType.
 *
 * @param string $uid          Idenfity the event to replace.
 * @param string $content      The content of the event.
 * @param string $contentType  What format is the data in? Currently supports:
 *                             text/calendar
 *                             text/x-icalendar
 *                             text/x-vcalendar
 *                             text/x-vevent
 *
 * @return mixed  True on success, PEAR_Error otherwise.
 */
function _kronolith_replace($uid, $content, $contentType)
{
    require_once dirname(__FILE__) . '/base.php';
    global $kronolith;

    $event = $kronolith->getByUID($uid);
    if (is_a($event, 'PEAR_Error')) {
        return $event;
    }

    if (!array_key_exists($event->getCalendar(),
                          Kronolith::listCalendars(false, PERMS_EDIT))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    switch ($contentType) {
    case 'text/calendar':
    case 'text/x-icalendar':
    case 'text/x-vcalendar':
    case 'text/x-vevent':
        if (!is_a($content, 'Horde_iCalendar_vevent')) {
            require_once 'Horde/iCalendar.php';
            $iCal = new Horde_iCalendar();
            if (!$iCal->parsevCalendar($content)) {
                return PEAR::raiseError(_("There was an error importing the iCalendar data."));
            }

            $components = $iCal->getComponents();
            switch (count($components)) {
            case 0:
                return PEAR::raiseError(_("No iCalendar data was found."));

            case 1:
                $content = $components[0];
                if (!is_a($content, 'Horde_iCalendar_vevent')) {
                    return PEAR::raiseError(_("vEvent not found."));
                }
                break;

            default:
                return PEAR::raiseError(_("Multiple iCalendar components found; only one vEvent is supported."));
            }
        }

        $event->fromiCalendar($content);
        $eventId = $event->save();
        break;

    default:
        return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));
    }

    return is_a($eventId, 'PEAR_Error') ? $eventId : true;
}

/**
 * Generates free/busy information for a given time period.
 *
 * @param integer $startstamp  The start of the time period to retrieve.
 * @param integer $endstamp    The end of the time period to retrieve.
 * @param string $calendar     The calendar to view free/busy slots for.
 *                             Defaults to the user's default calendar.
 *
 * @return Horde_iCalendar_vfreebusy  A freebusy object that covers the
 *                                    specified time period.
 */
function _kronolith_getFreeBusy($startstamp = null, $endstamp = null,
                                $calendar = null)
{
    require_once dirname(__FILE__) . '/base.php';

    if (is_null($calendar)) {
        $calendar = Kronolith::getDefaultCalendar();
    }
    // Free/Busy information is globally available; no permission
    // check is needed.

    return Kronolith::generateFreeBusy($calendar, $startstamp, $endstamp, true);
}

/**
 * Retrieves a Kronolith_Event object, given an event UID.
 *
 * @param string $uid  The event's UID.
 *
 * @return Kronolith_Event  A valid Kronolith_Event on success, or a PEAR_Error
 *                          on failure.
 */
function &_kronolith_eventFromUID($uid)
{
    require_once dirname(__FILE__) . '/base.php';
    global $kronolith;

    $event = $kronolith->getByUID($uid);
    if (is_a($event, 'PEAR_Error')) {
        return $event;
    }

    if (!array_key_exists($event->getCalendar(),
                          Kronolith::listCalendars(false, PERMS_SHOW))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    return $event;
}

/**
 * Updates an attendee's response status for a specified event.
 *
 * @param Horde_iCalender_vevent $response  A Horde_iCalender_vevent object,
 *                                          with a valid UID attribute that
 *                                          points to an existing event.
 *                                          This is typically the vEvent
 *                                          portion of an iTip meeting-request
 *                                          response, with the attendee's
 *                                          response in an ATTENDEE parameter.
 *
 * @return mixed  True on success, PEAR_Error on failure.
 */
function _kronolith_updateAttendee($response)
{
    require_once dirname(__FILE__) . '/base.php';
    global $kronolith;

    $uid = $response->getAttribute('UID');
    if (is_a($uid, 'PEAR_Error')) {
        return $uid;
    }

    $events = $kronolith->getByUID($uid, true);
    if (is_a($events, 'PEAR_Error')) {
        return $events;
    }

    /* First try the user's own calendars. */
    $ownerCalendars = Kronolith::listCalendars(true, PERMS_EDIT);
    $event = null;
    foreach ($events as $ev) {
        if (isset($ownerCalendars[$ev->getCalendar()])) {
            $event = $ev;
            break;
        }
    }

    /* If not successful, try all calendars the user has access too. */
    if (empty($event)) {
        $editableCalendars = Kronolith::listCalendars(false, PERMS_EDIT);
        foreach ($events as $ev) {
            if (isset($editableCalendars[$ev->getCalendar()])) {
                $event = $ev;
                break;
            }
        }
    }

    if (empty($event)) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    $atnames = $response->getAttribute('ATTENDEE');
    if (!is_array($atnames)) {
        $atnames = array($atnames);
    }
    $atparms = $response->getAttribute('ATTENDEE', true);

    $found = false;
    foreach ($atnames as $index => $attendee) {
        $attendee = str_replace('mailto:', '', String::lower($attendee));
        if ($event->hasAttendee($attendee)) {
            $event->addAttendee($attendee, KRONOLITH_PART_IGNORE, Kronolith::responseFromICal($atparms[$index]['PARTSTAT']));
            $found = true;
        }
    }

    $result = $event->save();
    if (is_a($result, 'PEAR_Error')) {
        return $result;
    }

    if (!$found) {
        return PEAR::raiseError(_("No attendees have been updated because none of the provided email addresses have been found in the event's attendees list."));
    }

    return true;
}

/**
 * Lists events for a given time period.
 *
 * @param integer $startstamp      The start of the time period to retrieve.
 * @param integer $endstamp        The end of the time period to retrieve.
 * @param string $calendar         The calendar to view free/busy slots for.
 *                                 Defaults to the user's default calendar.
 * @param boolean $showRecurrence  Return every instance of a recurring event?
 *                                 If false, will only return recurring events
 *                                 once inside the $startDate - $endDate range.
 *
 * @return array  An array of UIDs
 */
function _kronolith_listEvents($startstamp = null, $endstamp = null,
                               $calendar = null, $showRecurrence = true)
{
    require_once dirname(__FILE__) . '/base.php';

    if (is_null($calendar)) {
        $calendar = $GLOBALS['prefs']->getValue('default_share');
    }
    if (!array_key_exists($calendar,
                          Kronolith::listCalendars(false, PERMS_EDIT))) {
        return PEAR::raiseError(_("Permission Denied"));
    }

    return Kronolith::listEvents($startstamp, $endstamp, $calendar, $showRecurrence);
}
