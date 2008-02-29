<?php
/**
 * $Horde: kronolith/delevent.php,v 1.42.8.4 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

$kronolith->open(Util::getFormData('calendar'));
$event = &$kronolith->getEvent(Util::getFormData('eventID'));
if (!$event) {
    if (($url = Util::getFormData('url')) === null) {
        $url = Horde::applicationUrl($prefs->getValue('defaultview') . '.php', true);
    }
    header('Location: ' . $url);
    exit;
} elseif ($event->hasRecurType(KRONOLITH_RECUR_NONE) &&
    !$prefs->getValue('confirm_delete')) {
    header('Location: ' . Horde::applicationUrl('deleventaction.php?' . $_SERVER['QUERY_STRING'], 1));
    exit;
}

if ($timestamp = Util::getFormData('timestamp')) {
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
    $day = date('j', $timestamp);
} else {
    $month = Util::getFormData('month', date('n'));
    $day = Util::getFormData('mday', date('j'));
    $year = Util::getFormData('year', date('Y'));
}

$url = Util::getFormData('url');

$title = sprintf(_("Delete %s"), $event->getTitle());
require KRONOLITH_TEMPLATES . '/common-header.inc';
require KRONOLITH_TEMPLATES . '/menu.inc';
echo '<div id="page">';
if ($event->hasRecurType(KRONOLITH_RECUR_NONE)) {
    require KRONOLITH_TEMPLATES . '/delete/one.inc';
} else {
    require KRONOLITH_TEMPLATES . '/delete/delete.inc';
}
echo '</div>';
require $registry->get('templates', 'horde') . '/common-footer.inc';
