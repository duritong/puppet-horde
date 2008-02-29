<?php
/**
 * $Horde: kronolith/week.php,v 1.43.2.8 2007/01/02 13:55:05 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';
require_once KRONOLITH_BASE . '/lib/WeekView.php';

$week = (int)Util::getFormData('week');
$year = (int)Util::getFormData('year');
if ($timestamp = (int)Util::getFormData('timestamp')) {
    $date = &new Horde_Date($timestamp);
    $year = $date->year;
    $week = $date->weekOfYear();
    if (!$prefs->getValue('week_start_monday') && $date->dayOfWeek() == HORDE_DATE_SUNDAY) {
        $week++;
    }
    if ($week > 51 && $date->month == 1) {
        $year--;
    }
}

if ($prefs->getValue('week_start_monday')) {
    $weekOb = &new Kronolith_WeekView($week, $year, HORDE_DATE_MONDAY, HORDE_DATE_SUNDAY + 7);
} else {
    $weekOb = &new Kronolith_WeekView($week, $year, HORDE_DATE_SUNDAY, HORDE_DATE_SATURDAY);
}
$title = sprintf(_("Week %d"), $weekOb->week);
$print_view = (bool)Util::getFormData('print');

Horde::addScriptFile('tooltip.js', 'horde', true);
Horde::addScriptFile('stripe.js', 'kronolith', true);
if (!$print_view) {
    Horde::addScriptFile('popup.js', 'horde', true);
}
require KRONOLITH_TEMPLATES . '/common-header.inc';

if ($print_view) {
    require_once $registry->get('templates', 'horde') . '/javascript/print.js';
} else {
    $print_link = Util::addParameter('week.php', array('week' => $week,
                                                       'year' => $year,
                                                       'print' => 'true'));
    $print_link = Horde::url($print_link);
    require KRONOLITH_TEMPLATES . '/menu.inc';
}

echo '<div id="page">';
if (!$print_view) {
    Kronolith::tabs();
}
$weekOb->html(KRONOLITH_TEMPLATES);
echo '</div>';
require $registry->get('templates', 'horde') . '/common-footer.inc';
