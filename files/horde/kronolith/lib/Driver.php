<?php
/**
 * Kronolith_Driver:: defines an API for implementing storage backends
 * for Kronolith.
 *
 * $Horde: kronolith/lib/Driver.php,v 1.116.2.63 2007/05/02 22:01:09 jan Exp $
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Kronolith 0.1
 * @package Kronolith
 */
class Kronolith_Driver {

    /**
     * A hash containing any parameters for the current driver.
     *
     * @var array
     */
    var $_params = array();

    /**
     * The current calendar.
     *
     * @var string
     */
    var $_calendar;

    /**
     * Constructor - just store the $params in our newly-created
     * object. All other work is done by open().
     *
     * @param array $params  Any parameters needed for this driver.
     */
    function Kronolith_Driver($params = array())
    {
        $this->_params = $params;
    }

    /**
     * Get the currently open calendar.
     *
     * @return string  The current calendar name.
     */
    function getCalendar()
    {
        return $this->_calendar;
    }

    /**
     * Generate a universal / unique identifier for a task. This is
     * NOT something that we expect to be able to parse into a
     * tasklist and a taskId.
     *
     * @return string  A nice unique string (should be 255 chars or less).
     */
    function generateUID()
    {
        return date('YmdHis') . '.' .
            substr(base_convert(microtime(), 10, 36), -16) .
            '@' . $GLOBALS['conf']['server']['name'];
    }

    /**
     * Rename a calendar.
     *
     * @param string $from  The current name of the calendar.
     * @param string $to    The new name of the calendar.
     *
     * @return mixed  True or a PEAR_Error on failure.
     */
    function rename($from, $to)
    {
        return true;
    }

    /**
     * Search a calendar.
     *
     * @param object Kronolith_Event $query  A Kronolith_Event object with the
     *                                       criteria to search for.
     *
     * @return mixed  An array of Kronolith_Events or a PEAR_Error.
     */
    function search($query)
    {
        /* Our default implementation first gets <em>all</em> events in
         * a specific period, and then filters based on the actual
         * values that are filled in. Drivers can optimize this
         * behavior if they have the ability. */
        $results = array();

        $events = &$this->listEvents($query->start, $query->end);
        if (is_a($events, 'PEAR_Error')) {
            return $events;
        }

        if (isset($query->start)) {
            $startTime = $query->start->timestamp();
        } else {
            $startTime = null;
        }

        if (isset($query->end)) {
            $endTime = $query->end->timestamp();
        } else {
            $endTime = null;
        }

        foreach ($events as $eventid) {
            $event = &$this->getEvent($eventid);
            if (is_a($event, 'PEAR_Error')) {
                return $event;
            }

            $evStartTime = $event->start->timestamp();
            $evEndTime = $event->end->timestamp();

            if (((($evEndTime > $startTime || !isset($startTime)) &&
                  ($evStartTime < $endTime || !isset($endTime))) ||
                 ($event->getRecurType() != KRONOLITH_RECUR_NONE && $evEndTime >= $startTime && $evStartTime <= $endTime)) &&
                (empty($query->title) || stristr($event->getTitle(), $query->title)) &&
                (empty($query->location) || stristr($event->getLocation(), $query->location)) &&
                (empty($query->description) || stristr($event->getDescription(), $query->description)) &&
                (!isset($query->category) || $event->getCategory() == $query->category) &&
                (!isset($query->status) || $event->getStatus() == $query->status)) {
                $results[] = $event;
            }
        }

        return $results;
    }

    /**
     * Find the next recurrence of $eventId that's after $afterDate.
     *
     * @param string     $eventId    The ID of the event to fetch.
     * @param Horde_Date $afterDate  Return events after this date.
     *
     * @return Horde_Date | boolean  The date of the next recurrence or false
     *                               if the event does not recur after $afterDate.
     */
    function nextRecurrence($eventId, $afterDate)
    {
        $event = &$this->getEvent($eventId);
        if (is_a($event, 'PEAR_Error')) {
            return $event;
        }

        return $event->nextRecurrence($afterDate);
    }

    /**
     * Attempts to return a concrete Kronolith_Driver instance based
     * on $driver.
     *
     * @param string $driver  The type of concrete Kronolith_Driver subclass
     *                        to return.
     *
     * @param array $params   A hash containing any additional configuration or
     *                        connection parameters a subclass might need.
     *
     * @return Kronolith_Driver  The newly created concrete Kronolith_Driver
     *                           instance, or a PEAR_Error on error.
     */
    function &factory($driver = null, $params = null)
    {
        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['calendar']['driver'];
        }

        $driver = basename($driver);

        if (is_null($params)) {
            $params = Horde::getDriverConfig('calendar', $driver);
        }

        include_once dirname(__FILE__) . '/Driver/' . $driver . '.php';
        $class = 'Kronolith_Driver_' . $driver;
        if (class_exists($class)) {
            $driver = &new $class($params);
        } else {
            $driver = PEAR::raiseError(sprintf(_("Unable to load the definition of %s."), $class));
        }

        return $driver;
    }

}

/**
 * Kronolith_Event:: defines a generic API for events.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Kronolith 0.1
 * @package Kronolith
 */
class Kronolith_Event {

    /**
     * Flag that is set to true if this event has data from either a
     * storage backend or a form or other import method.
     *
     * @var boolean
     */
    var $initialized = false;

    /**
     * Flag that is set to true if this event exists in a storage driver.
     *
     * @var boolean
     */
    var $stored = false;

    /**
     * The driver unique identifier for this event.
     *
     * @var string
     */
    var $eventID = null;

    /**
     * The UID for this event.
     *
     * @var string
     */
    var $_uid = null;

    /**
     * The user id of the creator of the event.
     *
     * @var string
     */
    var $creatorID = null;

    /**
     * The title of this event.
     *
     * @var string
     */
    var $title = '';

    /**
     * The category of this event.
     *
     * @var string
     */
    var $category = '';

    /**
     * The location this event occurs at.
     *
     * @var string
     */
    var $location = '';

    /**
     * The status of this event.
     *
     * @var integer
     */
    var $status = KRONOLITH_STATUS_CONFIRMED;

    /**
     * The description for this event
     *
     * @var string
     */
    var $description = '';

    /**
     * All the attendees of this event.
     *
     * This is an associative array where the keys are the email addresses
     * of the attendees, and the values are also associative arrays with
     * keys 'attendance' and 'response' pointing to the attendees' attendance
     * and response values, respectively.
     *
     * @var array
     */
    var $attendees = array();

    /**
     * All the key words associtated with this event.
     *
     * @var array
     */
    var $keywords = array();

    /**
     * All the exceptions from recurrence for this event.
     *
     * @var array
     */
    var $exceptions = array();

    /**
     * The start time of the event.
     *
     * @var Horde_Date
     */
    var $start;

    /**
     * The end time of the event.
     *
     * @var Horde_Date
     */
    var $end;

    /**
     * The end time of the recurrence interval.
     *
     * @var Horde_Date
     */
    var $recurEnd = null;

    /**
     * The duration of this event in minutes
     *
     * @var integer
     */
    var $durMin = 0;

    /**
     * Number of minutes before the event starts to trigger an alarm.
     *
     * @var integer
     */
    var $alarm = 0;

    /**
     * The type of recurrence this event follows. KRONOLITH_RECUR_* constant.
     *
     * @var integer
     */
    var $recurType = KRONOLITH_RECUR_NONE;

    /**
     * TODO The length of time between recurrences in seconds?
     *
     * @var integer
     */
    var $recurInterval = null;

    /**
     * Any additional recurrence data.
     *
     * @var integer
     */
    var $recurData = null;

    /**
     * The identifier of the calender this event exists on.
     *
     * @var string
     */
    var $_calendar;

    /**
     * The VarRenderer class to use for printing select elements.
     *
     * @var Horde_UI_VarRenderer
     */
    var $_varRenderer;

    /**
     * Constructor
     *
     * @param Kronolith_Driver $driver        The backend driver that this
     *                                        event is stored in.
     * @param Kronolith_Event  $eventObject   Backend specific event object
     *                                        that this will represent.
     */
    function Kronolith_Event(&$driver, $eventObject = null)
    {
        $this->_calendar = $driver->getCalendar();

        if (isset($eventObject)) {
            $this->fromDriver($eventObject);
        }
    }

    /**
     * Return a reference to a driver that's valid for this event.
     *
     * @return Kronolith_Driver  A driver that this event can use to save itself, etc.
     */
    function &getDriver()
    {
        global $kronolith;
        if ($kronolith->getCalendar() != $this->_calendar) {
            $kronolith->close();
            $kronolith->open($this->_calendar);
        }

        return $kronolith;
    }


