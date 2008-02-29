<?php
/**
 * The Kronolith_WeekView:: class provides an API for viewing weeks.
 *
 * $Horde: kronolith/lib/WeekView.php,v 1.88.2.4 2005/10/18 12:27:30 jan Exp $
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jan Schneider <jan@horde.org>
 * @since   Kronolith 0.1
 * @package Kronolith
 */
class Kronolith_WeekView {

    var $parsed = false;
    var $days = array();
    var $week = null;
    var $year = null;
    var $startDay = HORDE_DATE_SUNDAY;
    var $endDay = HORDE_DATE_SATURDAY;
    var $_sidebyside = false;
    var $_currentCalendars = array();

    /**
     * How many time slots are we dividing each hour into?
     *
     * @var integer
     */
    var $_slotsPerHour = 2;

    /**
     * How many slots do we have per day? Calculated from $_slotsPerHour.
     *
     * @see $_slotsPerHour
     * @var integer
     */
    var $_slotsPerDay;

    function Kronolith_WeekView($week = null, $year = null, $startDay = null, $endDay = null)
    {
        if (empty($year)) {
            $year = date('Y');
        }
        if (empty($week)) {
            $date = &new Horde_Date(array('year' => $year, 'month' => date('n'), 'mday' => date('j')));
            $week = $date->weekOfYear();
            if ($week == 1 && date('n') == 12) {
                $year++;
            }
        } else {
            $weeksInYear = Horde_Date::weeksInYear($year);
            if ($week < 1) {
                $year--;
                $week += $weeksInYear;
            } elseif ($week > $weeksInYear) {
                $week -= $weeksInYear;
                $year++;
            }
        }

        $this->year = $year;
        $this->week = $week;

        if (isset($startDay)) {
            $this->startDay = $startDay;
        }
        if (isset($endDay)) {
            $this->endDay = $endDay;
        }

        $firstDay = Horde_Date::firstDayOfWeek($week, $year) + Date_Calc::dateToDays(1, 1, $year) - 1;

        require_once KRONOLITH_BASE . '/lib/DayView.php';
        for ($i = $this->startDay; $i <= $this->endDay; $i++) {
            list($day, $month, $year) = explode('/', Date_Calc::daysToDate($firstDay + $i, '%d/%m/%Y'));
            $this->days[$i] = &new Kronolith_DayView($month, $day, $year, null, array());
        }

        list($sday, $smonth, $syear) = explode('/', Date_Calc::daysToDate($firstDay + $this->startDay, '%d/%m/%Y'));
        list($eday, $emonth, $eyear) = explode('/', Date_Calc::daysToDate($firstDay + $this->endDay, '%d/%m/%Y'));
        $startDate = &new Horde_Date(array('year' => $syear, 'month' => $smonth, 'mday' => $sday));
        $endDate = &new Horde_Date(array('year' => $eyear, 'month' => $emonth, 'mday' => $eday,
                                         'hour' => 23, 'min' => 59, 'sec' => 59));
        $endDate->correct();
        $allevents = Kronolith::listEvents($startDate, $endDate, $GLOBALS['display_calendars']);
        for ($i = $this->startDay; $i <= $this->endDay; $i++) {
            $this->days[$i]->setEvents(isset($allevents[$this->days[$i]->getStamp()]) ?
                                       $allevents[$this->days[$i]->getStamp()] :
                                       array());
        }
        $this->_sidebyside = $this->days[$this->startDay]->_sidebyside;
        $this->_currentCalendars = $this->days[$this->startDay]->_currentCalendars;
        $this->_slotsPerHour = $this->days[$this->startDay]->_slotsPerHour;
        $this->_slotsPerDay = $this->days[$this->startDay]->_slotsPerDay;
        $this->_slotLength = $this->days[$this->startDay]->_slotLength;
    }

