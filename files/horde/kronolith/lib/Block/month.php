<?php

$block_name = _("Month View");

/**
 * Horde_Block_Kronolith_month:: Implementation of the Horde_Block API
 * to display a mini month view of calendar items.
 *
 * $Horde: kronolith/lib/Block/month.php,v 1.21.2.7 2006/01/03 20:50:17 chuck Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_Kronolith_month extends Horde_Block {

    var $_app = 'kronolith';

    function _params()
    {
        require_once dirname(__FILE__) . '/../base.php';

        $params = array('calendar' => array('name' => _("Calendar"),
                                            'type' => 'enum',
                                            'default' => '__all'));
        $params['calendar']['values']['__all'] = _("All Visible");
        foreach (Kronolith::listCalendars() as $id => $cal) {
            $params['calendar']['values'][$id] = $cal->get('name');
        }

        return $params;
    }

    /**
     * The title to go in this block.
     *
     * @return string   The title text.
     */
    function _title()
    {
        global $registry;
        return Horde::link(Horde::url($registry->getInitialPage(), true)) . strftime('%B, %Y') . '</a> <small>' .
            Horde::link(Horde::applicationUrl('addevent.php', true)) . Horde::img('new.png', _("New Event")) . ' ' . _("New Event") . '</a></small>';
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content
     */
    function _content()
    {
        global $prefs;
        require_once dirname(__FILE__) . '/../base.php';
        require_once KRONOLITH_BASE . '/lib/Day.php';

        Horde::addScriptFile('tooltip.js', 'horde', true);

        $year = date('Y');
        $month = date('m');
        $today = mktime(0, 0, 0, date('n'), date('j'), date('Y'));

        $startday = &new Horde_Date(array('mday' => 1,
                                          'month' => $month,
                                          'year' => $year));
        $startday = $startday->dayOfWeek();
        $daysInView = Date_Calc::weeksInMonth($month, $year) * 7;
        if (!$prefs->getValue('week_start_monday')) {
            $startOfView = 1 - $startday;

            // We may need to adjust the number of days in the view if
            // we're starting weeks on Sunday.
            if ($startday == HORDE_DATE_SUNDAY) {
                $daysInView -= 7;
            }
            $endday = &new Horde_Date(array('mday' => Horde_Date::daysInMonth($month, $year),
                                            'month' => $month,
                                            'year' => $year));
            $endday = $endday->dayOfWeek();
            if ($endday == HORDE_DATE_SUNDAY) {
                $daysInView += 7;
            }
        } else {
            if ($startday == HORDE_DATE_SUNDAY) {
                $startOfView = -5;
            } else {
                $startOfView = 2 - $startday;
            }
        }

        $startDate = &new Horde_Date(array('year' => $year,
                                           'month' => $month,
                                           'mday' => $startOfView));
        $endDate = &new Horde_Date(array('year' => $year,
                                         'month' => $month,
                                         'mday' => $startOfView + $daysInView,
                                         'hour' => 23,
                                         'min' => 59,
                                         'sec' => 59));
        $startDate->correct();
        $endDate->correct();

        /* Table start. and current month indicator. */
        $html = '<table cellspacing="1" class="block-monthgrid" width="100%"><tr>';

        /* Set up the weekdays. */
        $weekdays = array(_("Mo"), _("Tu"), _("We"), _("Th"), _("Fr"), _("Sa"));
        if (!$prefs->getValue('week_start_monday')) {
            array_unshift($weekdays, _("Su"));
        } else {
            $weekdays[] = _("Su");
        }
        foreach ($weekdays as $weekday) {
            $html .= '<th class="item">' . $weekday . '</th>';
        }

        if (isset($this->_params['calendar']) && $this->_params['calendar'] != '__all') {
            $all_events = Kronolith::listEvents($startDate, $endDate, array($this->_params['calendar']));
        } else {
            $all_events = Kronolith::listEvents($startDate, $endDate, $GLOBALS['display_calendars']);
        }

        $weeks = array();
        $weekday = 0;
        $week = -1;
        for ($day = $startOfView; $day < $startOfView + $daysInView; $day++) {
            $dayStamp = mktime(0, 0, 0, $month, $day, $year);

            if ($weekday == 7) {
                $weekday = 0;
            }
            if ($weekday == 0) {
                $week++;
                $html .= '</tr><tr>';
            }

            if (mktime(0, 0, 0) == $dayStamp) {
                $td_class = 'today';
            } elseif (date('n', $dayStamp) != $month) {
                $td_class = 'othermonth';
            } elseif (date('w', $dayStamp) == 0 || date('w', $dayStamp) == 6) {
                $td_class = 'weekend';
            } else {
                $td_class = 'text';
            }
            $html .= '<td align="center" class="' . $td_class . '">';

            /* Set up the link to the day view. */
            $url = Horde::applicationUrl('day.php', true);
            $url = Util::addParameter($url, array('timestamp' => $dayStamp));

            if (!empty($all_events[$dayStamp])) {
                /* There are events; create a cell with tooltip to
                 * list them. */
                $day_events = '';
                foreach ($all_events[$dayStamp] as $event) {
                    $day_events .= date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia', $event->start->timestamp()) . ' - ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia', $event->end->timestamp());
                    $day_events .= ($event->getLocation()) ? ' (' . $event->getLocation() . ')' : '';
                    $day_events .= ' ' . $event->getTitle() . "\n";
                }
                $cell = Horde::linkTooltip($url, _("View Day"), '', '', '', $day_events) . date('j', $dayStamp) . '</a>';
            } else {
                /* No events, plain link to the day. */
                $cell = Horde::linkTooltip($url, _("View Day")) . date('j', $dayStamp) . '</a>';
            }

            /* Bold the cell if there are events. */
            if (!empty($all_events[$dayStamp])) {
                $cell = '<strong>' . $cell . '</strong>';
            }

            $html .= $cell . '</td>';
            $weekday++;
        }

        return $html . '</tr></table>';
    }

}