    /**
     * Export this event in iCalendar format.
     *
     * @param Horde_iCalendar &$calendar  A Horde_iCalendar object that acts as
     *                                    a container.
     *
     * @return Horde_iCalendar_vevent  The vEvent object for this event.
     */
    function &toiCalendar(&$calendar)
    {
        $vEvent = &Horde_iCalendar::newComponent('vevent', $calendar);
        $v1 = $calendar->getAttribute('VERSION') == '1.0';

        if ($this->isAllDay()) {
            $vEvent->setAttribute('DTSTART', $this->start, array('VALUE' => 'DATE'));
            $vEvent->setAttribute('DTEND', new Horde_Date($this->end->timestamp()), array('VALUE' => 'DATE'));
        } else {
            $vEvent->setAttribute('DTSTART', $this->start);
            $vEvent->setAttribute('DTEND', $this->end);
        }

        $vEvent->setAttribute('DTSTAMP', time());
        $vEvent->setAttribute('UID', $this->_uid);
        $vEvent->setAttribute('SUMMARY', $v1 ? $this->title : String::convertCharset($this->title, NLS::getCharset(), 'utf-8'));
        $vEvent->setAttribute('TRANSP', 'OPAQUE');
        $name = Kronolith::getUserName($this->getCreatorId());
        if (!$v1) {
            $name = String::convertCharset($name, NLS::getCharset(), 'utf-8');
        }
        $vEvent->setAttribute('ORGANIZER',
                              'mailto:' . Kronolith::getUserEmail($this->getCreatorId()),
                              array('CN' => $name));
        if (!empty($this->description)) {
            $vEvent->setAttribute('DESCRIPTION', $v1 ? $this->description : String::convertCharset($this->description, NLS::getCharset(), 'utf-8'));
        }
        $categories = $this->getCategory();
        if (!empty($categories)) {
            $vEvent->setAttribute('CATEGORIES', $v1 ? $categories : String::convertCharset($categories, NLS::getCharset(), 'utf-8'));
        }
        if (!empty($this->location)) {
            $vEvent->setAttribute('LOCATION', $v1 ? $this->location : String::convertCharset($this->location, NLS::getCharset(), 'utf-8'));
        }

        // Status.
        switch ($this->getStatus()) {
        case KRONOLITH_STATUS_TENTATIVE:
            $vEvent->setAttribute('STATUS', 'TENTATIVE');
            break;
        case KRONOLITH_STATUS_CONFIRMED:
            $vEvent->setAttribute('STATUS', 'CONFIRMED');
            break;
        case KRONOLITH_STATUS_CANCELLED:
            if ($v1) {
                $vEvent->setAttribute('STATUS', 'DECLINED');
            } else {
                $vEvent->setAttribute('STATUS', 'CANCELLED');
            }
            break;
        }

        // Attendees.
        foreach ($this->getAttendees() as $email => $status) {
            $params = array();
            switch ($status['attendance']) {
            case KRONOLITH_PART_REQUIRED:
                if ($v1) {
                    $params['EXPECT'] = 'REQUIRE';
                } else {
                    $params['ROLE'] = 'REQ-PARTICIPANT';
                }
                break;

            case KRONOLITH_PART_OPTIONAL:
                if ($v1) {
                    $params['EXPECT'] = 'REQUEST';
                } else {
                    $params['ROLE'] = 'OPT-PARTICIPANT';
                }
                break;

            case KRONOLITH_PART_NONE:
                if ($v1) {
                    $params['EXPECT'] = 'FYI';
                } else {
                    $params['ROLE'] = 'NON-PARTICIPANT';
                }
                break;
            }

            switch ($status['response']) {
            case KRONOLITH_RESPONSE_NONE:
                if ($v1) {
                    $params['STATUS'] = 'NEEDS ACTION';
                    $params['RSVP'] = 'YES';
                } else {
                    $params['PARTSTAT'] = 'NEEDS-ACTION';
                    $params['RSVP'] = 'TRUE';
                }
                break;

            case KRONOLITH_RESPONSE_ACCEPTED:
                if ($v1) {
                    $params['STATUS'] = 'ACCEPTED';
                } else {
                    $params['PARTSTAT'] = 'ACCEPTED';
                }
                break;

            case KRONOLITH_RESPONSE_DECLINED:
                if ($v1) {
                    $params['STATUS'] = 'DECLINED';
                } else {
                    $params['PARTSTAT'] = 'DECLINED';
                }
                break;

            case KRONOLITH_RESPONSE_TENTATIVE:
                if ($v1) {
                    $params['STATUS'] = 'TENTATIVE';
                } else {
                    $params['PARTSTAT'] = 'TENTATIVE';
                }
                break;
            }

            if (!$v1) {
                $email = 'mailto:' . $email;
            }
            $vEvent->setAttribute('ATTENDEE', $email, $params);
        }

        // vCalendar 1.0 alarms. Has to be replaced with vAlarm components for
        // RFC 2445, if anyone ever requests.
        if (!empty($this->alarm)) {
            $vEvent->setAttribute('AALARM', $this->start->timestamp() - $this->alarm * 60);
        }

        // Recurrence.
        if ($this->recurType) {
            if ($v1) {
                $rrule = $this->toRRule10($calendar);
            } else {
                $rrule = $this->toRRule20($calendar);
            }
            if (!empty($rrule)) {
                $vEvent->setAttribute('RRULE', $rrule);
            }
        }

        // Exceptions.
        $exceptions = $this->getExceptions();
        $exdates = array();
        foreach ($exceptions as $exception) {
            if (!empty($exception)) {
                list($year, $month, $mday) = sscanf($exception, '%04d%02d%02d');
                $exdates[] = &new Horde_Date(array('year' => $year, 'month' => $month, 'mday' => $mday));
            }
        }
        if (count($exdates)) {
            $vEvent->setAttribute('EXDATE', $exdates, array('VALUE' => 'DATE'));
        }

        return $vEvent;
    }

    function toRRule10($calendar)
    {
        switch ($this->recurType) {
        case KRONOLITH_RECUR_NONE:
            return '';

        case KRONOLITH_RECUR_DAILY:
            $rrule = 'D' . $this->recurInterval;
            break;

        case KRONOLITH_RECUR_WEEKLY:
            $rrule = 'W' . $this->recurInterval;
            $vcaldays = array('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA');

            for ($i = 0; $i <= 7 ; ++$i) {
                if ($this->recurOnDay(pow(2, $i))) {
                    $rrule .= ' ' . $vcaldays[$i];
                }
            }
            break;

        case KRONOLITH_RECUR_DAY_OF_MONTH:
            $rrule = 'MD' . $this->recurInterval . ' ' . trim($this->start->mday);
            break;

        case KRONOLITH_RECUR_WEEK_OF_MONTH:
            $next = new Horde_Date($this->start);
            $next->mday += 7;
            $next->correct();

            if ($this->start->month != $next->start->month) {
                $p = 5;
            } else {
                $p = (int)($this->start->mday / 7);
                if (($this->start->mday % 7) > 0) {
                    $p++;
                }
            }

            $vcaldays = array('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA');
            $rrule = 'MP' . $this->recurInterval . ' ' . $p . '+ ' . $vcaldays[$this->start->dayOfWeek()];
            break;

        case KRONOLITH_RECUR_YEARLY:
            $rrule = 'YM' . $this->recurInterval . ' ' . trim($this->start->month);
            break;
        }

        return $this->hasRecurEnd() ?
            $rrule . ' ' . $calendar->_exportDate($this->recurEnd) :
            $rrule . ' #0';
    }

    function toRRule20($calendar)
    {
        switch ($this->recurType) {
        case KRONOLITH_RECUR_NONE:
            return '';

        case KRONOLITH_RECUR_DAILY:
            $rrule = 'FREQ=DAILY;INTERVAL='  . $this->recurInterval;
            break;

        case KRONOLITH_RECUR_WEEKLY:
            $rrule = 'FREQ=WEEKLY;INTERVAL=' . $this->recurInterval . ';BYDAY=';
            $vcaldays = array('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA');

            for ($i = $flag = 0; $i <= 7 ; ++$i) {
                if ($this->recurOnDay(pow(2, $i))) {
                    if ($flag) {
                        $rrule .= ',';
                    }
                    $rrule .= $vcaldays[$i];
                    $flag = true;
                }
            }
            break;

        case KRONOLITH_RECUR_DAY_OF_MONTH:
            $rrule = 'FREQ=MONTHLY;INTERVAL=' . $this->recurInterval;
            break;

        case KRONOLITH_RECUR_WEEK_OF_MONTH:
            $vcaldays = array('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA');
            $weekday =  date('W', mktime(0,
                                         0,
                                         0,
                                         $this->start->month,
                                         1,
                                         $this->start->year));
            $rrule = 'FREQ=MONTHLY;INTERVAL=' . $this->recurInterval
                . ';BYDAY=' . ($this->start->weekOfYear() - $weekday + 1)
                . $vcaldays[$this->start->dayOfWeek()];
            break;

        case KRONOLITH_RECUR_YEARLY:
            $rrule = 'FREQ=YEARLY;INTERVAL=' . $this->recurInterval;
            break;
        }

        return $this->hasRecurEnd()
            ? $rrule . ';UNTIL=' . $calendar->_exportDate($this->recurEnd)
            : $rrule;
    }

