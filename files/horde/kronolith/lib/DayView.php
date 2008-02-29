<?php

require_once KRONOLITH_BASE . '/lib/Day.php';

/**
 * The Kronolith_DayView:: class provides an API for viewing days.
 *
 * $Horde: kronolith/lib/DayView.php,v 1.139.2.13 2006/11/18 22:44:55 chuck Exp $
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jan Schneider <jan@horde.org>
 * @since   Kronolith 0.1
 * @package Kronolith
 */
class Kronolith_DayView extends Kronolith_Day {

    var $_events = array();
    var $_all_day_events = array();
    var $_event_matrix = array();
    var $_parsed = false;
    var $_span = array();
    var $_totalspan = 0;
    var $_sidebyside = false;
    var $_currentCalendars = array();

    function Kronolith_DayView($month = null, $day = null, $year = null, $events = null)
    {
        parent::Kronolith_Day($month, $day, $year);

        global $prefs;
        $this->_sidebyside = $prefs->getValue('show_shared_side_by_side');
        if ($this->_sidebyside) {
            $allCalendars = Kronolith::listCalendars();
            foreach ($GLOBALS['display_calendars'] as $cid) {
                 $this->_currentCalendars[$cid] = &$allCalendars[$cid];
                 $this->_all_day_events[$cid] = array();
            }
        } else {
            $this->_currentCalendars = array(0);
        }

        if (is_null($events)) {
            $events = Kronolith::listEvents($this,
                                            new Horde_Date(array('year' => $this->year, 'month' => $this->month, 'mday' => $this->mday,
                                                                 'hour' => 23, 'min' => 59, 'sec' => 59)),
                                            $GLOBALS['display_calendars']);
            $this->_events = array_shift($events);
        } else {
            $this->_events = $events;
        }

        if (!is_array($this->_events)) {
            $this->_events = array();
        }
    }

    function setEvents($events)
    {
        $this->_events = $events;
    }

