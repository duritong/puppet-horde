<?php
/**
 * $Horde: kronolith/month.php,v 1.170.4.12 2007/01/02 13:55:05 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

if ($timestamp = (int)Util::getFormData('timestamp')) {
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
} else {
    $month = (int)Util::getFormData('month', date('n'));
    $year = (int)Util::getFormData('year', date('Y'));
}
$sidebyside = $prefs->getValue('show_shared_side_by_side');
$timestamp = mktime(1, 1, 1, $month, 1, $year);
$prevstamp = mktime(1, 1, 1, $month - 1, 1, $year);
$nextstamp = mktime(1, 1, 1, $month + 1, 1, $year);
$title = strftime('%B %Y', $timestamp);
$print_view = (bool)Util::getFormData('print');

Horde::addScriptFile('tooltip.js', 'horde', true);
if (!$print_view) {
    Horde::addScriptFile('popup.js', 'horde', true);
}
require KRONOLITH_TEMPLATES . '/common-header.inc';

if ($print_view) {
    require_once $registry->get('templates', 'horde') . '/javascript/print.js';
} else {
    $print_link = Util::addParameter('month.php', array('timestamp' => $timestamp,
                                                        'print' => 'true'));
    $print_link = Horde::url($print_link);
    require KRONOLITH_TEMPLATES . '/menu.inc';
}

echo '<div id="page">';

if (!$print_view) {
    Kronolith::tabs();
}

$startday = &new Horde_Date(array('mday' => 1,
                                  'month' => $month,
                                  'year' => $year));
$startday = $startday->dayOfWeek();
$daysInView = Date_Calc::weeksInMonth($month, $year) * 7;
if (!$prefs->getValue('week_start_monday')) {
    $startOfView = 1 - $startday;

    // We may need to adjust the number of days in the view if we're
    // starting weeks on Sunday.
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

$prevurl = Horde::applicationurl(Util::addParameter('month.php', array('month' => date('n', $prevstamp),
                                                                       'year' => date('Y', $prevstamp))));
$nexturl = Horde::applicationUrl(Util::addParameter('month.php', array('month' => date('n', $nextstamp),
                                                                       'year' => date('Y', $nextstamp))));
if ($sidebyside) {
    require KRONOLITH_TEMPLATES . '/month/head_side_by_side.inc';
} else {
    require KRONOLITH_TEMPLATES . '/month/head.inc';
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
$allevents = Kronolith::listEvents($startDate, $endDate, $GLOBALS['display_calendars']);
if ($sidebyside) {
    $allCalendars = Kronolith::listCalendars();
    $currentCalendars = array();
    foreach ($GLOBALS['display_calendars'] as $id) {
        $currentCalendars[$id] = &$allCalendars[$id];
    }
    $sharedCalendars = $GLOBALS['display_calendars'];
} else {
   $currentCalendars = array(true);
}

$eventCategories = array();
$addLinks = Kronolith::getDefaultCalendar(PERMS_EDIT) && !$print_view &&
    (!empty($conf['hooks']['permsdenied']) ||
     Kronolith::hasPermission('max_events') === true ||
     Kronolith::hasPermission('max_events') > Kronolith::countEvents());

$html = '';
if (!$sidebyside && count($currentCalendars)) {
    $html .= '<tr>';
}
foreach ($currentCalendars as $id => $cal) {
    if ($sidebyside) {
        $html .= '<tr>';
    }

    $cell = 0;
    for ($day = $startOfView; $day < $startOfView + $daysInView; $day++) {
        $date = &new Horde_Date(array('year' => $year, 'month' => $month, 'mday' => $day));
        $daystamp = $date->timestamp();
        $date->hour = $prefs->getValue('twentyFour') ? 12 : 6;
        $timestamp = $date->timestamp();
        $week = $date->weekOfYear();

        if ($cell % 7 == 0 && $cell != 0) {
            if ($sidebyside) {
                $html .= '<td>' . $cal->get('name');
                if (!$print_view) {
                    $html .= ' ' . Horde::link(Util::addParameter(Horde::selfUrl(), 'display_cal', $cal->getShortName()), sprintf(_("Hide %s"), $cal->get('name'))) . Horde::img('delete-small.png', _("Hide"), '', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
                }
                $html .= '</td>';
            } else {
                $html .= "</tr>\n<tr>";
            }
        }
        if (mktime(0, 0, 0) == $daystamp) {
            $style = 'today';
        } elseif (date('n', $daystamp) != $month) {
            $style = 'othermonth';
        } elseif (date('w', $daystamp) == 0 || date('w', $daystamp) == 6) {
            $style = 'weekend';
        } else {
            $style = 'text';
        }

        $html .= '<td class="' . $style . '" height="70" width="14%" valign="top"><div>';

        $url = Util::addParameter(Horde::applicationUrl('day.php'),
                                  'timestamp', $daystamp);
        $html .= '<a class="day" href="' . $url . '">' . date('j', $daystamp) . '</a>';

        if ($addLinks) {
            $url = Util::addParameter(Horde::applicationUrl('addevent.php'),
                                      array('timestamp' => $timestamp,
                                            'url' => Horde::selfUrl(true, false, true)));
            $html .= Horde::link($url, _("Create a New Event"), 'newEvent')
                . Horde::img('new_small.png', '+')
                . '</a>';
        }

        if ($date->dayOfWeek() == HORDE_DATE_MONDAY) {
            $url = Util::addParameter('week.php', 'week', $week);
            if ($month == 12 && $week == 1) {
                $url = Util::addParameter($url, 'year', $year + 1);
            } elseif ($month == 1 && $week > 51) {
                $url = Util::addParameter($url, 'year', $year - 1);
            } else {
                $url = Util::addParameter($url, 'year', $year);
            }
            $html .= Horde::link(Horde::applicationUrl($url), '', 'week') . sprintf(_("Week %d"), $week) . '</a>';
        }

        $html .= '</div><div class="clear">&nbsp;</div>';

        if (!empty($allevents[$daystamp]) &&
            count($allevents[$daystamp])) {
            foreach ($allevents[$daystamp] as $event) {
                if (!$sidebyside || $event->getCalendar() == $id) {
                    $eventCategories[$event->getCategory()] = true;
                    $html .= '<div class="month-eventbox category' . md5($event->getCategory()) . '">' .
                        $event->getLink($timestamp) . '</div>';
                }
            }
        }

        $html .= "</td>\n";
        $cell++;
    }

    if ($sidebyside) {
        $html .= '</tr>';
    }
}
if (!$sidebyside && count($currentCalendars)) {
    $html .= '</tr>';
}

echo $html . '</table></td></tr></table></div>';
require KRONOLITH_TEMPLATES . '/category_legend.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
