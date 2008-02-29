<?php

require_once 'Horde/Kolab.php';
require_once 'Horde/Identity.php';

/**
 * Horde Kronolith driver for the Kolab IMAP Server.
 * Copyright 2004-2007 Horde Project (http://horde.org/)
 *
 * $Horde: kronolith/lib/Driver/kolab.php,v 1.16.2.14 2007/01/02 13:55:06 jan Exp $
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Stuart Binge <omicron@mighty.co.za>
 * @since   Kronolith 2.0
 * @package Kronolith
 */
class Kronolith_Driver_kolab extends Kronolith_Driver {

    /**
     * Our Kolab server connection.
     *
     * @var Kolab
     */
    var $_kolab = null;

    function open($calendar)
    {
        if (empty($this->_calendar) || $this->_calendar != $calendar) {
            $this->_calendar = $calendar;
        }

        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        return true;
    }

    function close()
    {
        return $this->_disconnect();
    }

    function _connect()
    {
        if (!$this->_kolab) {
            $this->_kolab = new Kolab();
        }
        return $this->_kolab->open($this->_calendar);
    }

    function _disconnect()
    {
        if (!$this->_kolab) {
            return true;
        }

        $this->_kolab->close();
        $this->_kolab = null;

        return true;
    }

    function listAlarms($date)
    {
        if (!$this->_kolab) {
            return array();
        }

        $allevents = $this->listEvents($date, $date, true);
        $events = array();

        foreach ($allevents as $eventId) {
            $event = &$this->getEvent($eventId);

            if ($event->getRecurType() == KRONOLITH_RECUR_NONE) {
                $start = new Horde_Date($event->start);
                $start->min -= $event->getAlarm();
                $start->correct();
                if ($start->compareDateTime($date) <= 0 &&
                    $date->compareDateTime($event->end) <= -1) {
                    $events[] = $eventId;
                }
            } else {
                if ($next = $this->nextRecurrence($eventId, $date)) {
                    $start = new Horde_Date($next);
                    $start->min -= $event->getAlarm();
                    $start->correct();
                    if ($start->compareDateTime($date) <= 0 &&
                        $date->compareDateTime(new Horde_Date(array('year' => $next->year,
                                                                    'month' => $next->month,
                                                                    'mday' => $next->mday,
                                                                    'hour' => $event->end->hour,
                                                                    'min' => $event->end->min,
                                                                    'sec' => $event->end->sec))) <= -1) {
                        $events[] = $eventId;
                    }
                }
            }
        }

        return is_array($events) ? $events : array();
    }

    /**
     * Checks if the event's UID already exists and returns all event
     * ids with that UID.
     *
     * @param string $uid          The event's uid.
     * @param string $calendar_id  Calendar to search in.
     *
     * @return mixed  Returns a string with event_id or false if not found.
     */
    function exists($uid, $calendar_id = null)
    {
        // Don't use calendar id here.
        if (is_a($this->_kolab->loadObject($uid), 'PEAR_Error')) {
            return false;
        }

        return $uid;
    }

    function listEvents($startDate = null, $endDate = null)
    {
        // We don't perform any checking on $startDate and $endDate,
        // as that has the potential to leave out recurring event
        // instances.
        $events = array();

        $msg_list = $this->_kolab->listObjects();
        if (is_a($msg_list, 'PEAR_Error')) {
            return $msg_list;
        }

        if (empty($msg_list)) {
            return $events;
        }

        foreach ($msg_list as $msg) {
            $result = $this->_kolab->loadObject($msg, true);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }

            $events[$this->_kolab->getUID()] = $this->_kolab->getUID();
        }