    function html($template_path = KRONOLITH_TEMPLATES)
    {
        global $prefs, $print_view;

        if (!$this->_parsed) {
            $this->parse();
        }

        $started = false;
        $first_row = true;
        $addLinks = Kronolith::getDefaultCalendar(PERMS_EDIT) && !$print_view &&
            (!empty($GLOBALS['conf']['hooks']['permsdenied']) ||
             Kronolith::hasPermission('max_events') === true ||
             Kronolith::hasPermission('max_events') > Kronolith::countEvents());

        require $template_path . '/day/head.inc';
        if ($this->_sidebyside) {
            require $template_path . '/javascript/open_calendar_search.js';
            require $template_path . '/day/head_side_by_side.inc';
        }

        $eventCategories = array();

        $row = '';
        if ($addLinks) {
            $addeventurl = Util::addParameter('addevent.php', array('timestamp' => $this->slots[0]['timestamp'],
                                                                    'allday' => 1,
                                                                    'url' => Horde::selfUrl(true, true, true)));
            $addeventurl = Horde::link(Horde::applicationUrl($addeventurl), _("Create a New Event"), 'hour') . _("All day") .
                Horde::img('new_small.png', '+') . '</a>';
        } else {
            $addeventurl = '<span class="hour">' . _("All day") . '</span>';
        }

        /* The all day events are not listed in different columns, but in
         * different rows.  In side by side view we do not spread an event
         * over multiple rows if there are different numbers of all day events
         * for different calendars.  We just put one event in a single row
         * with no rowspan.  We put in a rowspan in the row after the last
         * event to fill all remaining rows. */
        $rowspan = ($this->_all_day_maxrowspan) ? ' rowspan="' . $this->_all_day_maxrowspan . '" ' : '';
        for ($k = 0; $k < $this->_all_day_maxrowspan; $k++) {
            $row = '';
            foreach ($this->_currentCalendars as $cid => $cal) {
                if (count($this->_all_day_events[$cid]) === $k) {
                    // There are no events or all events for this calendar
                    // have already been printed.
                    $row .= '<td class="allday" width="1%" rowspan="' . ($this->_all_day_maxrowspan - $k) . '" colspan="'.  $this->_span[$cid] . '">&nbsp;</td>';
                } elseif (count($this->_all_day_events[$cid]) > $k) {
                    // We have not printed every all day event yet. Put one
                    // into this row.
                    $event = $this->_all_day_events[$cid][$k];
                    $eventCategories[$event->getCategory()] = true;

                    $row .= '<td class="day-eventbox category' . md5($event->getCategory()) . '" ' .
                        'width="' . round(90 / count($this->_currentCalendars))  . '%" ' .
                        'valign="top" colspan="' . $this->_span[$cid] . '">' .
                        $event->getLink($this->getStamp()) . '</td>';
                }
            }
            require $template_path . '/day/all_day.inc';
            $first_row = false;
        }

        if ($first_row) {
            $row .= '<td colspan="' . $this->_totalspan . '">&nbsp;</td>';
            require $template_path . '/day/all_day.inc';
        }

        $twenty_four = $prefs->getValue('twentyFour');
        $day_hour_start = $prefs->getValue('day_hour_start') / 2 * $this->_slotsPerHour;
        $day_hour_end = $prefs->getValue('day_hour_end') / 2 * $this->_slotsPerHour;
        $rows = array();

        for ($i = 0; $i < $this->_slotsPerDay; $i++) {
            if ($i >= $day_hour_end && $i > $this->last) {
                break;
            }
            if ($i < $this->first && $i < $day_hour_start) {
                continue;
            }

            $row = '';
            if (($m = $i % $this->_slotsPerHour) != 0) {
                $time = ':' . $m * $this->_slotLength;
                $hourclass = 'halfhour';
            } else {
                $time = date($twenty_four ? 'G' : 'ga', $this->slots[$i]['timestamp']);
                $hourclass = 'hour';
            }

            if (!count($this->_currentCalendars)) {
                $row .= '<td>&nbsp;</td>';
            }

            foreach ($this->_currentCalendars as $cid => $cal) {
                $hspan = 0;
                foreach ($this->_event_matrix[$cid][$i] as $key) {
                    $event = &$this->_events[$key];

                    // Since we've made sure that this event's overlap is a
                    // factor of the total span, we get this event's
                    // individual span by dividing the total span by this
                    // event's overlap.
                    $span = $this->_span[$cid] / $event->overlap;
                    $hspan += $span;

                    $start = mktime(floor($i / $this->_slotsPerHour), ($i % $this->_slotsPerHour) * $this->_slotLength, 0,
                                    $this->month, $this->mday, $this->year);
                    if ($event->start->timestamp() >= $start && $event->start->timestamp() < $start + 60 * $this->_slotLength || $start == $this->getStamp()) {
                        $eventCategories[$event->getCategory()] = true;

                        $row .= '<td class="day-eventbox category' . md5($event->getCategory()) . '" ' .
                            'width="' . round((90 / count($this->_currentCalendars)) * ($span / $this->_span[$cid]))  . '%" ' .
                            'valign="top" colspan="' . $span . '" rowspan="' . $event->rowspan . '">' .
                            $event->getLink($this->getStamp()) .
                            '&nbsp;</td>';
                    }
                }

                $diff = $this->_span[$cid] - $hspan;
                if ($diff > 0) {
                    $row .= str_repeat('<td>&nbsp;</td>', $diff);
                }
            }

            if ($addLinks) {
                $addeventurl = Util::addParameter('addevent.php',
                                                  array('timestamp' => $this->slots[$i]['timestamp'],
                                                        'url' => Horde::selfUrl(true, true, true)));
                $addeventurl = Horde::link(Horde::applicationUrl($addeventurl), _("Create a New Event"), $hourclass) .
                    $time . Horde::img('new_small.png', '+') . '</a>';
            } else {
                $addeventurl = '<span class="' . $hourclass . '">' . $time . '</span>';
            }

            $rows[] = array('row' => $row, 'slot' => $addeventurl);
        }

        require_once 'Horde/Template.php';
        $template = &new Horde_Template();
        $template->set('row_height', round(20 / $this->_slotsPerHour));
        $template->set('rows', $rows);
        $template->set('show_slots', true, true);
        echo $template->fetch($template_path . '/day/rows.html');

        require $template_path . '/category_legend.inc';
    }