    /**
     * Update the properties of this event from a
     * Horde_iCalendar_vevent object.
     *
     * @param Horde_iCalendar_vevent $vEvent  The iCalendar data to update
     *                                        from.
     */
    function fromiCalendar($vEvent)
    {
        // Unique ID.
        $uid = $vEvent->getAttribute('UID');
        if (!empty($uid) && !is_a($uid, 'PEAR_Error')) {
            $this->setUID($uid);
        }

        // Title, category and description.
        $title = $vEvent->getAttribute('SUMMARY');
        if (!is_array($title) && !is_a($title, 'PEAR_Error')) {
            $this->setTitle($title);
        }
        $categories = $vEvent->getAttribute('CATEGORIES');
        if (!is_array($categories) && !is_a($categories, 'PEAR_Error')) {
            // The CATEGORY attribute is delimited by commas, so split
            // it up.
            $categories = explode(',', $categories);

            // We only support one category per event right now, so
            // arbitrarily take the last one.
            foreach ($categories as $category) {
                $this->setCategory($category);
            }
        }
        $desc = $vEvent->getAttribute('DESCRIPTION');
        if (!is_array($desc) && !is_a($desc, 'PEAR_Error')) {
            $this->setDescription($desc);
        }

        // Location.
        $location = $vEvent->getAttribute('LOCATION');
        if (!is_array($location) && !is_a($location, 'PEAR_Error')) {
            $this->setLocation($location);
        }

        // Start and end date.
        $start = $vEvent->getAttribute('DTSTART');
        if (!is_a($start, 'PEAR_Error')) {
            if (!is_array($start)) {
                // Date-Time field
                $this->start = new Horde_Date($start);
            } else {
                // Date field
                $this->start = new Horde_Date(array('year' => (int)$start['year'],
                                                    'month' => (int)$start['month'],
                                                    'mday' => (int)$start['mday']));
            }
        }
        $end = $vEvent->getAttribute('DTEND');
        if (!is_a($end, 'PEAR_Error')) {
            if (!is_array($end)) {
                // Date-Time field
                $this->end = new Horde_Date($end);
                /* All day events are transferred by many device as
                 * DSTART: YYYYMMDDT000000 DTEND: YYYYMMDDT2359(59|00)
                 * Convert accordingly */
                if (is_object($this->start)
                    && $this->start->hour == 0  && $this->start->min == 0
                    && $this->start->sec == 0
                    && $this->end->hour == 23 && $this->end->min == 59) {
                    $this->end = new Horde_Date(array(
                                   'year' => (int)$this->end->year,
                                   'month' => (int)$this->end->month,
                                   'mday' => (int)$this->end->mday + 1));
                    $this->end->correct();
                }
            } elseif (is_array($end) && !is_a($end, 'PEAR_Error')) {
                // Date field
                $this->end = new Horde_Date(array('year' => (int)$end['year'],
                                                  'month' => (int)$end['month'],
                                                  'mday' => (int)$end['mday']));
                $this->end->correct();
            }
        } else {
            $duration = $vEvent->getAttribute('DURATION');
            if (!is_array($duration) && !is_a($duration, 'PEAR_Error')) {
                $this->end = new Horde_Date($this->start->timestamp() + $duration);
            } else {
                // End date equal to start date as per RFC 2445.
                $this->end = Util::cloneObject($this->start);
                if (is_array($start)) {
                    // Date field
                    $this->end->mday++;
                    $this->end->correct();
                }
            }
        }

        /* vCalendar 1.0 alarms. */
        $alarm = $vEvent->getAttribute('AALARM');
        if (!is_array($alarm) && !is_a($alarm, 'PEAR_Error') && intval($alarm)) {
            $this->alarm = intval(($this->start->timestamp() - $alarm) / 60);
        }

        /* Attendance.
         * Importing attendance may result in confusion: editing an imported
         * copy of an event can cause invitation updates to be sent from
         * people other than the original organizer. So we don't import by
         * default. However to allow updates by SyncML replication, the custom
         * X-ATTENDEE attribute is used which has the same syntax as
         * ATTENDEE. */
        $attendee = $vEvent->getAttribute('X-ATTENDEE');
        if (!is_a($attendee, 'PEAR_Error')) {
            require_once 'Horde/MIME.php';

            if (!is_array($attendee)) {
                $attendee = array($attendee);
            }
            $params = $vEvent->getAttribute('X-ATTENDEE', true);
            if (!is_array($params)) {
                $params = array($params);
            }
            for ($i = 0; $i < count($attendee); ++$i) {
                $attendee[$i] = str_replace(array('MAILTO:', 'mailto:'), '', $attendee[$i]);
                $email = MIME::bareAddress($attendee[$i]);
                // Default according to rfc2445:
                $attendance = KRONOLITH_PART_REQUIRED;
                // vcalendar 2.0 style:
                if (!empty($params[$i]['ROLE'])) {
                    switch($params[$i]['ROLE']) {
                    case 'OPT-PARTICIPANT':
                        $attendance = KRONOLITH_PART_OPTIONAL;
                        break;

                    case 'NON-PARTICIPANT':
                        $attendance = KRONOLITH_PART_NONE;
                        break;
                    }
                }
                // vcalendar 1.0 style;
                if (!empty($params[$i]['EXPECT'])) {
                    switch($params[$i]['EXPECT']) {
                    case 'REQUEST':
                        $attendance = KRONOLITH_PART_OPTIONAL;
                        break;

                    case 'FYI':
                        $attendance = KRONOLITH_PART_NONE;
                        break;
                    }
                }
                $response = KRONOLITH_RESPONSE_NONE;
                if (empty($params[$i]['PARTSTAT']) && !empty($params[$i]['STATUS'])) {
                    $params[$i]['PARTSTAT']  = $params[$i]['STATUS'];
                }

                if (!empty($params[$i]['PARTSTAT'])) {
                    switch($params[$i]['PARTSTAT']) {
                    case 'ACCEPTED':
                        $response = KRONOLITH_RESPONSE_ACCEPTED;
                        break;

                    case 'DECLINED':
                        $response = KRONOLITH_RESPONSE_DECLINED;
                        break;

                    case 'TENTATIVE':
                        $response = KRONOLITH_RESPONSE_TENTATIVE;
                        break;
                    }
                }

                $this->addAttendee($email, $attendance, $response);
            }
        }

        // Recurrence.
        $rrule = $vEvent->getAttribute('RRULE');
        if (!is_array($rrule) && !is_a($rrule, 'PEAR_Error') && strpos($rrule, '=') !== false) {
            // Parse the recurrence rule into keys and values.
            $parts = explode(';', $rrule);
            foreach ($parts as $part) {
                list($key, $value) = explode('=', $part, 2);
                $rdata[$key] = $value;
            }

            if (isset($rdata['FREQ'])) {
                // Always default the recurInterval to 1.
                $this->setRecurInterval(isset($rdata['INTERVAL']) ? $rdata['INTERVAL'] : 1);

                $frequency = String::upper($rdata['FREQ']);
                switch ($frequency) {
                case 'DAILY':
                    $this->setRecurType(KRONOLITH_RECUR_DAILY);
                    break;

                case 'WEEKLY':
                    $this->setRecurType(KRONOLITH_RECUR_WEEKLY);
                    if (isset($rdata['BYDAY'])) {
                        $maskdays = array('SU' => HORDE_DATE_MASK_SUNDAY,
                                          'MO' => HORDE_DATE_MASK_MONDAY,
                                          'TU' => HORDE_DATE_MASK_TUESDAY,
                                          'WE' => HORDE_DATE_MASK_WEDNESDAY,
                                          'TH' => HORDE_DATE_MASK_THURSDAY,
                                          'FR' => HORDE_DATE_MASK_FRIDAY,
                                          'SA' => HORDE_DATE_MASK_SATURDAY);
                        $days = explode(',', $rdata['BYDAY']);
                        $mask = 0;
                        foreach ($days as $day) {
                            $mask |= $maskdays[$day];
                        }
                        $this->setRecurOnDay($mask);
                    } else {
                        // Recur on the day of the week of the
                        // original recurrence.
                        $maskdays = array(HORDE_DATE_SUNDAY => HORDE_DATE_MASK_SUNDAY,
                                          HORDE_DATE_MONDAY => HORDE_DATE_MASK_MONDAY,
                                          HORDE_DATE_TUESDAY => HORDE_DATE_MASK_TUESDAY,
                                          HORDE_DATE_WEDNESDAY => HORDE_DATE_MASK_WEDNESDAY,
                                          HORDE_DATE_THURSDAY => HORDE_DATE_MASK_THURSDAY,
                                          HORDE_DATE_FRIDAY => HORDE_DATE_MASK_FRIDAY,
                                          HORDE_DATE_SATURDAY => HORDE_DATE_MASK_SATURDAY);
                        $this->setRecurOnDay($maskdays[$this->start->dayOfWeek()]);
                    }
                    break;

                case 'MONTHLY':
                    if (isset($rdata['BYDAY'])) {
                        $this->setRecurType(KRONOLITH_RECUR_WEEK_OF_MONTH);
                    } else {
                        $this->setRecurType(KRONOLITH_RECUR_DAY_OF_MONTH);
                    }
                    break;

                case 'YEARLY':
                    $this->setRecurType(KRONOLITH_RECUR_YEARLY);
                    break;
                }

                if (isset($rdata['UNTIL'])) {
                    list($year, $month, $mday) = sscanf($rdata['UNTIL'], '%04d%02d%02d');
                    $this->recurEnd = &new Horde_Date(array('year' => $year,
                                                            'month' => $month,
                                                            'mday' => $mday));
                }
            } else {
                // No recurrence data - event does not recur.
                $this->setRecurType(KRONOLITH_RECUR_NONE);
            }
        }

        // Exceptions.
        $exdates = $vEvent->getAttribute('EXDATE');
        if (is_array($exdates)) {
            foreach ($exdates as $exdate) {
                if (is_array($exdate)) {
                    $this->addException((int)$exdate['year'],
                                        (int)$exdate['month'],
                                        (int)$exdate['mday']);
                }
            }
        }

        $this->initialized = true;
    }

