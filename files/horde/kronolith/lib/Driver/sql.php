<?php
/**
 * The Kronolith_Driver_sql:: class implements the Kronolith_Driver
 * API for a SQL backend.
 *
 * $Horde: kronolith/lib/Driver/sql.php,v 1.136.2.27 2007/03/08 13:52:35 jan Exp $
 *
 * @author  Luc Saillard <luc.saillard@fr.alcove.com>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Kronolith 0.3
 * @package Kronolith
 */
class Kronolith_Driver_sql extends Kronolith_Driver {

    /**
     * The object handle for the current database connection.
     *
     * @var DB
     */
    var $_db;

    /**
     * Boolean indicating whether or not we're currently connected to the SQL
     * server.
     *
     * @var boolean
     */
    var $_connected = false;

    /**
     * Cache events as we fetch them to avoid fetching the same event from the
     * DB twice.
     *
     * @var array
     */
    var $_cache = array();

    function open($calendar)
    {
        $this->_calendar = $calendar;
        $this->_connect();
    }

    function listAlarms($date)
    {
        $allevents = $this->listEvents($date, null, true);
        $events = array();

        foreach ($allevents as $eventId) {
            $event = &$this->getEvent($eventId);

            if ($event->getRecurType() == KRONOLITH_RECUR_NONE) {
                $start = &new Horde_Date($event->start);
                $start->min -= $event->getAlarm();
                $start->correct();
                if ($start->compareDateTime($date) <= 0 &&
                    $date->compareDateTime($event->end) <= -1) {
                    $events[] = $eventId;
                }
            } else {
                if ($next = $this->nextRecurrence($eventId, $date)) {
                    $start = &new Horde_Date($next);
                    $start->min -= $event->getAlarm();
                    $start->correct();
                    $end = &new Horde_Date(array('year' => $next->year,
                                                 'month' => $next->month,
                                                 'mday' => $next->mday,
                                                 'hour' => $event->end->hour,
                                                 'min' => $event->end->min,
                                                 'sec' => $event->end->sec));
                    if ($start->compareDateTime($date) <= 0 &&
                        $date->compareDateTime($end) <= -1) {
                        $events[] = $eventId;
                    }
                }
            }
        }

        return is_array($events) ? $events : array();
    }

    function search($query)
    {
        require_once 'Horde/SQL.php';

        // Build SQL conditions based on the query string.
        $cond = '((';
        $values = array();

        if (!empty($query->title)) {
            $binds = Horde_SQL::buildClause($this->_db, 'event_title', 'LIKE', $this->convertToDriver($query->title), true);
            if (is_array($binds)) {
                $cond .= $binds[0] . ' AND ';
                $values = array_merge($values, $binds[1]);
            } else {
                $cond .= $binds;
            }
        }
        if (!empty($query->location)) {
            $binds = Horde_SQL::buildClause($this->_db, 'event_location', 'LIKE', $this->convertToDriver($query->location), true);
            if (is_array($binds)) {
                $cond .= $binds[0] . ' AND ';
                $values = array_merge($values, $binds[1]);
            } else {
                $cond .= $binds;
            }
        }
        if (!empty($query->description)) {
            $binds = Horde_SQL::buildClause($this->_db, 'event_description', 'LIKE', $this->convertToDriver($query->description), true);
            if (is_array($binds)) {
                $cond .= $binds[0] . ' AND ';
                $values = array_merge($values, $binds[1]);
            } else {
                $cond .= $binds;
            }
        }
        if (isset($query->category)) {
            $binds = Horde_SQL::buildClause($this->_db, 'event_category', '=', $this->convertToDriver($query->category), true);
            if (is_array($binds)) {
                $cond .= $binds[0] . ' AND ';
                $values = array_merge($values, $binds[1]);
            } else {
                $cond .= $binds;
            }
        }
        if (isset($query->status)) {
            $binds = Horde_SQL::buildClause($this->_db, 'event_status', '=', $query->status, true);
            if (is_array($binds)) {
                $cond .= $binds[0] . ' AND ';
                $values = array_merge($values, $binds[1]);
            } else {
                $cond .= $binds;
            }
        }

        if ($cond == '((') {
            $cond = '';
        } else {
            $cond = substr($cond, 0, strlen($cond) - 5) . '))';
        }

        $eventIds = $this->listEventsConditional($query->start,
                                                 empty($query->end) ?
                                                     new Horde_Date(array('mday' => 31, 'month' => 12, 'year' => 9999)) :
                                                     $query->end,
                                                 $cond,
                                                 $values);
        if (is_a($eventIds, 'PEAR_Error')) {
            return $eventIds;
        }

        $events = array();
        foreach ($eventIds as $eventId) {
            $event = &$this->getEvent($eventId);
            if (is_a($event, 'PEAR_Error')) {
                return $event;
            }
            $events[] = $event;
        }

        return $events;
    }