        return $events;
    }

    function &getEvent($eventID = null)
    {
        if (is_null($eventID)) {
            $event = new Kronolith_Event_kolab($this);
            return $event;
        }

        $result = $this->_kolab->loadObject($eventID);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $event = new Kronolith_Event_kolab($this);
        $event->fromDriver($this);

        return $event;
    }

    function &getByUID($uid)
    {
        $this->open(Auth::getAuth());

        return $this->getEvent($uid);
    }

    function saveEvent(&$event)
    {
        $edit = false;
        if ($event->isStored() || $event->exists()) {
            $uid = $event->getID();

            $result = $this->_kolab->loadObject($uid);
            //No error check here, already done in exists()

            $edit = true;
        } else {
            if ($event->getID()) {
                $uid = $event->getID();
            } else {
                $uid = md5(uniqid(mt_rand(), true));
                $event->setUID($uid);
            }

            $this->_kolab->newObject($uid);
        }

        $xml_hash = &$this->_kolab->getCurrentObject();

        $this->_kolab->setStr('summary', $event->getTitle());
        $this->_kolab->setStr('body', $event->getDescription());
        $this->_kolab->setStr('categories', $event->getCategory());
        $this->_kolab->setStr('location', $event->getLocation());

        $organizer = &$this->_kolab->initRootElem('organizer');
        $this->_kolab->setElemStr($organizer, 'smtp-address', $event->getCreatorID());

        $this->_kolab->setVal('alarm', $event->getAlarm());
        if ($event->isAllDay()) {
            $this->_kolab->setVal('start-date', Kolab::encodeDate($event->start->timestamp()));
            $this->_kolab->setVal('end-date', Kolab::encodeDate($event->end->timestamp()-24*60*60));
        } else {
            $this->_kolab->setVal('start-date', Kolab::encodeDateTime($event->start->timestamp()));
            $this->_kolab->setVal('end-date', Kolab::encodeDateTime($event->end->timestamp()));
        }

        switch ($event->status) {
        case KRONOLITH_STATUS_CANCELLED:
            $this->_kolab->setVal('show-time-as', 'free');
            break;

        case KRONOLITH_STATUS_TENTATIVE:
            $this->_kolab->setVal('show-time-as', 'tentative');
            break;

        case KRONOLITH_STATUS_CONFIRMED:
        default:
            $this->_kolab->setVal('show-time-as', 'busy');
            break;
        }

        $this->_kolab->delAllRootElems('attendee');
        foreach ($event->attendees as $email => $status) {
            $attendee = &$this->_kolab->appendRootElem('attendee');
            $this->_kolab->setElemVal($attendee, 'smtp-address', $email);

            switch ($status['response']) {
            case KRONOLITH_RESPONSE_ACCEPTED:
                $this->_kolab->setElemVal($attendee, 'status', 'accepted');
                break;

            case KRONOLITH_RESPONSE_DECLINED:
                $this->_kolab->setElemVal($attendee, 'status', 'declined');
                break;

            case KRONOLITH_RESPONSE_TENTATIVE:
                $this->_kolab->setElemVal($attendee, 'status', 'tentative');
                break;

            case KRONOLITH_RESPONSE_NONE:
            default:
                $this->_kolab->setElemVal($attendee, 'status', 'none');
            }

            switch ($status['attendance']) {
            case KRONOLITH_PART_OPTIONAL:
                $this->_kolab->setElemVal($attendee, 'role', 'optional');
                break;

            case KRONOLITH_PART_NONE:
                $this->_kolab->setElemVal($attendee, 'role', 'resource');
                break;

            case KRONOLITH_PART_REQUIRED:
            default:
                $this->_kolab->setElemVal($attendee, 'role', 'required');
            }
        }

        $this->_kolab->delAllRootElems('recurrence');

        $range_type = 'none';
        $range = 0;
        if (!empty($event->recurEnd)) {
            $range_type = 'date';
            $range = Kolab::encodeDate($event->recurEnd->timestamp());
        }

        if ($event->recurType != KRONOLITH_RECUR_NONE) {
            $recurrence = &$this->_kolab->initRootElem('recurrence');
            $this->_kolab->setElemVal($recurrence, 'interval', $event->recurInterval);
            $range = &$this->_kolab->setElemVal($recurrence, 'range', $range);
            $range->set_attribute('type', $range_type);

            switch ($event->recurType) {
                case KRONOLITH_RECUR_DAILY:
                    $recurrence->set_attribute('cycle', 'daily');
                    break;

                case KRONOLITH_RECUR_WEEKLY:
                    $recurrence->set_attribute('cycle', 'weekly');

                    $days = array('sunday', 'monday', 'tuesday', 'wednesday',
                                  'thursday', 'friday', 'saturday');

                    for ($i = 0; $i <= 7 ; ++$i) {
                        if ($event->recurOnDay(pow(2, $i))) {
                            $day = &$this->_kolab->appendElem('day', $recurrence);
                            $day->set_content($days[$i]);
                        }
                    }
                    break;

                case KRONOLITH_RECUR_DAY_OF_MONTH:
                    $recurrence->set_attribute('cycle', 'monthly');
                    $recurrence->set_attribute('type', 'daynumber');
                    $this->_kolab->setElemVal($recurrence, 'date', $event->start->mday);
                    break;

                case KRONOLITH_RECUR_WEEK_OF_MONTH:
                    $recurrence->set_attribute('cycle', 'monthly');
                    $recurrence->set_attribute('type', 'weekday');
                    $this->_kolab->setElemVal($recurrence, 'daynumber', 1);
                    $start = new Horde_Date($event->start);
                    $days = array('sunday', 'monday', 'tuesday', 'wednesday',
                                  'thursday', 'friday', 'saturday');
                    $this->_kolab->setElemVal($recurrence, 'day', $days[$start->dayOfWeek()]);
                    break;

                case KRONOLITH_RECUR_YEARLY:
                    $recurrence->set_attribute('cycle', 'yearly');
                    $recurrence->set_attribute('type', 'monthday');

                    $months = array('january', 'february', 'march', 'april',
                                    'may', 'june', 'july', 'august', 'september',
                                    'october', 'november', 'december');

                    $this->_kolab->setElemVal($recurrence, 'month', $months[$event->start->month]);
                    $this->_kolab->setElemVal($recurrence, 'date', $event->start->mday);
                    break;
            }
        }

        $result = $this->_kolab->saveObject();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        if (is_callable('Kolab', 'triggerFreeBusyUpdate')) {
            Kolab::triggerFreeBusyUpdate($this->_calendar);
        }

        /* Notify about the changed event. */
        $result = Kronolith::sendNotification($event, $edit ? 'edit' : 'add');
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
        }

        return $uid;
    }

    /**
     * Move an event to a new calendar.
     *
     * @param string $eventId      The event to move.
     * @param string $newCalendar  The new calendar.
     */
    function move($eventID, $newCalendar)
    {
        $result = $this->_kolab->moveObject($eventID, $newCalendar);

        if (is_callable('Kolab', 'triggerFreeBusyUpdate')) {
            Kolab::triggerFreeBusyUpdate($this->_calendar);
            Kolab::triggerFreeBusyUpdate($newCalendar);
        }

        return $result;
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
        // This is handled by the share hooks.
        return true;
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
        // This is handled by the share hooks.
        return true;
    }

    /**
     * Delete an event.
     *
     * @param string $eventId  The ID of the event to delete.
     *
     * @return mixed  True or a PEAR_Error on failure.
     */
    function deleteEvent($eventID)
    {
        /* Fetch the event for later use. */
        $event = &$this->getEvent($eventID);

        /* Delete the event. */
        $deleted = $this->_kolab->removeObjects($eventID);
        if (!$deleted || is_a($deleted, 'PEAR_Error')) {
            return $deleted;
        }

        if (is_callable('Kolab', 'triggerFreeBusyUpdate')) {
            Kolab::triggerFreeBusyUpdate($this->_calendar);
        }

        /* Notify about the deleted event. */
        $result = Kronolith::sendNotification($event, 'delete');
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
        }
    }

}

