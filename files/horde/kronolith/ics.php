<?php
/**
 * $Horde: kronolith/ics.php,v 1.5.2.7 2007/06/19 07:20:56 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('AUTH_HANDLER', true);
@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';
require_once 'Horde/Cache.php';
require_once 'Horde/iCalendar.php';

// We want to always generate UTF-8 iCalendar data.
NLS::setCharset('UTF-8');

// Determine which calendar to export.
$calendar = Util::getFormData('c');
if (empty($calendar) && !empty($_SERVER['PATH_INFO'])) {
    $calendar = basename($_SERVER['PATH_INFO']);
}

$share = $kronolith_shares->getShare($calendar);
if (is_a($share, 'PEAR_Error')) {
    header('HTTP/1.0 400 Bad Request');
    echo '400 Bad Request';
    exit;
}

// First try guest permissions.
if (!$share->hasPermission('', PERMS_READ)) {
    // Authenticate.
    $auth = &Auth::singleton($conf['auth']['driver']);
    if (!isset($_SERVER['PHP_AUTH_USER'])
        || !$auth->authenticate($_SERVER['PHP_AUTH_USER'],
                                array('password' => isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null))
        || !$share->hasPermission(Auth::getAuth(), PERMS_READ)) {
        header('WWW-Authenticate: Basic realm="Kronolith iCalendar Interface"');
        header('HTTP/1.0 401 Unauthorized');
        echo '401 Unauthorized';
        exit;
    }
}

$cache = &Horde_Cache::singleton($conf['cache']['driver'], Horde::getDriverConfig('cache', $conf['cache']['driver']));
$key = 'kronolith.ics.' . $calendar;

$ics = $cache->get($key, 360);
if (!$ics) {
    $kronolith->open($calendar);
    $events = $kronolith->listEvents(null, null);

    $iCal = &new Horde_iCalendar();
    $iCal->setAttribute('X-WR-CALNAME', $share->get('name'));

    foreach ($events as $id) {
        $event = &$kronolith->getEvent($id);
        $iCal->addComponent($event->toiCalendar($iCal));
    }

    $ics = $iCal->exportvCalendar();
    $cache->set($key, $ics);
}

$browser->downloadHeaders($calendar . '.ics',
                          'text/calendar; charset=' . NLS::getCharset(),
                          true,
                          strlen($ics));
echo $ics;