    /**
     * Checks if the event's UID already exists and returns all event
     * ids with that UID.
     *
     * @param string $uid          The event's uid.
     * @param string $calendar_id  Calendar to search in.
     *
     * @return string|boolean  Returns a string with event_id or false if
     *                         not found.
     */
    function exists($uid, $calendar_id = null)
    {
        $query = 'SELECT event_id  FROM ' . $this->_params['table'] . ' WHERE event_uid = ?';
        $values = array($uid);

        if (!is_null($calendar_id)) {
            $query .= ' AND calendar_id = ?';
            $values[] = $calendar_id;
        }

        // Log the query at a DEBUG log level.
        Horde::logMessage(sprintf('SQL event fetch by %s: query = "%s"',
                                  Auth::getAuth(), $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $event = &$this->_db->getRow($query, $values, DB_FETCHMODE_ASSOC);
        if (is_a($event, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $event;
        }

        if ($event) {
            return $event['event_id'];
        } else {
            return false;
        }
    }

    /**
     * Lists all events in the time range, optionally restricting
     * results to only events with alarms.
     *
     * @param Horde_Date $startInterval  Start of range date object.
     * @param Horde_Date $endInterval    End of range data object.
     * @param boolean $hasAlarm          Only return events with alarms?
     *                                   Defaults to all events.
     *
     * @return array  Events in the given time range.
     */
    function listEvents($startDate = null, $endDate = null, $hasAlarm = false)
    {
        if (!isset($endDate)) {
            $endInterval = &new Horde_Date(array('mday' => 31, 'month' => 12, 'year' => 9999));
        } else {
            list($endInterval->mday, $endInterval->month, $endInterval->year) = explode('/', Date_Calc::nextDay($endDate->mday, $endDate->month, $endDate->year, '%d/%m/%Y'));
        }

        $startInterval = null;
        if (isset($startDate)) {
            if ($startDate === 0) {
                $startInterval = &new Horde_Date(array('mday' => 1, 'month' => 1, 'year' => 0000));
            } else {
                $startInterval = &new Horde_Date($startDate);
            }
            if ($startInterval->month == 0) {
                $startInterval->month = 1;
            }
            if ($startInterval->mday == 0) {
                $startInterval->mday = 1;
            }
        }

        return $this->listEventsConditional($startInterval, $endInterval,
                                            $hasAlarm ? 'event_alarm > ?' : '',
                                            $hasAlarm ? array(0) : array());
    }

    /**
     * Lists all events that satisfy the given conditions.
     *
     * @param Horde_Date $startInterval  Start of range date object.
     * @param Horde_Date $endInterval    End of range data object.
     * @param string $conditions         Conditions, given as SQL clauses.
     * @param array $vals                SQL bind variables for use with
     *                                   $conditions clauses.
     *
     * @return array  Events in the given time range satisfying the given
     *                conditions.
     */
    function listEventsConditional($startInterval, $endInterval,
                                   $conditions = '', $vals = array())
    {
        $q = 'SELECT event_id, event_uid, event_description, event_location,' .
            ' event_status, event_attendees,' .
            ' event_keywords, event_title, event_category,' .
            ' event_recurtype, event_recurenddate, event_recurinterval,' .
            ' event_recurdays, event_start, event_end, event_alarm,' .
            ' event_modified, event_exceptions, event_creator_id' .
            ' FROM ' . $this->_params['table'] .
            ' WHERE calendar_id = ? AND ((';
        $values = array($this->_calendar);

        if ($conditions) {
            $q .= $conditions . ')) AND ((';
            $values = array_merge($values, $vals);
        }

        $etime = sprintf('%04d-%02d-%02d 00:00:00', $endInterval->year, $endInterval->month, $endInterval->mday);
        if (isset($startInterval)) {
            $stime = sprintf('%04d-%02d-%02d 00:00:00', $startInterval->year, $startInterval->month, $startInterval->mday);
            $q .= 'event_end > ? AND ';
            $values[] = $stime;
        }
        $q .= 'event_start < ?) OR (';
        $values[] = $etime;
        if (isset($stime)) {
            $q .= 'event_recurenddate >= ? AND ';
            $values[] = $stime;
        }
        $q .= 'event_start <= ?' .
            ' AND event_recurtype <> ?))';
        array_push($values, $etime, KRONOLITH_RECUR_NONE);

        // Log the query at a DEBUG log level.
        Horde::logMessage(sprintf('SQL event list by %s: query = "%s"',
                                  Auth::getAuth(), $q),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        // Run the query.
        $qr = $this->_db->query($q, $values);
        if (is_a($qr, 'PEAR_Error')) {
            Horde::logMessage($qr, __FILE__, __LINE__, PEAR_LOG_ERR);
            return array();
        }

        $events = array();
        $row = $qr->fetchRow(DB_FETCHMODE_ASSOC);
        while ($row && !is_a($row, 'PEAR_Error')) {
            // If the event did not have a UID before, we need to give
            // it one.
            if (empty($row['event_uid'])) {
                $row['event_uid'] = $this->generateUID();

                // Save the new UID for data integrity.
                $query = 'UPDATE ' . $this->_params['table'] . ' SET event_uid = ? WHERE event_id = ?';
                $values = array($row['event_uid'], $row['event_id']);

                // Log the query at a DEBUG log level.
                Horde::logMessage(sprintf('SQL event uid autocreation for %s: query = "%s"',
                                          Auth::getAuth(), $query),
                                  __FILE__, __LINE__, PEAR_LOG_DEBUG);

                $result = $this->_db->query($query, $values);
                if (is_a($result, 'PEAR_Error')) {
                    Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                }
            }

            // We have all the information we need to create an event
            // object for this event, so go ahead and cache it.
            $this->_cache[$this->_calendar][$row['event_id']] = &new Kronolith_Event_sql($this, $row);
            if ($row['event_recurtype'] == KRONOLITH_RECUR_NONE) {
                $events[$row['event_uid']] = $row['event_id'];
            } else {
                $next = $this->nextRecurrence($row['event_id'], $startInterval);
                if ($next && $next->compareDate($endInterval) < 0) {
                    $events[$row['event_uid']] = $row['event_id'];
                }
            }

            $row = $qr->fetchRow(DB_FETCHMODE_ASSOC);
        }

        return $events;
    }

    function &getEvent($eventId = null)
    {
        if (is_null($eventId)) {
            $event = &new Kronolith_Event_sql($this);
            return $event;
        }

        if (isset($this->_cache[$this->_calendar][$eventId])) {
            return $this->_cache[$this->_calendar][$eventId];
        }

        $query = 'SELECT event_id, event_uid, event_description, event_location,' .
            ' event_status, event_attendees,' .
            ' event_keywords, event_title, event_category,' .
            ' event_recurtype, event_recurenddate, event_recurinterval,' .
            ' event_recurdays, event_start, event_end, event_alarm,' .
            ' event_modified, event_exceptions, event_creator_id' .
            ' FROM ' . $this->_params['table'] . ' WHERE event_id = ? AND calendar_id = ?';
        $values = array($eventId, $this->_calendar);

        // Log the query at a DEBUG log level.
        Horde::logMessage(sprintf('SQL event fetch by %s: query = "%s"',
                                  Auth::getAuth(), $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $event = &$this->_db->getRow($query, $values, DB_FETCHMODE_ASSOC);
        if (is_a($event, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $event;
        }

        if ($event) {
            $this->_cache[$this->_calendar][$eventId] = &new Kronolith_Event_sql($this, $event);
            return $this->_cache[$this->_calendar][$eventId];
        } else {
            return PEAR::raiseError(_("Event not found"));
        }
    }

    function &getByUID($uid, $getAll = false)
    {
        $this->_connect();

        $query = 'SELECT event_id, event_uid, calendar_id, event_description, event_location,' .
            ' event_status, event_attendees,' .
            ' event_keywords, event_title, event_category,' .
            ' event_recurtype, event_recurenddate, event_recurinterval,' .
            ' event_recurdays, event_start, event_end, event_alarm,' .
            ' event_modified, event_exceptions, event_creator_id' .
            ' FROM ' . $this->_params['table'] . ' WHERE event_uid = ?';
        $values = array($uid);

        // Log the query at a DEBUG log level.
        Horde::logMessage(sprintf('SQL event fetch by %s: query = "%s"',
                                  Auth::getAuth(), $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $events = $this->_db->getAll($query, $values, DB_FETCHMODE_ASSOC);
        if (is_a($events, 'PEAR_Error')) {
            Horde::logMessage($events, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $events;
        }
        if (!count($events)) {
            return PEAR::raiseError($uid . ' not found');
        }

        $eventArray = array();
        foreach ($events as $event) {
            $this->open($event['calendar_id']);
            $this->_cache[$this->_calendar][$event['event_id']] = &new Kronolith_Event_sql($this, $event);
            $eventArray[] = &$this->_cache[$this->_calendar][$event['event_id']];
        }

        if ($getAll) {
            return $eventArray;
        }

        /* First try the user's own calendars. */
        $ownerCalendars = Kronolith::listCalendars(true, PERMS_READ);
        $event = null;
        foreach ($eventArray as $ev) {
            if (isset($ownerCalendars[$ev->getCalendar()])) {
                $event = $ev;
                break;
            }
        }

        /* If not successful, try all calendars the user has access too. */
        if (empty($event)) {
            $readableCalendars = Kronolith::listCalendars(false, PERMS_READ);
            foreach ($eventArray as $ev) {
                if (isset($readableCalendars[$ev->getCalendar()])) {
                    $event = $ev;
                    break;
                }
            }
        }

        if (empty($event)) {
            $event = $eventArray[0];
        }

        return $event;
    }

    /**
     * Saves an event in the backend.
     * If it is a new event, it is added, otherwise the event is updated.
     *
     * @param Kronolith_Event $event  The event to save.
     */
    function saveEvent(&$event)
    {
        if ($event->isStored() || $event->exists()) {
            $values = array();

            $query = 'UPDATE ' . $this->_params['table'] . ' SET ';

            foreach ($event->getProperties() as $key => $val) {
                $query .= " $key = ?,";
                $values[] = $val;
            }
            $query = substr($query, 0, -1);
            $query .= ' WHERE event_id = ?';
            $values[] = $event->getId();

            // Log the query at a DEBUG log level.
            Horde::logMessage(sprintf('SQL event update by %s: query = "%s"',
                                      Auth::getAuth(), $query),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $result = $this->_db->query($query, $values);
            if (is_a($result, 'PEAR_Error')) {
                Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $result;
            }

            // Log the modification of this item in the history log.
            if ($event->getUID()) {
                $history = &Horde_History::singleton();
                $history->log('kronolith:' . $this->_calendar . ':' . $event->getUID(), array('action' => 'modify'), true);
            }

            // Notify users about the changed event.
            $result = Kronolith::sendNotification($event, 'edit');
            if (is_a($result, 'PEAR_Error')) {
                Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            }

            return $event->getId();
        } else {
            if ($event->getId()) {
                $id = $event->getId();
            } else {
                $id = md5(uniqid(mt_rand(), true));
                $event->setId($id);
            }

            if ($event->getUID()) {
                $uid = $event->getUID();
            } else {
                $uid = $this->generateUID();
                $event->setUID($uid);
            }

            $query = 'INSERT INTO ' . $this->_params['table'];
            $cols_name = ' (event_id, event_uid,';
            $cols_values = ' VALUES (?, ?,';
            $values = array($id, $uid);

            foreach ($event->getProperties() as $key => $val) {
                $cols_name .= " $key,";
                $cols_values .= ' ?,';
                $values[] = $val;
            }

            $cols_name .= ' calendar_id)';
            $cols_values .= ' ?)';
            $values[] = $this->_calendar;

            $query .= $cols_name . $cols_values;

            // Log the query at a DEBUG log level.
            Horde::logMessage(sprintf('SQL event store by %s: query = "%s"',
                                Auth::getAuth(), $query),
                                __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $result = $this->_db->query($query, $values);
            if (is_a($result, 'PEAR_Error')) {
                Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $result;
            }

            // Log the creation of this item in the history log.
            $history = &Horde_History::singleton();
            $history->log('kronolith:' . $this->_calendar . ':' . $uid, array('action' => 'add'), true);

            // Notify users about the new event.
            $result = Kronolith::sendNotification($event, 'add');
            if (is_a($result, 'PEAR_Error')) {
                Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            }

            return $id;
        }
    }

    /**
     * Move an event to a new calendar.
     *
     * @param string $eventId      The event to move.
     * @param string $newCalendar  The new calendar.
     */
    function move($eventId, $newCalendar)
    {
        // Make sure we have a valid database connection.
        $this->_connect();

        $query = 'UPDATE ' . $this->_params['table'] . ' SET calendar_id = ? WHERE calendar_id = ? AND event_id = ?';
        $values = array($newCalendar, $this->_calendar, $eventId);

        // Log the query at a DEBUG log level.
        Horde::logMessage(sprintf('Kronolith_Driver_sql::move(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        // Attempt the move query.
        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return true;
    }

    /**
     * Delete a calendar and all its events.
     *
     * @param string $calendar  The name of the calendar to delete.
     *
     * @return mixed  True or a PEAR_Error on failure.
     */
    function delete($calendar)
    {
        $this->_connect();

        $query = 'DELETE FROM ' . $this->_params['table'] . ' WHERE calendar_id = ?';
        $values = array($calendar);

        // Log the query at a DEBUG log level.
        Horde::logMessage(sprintf('SQL Calender Delete by %s: query = "%s"',
                                  Auth::getAuth(), $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        return $this->_db->query($query, $values);
    }

    /**
     * Delete an event.
     *
     * @param string $eventId  The ID of the event to delete.
     *
     * @return mixed  True or a PEAR_Error on failure.
     */
    function deleteEvent($eventId)
    {
        // Fetch the event for later use.
        $event = &$this->getEvent($eventId);

        $query = 'DELETE FROM ' . $this->_params['table'] . ' WHERE event_id = ? AND calendar_id = ?';
        $values = array($eventId, $this->_calendar);

        // Log the query at a DEBUG log level.
        Horde::logMessage(sprintf('SQL Event Delete by %s: query = "%s"',
                                  Auth::getAuth(), $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        // Log the deletion of this item in the history log.
        if ($event->getUID()) {
            $history = &Horde_History::singleton();
            $history->log('kronolith:' . $this->_calendar . ':' . $event->getUID(), array('action' => 'delete'), true);
        }

        // Notify about the deleted event.
        $result = Kronolith::sendNotification($event, 'delete');
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
        }

        return true;
    }

    /**
     * Attempts to open a persistent connection to the SQL server.
     *
     * @return boolean True.
     */
    function _connect()
    {
        if (!$this->_connected) {
            Horde::assertDriverConfig($this->_params, 'calendar',
                array('phptype'));

            if (!isset($this->_params['database'])) {
                $this->_params['database'] = '';
            }
            if (!isset($this->_params['username'])) {
                $this->_params['username'] = '';
            }
            if (!isset($this->_params['hostspec'])) {
                $this->_params['hostspec'] = '';
            }
            if (!isset($this->_params['table'])) {
                $this->_params['table'] = 'kronolith_events';
            }

            // Connect to the SQL server using the supplied parameters.
            require_once 'DB.php';
            $this->_db = &DB::connect($this->_params,
                                      array('persistent' => !empty($this->_params['persistent'])));
            if (is_a($this->_db, 'PEAR_Error')) {
                Horde::fatal($this->_db, __FILE__, __LINE__);
            }

            // Set DB portability options.
            switch ($this->_db->phptype) {
            case 'mssql':
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
                break;
            default:
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
            }

            $this->_connected = true;

            // Handle any database specific initialization code to
            // run.
            switch ($this->_db->dbsyntax) {
            case 'oci8':
                $query = "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'";

                // Log the query at a DEBUG log level.
                Horde::logMessage(sprintf('SQL session setup by %s: query = "%s"',
                                          Auth::getAuth(), $query),
                                  __FILE__, __LINE__, PEAR_LOG_DEBUG);

                $this->_db->query($query);
                break;

            case 'pgsql':
                $query = "SET datestyle TO 'iso'";

                // Log the query at a DEBUG log level.
                Horde::logMessage(sprintf('SQL session setup by %s: query = "%s"',
                                          Auth::getAuth(), $query),
                                  __FILE__, __LINE__, PEAR_LOG_DEBUG);

                $this->_db->query($query);
                break;
            }
        }

        return true;
    }

    function close()
    {
        return true;
    }

    /**
     * Converts a value from the driver's charset to the default
     * charset.
     *
     * @param mixed $value  A value to convert.
     *
     * @return mixed  The converted value.
     */
    function convertFromDriver($value)
    {
        return String::convertCharset($value, $this->_params['charset']);
    }

    /**
     * Converts a value from the default charset to the driver's
     * charset.
     *
     * @param mixed $value  A value to convert.
     *
     * @return mixed  The converted value.
     */
    function convertToDriver($value)
    {
        return String::convertCharset($value, NLS::getCharset(), $this->_params['charset']);
    }

}

/**
 * @package Kronolith
 */
class Kronolith_Event_sql extends Kronolith_Event {

    /**
     * @var array
     */
    var $_properties = array();

    function fromDriver($SQLEvent)
    {
        $driver = &$this->getDriver();

        $this->start = &new Horde_Date();
        $this->end = &new Horde_Date();
        list($this->start->year, $this->start->month, $this->start->mday, $this->start->hour, $this->start->min, $this->start->sec) = sscanf($SQLEvent['event_start'], '%04d-%02d-%02d %02d:%02d:%02d');
        list($this->end->year, $this->end->month, $this->end->mday, $this->end->hour, $this->end->min, $this->end->sec) = sscanf($SQLEvent['event_end'], '%04d-%02d-%02d %02d:%02d:%02d');

        $this->durMin = ($this->end->timestamp() - $this->start->timestamp()) / 60;

        if (isset($SQLEvent['event_recurenddate'])) {
            $this->recurEnd = &new Horde_Date();
            list($this->recurEnd->year, $this->recurEnd->month, $this->recurEnd->mday) = sscanf($SQLEvent['event_recurenddate'], '%04d-%02d-%02d 00:00:00');
            $this->recurEnd->hour = 23;
            $this->recurEnd->min = 59;
            $this->recurEnd->sec = 59;
        }

        $this->title = $driver->convertFromDriver($SQLEvent['event_title']);
        $this->eventID = $SQLEvent['event_id'];
        $this->setUID($SQLEvent['event_uid']);
        $this->creatorID = $SQLEvent['event_creator_id'];
        $this->recurType = (int)$SQLEvent['event_recurtype'];
        $this->recurInterval = (int)$SQLEvent['event_recurinterval'];

        if (isset($SQLEvent['event_category'])) {
            $this->category = $driver->convertFromDriver($SQLEvent['event_category']);
        }
        if (isset($SQLEvent['event_location'])) {
            $this->location = $driver->convertFromDriver($SQLEvent['event_location']);
        }
        if (isset($SQLEvent['event_status'])) {
            $this->status = $SQLEvent['event_status'];
        }
        if (isset($SQLEvent['event_attendees'])) {
            $this->attendees = array_change_key_case(unserialize($driver->convertFromDriver($SQLEvent['event_attendees'])));
        }
        if (isset($SQLEvent['event_keywords'])) {
            $this->keywords = explode(',', $driver->convertFromDriver($SQLEvent['event_keywords']));
        }
        if (!empty($SQLEvent['event_exceptions'])) {
            $this->exceptions = explode(',', $SQLEvent['event_exceptions']);
        }
        if (isset($SQLEvent['event_description'])) {
            $this->description = $driver->convertFromDriver($SQLEvent['event_description']);
        }
        if (isset($SQLEvent['event_alarm'])) {
            $this->alarm = (int)$SQLEvent['event_alarm'];
        }
        if (isset($SQLEvent['event_recurdays'])) {
            $this->recurData = (int)$SQLEvent['event_recurdays'];
        }

        $this->initialized = true;
        $this->stored = true;
    }

    function toDriver()
    {
        $driver = &$this->getDriver();

        // Basic fields.
        $this->_properties['event_creator_id'] = $driver->convertToDriver($this->getCreatorId());
        $this->_properties['event_title'] = $driver->convertToDriver($this->title);
        $this->_properties['event_description'] = $driver->convertToDriver($this->getDescription());
        $this->_properties['event_category'] = $driver->convertToDriver($this->getCategory());
        $this->_properties['event_location'] = $driver->convertToDriver($this->getLocation());
        $this->_properties['event_status'] = $this->getStatus();
        $this->_properties['event_attendees'] = $driver->convertToDriver(serialize($this->getAttendees()));
        $this->_properties['event_keywords'] = $driver->convertToDriver(implode(',', $this->getKeywords()));
        $this->_properties['event_exceptions'] = implode(',', $this->getExceptions());
        $this->_properties['event_modified'] = time();

        $this->_properties['event_start'] = date('Y-m-d H:i:s', $this->start->timestamp());

        // Event end.
        $this->_properties['event_end'] = date('Y-m-d H:i:s', $this->end->timestamp());

        // Alarm.
        $this->_properties['event_alarm'] = $this->getAlarm();

        // Recurrence.
        $recur_end = isset($this->recurEnd) ? explode(':', @date('Y:n:j', $this->recurEnd->timestamp())) : null;
        if (empty($recur_end[0]) || $recur_end[0] <= 1970) {
            $recur_end[0] = 9999;
            $recur_end[1] = 12;
            $recur_end[2] = 31;
        }

        $recur = $this->getRecurType();
        $this->_properties['event_recurtype'] = $recur;
        if ($recur != KRONOLITH_RECUR_NONE) {
            $this->_properties['event_recurinterval'] = $this->getRecurInterval();
            $this->_properties['event_recurenddate'] = sprintf('%04d-%02d-%02d', $recur_end[0],
                                                               $recur_end[1], $recur_end[2]);

            switch ($recur) {
            case KRONOLITH_RECUR_WEEKLY:
                $this->_properties['event_recurdays'] = $this->getRecurOnDays();
                break;
            }
        }
    }

    function getProperties()
    {
        return $this->_properties;
    }

}