    /**
     * Import the values for this event from an array of values.
     *
     * @param array $hash  Array containing all the values.
     */
    function fromHash($hash)
    {
        // See if it's a new event.
        if (is_null($this->getId())) {
            $this->setCreatorId(Auth::getAuth());
        }
        if (!empty($hash['title'])) {
            $this->setTitle($hash['title']);
        } else {
            return PEAR::raiseError(_("Events must have a title."));
        }
        if (!empty($hash['description'])) {
            $this->setDescription($hash['description']);
        }
        if (!empty($hash['category'])) {
            global $cManager;
            $categories = $cManager->get();
            if (!in_array($hash['category'], $categories)) {
                $cManager->add($hash['category']);
            }
            $this->setCategory($hash['category']);
        }
        if (!empty($hash['location'])) {
            $this->setLocation($hash['location']);
        }
        if (!empty($hash['keywords'])) {
            $this->setKeywords(explode(',', $hash['keywords']));
        }
        if (!empty($hash['start_date'])) {
            $date = explode('-', $hash['start_date']);
            if (empty($hash['start_time'])) {
                $time = array(0, 0, 0);
            } else {
                $time = explode(':', $hash['start_time']);
                if (count($time) == 2) {
                    $time[2] = 0;
                }
            }
            if (count($time) == 3 && count($date) == 3) {
                $this->start = &new Horde_Date(array('year' => $date[0],
                                                     'month' => $date[1],
                                                     'mday' => $date[2],
                                                     'hour' => $time[0],
                                                     'min' => $time[1],
                                                     'sec' => $time[2]));
            }
        } else {
            return PEAR::raiseError(_("Events must have a start date."));
        }
        if (empty($hash['duration'])) {
            if (empty($hash['end_date'])) {
                $hash['end_date'] = $hash['start_date'];
            }
            if (empty($hash['end_time'])) {
                $hash['end_time'] = $hash['start_time'];
            }
        } else {
            $weeks = str_replace('W', '', $hash['duration'][1]);
            $days = str_replace('D', '', $hash['duration'][2]);
            $hours = str_replace('H', '', $hash['duration'][4]);
            $minutes = isset($hash['duration'][5]) ? str_replace('M', '', $hash['duration'][5]) : 0;
            $seconds = isset($hash['duration'][6]) ? str_replace('S', '', $hash['duration'][6]) : 0;
            $hash['duration'] = ($weeks * 60 * 60 * 24 * 7) + ($days * 60 * 60 * 24) + ($hours * 60 * 60) + ($minutes * 60) + $seconds;
            $this->end = &new Horde_Date($this->start->timestamp() + $hash['duration']);
        }
        if (!empty($hash['end_date'])) {
            $date = explode('-', $hash['end_date']);
            if (empty($hash['end_time'])) {
                $time = array(0, 0, 0);
            } else {
                $time = explode(':', $hash['end_time']);
                if (count($time) == 2) {
                    $time[2] = 0;
                }
            }
            if (count($time) == 3 && count($date) == 3) {
                $this->end = &new Horde_Date(array('year' => $date[0],
                                                   'month' => $date[1],
                                                   'mday' => $date[2],
                                                   'hour' => $time[0],
                                                   'min' => $time[1],
                                                   'sec' => $time[2]));
            }
        }
        if (!empty($hash['alarm'])) {
            $this->setAlarm($hash['alarm']);
        } elseif (!empty($hash['alarm_date']) &&
                  !empty($hash['alarm_time'])) {
            $date = explode('-', $hash['alarm_date']);
            $time = explode(':', $hash['alarm_time']);
            if (count($time) == 2) {
                $time[2] = 0;
            }
            if (count($time) == 3 && count($date) == 3) {
                $this->setAlarm(($this->start->timestamp() - mktime($time[0], $time[1], $time[2], $date[1], $date[2], $date[0])) / 60);
            }
        }
        if (!empty($hash['recur_type'])) {
            $this->setRecurType($hash['recur_type']);
            if (!empty($hash['recur_end_date'])) {
                $date = explode('-', $hash['recur_end_date']);
                $this->recurEnd = &new Horde_Date(array('year' => $date[0], 'month' => $date[1], 'mday' => $date[2]));
            }
            if (!empty($hash['recur_interval'])) {
                $this->setRecurInterval($hash['recur_interval']);
            }
            if (!empty($hash['recur_data'])) {
                $this->setRecurOnDay($hash['recur_data']);
            }
        }

        $this->initialized = true;
    }

    /**
     * Save changes to this event.
     *
     * @return mixed  True or a PEAR_Error on failure.
     */
    function save()
    {
        if (!$this->isInitialized()) {
            return PEAR::raiseError('Event not yet initialized');
        }

        $this->toDriver();
        $driver = &$this->getDriver();
        return $driver->saveEvent($this);
    }

    /**
     * Add an exception to a recurring event.
     *
     * @param integer $year   The year of the execption.
     * @param integer $month  The month of the execption.
     * @param integer $mday   The day of the month of the exception.
     */
    function addException($year, $month, $mday)
    {
        $this->exceptions[] = sprintf('%04d%02d%02d', $year, $month, $mday);
    }

    /**
     * Check if an exception exists for a given reccurence of an event.
     *
     * @param integer $year   The year of the reucrance.
     * @param integer $month  The month of the reucrance.
     * @param integer $mday   The day of the month of the reucrance.
     *
     * @return boolean  True if an exception exists for the given date.
     */
    function hasException($year, $month, $mday)
    {
        return in_array(sprintf('%04d%02d%02d', $year, $month, $mday), $this->getExceptions());
    }

    /**
     * Retrieve all the exceptions for this event.
     *
     * @return array  Array containing the dates of all the exceptions in
     *                YYYYMMDD form.
     */
    function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * TODO
     */
    function isInitialized()
    {
        return $this->initialized;
    }

    /**
     * TODO
     */
    function isStored()
    {
        return $this->stored;
    }

    /**
     * Check if the current event is already present in the calendar.
     * Do the check based on the uid.
     *
     * @return boolean  True if event exists, false otherwise.
     */
    function exists()
    {
        if (!isset($this->_uid) || !isset($this->_calendar)) {
            return false;
        }

        $eventID = $GLOBALS['kronolith']->exists($this->_uid, $this->_calendar);
        if (is_a($eventID, 'PEAR_Error') || !$eventID) {
            return false;
        } else {
            $this->eventID = $eventID;
            return true;
        }
    }

    /**
     * Check if this event recurs on a given day of the week.
     *
     * @param integer $dayMask  A mask specifying the day(s) to check.
     *
     * @return boolean  True if this event recurs on the given day(s).
     */
    function recurOnDay($dayMask)
    {
        return ($this->recurData & $dayMask);
    }

    /**
     * Specify the days this event recurs on.
     *
     * @param integer $dayMask  A mask specifying the day(s) to recur on.
     */
    function setRecurOnDay($dayMask)
    {
        $this->recurData = $dayMask;
    }

    /**
     * Return the days this event recurs on.
     *
     * @return integer  A mask specifying the day(s) this event recurs on.
     */
    function getRecurOnDays()
    {
        return $this->recurData;
    }

    function getRecurType()
    {
        return $this->recurType;
    }

    function hasRecurType($recurrence)
    {
        return ($recurrence === $this->recurType);
    }

    function setRecurType($recurrence)
    {
        $this->recurType = $recurrence;
    }

    /**
     * Set the length of time between recurrences of this event.
     *
     * @param integer $interval  The number of seconds between recurrences.
     */
    function setRecurInterval($interval)
    {
        if ($interval > 0) {
            $this->recurInterval = $interval;
        }
    }

    /**
     * Retrieve the length of time between recurrences fo this event.
     *
     * @return integer  The number of seconds between recurrences.
     */
    function getRecurInterval()
    {
        return $this->recurInterval;
    }

    function hasRecurEnd()
    {
        return (isset($this->recurEnd) && isset($this->recurEnd->year) && $this->recurEnd->year != 9999);
    }

    /**
     * Retrieve the locally unique identifier for this event.
     *
     * @return string  The local identifier for this event.
     */
    function getId()
    {
        return $this->eventID;
    }

