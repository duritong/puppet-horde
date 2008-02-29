<?php
/**
 * $Horde: kronolith/year.php,v 1.16.2.4 2007/01/02 13:55:05 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

if ($timestamp = (int)Util::getFormData('timestamp')) {
    $year = date('Y', $timestamp);
} else {
    $year = (int)Util::getFormData('year', date('Y'));
}

$today = mktime(0, 0, 0);
$timestamp = mktime(1, 1, 1, 1, 1, $year);
$prevstamp = mktime(1, 1, 1, 1, 1, $year - 1);
$nextstamp = mktime(1, 1, 1, 1, 1, $year + 1);
$title = strftime('%Y', $timestamp);
$print_view = (bool)Util::getFormData('print');

Horde::addScriptFile('tooltip.js', 'horde', true);
if (!$print_view) {
    Horde::addScriptFile('popup.js', 'horde', true);
}
require KRONOLITH_TEMPLATES . '/common-header.inc';

if ($print_view) {
    require_once $registry->get('templates', 'horde') . '/javascript/print.js';
} else {
    $print_link = Util::addParameter('year.php', array('timestamp' => $timestamp,
                                                       'print' => 'true'));
    $print_link = Horde::url($print_link);
    require KRONOLITH_TEMPLATES . '/menu.inc';
}

$prevurl = Horde::applicationurl(Util::addParameter('year.php', array('year' => date('Y', $prevstamp))));
$nexturl = Horde::applicationUrl(Util::addParameter('year.php', array('year' => date('Y', $nextstamp))));

echo '<div id="page">';

if (!$print_view) {
    Kronolith::tabs();
}

require KRONOLITH_TEMPLATES . '/year/head.inc';

$startDate = &new Horde_Date(array('year' => $year,
                                   'month' => 1,
                                   'mday' => 1));
$endDate = &new Horde_Date(array('year' => $year,
                                 'month' => 12,
                                 'mday' => 31,
                                 'hour' => 23,
                                 'min' => 59,
                                 'sec' => 59));
$startDate->correct();
$endDate->correct();
$allevents = Kronolith::listEvents($startDate, $endDate, $GLOBALS['display_calendars']);

$html = '';
for ($month = 1; $month <= 12; $month++) {
    $html .= '<td valign="top">';

    // Heading for each month.
    $mtitle = strftime('%B', mktime(1, 1, 1, $month, 1, $year));
    $html .= '<table class="nopadding" cellspacing="0" width="100%"><tr><th class="header">';

    $url = Util::addParameter(Horde::applicationUrl('month.php'), array('month' => $month, 'year' => date('Y', $timestamp), 'timestamp' => $timestamp));
    $html .= '<a href="' . $url . '">' . $mtitle . '</a></th></tr>';
    $html .= '<tr><td class="monthgrid"><table class="nopadding" cellspacing="1" width="100%"><tr>';
    if (!$prefs->getValue('week_start_monday')) {
        $html .= '<th class="item">' . _("Su"). '</th>';
    }
    $html .= '<th class="item">' . _("Mo") . '</th>';
    $html .= '<th class="item">' . _("Tu") . '</th>';
    $html .= '<th class="item">' . _("We") . '</th>';
    $html .= '<th class="item">' . _("Th") . '</th>';
    $html .= '<th class="item">' . _("Fr") . '</th>';
    $html .= '<th class="item">' . _("Sa") . '</th>';
    if ($prefs->getValue('week_start_monday')) {
        $html .= '<th class="item">' . _("Su") . '</th>';
    }
    $html .= "</tr>\n<tr>";

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

    $currentCalendars = array(true);

    $eventCategories = array();

    foreach ($currentCalendars as $id => $cal) {
        $cell = 0;
        for ($day = $startOfView; $day < $startOfView + $daysInView; $day++) {
            $date = &new Horde_Date(array('year' => $year, 'month' => $month, 'mday' => $day));
            $daystamp = $date->timestamp();
            $date->hour = $prefs->getValue('twentyFour') ? 12 : 6;
            $timestamp = $date->timestamp();
            $week = $date->weekOfYear();

            if ($cell % 7 == 0 && $cell != 0) {
                $html .= "</tr>\n<tr>";
            }
            if (date('n', $daystamp) != $month) {
                $style = 'monthgrid';
            } elseif (date('w', $daystamp) == 0 || date('w', $daystamp) == 6) {
                $style = 'weekend';
            } else {
                $style = 'text';
            }

            /* Set up the link to the day view. */
            $url = Horde::applicationUrl('day.php', true);
            $url = Util::addParameter($url, array('timestamp' => $daystamp));

            if (date('n', $daystamp) != $month) {
                $cellday = '&nbsp;';
            } elseif (!empty($allevents[$daystamp])) {
                /* There are events; create a cell with tooltip to list
                 * them. */
                $day_events = '';
                foreach ($allevents[$daystamp] as $event) {
                    $day_events .= date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia', $event->start->timestamp()) . ' - ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:ia', $event->end->timestamp());
                    $day_events .= ($event->getLocation()) ? ' (' . $event->getLocation() . ')' : '';
                    $day_events .= ' ' . $event->getTitle() . "\n";
                }
                /* Bold the cell if there are events. */
                $cellday = '<strong>' . Horde::linkTooltip($url, _("View Day"), '', '', '', $day_events) . date('j', $daystamp) . '</a></strong>';

                /* Set the background color to distinguish the day */
                $style = 'year-event';
            } else {
                /* No events, plain link to the day. */
                $cellday = Horde::linkTooltip($url, _("View Day")) . date('j', $daystamp) . '</a>';
            }
            if ($today == $daystamp && date('n', $daystamp) == $month) {
                $style .= ' today';
            }

            $html .= '<td align="center" class="' . $style . '" height="10" width="5%" valign="top">';
            $html .= $cellday . '</td>';
            $cell++;
        }
    }

    $html .= "</tr></table></td></tr></table></td>\n";
    if ($month % 3 == 0 && $month != 12) {
        $html .= "</tr>\n<tr>";
    }
}

echo $html . '</tr></table></div>';
require $registry->get('templates', 'horde') . '/common-footer.inc';
