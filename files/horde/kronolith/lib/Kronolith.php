<?php

require_once KRONOLITH_BASE . '/lib/Driver.php';

define('KRONOLITH_RECUR_NONE',          0);
define('KRONOLITH_RECUR_DAILY',         1);
define('KRONOLITH_RECUR_WEEKLY',        2);
define('KRONOLITH_RECUR_DAY_OF_MONTH',  3);
define('KRONOLITH_RECUR_WEEK_OF_MONTH', 4);
define('KRONOLITH_RECUR_YEARLY',        5);

define('KRONOLITH_STATUS_NONE', 0);
define('KRONOLITH_STATUS_TENTATIVE', 1);
define('KRONOLITH_STATUS_CONFIRMED', 2);
define('KRONOLITH_STATUS_CANCELLED', 3);
define('KRONOLITH_STATUS_FREE', 4);

define('KRONOLITH_RESPONSE_NONE',      1);
define('KRONOLITH_RESPONSE_ACCEPTED',  2);
define('KRONOLITH_RESPONSE_DECLINED',  3);
define('KRONOLITH_RESPONSE_TENTATIVE', 4);

define('KRONOLITH_PART_REQUIRED', 1);
define('KRONOLITH_PART_OPTIONAL', 2);
define('KRONOLITH_PART_NONE',     3);
define('KRONOLITH_PART_IGNORE',   4);

define('KRONOLITH_ITIP_REQUEST', 1);
define('KRONOLITH_ITIP_CANCEL',  2);

define('KRONOLITH_ERROR_FB_NOT_FOUND', 1);

/**
 * The Kronolith:: class provides functionality common to all of Kronolith.
 *
 * $Horde: kronolith/lib/Kronolith.php,v 1.263.2.56 2007/06/19 07:28:59 jan Exp $
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Kronolith 0.1
 * @package Kronolith
 */
class Kronolith {

    /**
     * Returns all the events that happen each day within a time period.
     *
     * @param object $startDate  The start of the time range.
     * @param object $endDate    The end of the time range.
     * @param array  $calendars  The calendars to check for events.
     *
     * @return array  The events happening in this time period.
     */
    function listEventIds($startDate = null, $endDate = null, $calendars = null)
    {
        global $kronolith;

        if (!isset($startDate)) {
            $startDate = new Horde_Date(time());
        } else {
            $startDate = Util::cloneObject(new Horde_Date($startDate));
        }
        if (!isset($endDate)) {
            $endDate = new Horde_Date(time());
        } else {
            $endDate = Util::cloneObject(new Horde_Date($endDate));
        }
        if (!isset($calendars)) {
            $calendars = $GLOBALS['display_calendars'];
        }
        if (!is_array($calendars)) {
            $calendars = array($calendars);
        }

        $eventIds = array();
        foreach ($calendars as $cal) {
            if ($kronolith->getCalendar() != $cal) {
                $kronolith->close();
                $kronolith->open($cal);
            }
            $eventIds[$cal] = $kronolith->listEvents($startDate, $endDate);
        }

        return $eventIds;
    }

    /**
     * Returns all the alarms active right on $date.
     *
     * @param object $date         The start of the time range.
     * @param array  $calendars    The calendars to check for events.
     *
     * @return array  The alarms active on $date.
     */
    function listAlarms($date, $calendars)
    {
        global $kronolith;

        $alarms = array();
        foreach ($calendars as $cal) {
            if ($kronolith->getCalendar() != $cal) {
                $kronolith->close();
                $kronolith->open($cal);
            }
            $alarms[$cal] = $kronolith->listAlarms($date);
        }

        return $alarms;
    }