    /**
     * Set the locally unique identifier for this event.
     *
     * @param string $eventId  The local identifier for this event.
     */
    function setId($eventId)
    {
        if (substr($eventId, 0, 10) == 'kronolith:') {
            $eventId = substr($eventId, 10);
        }
        $this->eventID = $eventId;
    }

    /**
     * Retrieve the global UID for this event.
     *
     * @return string  The global UID for this event.
     */
    function getUID()
    {
        return $this->_uid;
    }

    /**
     * Set the global UID for this event.
     *
     * @param string $uid  The global UID for this event.
     */
    function setUID($uid)
    {
        $this->_uid = $uid;
    }

    /**
     * Retrieve the id of the user who created the event.
     *
     * @return string  The creator id
     */
    function getCreatorId()
    {
        return !empty($this->creatorID) ? $this->creatorID : Auth::getAuth();
    }

    /**
     * Set the id of the creator of the event.
     *
     * @param string $creatorID  The user id for the user who created the event
     */
    function setCreatorId($creatorID)
    {
        $this->creatorID = $creatorID;
    }

    /**
     * Retrieve the title of this event.
     *
     * @return string  The title of this event.
     */
    function getTitle()
    {
        if (isset($this->taskID) ||
            isset($this->contactID) ||
            isset($this->remoteCal)) {
            return !empty($this->title) ? $this->title : _("[Unnamed event]");
        }

        if (!$this->isInitialized()) {
            return '';
        }

        if (isset($GLOBALS['all_calendars'][$this->getCalendar()]) &&
            !is_a($share = $GLOBALS['all_calendars'][$this->getCalendar()], 'PEAR_Error') &&
            $share->hasPermission(Auth::getAuth(), PERMS_READ, $this->getCreatorId())) {
            return !empty($this->title) ? $this->title : _("[Unnamed event]");
        } else {
            global $prefs;
            return sprintf(_("Event from %s to %s"),
                           date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia', $this->start->timestamp()),
                           date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia', $this->end->timestamp()));
        }
    }

    /**
     * Set the title of this event.
     *
     * @param string  The new title for this event.
     */
    function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Retieve the description of this event.
     *
     * @return string  The description of this event.
     */
    function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the description of this event.
     *
     * @param string $description  The new description for this event.
     */
    function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Set the category of this event.
     *
     * @param string $category  The category of this event.
     */
    function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * Retrieve the category of this event.
     *
     * @return string  The category of this event.
     */
    function getCategory()
    {
        return $this->category;
    }

    /**
     * Set the location this event occurs at.
     *
     * @param string $location  The new location for this event.
     */
    function setLocation($location)
    {
        $this->location = $location;
    }

    /**
     * Retrieve the location this event occurs at.
     *
     * @return string  The location of this event.
     */
    function getLocation()
    {
        return $this->location;
    }

    /**
     * Retrieve the event status.
     *
     * @return integer  The status of this event.
     */
    function getStatus()
    {
        return $this->status;
    }

    /**
     * Checks whether the events status is the same as the specified value.
     *
     * @param integer $status  The status value to check against.
     *
     * @return boolean  True if the events status is the same as $status.
     */
    function hasStatus($status)
    {
        return ($status == $this->status);
    }

    /**
     * Set the status of this event.
     *
     * @param integer $status  The new event status.
     */
    function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Sets the entire attendee array.
     *
     * @param array $attendees  The new attendees array. This should be of the
     *                          correct format to avoid driver problems.
     */
    function setAttendees($attendees)
    {
        $this->attendees = array_change_key_case($attendees);
    }

    /**
     * Adds a new attendee to the current event. This will overwrite an
     * existing attendee if one exists with the same email address.
     *
     * @param string $email        The email address of the attendee.
     * @param integer $attendance  The attendance code of the attendee.
     * @param integer $response    The response code of the attendee.
     */
    function addAttendee($email, $attendance, $response)
    {
        $email = String::lower($email);
        if ($attendance == KRONOLITH_PART_IGNORE) {
            if (isset($this->attendees[$email])) {
                $attendance = $this->attendees[$email]['attendance'];
            } else {
                $attendance = KRONOLITH_PART_REQUIRED;
            }
        }

        $this->attendees[$email] = array(
            'attendance' => $attendance,
            'response' => $response
        );
    }

    /**
     * Removes the specified attendee from the current event.
     *
     * @param string $email  The email address of the attendee.
     */
    function removeAttendee($email)
    {
        $email = String::lower($email);
        if (isset($this->attendees[$email])) {
            unset($this->attendees[$email]);
        }
    }

    /**
     * Returns the entire attendees array.
     *
     * @return array  A copy of the attendees array.
     */
    function getAttendees()
    {
        return $this->attendees;
    }

    /**
     * Checks to see whether the specified attendee is associated with the
     * current event.
     *
     * @param string $email  The email address of the attendee.
     *
     * @return boolean  True if the specified attendee is present for this
     *                  event.
     */
    function hasAttendee($email)
    {
        $email = String::lower($email);
        return isset($this->attendees[$email]);
    }

    function setKeywords($keywords)
    {
        $this->keywords = $keywords;
    }

    function getKeywords()
    {
        return $this->keywords;
    }

    function hasKeyword($keyword)
    {
        return in_array($keyword, $this->keywords);
    }

    function isAllDay()
    {
        return ($this->start->hour == 0 && $this->start->min == 0 && $this->start->sec == 0 &&
                $this->end->hour == 0 && $this->end->min == 0 && $this->start->sec == 0 &&
                ($this->end->mday > $this->start->mday ||
                 $this->end->month > $this->start->month ||
                 $this->end->year > $this->start->year));
    }

    function setAlarm($alarm)
    {
        $this->alarm = $alarm;
    }

    function getAlarm()
    {
        return $this->alarm;
    }

    function readForm()
    {
        global $prefs, $cManager;

        // See if it's a new event.
        if (!$this->isInitialized()) {
            $this->setCreatorId(Auth::getAuth());
        }

        // Basic fields.
        $this->setTitle(Util::getFormData('title', $this->title));
        $this->setDescription(Util::getFormData('description', $this->description));
        $this->setLocation(Util::getFormData('location', $this->location));
        $this->setKeywords(Util::getFormData('keywords', $this->keywords));

        // Category.
        if ($new_category = Util::getFormData('new_category')) {
            $new_category = $cManager->add($new_category);
            if ($new_category) {
                $category = $new_category;
            }
        } else {
            $category = Util::getFormData('category', $this->category);
        }
        $this->setCategory($category);

        // Status.
        $this->setStatus(Util::getFormData('status', $this->status));

        // Attendees.
        if (isset($_SESSION['attendees']) && is_array($_SESSION['attendees'])) {
            $this->setAttendees($_SESSION['attendees']);
        }

        // Event start.
        $start = Util::getFormData('start');
        $start_year = $start['year'];
        $start_month = $start['month'];
        $start_day = $start['day'];
        $start_hour = Util::getFormData('start_hour');
        $start_min = Util::getFormData('start_min');
        $am_pm = Util::getFormData('am_pm');

        if (!$prefs->getValue('twentyFour')) {
            if ($am_pm == 'PM') {
                if ($start_hour != 12) {
                    $start_hour += 12;
                }
            } elseif ($start_hour == 12) {
                $start_hour = 0;
            }
        }

        if (Util::getFormData('end_or_dur') == 1) {
            if (Util::getFormData('whole_day') == 1) {
                $start_hour = 0;
                $start_min = 0;
                $dur_day = 0;
                $dur_hour = 24;
                $dur_min = 0;
            } else {
                $dur_day = (int)Util::getFormData('dur_day');
                $dur_hour = (int)Util::getFormData('dur_hour');
                $dur_min = (int)Util::getFormData('dur_min');
            }
        }

        $this->start = &new Horde_Date(array('hour' => $start_hour,
                                             'min' => $start_min,
                                             'month' => $start_month,
                                             'mday' => $start_day,
                                             'year' => $start_year));
        $this->start->correct();

        if (Util::getFormData('end_or_dur') == 1) {
            // Event duration.
            $this->end = &new Horde_Date(array('hour' => $start_hour + $dur_hour,
                                               'min' => $start_min + $dur_min,
                                               'month' => $start_month,
                                               'mday' => $start_day + $dur_day,
                                               'year' => $start_year));
            $this->end->correct();
        } else {
            // Event end.
            $end = Util::getFormData('end');
            $end_year = $end['year'];
            $end_month = $end['month'];
            $end_day = $end['day'];
            $end_hour = Util::getFormData('end_hour');
            $end_min = Util::getFormData('end_min');
            $end_am_pm = Util::getFormData('end_am_pm');

            if (!$prefs->getValue('twentyFour')) {
                if ($end_am_pm == 'PM') {
                    if ($end_hour != 12) {
                        $end_hour += 12;
                    }
                } elseif ($end_hour == 12) {
                    $end_hour = 0;
                }
            }

            $this->end = &new Horde_Date(array('hour' => $end_hour,
                                               'min' => $end_min,
                                               'month' => $end_month,
                                               'mday' => $end_day,
                                               'year' => $end_year));
            $this->end->correct();
            if ($this->end->timestamp() < $this->start->timestamp()) {
                $this->end = Util::cloneObject($this->start);
            }
        }

        // Alarm.
        if (Util::getFormData('alarm') == 1) {
            $this->setAlarm(Util::getFormData('alarm_value') * Util::getFormData('alarm_unit'));
        } else {
            $this->setAlarm(0);
        }

        // Recurrence.
        $recur = Util::getFormData('recur');
        if (!is_null($recur) && $recur !== '') {
            if (Util::getFormData('recur_enddate_type') == 'none') {
                $this->recurEnd = null;
            } else {
                $recur_enddate = Util::getFormData('recur_enddate');
                $recur_enddate_year = $recur_enddate['year'];
                $recur_enddate_month = $recur_enddate['month'];
                $recur_enddate_day = $recur_enddate['day'];

                $this->recurEnd = &new Horde_Date(array('hour' => 1, 'min' => 1, 'sec' => 1,
                                                        'month' => $recur_enddate_month,
                                                        'mday' => $recur_enddate_day,
                                                        'year' => $recur_enddate_year));
                $this->recurEnd->correct();
            }

            $this->setRecurType($recur);
            switch ($recur) {
            case KRONOLITH_RECUR_DAILY:
                $this->setRecurInterval(Util::getFormData('recur_daily_interval', 1));
                break;

            case KRONOLITH_RECUR_WEEKLY:
                $weekly = Util::getFormData('weekly');
                $weekdays = 0;
                if (is_array($weekly)) {
                    foreach ($weekly as $day) {
                        $weekdays |= $day;
                    }
                }

                if ($weekdays == 0) {
                    // Sunday starts at 0.
                    switch ($this->start->dayOfWeek()) {
                    case 0: $weekdays |= HORDE_DATE_MASK_SUNDAY; break;
                    case 1: $weekdays |= HORDE_DATE_MASK_MONDAY; break;
                    case 2: $weekdays |= HORDE_DATE_MASK_TUESDAY; break;
                    case 3: $weekdays |= HORDE_DATE_MASK_WEDNESDAY; break;
                    case 4: $weekdays |= HORDE_DATE_MASK_THURSDAY; break;
                    case 5: $weekdays |= HORDE_DATE_MASK_FRIDAY; break;
                    case 6: $weekdays |= HORDE_DATE_MASK_SATURDAY; break;
                    }
                }

                $this->setRecurInterval(Util::getFormData('recur_weekly_interval', 1));
                $this->setRecurOnDay($weekdays);
                break;

            case KRONOLITH_RECUR_DAY_OF_MONTH:
                $this->setRecurInterval(Util::getFormData('recur_day_of_month_interval', 1));
                break;

            case KRONOLITH_RECUR_WEEK_OF_MONTH:
                $this->setRecurInterval(Util::getFormData('recur_week_of_month_interval', 1));
                break;

            case KRONOLITH_RECUR_YEARLY:
                $this->setRecurInterval(Util::getFormData('recur_yearly_interval', 1));
                break;
            }
        }

        $this->initialized = true;
    }

    function getDuration()
    {
        static $duration = null;
        if (isset($duration)) {
            return $duration;
        }

        if ($this->isInitialized()) {
            require_once 'Date/Calc.php';
            $dur_day_match = Date_Calc::dateDiff($this->start->mday,
                                                 $this->start->month,
                                                 $this->start->year,
                                                 $this->end->mday,
                                                 $this->end->month,
                                                 $this->end->year);
            $dur_hour_match = $this->end->hour - $this->start->hour;
            $dur_min_match = $this->end->min - $this->start->min;
            while ($dur_min_match < 0) {
                $dur_min_match += 60;
                --$dur_hour_match;
            }
            while ($dur_hour_match < 0) {
                $dur_hour_match += 24;
                --$dur_day_match;
            }
            if ($dur_hour_match == 0 && $dur_min_match == 0
                && $this->end->mday - $this->start->mday == 1) {
                $dur_day_match = 0;
                $dur_hour_match = 23;
                $dur_min_match = 60;
                $whole_day_match = true;
            } else {
                $whole_day_match = false;
            }
        } else {
            $dur_day_match = 0;
            $dur_hour_match = 1;
            $dur_min_match = 0;
            $whole_day_match = false;
        }

        $duration = new stdClass;
        $duration->day = $dur_day_match;
        $duration->hour = $dur_hour_match;
        $duration->min = $dur_min_match;
        $duration->wholeDay = $whole_day_match;

        return $duration;
    }

    function html($property)
    {
        global $prefs;

        $options = array();
        $attributes = '';
        $sel = false;

        switch ($property) {
        case 'start[year]':
            return  '<input name="' . $property . '" value="' . $this->start->year .
                '" type="text" onchange="' . $this->js($property) .
                '" id="' . $property . '" size="4" maxlength="4" />';

        case 'start[month]':
            $sel = $this->start->month;
            for ($i = 1; $i < 13; ++$i) {
                $options[$i] = strftime('%b', mktime(1, 1, 1, $i, 1));
            }
            $attributes = ' onchange="' . $this->js($property) . '"';
            break;

        case 'start[day]':
            $sel = $this->start->mday;
            for ($i = 1; $i < 32; ++$i) {
                $options[$i] = $i;
            }
            $attributes = ' onchange="' . $this->js($property) . '"';
            break;

        case 'start_hour':
            $sel = (int)date($prefs->getValue('twentyFour') ? 'G' : 'g', $this->start->timestamp());
            $hour_min = $prefs->getValue('twentyFour') ? 0 : 1;
            $hour_max = $prefs->getValue('twentyFour') ? 24 : 13;
            for ($i = $hour_min; $i < $hour_max; ++$i) {
                $options[$i] = $i;
            }
            $attributes = ' onchange="document.event.whole_day.checked = false; updateEndDate();"';
            break;

        case 'start_min':
            $sel = sprintf('%02d', $this->start->min);
            for ($i = 0; $i < 12; ++$i) {
                $min = sprintf('%02d', $i * 5);
                $options[$min] = $min;
            }
            $attributes = ' onchange="document.event.whole_day.checked = false; updateEndDate();"';
            break;

        case 'end[year]':
            return  '<input name="' . $property . '" value="' . $this->end->year .
                '" type="text" onchange="' . $this->js($property) .
                '" id="' . $property . '" size="4" maxlength="4" />';

        case 'end[month]':
            $sel = $this->isInitialized() ? $this->end->month : $this->start->month;
            for ($i = 1; $i < 13; ++$i) {
                $options[$i] = strftime('%b', mktime(1, 1, 1, $i, 1));
            }
            $attributes = ' onchange="' . $this->js($property) . '"';
            break;

        case 'end[day]':
            $sel = $this->isInitialized() ? $this->end->mday : $this->start->mday;
            for ($i = 1; $i < 32; ++$i) {
                $options[$i] = $i;
            }
            $attributes = ' onchange="' . $this->js($property) . '"';
            break;

        case 'end_hour':
            $sel = $this->isInitialized() ?
                (int)date($prefs->getValue('twentyFour') ? 'G' : 'g', $this->end->timestamp()) :
                (int)date($prefs->getValue('twentyFour') ? 'G' : 'g', $this->start->timestamp()) + 1;
            $hour_min = $prefs->getValue('twentyFour') ? 0 : 1;
            $hour_max = $prefs->getValue('twentyFour') ? 24 : 13;
            for ($i = $hour_min; $i < $hour_max; ++$i) {
                $options[$i] = $i;
            }
            $attributes = ' onchange="updateDuration(); document.event.end_or_dur[0].checked = true"';
            break;

        case 'end_min':
            $sel = $this->isInitialized() ? $this->end->min : $this->start->min;
            $sel = sprintf('%02d', $sel);
            for ($i = 0; $i < 12; ++$i) {
                $min = sprintf('%02d', $i * 5);
                $options[$min] = $min;
            }
            $attributes = ' onchange="updateDuration(); document.event.end_or_dur[0].checked = true"';
            break;

        case 'dur_day':
            $dur = $this->getDuration();
            $sel = $dur->day;
            for ($i = 0; $i < 366; ++$i) {
                $options[$i] = $i;
            }
            $attributes = ' onchange="document.event.whole_day.checked = false; updateEndDate(); document.event.end_or_dur[1].checked = true;"';
            break;

        case 'dur_hour':
            $dur = $this->getDuration();
            $sel = $dur->hour;
            for ($i = 0; $i < 24; ++$i) {
                $options[$i] = $i;
            }
            $attributes = ' onchange="document.event.whole_day.checked = false; updateEndDate(); document.event.end_or_dur[1].checked = true;"';
            break;

        case 'dur_min':
            $dur = $this->getDuration();
            $sel = $dur->min;
            for ($i = 0; $i < 13; ++$i) {
                $min = sprintf('%02d', $i * 5);
                $options[$min] = $min;
            }
            $attributes = ' onchange="document.event.whole_day.checked = false;updateEndDate();document.event.end_or_dur[1].checked=true"';
            break;

        case 'recur_enddate[year]':
            if ($this->isInitialized()) {
                $end = $this->hasRecurEnd() ? $this->recurEnd->year : $this->end->year;
            } else {
                $end = $this->start->year;
            }
            return  '<input name="' . $property . '" value="' . $end .
                '" type="text" onchange="' . $this->js($property) .
                '" id="' . $property . '" size="4" maxlength="4" />';

        case 'recur_enddate[month]':
            if ($this->isInitialized()) {
                $sel = $this->hasRecurEnd() ? $this->recurEnd->month : $this->end->month;
            } else {
                $sel = $this->start->month;
            }
            for ($i = 1; $i < 13; ++$i) {
                $options[$i] = strftime('%b', mktime(1, 1, 1, $i, 1));
            }
            $attributes = ' onchange="' . $this->js($property) . '"';
            break;

        case 'recur_enddate[day]':
            if ($this->isInitialized()) {
                $sel = $this->hasRecurEnd() ? $this->recurEnd->mday : $this->end->mday;
            } else {
                $sel = $this->start->mday;
            }
            for ($i = 1; $i < 32; ++$i) {
                $options[$i] = $i;
            }
            $attributes = ' onchange="' . $this->js($property) . '"';
            break;
        }

        if (!$this->_varRenderer) {
            require_once 'Horde/UI/VarRenderer.php';
            $this->_varRenderer = &Horde_UI_VarRenderer::factory('html');
        }

        return '<select name="' . $property . '"' . $attributes . ' id="' . $property . '">' .
            $this->_varRenderer->_selectOptions($options, $sel) .
            '</select>';
    }

    function js($property)
    {
        switch ($property) {
        case 'start[month]':
        case 'start[year]':
        case 'start[day]':
        case 'start':
            return 'updateWday(\'start_wday\'); document.event.whole_day.checked = false; updateEndDate();';

        case 'end[month]':
        case 'end[year]':
        case 'end[day]':
        case 'end':
            return 'updateWday(\'end_wday\'); updateDuration(); document.event.end_or_dur[0].checked = true;';

        case 'recur_enddate[month]':
        case 'recur_enddate[year]':
        case 'recur_enddate[day]':
        case 'recur_enddate':
            return 'updateWday(\'recur_end_wday\'); document.event.recur_enddate_type[1].checked = true;';
        }
    }

    function getLink($timestamp = 0, $icons = true, $from_url = null)
    {
        global $print_view, $prefs, $registry, $cManager;

        if ($from_url === null) {
            $from_url = Horde::selfUrl(true, false, true);
        }

        if (isset($this->remoteCal)) {
            $share = false;
            $url = Util::addParameter('viewevent.php',
                                      array('eventID' => $this->eventID,
                                            'calendar' => '**remote',
                                            'remoteCal' => $this->remoteCal,
                                            'timestamp' => $timestamp,
                                            'url' => $from_url));
            $url = Horde::applicationUrl($url);
            $link = Horde::linkTooltip($url, $this->getTitle(),
                                       $this->getStatusClass(), '', '',
                                       $this->getTooltip());
        } else {
            $share =& $GLOBALS['all_calendars'][$this->getCalendar()];
            if (is_a($share, 'PEAR_Error') ||
                !$share->hasPermission(Auth::getAuth(), PERMS_READ,
                                       $this->getCreatorId())) {
                $link = '';
            } elseif (isset($this->eventID)) {
                $url = Util::addParameter('viewevent.php',
                                          array('eventID' => $this->eventID,
                                                'calendar' => $this->getCalendar(),
                                                'timestamp' => $timestamp,
                                                'url' => $from_url));
                $url = Horde::applicationUrl($url);
                $link = Horde::linkTooltip($url, $this->title,
                                           $this->getStatusClass(), '', '',
                                           $this->getTooltip());
            } elseif (isset($this->taskID)) {
                $link = $GLOBALS['registry']->link('tasks/show',
                                                   array('task' => $this->taskID,
                                                         'tasklist' => $this->tasklistID));
                $link = Horde::link(Horde::url($link), '', 'event');
            } else {
                $link = '';
            }
        }

        if (!$this->isAllDay()) {
            if (($cmp = $this->start->compareDate($this->end)) > 0) {
                $df = $prefs->getValue('date_format');
                if ($cmp > 0) {
                        $link .= strftime($df, $this->end->timestamp()) . '-' .  strftime($df, $this->start->timestamp());
                } else {
                        $link .= strftime($df, $this->start->timestamp()) . '-' .
                            strftime($df, $this->end->timestamp());
                }
            } else {
                $link .= date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia',
                             $this->start->timestamp()) . '-' .
                             date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia',
                             $this->end->timestamp());
            }
            $link .= ': ';
        }

