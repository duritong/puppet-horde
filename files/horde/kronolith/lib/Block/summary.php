<?php

$block_name = _("Calendar Summary");

/**
 * Horde_Block_Kronolith_summary:: Implementation of the Horde_Block API to
 * display a summary of calendar items.
 *
 * $Horde: kronolith/lib/Block/summary.php,v 1.41.2.7 2007/01/18 15:54:20 jan Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_Kronolith_summary extends Horde_Block {

    var $_app = 'kronolith';

    function _params()
    {
        @define('KRONOLITH_BASE', dirname(__FILE__) . '/../..');
        require_once KRONOLITH_BASE . '/lib/base.php';

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
        return Horde::link(Horde::url($registry->getInitialPage(), true)) . $registry->get('name') . '</a> <small>' . Horde::link(Horde::applicationUrl('addevent.php', true)) . Horde::img('new.png', _("New Event")) . ' ' . _("New Event") . '</a></small>';
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content
     */
    function _content()
    {
        global $registry, $prefs;
        require_once dirname(__FILE__) . '/../base.php';
        require_once KRONOLITH_BASE . '/lib/Day.php';
        require_once 'Horde/Prefs/CategoryManager.php';

        Horde::addScriptFile('tooltip.js', 'horde', true);

        $now = time();
        $today = date('j');

        $startDate = &new Horde_Date(array('year' => date('Y'),
                                           'month' => date('n'),
                                           'mday' => date('j')));
        $endDate = &new Horde_Date(array('year' => date('Y'),
                                         'month' => date('n'),
                                         'mday' => date('j') + $prefs->getValue('summary_days')));
        $endDate->correct();

        if (isset($this->_params['calendar']) &&
            $this->_params['calendar'] != '__all') {
            $allevents = Kronolith::listEvents($startDate,
                                               $endDate,
                                               array($this->_params['calendar']));
        } else {
            $allevents = Kronolith::listEvents($startDate,
                                               $endDate,
                                               $GLOBALS['display_calendars']);
        }

        $html = '';
        $iMax = $today + $prefs->getValue('summary_days');
        $firstday = true;
        for ($i = $today; $i < $iMax; ++$i) {
            $day = &new Kronolith_Day(date('n'), $i);
            if (empty($allevents[$day->getStamp()])) {
                continue;
            }

            $events = &$allevents[$day->getStamp()];
            $firstevent = true;

            $today12am = mktime(0, 0, 0,
                                $day->month,
                                $day->mday,
                                $day->year);
            $tomorrow12am = mktime(0, 0, 0,
                                   $day->month,
                                   $day->mday + 1,
                                   $day->year);
            foreach ($events as $event) {
                if (!$event->hasRecurType(KRONOLITH_RECUR_NONE)) {
                    $event->start = &new Horde_Date(
                        array('hour' => $event->start->hour,
                              'min' => $event->start->min,
                              'sec' => $event->start->sec,
                              'month' => $day->month,
                              'mday' => $day->mday,
                              'year' => $day->year));
                    $event->end = &new Horde_Date($event->start->timestamp() +
                                                  ($event->durMin * 60));
                } else {
                    if ($event->start->timestamp() < $today12am) {
                        $event->start = &new Horde_Date($today12am);
                    }
                    if ($event->end->timestamp() >= $tomorrow12am) {
                        $event->end = &new Horde_Date($tomorrow12am);
                    }
                }
                if ($event->end->timestamp() < $now) continue;
                if ($prefs->getValue('summary_alarms') && !$event->alarm) continue;
                if ($firstevent) {
                    if (!$firstday) {
                        $html .= '<tr><td colspan="3" style="font-size:2px;">&nbsp;</td></tr>';
                    }
                    $html .= '<tr><td colspan="3" class="control"><strong>';
                    if ($day->isToday()) {
                        $dayname = _("Today");
                    } elseif ($day->isTomorrow()) {
                        $dayname = _("Tomorrow");
                    } elseif ($day->diff() < 7) {
                        $dayname = strftime('%A', $day->getStamp());
                    } else {
                        $dayname = strftime($prefs->getValue('date_format'),
                                            $day->getStamp());
                    }
                    $daylink = Horde::applicationUrl('day.php');
                    $daylink = Util::addParameter($daylink,
                                                  'timestamp',
                                                  $day->getStamp());
                    $html .= Horde::link($daylink, sprintf(_("Goto %s"),
                                                           $dayname));
                    $html .= $dayname . '</a></strong></td></tr>';
                    $firstevent = false;
                    $firstday = false;
                }
                $html .= '<tr><td class="text" nowrap="nowrap" valign="top">';
                if ($event->start->timestamp() < $now &&
                    $event->end->timestamp() > $now) {
                    $html .= '<strong>';
                }

                if ($event->start->hour != 0 ||
                    $event->start->min != 0 ||
                    (($event->end->timestamp() - $event->start->timestamp()) %
                     (24 * 60 * 60)) != 0) {
                    if ($prefs->getValue('twentyFour')) {
                        $time = date('G:i', $event->start->timestamp()) . '-' .
                            date('G:i', $event->end->timestamp());
                    } else {
                        $time = date('g:i A', $event->start->timestamp()) . '-' .
                            date('g:i A', $event->end->timestamp());
                    }
                } else {
                    $time = _("All day event");
                }

                $text = $event->getTitle();
                if ($location = $event->getLocation()) {
                    $text .= ' (' . $location . ')';
                }
                $html .= $time;
                if ($event->start->timestamp() < $now &&
                    $event->end->timestamp() > $now) {
                    $html .= '</strong>';
                }

                $html .= '</td><td class="text">&nbsp;&nbsp;&nbsp;</td>' .
                    '<td class="block-eventbox category' . md5($event->getCategory()) . '" valign="top">';

                if ($event->start->timestamp() < $now &&
                    $event->end->timestamp() > $now) {
                    $html .= '<strong>';
                }
                $html .= $event->getLink(null, true);
                if ($event->start->timestamp() < $now &&
                    $event->end->timestamp() > $now) {
                    $html .= '</strong>';
                }
                $html .= '</td></tr>';
            }
        }

        if (empty($html)) {
            return '<em>' . _("No events to display") . '</em>';
        }

        return '<link href="' . Horde::applicationUrl('themes/categoryCSS.php') . '" rel="stylesheet" type="text/css" /><table cellspacing="0" width="100%">' . $html . '</table>';
    }

}