    /**
     * Search for events with the given properties
     *
     * @param object $query  The search query
     *
     * @return array  The events
     */
    function search($query)
    {
        global $kronolith;

        if (!isset($query->calendars)) {
            $calendars = $GLOBALS['display_calendars'];
        }

        $events = array();
        foreach ($calendars as $cal) {
            if ($kronolith->getCalendar() != $cal) {
                $kronolith->close();
                $kronolith->open($cal);
            }
            $retevents = $kronolith->search($query);
            foreach ($retevents as $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Fetches a remote calendar into the session and return the data.
     *
     * @param string $url  The location of the remote calendar.
     *
     * @return mixed  Either the calendar data, or an error on failure.
     */
    function getRemoteCalendar($url)
    {
        $url = trim($url);

        // Treat webcal:// URLs as http://.
        if (substr($url, 0, 9) == 'webcal://') {
            $url = str_replace('webcal://', 'http://', $url);
        }

        if (empty($_SESSION['kronolith']['remote'][$url])) {
            $options['method'] = 'GET';
            $options['timeout'] = 5;
            $options['allowRedirects'] = true;

            require_once 'HTTP/Request.php';
            $http = new HTTP_Request($url, $options);
            @$http->sendRequest();
            if ($http->getResponseCode() != 200) {
                Horde::logMessage(sprintf('Failed to retrieve remote calendar: url = "%s", status = %s',
                                          $url, $http->getResponseCode()),
                                  __FILE__, __LINE__, PEAR_LOG_ERR);
                return PEAR::raiseError(sprintf(_("Could not open %s."), $url));
            }
            $_SESSION['kronolith']['remote'][$url] = $http->getResponseBody();

            // Log fetch at DEBUG level.
            Horde::logMessage(sprintf('Retrieved remote calendar for %s: url = "%s"',
                                      Auth::getAuth(), $url),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
        }

        return $_SESSION['kronolith']['remote'][$url];
    }

    /**
     * Returns all the events from a remote calendar.
     *
     * @param string $url  The url of the remote calendar.
     */
    function listRemoteEvents($url)
    {
        global $kronolith;

        $data = Kronolith::getRemoteCalendar($url);
        if (is_a($data, 'PEAR_Error')) {
            return $data;
        }

        require_once 'Horde/iCalendar.php';
        $iCal = new Horde_iCalendar();
        if (!$iCal->parsevCalendar($data)) {
            return array();
        }

        $components = $iCal->getComponents();
        $events = array();
        $count = count($components);
        for ($i = 0; $i < $count; $i++) {
            $component = $components[$i];
            if ($component->getType() == 'vEvent') {
                $event = &$kronolith->getEvent();
                $event->status = KRONOLITH_STATUS_FREE;
                $event->fromiCalendar($component);
                $event->remoteCal = $url;
                $event->eventID = $i;
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Returns an event object for an event on a remote calendar.
     *
     * This is kind of a temorary solution until we can have multiple drivers
     * in use at the same time.
     *
     * @param $url      The url of the remote calendar.
     * @param $eventId  The index of the event on the remote calendar.
     *
     * @return Kronolith_Event  The event object.
     */
    function &getRemoteEventObject($url, $eventId)
    {
        global $kronolith;

        $data = Kronolith::getRemoteCalendar($url);
        if (is_a($data, 'PEAR_Error')) {
            return $data;
        }

        require_once 'Horde/iCalendar.php';
        $iCal = new Horde_iCalendar();
        if (!$iCal->parsevCalendar($data)) {
            return array();
        }

        $components = $iCal->getComponents();
        if (isset($components[$eventId]) &&
            $components[$eventId]->getType() == 'vEvent') {
            $event = &$kronolith->getEvent();
            $event->status = KRONOLITH_STATUS_FREE;
            $event->fromiCalendar($components[$eventId]);
            $event->remoteCal = $url;
            $event->eventID = $eventId;

            return $event;
        }

        return false;
    }

    /**
     * Returns all the events that happen each day within a time period
     *
     * @param int|Horde_Date $startDate  The start of the time range.
     * @param int|Horde_Date $endDate    The end of the time range.
     * @param array $calendars           The calendars to check for events.
     * @param boolean $showRecurrence    Return every instance of a recurring
     *                                   event? If false, will only return
     *                                   recurring events once inside the
     *                                   $startDate - $endDate range.
     *
     * @return array  The events happening in this time period.
     */
    function listEvents($startDate = null, $endDate = null, $calendars = null,
                        $showRecurrence = true)
    {
        global $kronolith, $prefs, $registry;

        if (!isset($startDate)) {
            $startDate = new Horde_Date(time());
        } else {
            $startDate = Util::cloneObject(new Horde_Date($startDate));
        }
        if (!isset($endDate)) {
            $endDate = new Horde_Date(time());
        } else {
            $endDate = Util::cloneObject(new Horde_Date($endDate));
        }
        if (!isset($calendars)) {
            $calendars = $GLOBALS['display_calendars'];
        }

        $eventIds = Kronolith::listEventIds($startDate, $endDate, $calendars);

        $startOfPeriod = Util::cloneObject($startDate);
        $startOfPeriod->hour = $startOfPeriod->min = $startOfPeriod->sec = 0;
        $endOfPeriod = Util::cloneObject($endDate);
        $endOfPeriod->hour = 23;
        $endOfPeriod->min = $endOfPeriod->sec = 59;

        $results = array();
        foreach ($eventIds as $cal => $events) {
            if ($kronolith->getCalendar() != $cal) {
                $kronolith->close();
                $kronolith->open($cal);
            }
            foreach ($events as $id) {
                // FIXME: no longer need to fetch events before
                // getting recurrences.
                $event = &$kronolith->getEvent($id);

                Kronolith::_getEvents($results, $event, $startDate, $endDate,
                                      $startOfPeriod, $endOfPeriod,
                                      $showRecurrence);
            }
        }

        // Nag Tasks.
        if ($prefs->getValue('show_tasks') &&
            $registry->hasMethod('tasks/listTasks')) {
            $taskList = $registry->call('tasks/listTasks');
            if (!is_a($taskList, 'PEAR_Error')) {
                $kronolith->open(Kronolith::getDefaultCalendar(PERMS_SHOW));
                $dueEndStamp = mktime(0, 0, 0,
                                      $endDate->month,
                                      $endDate->mday + 1,
                                      $endDate->year);
                foreach ($taskList as $task) {
                    if (!empty($task['due']) &&
                        $task['due'] >= $startOfPeriod->timestamp() &&
                        $task['due'] < $dueEndStamp &&
                        empty($task['completed'])) {
                        $event = &$kronolith->getEvent();
                        $event->setTitle(sprintf(_("Due: %s"), $task['name']));
                        $event->description = $task['desc'];
                        $event->taskID = $task['task_id'];
                        $event->tasklistID = $task['tasklist_id'];
                        if ($prefs->getValue('show_task_colors') &&
                            isset($task['category'])) {
                            $event->category = isset($task['category']) ? $task['category'] : null;
                        }
                        $event->start = new Horde_Date($task['due']);
                        $event->end = new Horde_Date($task['due'] + 1);
                        $dayStamp = mktime(0, 0, 0,
                                           date('n', $task['due']),
                                           date('j', $task['due']),
                                           date('Y', $task['due']));
                        $results[$dayStamp]['_task' . $task['task_id']] = $event;
                    }
                }
            }
        }

        // Remote Calendars.
        foreach ($GLOBALS['display_remote_calendars'] as $url) {
            $events = Kronolith::listRemoteEvents($url);
            if (!is_a($events, 'PEAR_Error')) {
                $kronolith->open(Kronolith::getDefaultCalendar(PERMS_SHOW));
                foreach ($events as $event) {

                    /* Ignore events out of our period. */
                    if (
                        /* Starts after the period. */
                        $event->start->compareDateTime($endOfPeriod) > 0 ||
                        /* End before the period and doesn't recur. */
                        ($event->hasRecurType(KRONOLITH_RECUR_NONE) &&
                         $event->end->compareDateTime($startOfPeriod) < 0) ||
                        /* Recurs and ... */
                        (!$event->hasRecurType(KRONOLITH_RECUR_NONE) &&
                         /* ... we don't show recurring events or ... */
                         (!$showRecurrence ||
                          /* ... has a recurrence end before the period. */
                          ($event->hasRecurEnd() &&
                           $event->recurEnd->compareDateTime($startOfPeriod) < 0)))) {
                        continue;
                    }
                    Kronolith::_getEvents($results, $event, $startDate,
                                          $endDate, $startOfPeriod,
                                          $endOfPeriod, $showRecurrence);
                }
            }
        }

        foreach ($results as $day => $devents) {
            if (count($devents)) {
                uasort($devents, array('Kronolith', '_sortEventStartTime'));
                $results[$day] = $devents;
            }
        }

        return $results;
    }

    /**
     * Calculates recurrences of an event during a certain period.
     *
     * @access private
     */
    function _getEvents(&$results, &$event, $startDate, $endDate,
                        $startOfPeriod, $endOfPeriod,
                        $showRecurrence)
    {
        global $kronolith;

        if (!$event->hasRecurType(KRONOLITH_RECUR_NONE) && $showRecurrence) {
            // Recurring Event.

            /* We can't use the event duration here because we might cover a
             * daylight saving time switch. */
            $diff = array($event->end->year - $event->start->year,
                          $event->end->month - $event->start->month,
                          $event->end->mday - $event->start->mday,
                          $event->end->hour - $event->start->hour,
                          $event->end->min - $event->start->min);
            while ($diff[4] < 0) {
                --$diff[3];
                $diff[4] += 60;
            }
            while ($diff[3] < 0) {
                --$diff[2];
                $diff[3] += 24;
            }
            while ($diff[2] < 0) {
                --$diff[1];
                $diff[2] += Horde_Date::daysInMonth($event->start->month, $event->start->year);
            }
            while ($diff[1] < 0) {
                --$diff[0];
                $diff[1] += 12;
            }

            if ($event->start->compareDateTime($startOfPeriod) < 0) {
                // The first time the event happens was before the period
                // started. Start searching for recurrences from the start of
                // the period.
                $next = array('year' => $startDate->year,
                              'month' => $startDate->month,
                              'mday' => $startDate->mday);
            } else {
                // The first time the event happens is in the range; unless
                // there is an exception for this ocurrence, add it.
                if (!$event->hasException($event->start->year,
                                          $event->start->month,
                                          $event->start->mday)) {
                    Kronolith::_addCoverDates($results, $event, $event->start, $event->end);
                }

                // Start searching for recurrences from the day after it
                // starts.
                $next = Util::cloneObject($event->start);
                $next->mday++;
                $next->correct();
            }

            // Add all recurrences of the event.
            $next = $event->nextRecurrence($next);
            while ($next !== false && $next->compareDate($endDate) <= 0) {
                if (!$event->hasException($next->year, $next->month, $next->mday)) {
                    /* Add the event to all the days it covers. */
                    $nextEnd = Util::cloneObject($next);
                    $nextEnd->year  += $diff[0];
                    $nextEnd->month += $diff[1];
                    $nextEnd->mday  += $diff[2];
                    $nextEnd->hour  += $diff[3];
                    $nextEnd->min   += $diff[4];
                    $nextEnd->correct();
                    Kronolith::_addCoverDates($results, $event, $next, $nextEnd);
                }
                $next = $event->nextRecurrence(array('year' => $next->year,
                                                     'month' => $next->month,
                                                     'mday' => $next->mday + 1,
                                                     'hour' => $next->hour,
                                                     'min' => $next->min,
                                                     'sec' => $next->sec));
            }
        } else {
            // Event only occurs once.

            // Work out what day it starts on.
            if ($event->start->compareDateTime($startOfPeriod) < 0) {
                // It started before the beginning of the period.
                $eventStart = Util::cloneObject($startOfPeriod);
            } else {
                $eventStart = Util::cloneObject($event->start);
            }

            // Work out what day it ends on.
            if ($event->end->compareDateTime($endOfPeriod) > 0) {
                // Ends after the end of the period.
                $eventEnd = Util::cloneObject($event->end);
            } else {
                // If the event doesn't end at 12am set the end date to the
                // current end date. If it ends at 12am and does not end at
                // the same time that it starts (0 duration), set the end date
                // to the previous day's end date.
                if ($event->end->hour != 0 ||
                    $event->end->min != 0 ||
                    $event->start->compareDateTime($event->end) == 0 ||
                    $event->isAllDay()) {
                    $eventEnd = Util::cloneObject($event->end);
                } else {
                    $eventEnd = new Horde_Date(
                        array('hour' =>  23,
                              'min' =>   59,
                              'sec' =>   59,
                              'month' => $event->end->month,
                              'mday' =>  $event->end->mday - 1,
                              'year' =>  $event->end->year));
                }
            }

            // Add the event to all the days it covers.
            Kronolith::_addCoverDates($results, $event, $eventStart, $eventEnd);
        }
    }

    /**
     * Adds an event to all the days it covers.
     *
     * @param array $result           The current result list.
     * @param Kronolith_Event $event  An event object.
     * @param Horde_Date $eventStart  The event's start at the actual
     *                                recurrence.
     * @param Horde_Date $eventEnd    The event's end at the actual recurrence.
     */
    function _addCoverDates(&$results, $event, $eventStart, $eventEnd)
    {
        $i = $eventStart->mday;
        $loopDate = new Horde_Date(array('month' => $eventStart->month,
                                         'mday' => $i,
                                         'year' => $eventStart->year));
        while ($loopDate->compareDateTime($eventEnd) <= 0) {
            if (!$event->isAllDay() ||
                $loopDate->compareDateTime($eventEnd) != 0) {
                $addEvent = Util::cloneObject($event);
                $addEvent->start = $eventStart;
                $addEvent->end = $eventEnd;
                $results[$loopDate->timestamp()][$addEvent->getID()] = $addEvent;
            }
            $loopDate = new Horde_Date(
                array('month' => $eventStart->month,
                      'mday' => ++$i,
                      'year' => $eventStart->year));
            $loopDate->correct();
        }
    }

    /**
     * Returns the number of events in calendars that the current user owns.
     *
     * @return integer  The number of events that the user owns.
     */
    function countEvents()
    {
        global $kronolith;

        static $count;
        if (isset($count)) {
            return $count;
        }

        $calendars = Kronolith::listCalendars(true, PERMS_ALL);
        $current_calendar = $kronolith->getCalendar();

        $count = 0;
        foreach (array_keys($calendars) as $calendar) {
            if ($kronolith->getCalendar() != $calendar) {
                $kronolith->close();
                $kronolith->open($calendar);
            }

            // Retrieve the event list from storage.
            $count += count($kronolith->listEvents());
        }

        // Reopen last calendar.
        if ($kronolith->getCalendar() != $current_calendar) {
            $kronolith->close();
            $kronolith->open($current_calendar);
        }

        return $count;
    }

    /**
     * Returns the real name, if available, of a user.
     */
    function getUserName($uid)
    {
        static $names = array();

        if (!isset($names[$uid])) {
            require_once 'Horde/Identity.php';
            $ident = &Identity::singleton('none', $uid);
            $ident->setDefault($ident->getDefault());
            $names[$uid] = $ident->getValue('fullname');
            if (empty($names[$uid])) {
                $names[$uid] = $uid;
            }
        }

        return $names[$uid];
    }

    /**
     * Returns the email address, if available, of a user.
     */
    function getUserEmail($uid)
    {
        static $emails = array();

        if (!isset($emails[$uid])) {
            require_once 'Horde/Identity.php';
            $ident = &Identity::singleton('none', $uid);
            $ident->setDefault($ident->getDefault());
            $emails[$uid] = $ident->getValue('from_addr');
            if (empty($emails[$uid])) {
                $emails[$uid] = $uid;
            }
        }

        return $emails[$uid];
    }

    /**
     * Maps a Kronolith recurrence value to a translated string suitable for
     * display.
     *
     * @param integer $type  The recurrence value; one of the
     *                       KRONOLITH_RECUR_XXX constants.
     *
     * @return string  The translated displayable recurrence value string.
     */
    function recurToString($type)
    {
        switch ($type) {
        case KRONOLITH_RECUR_NONE:
            return _("Does not recur");

        case KRONOLITH_RECUR_DAILY:
            return _("Recurs daily");

        case KRONOLITH_RECUR_WEEKLY:
            return _("Recurs weekly");

        case KRONOLITH_RECUR_DAY_OF_MONTH:
        case KRONOLITH_RECUR_WEEK_OF_MONTH:
            return _("Recurs monthly");

        case KRONOLITH_RECUR_YEARLY:
            return _("Recurs yearly");
        }
    }

    /**
     * Maps a Kronolith meeting status string to a translated string suitable
     * for display.
     *
     * @param integer $status  The meeting status; one of the
     *                         KRONOLITH_STATUS_XXX constants.
     *
     * @return string  The translated displayable meeting status string.
     */
    function statusToString($status)
    {
        switch ($status) {
        case KRONOLITH_STATUS_CONFIRMED:
            return _("Confirmed");

        case KRONOLITH_STATUS_CANCELLED:
            return _("Cancelled");

        case KRONOLITH_STATUS_FREE:
            return _("Free");

        case KRONOLITH_STATUS_TENTATIVE:
        default:
            return _("Tentative");
        }
    }

    /**
     * Maps a Kronolith attendee response string to a translated string
     * suitable for display.
     *
     * @param integer $response  The attendee response; one of the
     *                           KRONOLITH_RESPONSE_XXX constants.
     *
     * @return string  The translated displayable attendee response string.
     */
    function responseToString($response)
    {
        switch ($response) {
        case KRONOLITH_RESPONSE_ACCEPTED:
            return _("Accepted");

        case KRONOLITH_RESPONSE_DECLINED:
            return _("Declined");

        case KRONOLITH_RESPONSE_TENTATIVE:
            return _("Tentative");

        case KRONOLITH_RESPONSE_NONE:
        default:
            return _("None");
        }
    }

    /**
     * Maps a Kronolith attendee participation string to a translated string
     * suitable for display.
     *
     * @param integer $part  The attendee participation; one of the
     *                       KRONOLITH_PART_XXX constants.
     *
     * @return string  The translated displayable attendee participation
     *                 string.
     */
    function partToString($part)
    {
        switch ($part) {
        case KRONOLITH_PART_OPTIONAL:
            return _("Optional");

        case KRONOLITH_PART_NONE:
            return _("None");

        case KRONOLITH_PART_REQUIRED:
        default:
            return _("Required");
        }
    }

    /**
     * Maps an iCalendar attendee response string to the corresponding
     * Kronolith value.
     *
     * @param string $response  The attendee response.
     *
     * @return string  The Kronolith response value.
     */
    function responseFromICal($response)
    {
        switch (String::upper($response)) {
        case 'ACCEPTED':
            return KRONOLITH_RESPONSE_ACCEPTED;

        case 'DECLINED':
            return KRONOLITH_RESPONSE_DECLINED;

        case 'TENTATIVE':
            return KRONOLITH_RESPONSE_TENTATIVE;

        case 'NEEDS-ACTION':
        default:
            return KRONOLITH_RESPONSE_NONE;
        }
    }

    /**
     * Builds the HTML for an event status widget.
     *
     * @param string $name     The name of the widget.
     * @param string $current  The selected status value.
     * @param string $any      Whether an 'any' item should be added
     *
     * @return string  The HTML <select> widget.
     */
    function buildStatusWidget($name, $current = KRONOLITH_STATUS_CONFIRMED,
                               $any = false)
    {
        $html = "<select id=\"$name\" name=\"$name\">";

        $statii = array(
            KRONOLITH_STATUS_FREE,
            KRONOLITH_STATUS_TENTATIVE,
            KRONOLITH_STATUS_CONFIRMED,
            KRONOLITH_STATUS_CANCELLED
        );

        if (!isset($current)) {
            $current = KRONOLITH_STATUS_NONE;
        }

        if ($any) {
            $html .= "<option value=\"" . KRONOLITH_STATUS_NONE . "\"";
            $html .= ($current == KRONOLITH_STATUS_NONE) ? ' selected="selected">' : '>';
            $html .= _("Any") . "</option>";
        }

        foreach ($statii as $status) {
            $html .= "<option value=\"$status\"";
            $html .= ($status == $current) ? ' selected="selected">' : '>';
            $html .= Kronolith::statusToString($status) . "</option>";
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Returns all calendars a user has access to, according to several
     * parameters/permission levels.
     *
     * This method doesn't work (obviously) if no DataTree backend has been
     * configured.
     *
     * @param boolean $owneronly   Only return calenders that this user owns?
     *                             Defaults to false.
     * @param integer $permission  The permission to filter calendars by.
     *
     * @return array  The calendar list.
     */
    function listCalendars($owneronly = false, $permission = PERMS_SHOW)
    {
        $calendars = $GLOBALS['kronolith_shares']->listShares(Auth::getAuth(), $permission, $owneronly ? Auth::getAuth() : null);
        if (is_a($calendars, 'PEAR_Error')) {
            Horde::logMessage($calendars, __FILE__, __LINE__, PEAR_LOG_ERR);
            return array();
        }

        return $calendars;
    }

    /**
     * Returns the default calendar for the current user at the specified
     * permissions level.
     */
    function getDefaultCalendar($permission = PERMS_SHOW)
    {
        global $prefs;

        $default_share = $prefs->getValue('default_share');
        $calendars = Kronolith::listCalendars(false, $permission);

        if (isset($calendars[$default_share]) ||
            $prefs->isLocked('default_share')) {
            return $default_share;
        } elseif (isset($GLOBALS['all_calendars'][Auth::getAuth()]) &&
                  $GLOBALS['all_calendars'][Auth::getAuth()]->hasPermission(Auth::getAuth(), $permission)) {
            return Auth::getAuth();
        } elseif (count($calendars)) {
            return key($calendars);
        }

        return false;
    }

    /**
     * Generates the free/busy text for $calendar. Cache it for at least an
     * hour, as well.
     *
     * @param string|array $calendar  The calendar to view free/busy slots for.
     * @param integer $startstamp     The start of the time period to retrieve.
     * @param integer $endstamp       The end of the time period to retrieve.
     * @param boolean $returnObj      Default false. Return a vFreebusy object
     *                                instead of text.
     * @param string $user            Set organizer to this user.
     *
     * @return string  The free/busy text.
     */
    function generateFreeBusy($calendar, $startstamp = null, $endstamp = null,
                              $returnObj = false, $user = null)
    {
        global $kronolith_shares, $prefs;

        require_once 'Horde/Identity.php';
        require_once 'Horde/iCalendar.php';
        require_once KRONOLITH_BASE . '/lib/version.php';

        if (!is_array($calendar)) {
            $calendar = array($calendar);
        }

        // Fetch the appropriate share and check permissions.
        $share = &$kronolith_shares->getShare($calendar[0]);
        if (is_a($share, 'PEAR_Error')) {
            return $returnObj ? $share : '';
        }

        // Default the start date to today.
        if (is_null($startstamp)) {
            $month = date('n');
            $year = date('Y');
            $day = date('j');

            $startstamp = mktime(0, 0, 0, $month, $day, $year);
        }

        // Default the end date to the start date + freebusy_days.
        if (is_null($endstamp) || $endstamp < $startstamp) {
            $month = date('n', $startstamp);
            $year = date('Y', $startstamp);
            $day = date('j', $startstamp);

            $endstamp = mktime(0, 0, 0,
                               $month,
                               $day + $prefs->getValue('freebusy_days'),
                               $year);
        }

        // Get the Identity for the owner of the share.
        $identity = &Identity::singleton('none', $user ? $user : $share->get('owner'));
        $email = $identity->getValue('from_addr');
        $cn = $identity->getValue('fullname');

        // Fetch events.
        $startDate = new Horde_Date($startstamp);
        $endDate = new Horde_Date($endstamp);
        $busy = Kronolith::listEvents($startDate, $endDate, $calendar);

        // Create the new iCalendar.
        $vCal = new Horde_iCalendar();
        $vCal->setAttribute('PRODID', '-//The Horde Project//Kronolith ' . KRONOLITH_VERSION . '//EN');
        $vCal->setAttribute('METHOD', 'PUBLISH');

        // Create new vFreebusy.
        $vFb = &Horde_iCalendar::newComponent('vfreebusy', $vCal);
        $params = array();
        if (!empty($cn)) {
            $params['CN'] = $cn;
        }
        if (!empty($email)) {
            $vFb->setAttribute('ORGANIZER', 'mailto:' . $email, $params);
        } else {
            $vFb->setAttribute('ORGANIZER', '', $params);
        }

        $vFb->setAttribute('DTSTAMP', time());
        $vFb->setAttribute('DTSTART', $startstamp);
        $vFb->setAttribute('DTEND', $endstamp);
        $vFb->setAttribute('URL', Horde::applicationUrl('fb.php?u=' . $share->get('owner'), true, -1));

        // Add all the busy periods.
        foreach ($busy as $day => $events) {
            foreach ($events as $event) {
                if ($event->hasStatus(KRONOLITH_STATUS_FREE)) {
                    continue;
                }

                $duration = $event->end->timestamp() - $event->start->timestamp();

                // Make sure that we're using the current date for recurring
                // events.
                if (!$event->hasRecurType(KRONOLITH_RECUR_NONE)) {
                    $startThisDay = mktime($event->start->hour,
                                           $event->start->min,
                                           $event->start->sec,
                                           date('n', $day),
                                           date('j', $day),
                                           date('Y', $day));
                } else {
                    $startThisDay = $event->start->timestamp();
                }
                $vFb->addBusyPeriod('BUSY', $startThisDay, null, $duration);
            }
        }

        // Remove the overlaps.
        $vFb->simplify();
        $vCal->addComponent($vFb);

        // Return the vFreebusy object if requested.
        if ($returnObj) {
            return $vFb;
        }

        // Generate the vCal file.
        return $vCal->exportvCalendar();
    }

    /**
     * Retrieves the free/busy information for a given email address, if any
     * information is available.
     *
     * @param string $email  The email address to look for.
     *
     * @return Horde_iCalendar_vfreebusy  Free/busy component on success,
     *                                    PEAR_Error on failure
     */
    function getFreeBusy($email)
    {
        global $conf, $prefs;

        require_once 'Horde/iCalendar.php';
        require_once 'Mail/RFC822.php';
        require_once 'Horde/MIME.php';

        // Properly handle RFC822-compliant email addresses.
        static $rfc822;
        if (is_null($rfc822)) {
            $rfc822 = new Mail_RFC822();
        }

        $default_domain = empty($conf['storage']['default_domain']) ? null : $conf['storage']['default_domain'];
        $res = $rfc822->parseAddressList($email, $default_domain);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }
        if (!count($res)) {
            return PEAR::raiseError(_("No valid email address found"));
        }

        $email = MIME::rfc822WriteAddress($res[0]->mailbox, $res[0]->host);

        // Check if we can retrieve a VFB from the Free/Busy URL, if one is
        // set.
        $url = Kronolith::getFreeBusyUrl($email);
        if (is_a($url, 'PEAR_Error')) {
            $url = null;
        } else {
            $url = trim($url);
        }
        if ($url) {
            require_once 'HTTP/Request.php';
            $http = new HTTP_Request($url,
                                     array('method' => 'GET',
                                           'timeout' => 5,
                                           'allowRedirects' => true));
            if (is_a($response = @$http->sendRequest(), 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("The free/busy url for %s cannot be retrieved."), $email));
            }
            if ($http->getResponseCode() == 200 &&
                $data = $http->getResponseBody()) {
                // Detect the charset of the iCalendar data.
                $contentType = $http->getResponseHeader('Content-Type');
                if ($contentType && strpos($contentType, ';') !== false) {
                    list(,$charset,) = explode(';', $contentType);
                    $charset = trim(str_replace('charset=', '', $charset));
                } else {
                    $charset = 'UTF-8';
                }

                $vCal = new Horde_iCalendar();
                $vCal->parsevCalendar($data, 'VCALENDAR', $charset);

                $vFb = &$vCal->findComponent('VFREEBUSY');
                if ($vFb !== false) {
                    return $vFb;
                }
            }
        }

        // Check storage driver.
        global $conf;
        require_once KRONOLITH_BASE . '/lib/Storage.php';
        $storage = &Kronolith_Storage::singleton();

        $fb = $storage->search($email);
        if (!is_a($fb, 'PEAR_Error')) {
            return $fb;
        } elseif ($fb->getCode() == KRONOLITH_ERROR_FB_NOT_FOUND) {
            return $url
                ? PEAR::raiseError(sprintf(_("No free/busy information found at the free/busy url of %s."), $email))
                : PEAR::raiseError(sprintf(_("No free/busy url found for %s."), $email));
        }

        // Or else return an empty VFB object
        $vCal = new Horde_iCalendar();
        $vFb = &Horde_iCalendar::newComponent('vfreebusy', $vCal);
        $vFb->setAttribute('ORGANIZER', $email);

        return $vFb;
    }

    /**
     * Searches address books for the freebusy URL for a given email address.
     *
     * @param string $email  The email address to look for.
     *
     * @return mixed  The url on success or false on failure.
     */
    function getFreeBusyUrl($email)
    {
        global $registry, $prefs;

        return $registry->call('contacts/getField', array($email, 'freebusyUrl', @unserialize($prefs->getValue('search_abook')), true));
    }

    /**
     * Sends out iTip event notifications to all attendees of a specific
     * event. Can be used to send event invitations, event updates as well as
     * event cancellations.
     *
     * @param Kronolith_Event $event      The event in question.
     * @param Notification $notification  A notification object used to show
     *                                    result status.
     * @param integer $action             The type of notification to send.
     *                                    One of the KRONOLITH_ITIP_* values.
     */
    function sendITipNotifications(&$event, &$notification, $action)
    {
        global $conf;

        $attendees = $event->getAttendees();
        if (!$attendees) {
            return;
        }

        require_once 'Horde/Identity.php';
        $ident = &Identity::singleton();

        $myemail = $ident->getValue('from_addr');
        if (!$myemail) {
            $notification->push(sprintf(_("You do not have an email address configured in your Personal Information Options. You must set one %shere%s before event notifications can be sent."), Horde::link(Util::addParameter(Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/prefs.php'), array('app' => 'horde', 'group' => 'identities'))), '</a>'), 'horde.error', array('content.raw'));
            return;
        }

        require_once 'Horde/MIME.php';
        require_once 'Horde/MIME/Headers.php';
        require_once 'Horde/MIME/Message.php';

        $myemail = explode('@', $myemail);
        $from = MIME::rfc822WriteAddress($myemail[0], isset($myemail[1]) ? $myemail[1] : '', $ident->getValue('fullname'));

        $mail_driver = $conf['mailer']['type'];
        $mail_params = $conf['mailer']['params'];
        if ($mail_driver == 'smtp' && $mail_params['auth'] &&
            empty($mail_params['username'])) {
            $mail_params['username'] = Auth::getAuth();
            $mail_params['password'] = Auth::getCredential('password');
        }

        foreach ($attendees as $email => $status) {
            // Don't bother sending an invitation/update if the recipient does
            // not need to participate, or has declined participating
            if ($status['attendance'] == KRONOLITH_PART_NONE ||
                $status['response'] == KRONOLITH_RESPONSE_DECLINED) {
                continue;
            }

            // Determine all notification-specific strings.
            switch ($action) {
            case KRONOLITH_ITIP_CANCEL:
                // Cancellation
                $method = 'CANCEL';
                $filename = 'event-cancellation.ics';
                $subject = sprintf(_("Cancelled: %s"), $event->getTitle());
                break;

            case KRONOLITH_ITIP_REQUEST:
            default:
                if ($status['response'] == KRONOLITH_RESPONSE_NONE) {
                    // Invitation.
                    $method = 'REQUEST';
                    $filename = 'event-invitation.ics';
                    $subject = $event->getTitle();
                } else {
                    // Update.
                    $method = 'ADD';
                    $filename = 'event-update.ics';
                    $subject = sprintf(_("Updated: %s."), $event->getTitle());
                }
                break;
            }

            $message = $subject . ' (';
            $message .= sprintf(_("on %s at %s"), strftime('%x', $event->start->timestamp()), date('H:i', $event->start->timestamp()));
            $message .= ")\n\n";

            if ($event->getDescription() != '') {
                $message .= _("The following is a more detailed description of the event:") . "\n\n" . $event->getDescription() . "\n\n";
            }
            $message .= _("Attached is an iCalendar file with more information about the event. If your mail client supports iTip requests you can use this file to easily update your local copy of the event.");

            if ($action == KRONOLITH_ITIP_REQUEST) {
                $attend_link = Util::addParameter(Horde::applicationUrl('attend.php', true, -1), array('i' => $event->getUID(), 'u' => $email), null, false);
                $message .= "\n\n" . sprintf(_("If your email client doesn't support iTip requests you can use one of the following links to accept or decline the event.\n\nTo accept the event:\n%s\n\nTo accept the event tentatively:\n%s\n\nTo decline the event:\n%s"), Util::addParameter($attend_link, 'a', 'accept', false), Util::addParameter($attend_link, 'a', 'tentative', false), Util::addParameter($attend_link, 'a', 'decline', false));
            }

            $mime = new MIME_Part('multipart/alternative');
            $body = new MIME_Part('text/plain', $message, NLS::getCharset());
            $body->setTransferEncoding('quoted-printable');

            require_once 'Horde/Data.php';
            require_once 'Horde/iCalendar.php';

            $iCal = new Horde_iCalendar();
            $iCal->setAttribute('METHOD', $method);
            $iCal->addComponent($event->toiCalendar($iCal));
            $ics = new MIME_Part('text/calendar', $iCal->exportvCalendar());
            $ics->setName($filename);
            $ics->setContentTypeParameter('METHOD', $method);
            $ics->setCharset(NLS::getCharset());

            $mime->addPart($body);
            $mime->addPart($ics);
            $mime = &MIME_Message::convertMimePart($mime);

            // Build the notification headers.
            $msg_headers = new MIME_Headers();
            $msg_headers->addReceivedHeader();
            $msg_headers->addMessageIdHeader();
            $msg_headers->addHeader('Date', date('r'));
            $msg_headers->addHeader('From', MIME::encode($from, NLS::getCharset()));
            $msg_headers->addHeader('To', MIME::encode($email, NLS::getCharset()));
            $msg_headers->addHeader('Subject', MIME::encode($subject, NLS::getCharset()));
            require_once KRONOLITH_BASE . '/lib/version.php';
            $msg_headers->addHeader('User-Agent', 'Kronolith ' . KRONOLITH_VERSION);
            $msg_headers->addMIMEHeaders($mime);

            $status = $mime->send($email, $msg_headers, $mail_driver, $mail_params);
            if (!is_a($status, 'PEAR_Error')) {
                $notification->push(
                    sprintf(_("The event notification to %s was successfully sent."), $email),
                    'horde.success'
                );
            } else {
                $notification->push(
                    sprintf(_("There was an error sending an event notification to %s: %s"), $email, $status->getMessage()),
                    'horde.error'
                );
            }
        }
    }

    /**
     * Sends email notifications that a event has been added, edited, or
     * deleted to users that want such notifications.
     *
     * @param Kronolith_Event $event  An event.
     * @param string $action          The event action. One of "add", "edit",
     *                                or "delete".
     */
    function sendNotification(&$event, $action)
    {
        global $conf;

        switch ($action) {
        case 'add':
            $subject = _("Event added:");
            $notification_message = _("You requested to be notified when events are added to your calendars.") . "\n\n" . _("The event \"%s\" has been added to \"%s\" calendar, which is on %s at %s.");
            break;

        case 'edit':
            $subject = _("Event edited:");
            $notification_message = _("You requested to be notified when events are edited in your calendars.") . "\n\n" . _("The event \"%s\" has been edited on \"%s\" calendar, which is on %s at %s.");
            break;

        case 'delete':
            $subject = _("Event deleted:");
            $notification_message = _("You requested to be notified when events are deleted from your calendars.") . "\n\n" . _("The event \"%s\" has been deleted from \"%s\" calendar, which was on %s at %s.");
            break;

        default:
            return PEAR::raiseError('Unknown event action: ' . $action);
        }

        require_once 'Horde/Group.php';
        require_once 'Horde/Identity.php';
        require_once 'Horde/MIME.php';
        require_once 'Horde/MIME/Headers.php';
        require_once 'Horde/MIME/Message.php';

        $groups = &Group::singleton();
        $calendar = $event->getCalendar();
        $share = &$GLOBALS['kronolith_shares']->getShare($calendar);
        $recipients = array();

        $identity = &Identity::singleton();
        $from = $identity->getDefaultFromAddress(true);

        $owner = $share->get('owner');
        if (Kronolith::_notificationPref($owner, 'owner')) {
            $recipients[$owner] = true;
        }

        foreach ($share->listUsers(PERMS_READ) as $user) {
            if (!isset($recipients[$user])) {
                $recipients[$user] = Kronolith::_notificationPref($user, 'read', $calendar);
            }
        }
        foreach ($share->listGroups(PERMS_READ) as $group) {
            $group = $groups->getGroupById($group);
            if (is_a($group, 'PEAR_Error')) {
                continue;
            }
            $group_users = $group->listAllUsers();
            if (is_a($group_users, 'PEAR_Error')) {
                Horde::logMessage($group_users, __FILE__, __LINE__, PEAR_LOG_ERR);
                continue;
            }
            foreach ($group_users as $user) {
                if (!isset($recipients[$user])) {
                    $recipients[$user] = Kronolith::_notificationPref($user, 'read', $calendar);
                }
            }
        }

        $addresses = array();
        foreach ($recipients as $user => $send) {
            if ($send) {
                $identity = &Identity::singleton('none', $user);
                $email = $identity->getValue('from_addr');
                if (strstr($email, '@')) {
                    list($mailbox, $host) = explode('@', $email);
                    $addresses[] = MIME::rfc822WriteAddress($mailbox, $host, $identity->getValue('fullname'));
                }
            }
        }

        if (!count($addresses)) {
            return;
        }

        $msg_headers = new MIME_Headers();
        $msg_headers->addMessageIdHeader();
        $msg_headers->addAgentHeader();
        $msg_headers->addHeader('Date', date('r'));
        $msg_headers->addHeader('From', $from);
        $msg_headers->addHeader('Subject', $subject . ' ' . $event->title);

        $message = "\n" . sprintf($notification_message, $event->title, $share->get('name'), strftime('%x', $event->start->timestamp()), date('H:i', $event->start->timestamp())) . "\n\n" . $event->getDescription();

        $mime = new MIME_Message();
        $body = new MIME_Part('text/plain', String::wrap($message, 76, "\n"), NLS::getCharset());

        $mime->addPart($body);
        $msg_headers->addMIMEHeaders($mime);

        $mail_driver = $conf['mailer']['type'];
        $mail_params = $conf['mailer']['params'];
        if ($mail_driver == 'smtp' && $mail_params['auth'] &&
            empty($mail_params['username'])) {
            $mail_params['username'] = Auth::getAuth();
            $mail_params['password'] = Auth::getCredential('password');
        }

        Horde::logMessage(sprintf('Sending event notifications for %s to %s', $event->title, implode(', ', $addresses)), __FILE__, __LINE__, PEAR_LOG_DEBUG);
        return $mime->send(implode(', ', $addresses), $msg_headers, $mail_driver, $mail_params);
    }

    /**
     * Returns whether a user wants email notifications for a calendar.
     *
     * @access private
     *
     * @todo This method is causing a memory leak somewhere, noticeable if
     *       importing a large amount of events.
     *
     * @param string $user      A user name.
     * @param string $mode      The check "mode". If "owner", the method checks
     *                          if the user wants notifications only for
     *                          calendars he owns. If "read", the method checks
     *                          if the user wants notifications for all
     *                          calendars he has read access to, or only for
     *                          shown calendars and the specified calendar is
     *                          currently shown.
     * @param string $calendar  The name of the calendar if mode is "read".
     *
     * @return boolean  True if the user wants notifications for the calendar.
     */
    function _notificationPref($user, $mode, $calendar = null)
    {
        $prefs = &Prefs::singleton($GLOBALS['conf']['prefs']['driver'],
                                   'kronolith', $user, '', null,
                                   false);
        $prefs->retrieve();

        $notification = $prefs->getValue('event_notification');
        switch ($notification) {
        case 'owner':
            return $mode == 'owner';
        case 'read':
            return $mode == 'read';
        case 'show':
            if ($mode == 'read') {
                $display_calendars = unserialize($prefs->getValue('display_cals'));
                return in_array($calendar, $display_calendars);
            }
        }

        return false;
    }

    function currentTimestamp()
    {
        $timestamp = (int)Util::getFormData('timestamp');
        if (!$timestamp) {
            $year = (int)Util::getFormData('year', date('Y'));
            $month = (int)Util::getFormData('month', date('n'));
            $day = (int)Util::getFormData('mday', date('d'));
            if ($week = (int)Util::getFormData('week')) {
                $month = 1;
                $day = Horde_Date::firstDayOfWeek($week, $year);
                if ($GLOBALS['prefs']->getValue('week_start_monday')) {
                    $day++;
                }
            }
            $timestamp = mktime(0, 0, 0, $month, $day, $year);
        }

        return $timestamp;
    }

    /**
     * Returns the specified permission for the current user.
     *
     * @since Kronolith 2.1
     *
     * @param string $permission  A permission, currently only 'max_events'.
     *
     * @return mixed  The value of the specified permission.
     */
    function hasPermission($permission)
    {
        global $perms;

        if (!$perms->exists('kronolith:' . $permission)) {
            return true;
        }

        $allowed = $perms->getPermissions('kronolith:' . $permission);
        if (is_array($allowed)) {
            switch ($permission) {
            case 'max_events':
                $allowed = array_reduce($allowed, create_function('$a, $b', 'return max($a, $b);'), 0);
                break;
            }
        }

        return $allowed;
    }

    function tabs()
    {
        require_once 'Horde/UI/Tabs.php';
        require_once 'Horde/Variables.php';
        $tabs = new Horde_UI_Tabs('view', Variables::getDefaultVariables());
        $tabs->preserve('timestamp', Kronolith::currentTimestamp());

        $tabs->addTab(_("Day"), Horde::applicationUrl('day.php'), 'day');
        $tabs->addTab(_("Work Week"), Horde::applicationUrl('workweek.php'), 'workweek');
        $tabs->addTab(_("Week"), Horde::applicationUrl('week.php'), 'week');
        $tabs->addTab(_("Month"), Horde::applicationUrl('month.php'), 'month');
        $tabs->addTab(_("Year"), Horde::applicationUrl('year.php'), 'year');

        echo $tabs->render(basename($_SERVER['PHP_SELF']) == 'index.php' ? $GLOBALS['prefs']->getValue('defaultview') : str_replace('.php', '', basename($_SERVER['PHP_SELF'])));
    }

    /**
     * Builds Kronolith's list of menu items.
     */
    function getMenu($returnType = 'object')
    {
        global $conf, $registry, $browser, $prefs, $print_link;

        // Check here for guest calendars so that we don't get multiple
        // messages after redirects, etc.
        if (!Auth::getAuth() && !count($GLOBALS['all_calendars'])) {
            $GLOBALS['notification']->push(_("No calendars are available to guests."));
        }

        require_once 'Horde/Menu.php';
        $menu = new Menu();

        if (Auth::getAuth()) {
            $menu->add(Horde::applicationUrl('calendars.php'), _("_My Calendars"), 'calendars.png');
        }

        if (Kronolith::getDefaultCalendar(PERMS_EDIT) &&
            (!empty($conf['hooks']['permsdenied']) ||
             Kronolith::hasPermission('max_events') === true ||
             Kronolith::hasPermission('max_events') > Kronolith::countEvents())) {
            $menu->add(Util::addParameter(Horde::applicationUrl('addevent.php'), 'url', Horde::selfUrl(true, false, true)), _("_New Event"), 'new.png');
        }

        $menu->add(Horde::applicationUrl($prefs->getValue('defaultview') . '.php'), _("_Today"), 'today.png', null, null, null, '__noselection');
        if ($browser->hasFeature('dom')) {
            Horde::addScriptFile('goto.js', 'kronolith');
            $menu->add('#', _("_Goto"), 'goto.png', null, '', 'openKGoto(\'' . Kronolith::currentTimestamp() . '\'); return false;');
        }
        $menu->add(Horde::applicationUrl('search.php'), _("_Search"), 'search.png', $registry->getImageDir('horde'));

        // Import/Export.
        if ($conf['menu']['import_export']) {
            $menu->add(Horde::applicationUrl('data.php'), _("_Import/Export"), 'data.png', $registry->getImageDir('horde'));
        }

        // Print.
        if ($conf['menu']['print'] && isset($print_link)) {
            $menu->add($print_link, _("_Print"), 'print.png', $registry->getImageDir('horde'), '_blank', 'popup(this.href); return false;');
        }

        if ($returnType == 'object') {
            return $menu;
        } else {
            return $menu->render();
        }
    }

    /**
     * Used with usort() to sort events based on their start times.
     * This function ignores the date component so recuring events can
     * be sorted correctly on a per day basis.
     */
    function _sortEventStartTime($a, $b)
    {
        $diff = (int)date('Gis', $a->start->timestamp()) - (int)date('Gis', $b->start->timestamp());
        if ($diff == 0) {
            return strcoll($a->title, $b->title);
        } else {
            return $diff;
        }
    }

}
