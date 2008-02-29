<?php
/**
 * $Horde: kronolith/day.php,v 1.63.8.7 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';
require_once KRONOLITH_BASE . '/lib/DayView.php';

if ($timestamp = (int)Util::getFormData('timestamp')) {
    $day = &new Horde_Date($timestamp);
    list($year, $month, $day) = array($day->year, $day->month, $day->mday);
} else {
    $year = (int)Util::getFormData('year');
    $month = (int)Util::getFormData('month');
    $day = (int)Util::getFormData('mday');
}
$dayOb = &new Kronolith_DayView($month, $day, $year);
$title = $dayOb->getTime($prefs->getValue('date_format'));
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
    $print_link = Util::addParameter('day.php', array('month' => $month,
                                                      'mday' => $day,
                                                      'year' => $year,
                                                      'timestamp' => $timestamp,
                                                      'print' => 'true'));
    $print_link = Horde::url($print_link);
    require KRONOLITH_TEMPLATES . '/menu.inc';
}

echo '<div id="page">';
if (!$print_view) {
    Kronolith::tabs();
}
$dayOb->html(KRONOLITH_TEMPLATES);
echo '</div>';
require $registry->get('templates', 'horde') . '/common-footer.inc';