        $link .= @htmlspecialchars($this->getTitle(), ENT_QUOTES,
                                   NLS::getCharset());

        if ($this->location) {
               $link .= ' ('. $this->location .')';
        }

        if (isset($this->remoteCal) ||
            (!is_a($share, 'PEAR_Error') &&
             $share->hasPermission(Auth::getAuth(), PERMS_READ,
                                   $this->getCreatorId()) &&
             (isset($this->eventID) ||
              isset($this->taskID)))) {
            $link .= '</a>';
        }

        if ($icons && $prefs->getValue('show_icons')) {
            $status = '';
            if ($this->alarm) {
                if ($this->alarm % 10080 == 0) {
                    $alarm_value = $this->alarm / 10080;
                    $title = $alarm_value == 1 ?
                        _("Alarm 1 week before") :
                        sprintf(_("Alarm %d weeks before"), $alarm_value);
                } elseif ($this->alarm % 1440 == 0) {
                    $alarm_value = $this->alarm / 1440;
                    $title = $alarm_value == 1 ?
                        _("Alarm 1 day before") :
                        sprintf(_("Alarm %d days before"), $alarm_value);
                } elseif ($this->alarm % 60 == 0) {
                    $alarm_value = $this->alarm / 60;
                    $title = $alarm_value == 1 ?
                        _("Alarm 1 hour before") :
                        sprintf(_("Alarm %d hours before"), $alarm_value);
                } else {
                    $alarm_value = $this->alarm;
                    $title = $alarm_value == 1 ?
                        _("Alarm 1 minute before") :
                        sprintf(_("Alarm %d minutes before"), $alarm_value);
                }
                $status .= Horde::img('alarm_small.png', $title,
                                      array('title' => $title));
            }

            if (!$this->hasRecurType(KRONOLITH_RECUR_NONE)) {
                $title = Kronolith::recurToString($this->recurType);
                $status .= Horde::img('recur.png', $title,
                                      array('title' => $title));
            }

            if (!empty($this->attendees)) {
                $title = count($this->attendees) == 1
                    ? _("1 attendee")
                    : sprintf(_("%s attendees"), count($this->attendees));
                $status .= Horde::img('attendees.png', $title,
                                      array('title' => $title));
            }

            if (!empty($status)) {
                $link .= ' ' . $status;
            }

            if ($print_view ||
                is_a($share, 'PEAR_Error') ||
                !isset($this->eventID)) {
                return $link;
            }

            if (isset($this->remoteCal) ||
                $share->hasPermission(Auth::getAuth(), PERMS_EDIT,
                                      $this->getCreatorId())) {
                $url = Util::addParameter('editevent.php',
                                          array('eventID' => $this->eventID,
                                                'calendar' => isset($this->remoteCal) ? '**remote' : $this->getCalendar(),
                                                'timestamp' => $timestamp,
                                                'url' => $from_url));
                if (isset($this->remoteCal)) {
                    $url = Util::addParameter($url, 'remoteCal',
                                              $this->remoteCal);
                }
                $link .= ' ' . Horde::link(Horde::applicationUrl($url),
                                           sprintf(_("Edit %s"), $this->title)) .
                    Horde::img('edit-small.png', _("Edit"), '',
                               $registry->getImageDir('horde')) .
                    '</a>';
            }
            if (!isset($this->remoteCal) &&
                $share->hasPermission(Auth::getAuth(), PERMS_DELETE,
                                      $this->getCreatorId())) {
                $url = Util::addParameter('delevent.php',
                                          array('eventID' => $this->eventID,
                                                'calendar' => $this->getCalendar(),
                                                'timestamp' => $timestamp,
                                                'url' => $from_url));
                $link .= ' ' . Horde::link(Horde::applicationUrl($url),
                                           sprintf(_("Delete %s"), $this->title)) .
                    Horde::img('delete-small.png', _("Delete"), '',
                               $registry->getImageDir('horde')) .
                    '</a>';
            }
        }