    /**
     * This function runs through the events and tries to figure out
     * what should be on each line of the output table. This is a
     * little tricky.
     */
    function parse()
    {
        global $prefs;

        $tmp = array();
        $this->_all_day_maxrowspan = 0;

        // Separate out all day events and do some initialization/prep
        // for parsing.
        foreach ($this->_currentCalendars as $cid => $cal) {
            $this->_all_day_events[$cid] = array();
            $this->_all_day_rowspan[$cid] = 0;
        }

        foreach ($this->_events as $key => $event) {
            // If we have side_by_side we only want to include the
            // event in the proper calendar.
            if ($this->_sidebyside) {
                $cid = $event->getCalendar();
            } else {
                $cid = 0;
            }

            // All day events are easy; store them seperately.
            if ($event->isAllDay()) {
                $this->_all_day_events[$cid][] = &Util::cloneObject($event);
                $this->_all_day_rowspan[$cid]++;
                $this->_all_day_maxrowspan = max($this->_all_day_maxrowspan, $this->_all_day_rowspan[$cid]);
            } else {
                // Initialize the number of events that this event
                // overlaps with.
                $event->overlap = 0;

                // Initialize this event's vertical span.
                $event->rowspan = 0;

                $tmp[] = &Util::cloneObject($event);
            }
        }
        $this->_events = $tmp;

        // Initialize the set of different rowspans needed.
        $spans = array(1 => true);

        // Track the last slot in which we have an event.
        $this->last = 0;
        $this->first = $this->_slotsPerDay;

        // Run through every slot, adding in entries for every event
        // that we have here.
        for ($i = 0; $i < $this->_slotsPerDay; $i++) {
            // Initialize this slot in the event matrix.
            foreach ($this->_currentCalendars as $cid => $cal) {
                $this->_event_matrix[$cid][$i] = array();
            }

            // Calculate the start and end timestamps for this slot.
            $start = mktime(floor($i / $this->_slotsPerHour), ($i % $this->_slotsPerHour) * $this->_slotLength, 0,
                            $this->month, $this->mday, $this->year);
            $end = $start + (60 * $this->_slotLength);

            // Search through our events.
            foreach ($this->_events as $key => $event) {
                // If we have side_by_side we only want to include the
                // event in the proper calendar.
                if ($this->_sidebyside) {
                    $cid = $event->getCalendar();
                } else {
                    $cid = 0;
                }

                // If the event falls anywhere inside this slot, add
                // it, make sure other events know that they overlap
                // it, and increment the event's vertical span.
                if (($event->end->timestamp() > $start && $event->start->timestamp() < $end ) ||
                    ($event->end->timestamp() == $event->start->timestamp() && $event->start->timestamp() == $start)) {

                    // Make sure we keep the latest hour than an event
                    // reaches up-to-date.
                    if ($i > $this->last) {
                        $this->last = $i;
                    }

                    // Make sure we keep the first hour than an event
                    // reaches up-to-date.
                    if ($i < $this->first) {
                        $this->first = $i;
                    }

                    // Add this event to the events which are in this
                    // row.
                    $this->_event_matrix[$cid][$i][] = $key;

                    // Increment the event's vertical span.
                    $this->_events[$key]->rowspan++;
                }
            }

            foreach ($this->_currentCalendars as $cid => $cal) {
                // Update the number of events that events in this row
                // overlap with.
                foreach ($this->_event_matrix[$cid][$i] as $ev) {
                    $this->_events[$ev]->overlap = max($this->_events[$ev]->overlap,
                                                       count($this->_event_matrix[$cid][$i]));
                }

                // Update the set of rowspans to include the value for
                // this row.
                $spans[$cid][count($this->_event_matrix[$cid][$i])] = true;
            }
        }

        foreach ($this->_currentCalendars as $cid => $cal) {
            // Sort every row by event duration, so that longer events are
            // farther to the left.
            for ($i = 0; $i <= $this->last; $i++) {
                if (count($this->_event_matrix[$cid][$i])) {
                    usort($this->_event_matrix[$cid][$i], array($this, '_sortByDuration'));
                }
            }

            // Now that we have the number of events in each row, we
            // can calculate the total span needed.
            $span[$cid] = 1;

            // Turn keys into array values.
            $spans[$cid] = array_keys($spans[$cid]);

            // Start with the biggest one first.
            rsort($spans[$cid]);
            foreach ($spans[$cid] as $s) {
                // If the number of events in this row doesn't divide
                // cleanly into the current total span, we need to
                // multiply the total span by the number of events in
                // this row.
                if ($s != 0 && $span[$cid] % $s != 0) {
                    $span[$cid] *= $s;
                }
            }
            $this->_totalspan += $span[$cid];
        }
        // Set the final span.
        if (isset($span)) {
            $this->_span = $span;
        } else {
            $this->_totalspan = 1;
        }

        // We're now parsed and ready to go.
        $this->_parsed = true;
    }

    function link($offset = 0)
    {
        $url = Horde::applicationUrl('day.php');
        $url = Util::addParameter($url, 'month', $this->getTime('%m', $offset));
        $url = Util::addParameter($url, 'mday', ltrim($this->getTime('%d', $offset)));
        $url = Util::addParameter($url, 'year', $this->getTime('%Y', $offset));

        return $url;
    }

    function _sortByDuration($evA, $evB)
    {
        $durA = $this->_events[$evA]->rowspan;
        $durB = $this->_events[$evB]->rowspan;

        if ($durA > $durB) {
            return -1;
        } elseif ($durA == $durB) {
            return 0;
        } else {
            return 1;
        }
    }

}