class Kronolith_Event_kolab extends Kronolith_Event {

    function fromDriver($dummy)
    {
        $driver = &$this->getDriver();
        $kolab = &$driver->_kolab;

        $this->eventID = $kolab->getUID();
        $this->setUID($kolab->getUID());
        $this->title = $kolab->getStr('summary');
        $this->description = $kolab->getStr('body');
        $this->location = $kolab->getStr('location');
        $this->category = $kolab->getStr('categories');

        $organizer = &$kolab->getRootElem('organizer');
        $this->creatorID = $kolab->getElemStr($organizer, 'smtp-address');

        $this->alarm = $kolab->getVal('alarm');
        $this->start = new Horde_Date(Kolab::decodeDateOrDateTime($kolab->getVal('start-date')));
        $this->end = new Horde_Date(Kolab::decodeFullDayDate($kolab->getVal('end-date')));
        $this->durMin = ($this->end->timestamp() - $this->start->timestamp()) / 60;

        $status = $kolab->getVal('show-time-as');
        switch ($status) {
        case 'free':
            $this->status = KRONOLITH_STATUS_CANCELLED;
            break;

        case 'tentative':
            $this->status = KRONOLITH_STATUS_TENTATIVE;
            break;

        case 'busy':
        case 'outofoffice':
        default:
            $this->status = KRONOLITH_STATUS_CONFIRMED;
        }

        $attendees = array_change_key_case($kolab->getAllRootElems('attendee'));
        for ($i = 0, $iMax = count($attendees); $i < $iMax; ++$i) {
            $attendee = $attendees[$i];

            $email = $kolab->getElemStr($attendee, 'smtp-address');
            if (empty($email)) {
                continue;
            }

            $role = $kolab->getElemVal($attendee, 'role');
            switch ($role) {
            case 'optional':
                $role = KRONOLITH_PART_OPTIONAL;
                break;

            case 'resource':
                $role = KRONOLITH_PART_NONE;
                break;

            case 'required':
            default:
                $role = KRONOLITH_PART_REQUIRED;
                break;
            }

            $status = $kolab->getElemVal($attendee, 'status');
            switch ($status) {
            case 'accepted':
                $status = KRONOLITH_RESPONSE_ACCEPTED;
                break;

            case 'declined':
                $status = KRONOLITH_RESPONSE_DECLINED;
                break;

            case 'tentative':
                $status = KRONOLITH_RESPONSE_TENTATIVE;
                break;

            case 'none':
            default:
                $status = KRONOLITH_RESPONSE_NONE;
                break;
            }

            $this->addAttendee($email, $role, $status);
        }

        $this->recurEnd = null;
        $this->recurType = KRONOLITH_RECUR_NONE;

        $recurrence = &$kolab->getRootElem('recurrence');
        if ($recurrence !== false) {
            $cycle = $recurrence->get_attribute('cycle');
            $this->recurInterval = $kolab->getElemVal($recurrence, 'interval');

            switch ($cycle) {
            case 'daily':
                $this->recurType = KRONOLITH_RECUR_DAILY;
                break;

            case 'weekly':
                $this->recurType = KRONOLITH_RECUR_WEEKLY;

                $mask = 0;
                $bits = array(
                    'monday' => HORDE_DATE_MASK_MONDAY,
                    'tuesday' => HORDE_DATE_MASK_TUESDAY,
                    'wednesday' => HORDE_DATE_MASK_WEDNESDAY,
                    'thursday' => HORDE_DATE_MASK_THURSDAY,
                    'friday' => HORDE_DATE_MASK_FRIDAY,
                    'saturday' => HORDE_DATE_MASK_SATURDAY,
                    'sunday' => HORDE_DATE_MASK_SUNDAY,
                );

                $days = $kolab->getAllElems('day', $recurrence);
                foreach ($days as $day) {
                    $day_str = $day->get_content();

                    if (empty($day_str) || !isset($bits[$day_str])) {
                        continue;
                    }

                    $mask |= $bits[$day_str];
                }

                $this->setRecurOnDay($mask);
                break;

            case 'monthly':
                $type = $recurrence->get_attribute('type');
                switch ($type) {
                case 'daynumber':
                    $this->recurType = KRONOLITH_RECUR_DAY_OF_MONTH;
                    break;

                case 'weekday':
                    $this->recurType = KRONOLITH_RECUR_DAY_OF_MONTH;
                    break;
                }
                break;

            case 'yearly':
                $this->recurType = KRONOLITH_RECUR_YEARLY;
                break;
            }

            $range = &$kolab->getElem('range', $recurrence);
            $range_type = $range->get_attribute('type');
            $range_val = $kolab->getElemVal($recurrence, 'range');

            switch ($range_type) {
            case 'number':
                // Kronolith can't handle recurrence intervals by
                // number of instances yet.
                break;

            case 'date':
                $this->recurEnd = new Horde_Date(Kolab::decodeDate($range_val));
                break;
            }
        }

        $this->initialized = true;
        $this->stored = true;
    }

    function toDriver()
    {
    }

}