        return $link;
    }

    /**
     * @return string  A tooltip for quick descriptions of this event.
     */
    function getTooltip()
    {
        global $prefs;

        $tooltip = '';
        if ($this->isAllDay()) {
            $tooltip = _("All day");
        } elseif (($cmp = $this->start->compareDate($this->end)) > 0) {
            $df = $prefs->getValue('date_format');
            if ($cmp > 0) {
                $tooltip = strftime($df, $this->end->timestamp()) . '-' .
                    strftime($df, $this->start->timestamp());
            } else {
                $tooltip = strftime($df, $this->start->timestamp()) . '-' .
                    strftime($df, $this->end->timestamp());
            }
        } else {
            $tooltip = date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia',
                            $this->start->timestamp()) . '-' .
                date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia',
                     $this->end->timestamp());
        }

        $tooltip .= "\n" . sprintf(_("Owner: %s"), ($this->getCreatorId() == Auth::getAuth() ?
                                                    _("Me") : Kronolith::getUserName($this->getCreatorId())));

        if ($this->location) {
            $tooltip .= "\n" . _("Location") . ': ' . $this->location;
        }

        if ($this->description) {
            $tooltip .= "\n\n" . String::wrap($this->description);
        }

        return $tooltip;
    }

    /**
     * @return string  The CSS class for the event based on its status.
     */
    function getStatusClass()
    {
        switch ($this->status) {
        case KRONOLITH_STATUS_CANCELLED:
            return 'event-cancelled';

        case KRONOLITH_STATUS_TENTATIVE:
        case KRONOLITH_STATUS_FREE:
            return 'event-tentative';
        }

        return 'event';
    }

    /**
     * Find the next recurrence of this event that's after $afterDate.
     *
     * @param Horde_Date $afterDate  Return events after this date.
     *
     * @return Horde_Date|boolean  The date of the next recurrence or false
     *                             if the event does not recur after
     *                             $afterDate.
     */
    function nextRecurrence($afterDate)
    {
        $after = &new Horde_Date($afterDate);
        $after->correct();

        if ($this->start->compareDateTime($after) >= 0) {
            return new Horde_Date($this->start);
        }

        if ($this->recurInterval == 0) {
            return false;
        }

        switch ($this->getRecurType()) {
        case KRONOLITH_RECUR_DAILY:
            $diff = Date_Calc::dateDiff($this->start->mday, $this->start->month, $this->start->year, $after->mday, $after->month, $after->year);
            $recur = ceil($diff / $this->recurInterval) * $this->recurInterval;
            $next = Util::cloneObject($this->start);
            list($next->mday, $next->month, $next->year) = explode('/', Date_Calc::daysToDate(Date_Calc::dateToDays($next->mday, $next->month, $next->year) + $recur, '%e/%m/%Y'));
            if ((!$this->hasRecurEnd() ||
                 $next->compareDateTime($this->recurEnd) <= 0) &&
                $next->compareDateTime($after) >= 0) {
                return new Horde_Date($next);
            }
            break;

        case KRONOLITH_RECUR_WEEKLY:
            if (empty($this->recurData)) {
                return false;
            }

            list($start_week->mday, $start_week->month, $start_week->year) = explode('/', Date_Calc::beginOfWeek($this->start->mday, $this->start->month, $this->start->year, '%e/%m/%Y'));
            $start_week->hour = $this->start->hour;
            $start_week->min = $this->start->min;
            $start_week->sec = $this->start->sec;
            list($after_week->mday, $after_week->month, $after_week->year) = explode('/', Date_Calc::beginOfWeek($after->mday, $after->month, $after->year, '%e/%m/%Y'));
            $after_week_end = &new Horde_Date($after_week);
            $after_week_end->mday += 7;
            $after_week_end->correct();

            $diff = Date_Calc::dateDiff($start_week->mday, $start_week->month, $start_week->year,
                                        $after_week->mday, $after_week->month, $after_week->year);
            $recur = $diff + ($diff % ($this->recurInterval * 7));
            $next = $start_week;
            list($next->mday, $next->month, $next->year) = explode('/', Date_Calc::daysToDate(Date_Calc::dateToDays($next->mday, $next->month, $next->year) + $recur, '%e/%m/%Y'));
            $next = &new Horde_Date($next);
            while ($next->compareDateTime($after) < 0 &&
                   $next->compareDateTime($after_week_end) < 0) {
                ++$next->mday;
                $next->correct();
            }
            if (!$this->hasRecurEnd() ||
                $next->compareDateTime($this->recurEnd) <= 0) {
                if ($next->compareDateTime($after_week_end) >= 0) {
                    return $this->nextRecurrence($after_week_end);
                }
                while (!$this->recurOnDay((int)pow(2, $next->dayOfWeek())) &&
                       $next->compareDateTime($after_week_end) < 0) {
                    ++$next->mday;
                    $next->correct();
                }
                if (!$this->hasRecurEnd() ||
                    $next->compareDateTime($this->recurEnd) <= 0) {
                    if ($next->compareDateTime($after_week_end) >= 0) {
                        return $this->nextRecurrence($after_week_end);
                    } else {
                        return $next;
                    }
                }
            }
            break;

        case KRONOLITH_RECUR_DAY_OF_MONTH:
            $start = Util::cloneObject(new Horde_Date($this->start));
            if ($after->compareDateTime($start) < 0) {
                $after = $start;
            }

            // If we're starting past this month's recurrence of the
            // event, look in the next month on the day the event
            // recurs.
            if ($after->mday > $start->mday) {
                ++$after->month;
                $after->mday = $start->mday;
                $after->correct();
            }

            // Adjust $start to be the first match.
            $offset = ($after->month - $start->month) + ($after->year - $start->year) * 12;
            $offset = floor(($offset + $this->recurInterval - 1) / $this->recurInterval) * $this->recurInterval;

            $start->month += $offset;

            do {
                // Don't correct for day overflow; we just skip
                // February 30th, for example.
                $start->correct(HORDE_DATE_MASK_MONTH);
                if ($start->isValid()) {
                    return $start;
                }

                // If the interval is 12, and the date isn't valid,
                // then we need to see if February 29th is an
                // option. If not, then the event will _never_ recur,
                // and we need to stop checking to avoid an infinite
                // loop.
                if ($this->recurInterval == 12 && ($start->month != 2 || $start->mday > 29)) {
                    return false;
                }

                // Add the recurrence interval.
                $start->month += $this->recurInterval;

                // Bail if we've gone past the end of recurrence.
                if ($this->hasRecurEnd() &&
                    $this->recurEnd->compareDateTime($start) < 0) {
                    return false;
                }
            } while (true);

            break;

        case KRONOLITH_RECUR_WEEK_OF_MONTH:
            // Start with the start date of the event.
            $estart = Util::cloneObject(new Horde_Date($this->start));

            // What day of the week, and week of the month, do we
            // recur on?
            $nth = ceil($this->start->mday / 7);
            $weekday = $estart->dayOfWeek();

            // Adjust $estart to be the first candidate.
            $offset = ($after->month - $estart->month) + ($after->year - $estart->year) * 12;
            $offset = floor(($offset + $this->recurInterval - 1) / $this->recurInterval) * $this->recurInterval;

            // Adjust our working date until it's after $after.
            $estart->month += $offset - $this->recurInterval;
            do {
                $estart->month += $this->recurInterval;
                $estart->correct();

                $next = &Util::cloneObject($estart);
                $next->setNthWeekday($weekday, $nth);

                if ($next->compareDateTime($after) < 0) {
                    // We haven't made it past $after yet, try
                    // again.
                    continue;
                }
                if ($this->hasRecurEnd() &&
                    $next->compareDateTime($this->recurEnd) > 0) {
                    // We've gone past the end of recurrence; we can
                    // give up now.
                    return false;
                }

                // We have a candidate to return.
                break;
            } while (true);

            return $next;

        case KRONOLITH_RECUR_YEARLY:
            // Start with the start date of the event.
            $estart = Util::cloneObject(new Horde_Date($this->start));

            // We probably need a seperate case here for February 29th
            // and leap years, but until we're absolutely sure it's a
            // bug, we'll leave it out.
            if ($after->month > $estart->month ||
                ($after->month == $estart->month && $after->mday > $estart->mday)) {
                ++$after->year;
                $after->month = $estart->month;
                $after->mday = $estart->mday;
            }

            // Adjust $estart to be the first candidate.
            $offset = $after->year - $estart->year;
            if ($offset > 0) {
                $offset = (($offset + $this->recurInterval - 1) / $this->recurInterval) * $this->recurInterval;
                $estart->year += $offset;
            }

            // We've gone past the end of recurrence; give up.
            if ($this->hasRecurEnd() &&
                $this->recurEnd->compareDateTime($estart) < 0) {
                return false;
            }

            return $estart;
        }

        // We didn't find anything, the recurType was bad, or
        // something else went wrong - return false.
        return false;
    }

    function hasActiveRecurrence()
    {
        if (!$this->hasRecurEnd()) {
            return true;
        }

        $next = $this->nextRecurrence(Util::cloneObject($this->start));
        while (is_object($next)) {
            if (!$this->hasException($next->year, $next->month, $next->mday)) {
                return true;
            }

            $next = $this->nextRecurrence(array('year' => $next->year, 'month' => $next->month, 'mday' => $next->mday + 1, 'hour' => $next->hour, 'min' => $next->min, 'sec' => $next->sec));
        }

        return false;
    }

    function getCalendar()
    {
        return $this->_calendar;
    }

    function setCalendar($calendar)
    {
        $this->_calendar = $calendar;
    }

}