    function html($template_path = KRONOLITH_TEMPLATES)
    {
        global $prefs, $print_view;

        $more_timeslots = $prefs->getValue('time_between_days');
        $include_all_events = !$prefs->getValue('show_shared_side_by_side');

        if (!$this->parsed) {
            $this->parse();
        }

        $slots = $this->days[$this->startDay]->slots;
        $cid = 0;
        require $template_path . '/week/head.inc';
        if ($this->_sidebyside) {
            require $template_path . '/javascript/open_calendar_search.js';
            require $template_path . '/week/head_side_by_side.inc';
        }

        $event_count = 0;
        for ($j = $this->startDay; $j <= $this->endDay; $j++) {
            foreach ($this->_currentCalendars as $cid => $cal) {
                $event_count = max($event_count, count($this->days[$j]->_all_day_events[$cid]));
                reset($this->days[$j]->_all_day_events[$cid]);
            }
        }

        if ($more_timeslots) {
            $addeventurl = null;
        } else {
            $addeventurl = _("All day");
        }

        $eventCategories = array();

        $row = '';
        for ($j = $this->startDay; $j <= $this->endDay; $j++) {
            $row .= '<td class="hour" align="right">' . ($more_timeslots ? _("All day") : '&nbsp;') . '</td>';
            $row .= '<td colspan="' . $this->days[$j]->_totalspan . '" valign="top"><table width="100%" cellspacing="0">';
            if ($this->days[$j]->_all_day_maxrowspan > 0) {
                for ($k = 0; $k < $this->days[$j]->_all_day_maxrowspan; $k++) {
                    $row .= '<tr>';
                    foreach ($this->days[$j]->_currentCalendars as $cid => $cal) {
                        if (count($this->days[$j]->_all_day_events[$cid]) === $k) {
                            $row .= '<td rowspan="' . ($this->days[$j]->_all_day_maxrowspan - $k) . '" width="'. round(99 / count($this->days[$j]->_currentCalendars)) . '%">&nbsp;</td>';
                        } elseif (count($this->days[$j]->_all_day_events[$cid]) > $k) {
                            $event = $this->days[$j]->_all_day_events[$cid][$k];
                            $eventCategories[$event->getCategory()] = true;

                            $row .= '<td class="week-eventbox category' . md5($event->getCategory()) . '" ' .
                                'width="' . round(99 / count($this->days[$j]->_currentCalendars)) . '%" ' .
                                'valign="top">' .
                                $event->getLink($this->days[$j]->getStamp()) . '</td>';
                        }
                    }
                    $row .= '</tr>';
                }
            } else {
                $row .= '<tr><td colspan="' . count($this->_currentCalendars) . '">&nbsp;</td></tr>';
            }
            $row .= '</table></td>';
        }

        $rowspan = '';
        $first_row = true;
        require $template_path . '/day/all_day.inc';

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

            if (($m = $i % $this->_slotsPerHour) != 0) {
                $time = ':' . $m * $this->_slotLength;
                $hourclass = 'halfhour';
            } else {
                $time = date($twenty_four ? 'G' : 'ga', $slots[$i]['timestamp']);
                $hourclass = 'hour';
            }

            $row = '';
            for ($j = $this->startDay; $j <= $this->endDay; $j++) {
                // Add spacer between days, or timeslots.
                if ($more_timeslots) {
                    $row .= '<td align="right" class="' . $hourclass . '">' . $time . '</td>';
                } else {
                    $row .= '<td width="1%">&nbsp;</td>';
                }

                if (!count($this->_currentCalendars)) {
                    $row .= '<td>&nbsp;</td>';
                }
                foreach ($this->_currentCalendars as $cid => $cal) {
                    $hspan = 0;
                    foreach ($this->days[$j]->_event_matrix[$cid][$i] as $key) {
                        $event = &$this->days[$j]->_events[$key];
                        if ($include_all_events || $event->getCalendar() == $cid) {
                            // Since we've made sure that this event's
                            // overlap is a factor of the total span,
                            // we get this event's individual span by
                            // dividing the total span by this event's
                            // overlap.
                            $span = $this->days[$j]->_span[$cid] / $event->overlap;
                            $hspan += $span;

                            $start = mktime(floor($i / $this->_slotsPerHour), ($i % $this->_slotsPerHour) * $this->_slotLength, 0,
                                            $this->days[$j]->month, $this->days[$j]->mday, $this->days[$j]->year);
                            if ($event->start->timestamp() >= $start && $event->start->timestamp() < $start + 60 * $this->_slotLength || $start == $this->days[$j]->getStamp()) {
                                $eventCategories[$event->getCategory()] = true;

                                $row .= '<td class="week-eventbox category' . md5($event->getCategory()) . '" ' .
                                    'valign="top" ' .
                                    'width="' . floor(((90 / count($this->days)) / count($this->_currentCalendars)) * ($span / $this->days[$j]->_span[$cid])) . '%" ' .
                                    'colspan="' . $span . '" rowspan="' . $event->rowspan . '">' .
                                    $event->getLink($this->days[$j]->getStamp()) . '</td>';
                            }
                        }
                    }

                    $diff = $this->days[$j]->_span[$cid] - $hspan;
                    if ($diff > 0) {
                        $row .= str_repeat('<td>&nbsp;</td>', $diff);
                    }
                }
            }

            $rows[] = array('row' => $row, 'slot' => '<span class="' . $hourclass . '">' . $time . '</span>');
        }

        require_once 'Horde/Template.php';
        $template = &new Horde_Template();
        $template->set('row_height', round(20 / $this->_slotsPerHour));
        $template->set('rows', $rows);
        $template->set('show_slots', !$more_timeslots, true);
        echo $template->fetch($template_path . '/day/rows.html');

        require $template_path . '/category_legend.inc';
    }

    /**
     * Parse all events for all of the days that we're handling; then
     * run through the results to get the total horizontal span for
     * the week, and the latest event of the week.
     */
    function parse()
    {
        for ($i = $this->startDay; $i <= $this->endDay; $i++) {
            $this->days[$i]->parse();
        }

        $this->totalspan = 0;
        $this->span = array();
        for ($i = $this->startDay; $i <= $this->endDay; $i++) {
            $this->totalspan += $this->days[$i]->_totalspan;
            foreach ($this->_currentCalendars as $cid => $key) {
                if (isset($this->span[$cid])) {
                    $this->span[$cid] += $this->days[$i]->_span[$cid];
                } else {
                    $this->span[$cid] = $this->days[$i]->_span[$cid];
                }
            }
        }

        $this->last = 0;
        $this->first = $this->_slotsPerDay;
        for ($i = $this->startDay; $i <= $this->endDay; $i++) {
            if ($this->days[$i]->last > $this->last) {
                $this->last = $this->days[$i]->last;
            }
            if ($this->days[$i]->first < $this->first) {
                $this->first = $this->days[$i]->first;
            }
        }
    }

    function link($offset = 0)
    {
        $scriptName = basename($_SERVER['PHP_SELF']);
        $week = $this->week + $offset;
        $year = $this->year;
        $weeksInYear = Horde_Date::weeksInYear($year);
        if ($week < 1) {
            $year--;
            $week += $weeksInYear;
        } elseif ($week > $weeksInYear) {
            $week -= $weeksInYear;
            $year++;
        }
        $url = Util::addParameter($scriptName, 'week', $week);
        $url = Util::addParameter($url, 'year', $year);
        return Horde::applicationUrl($url);
    }

}
