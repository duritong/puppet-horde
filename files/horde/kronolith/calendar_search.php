<?php
/**
 * $Horde: kronolith/calendar_search.php,v 1.2.2.3 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 2005-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('KRONOLITH_BASE', dirname(__FILE__));
require_once KRONOLITH_BASE . '/lib/base.php';

if (!Auth::getAuth()) {
    Util::closeWindowJS();
    exit;
}

$results = false;
if (($search = Util::getFormData('search', '')) !== '') {
    $results = array_filter($all_calendars, create_function('&$share', 'return stristr($share->get("name"), $GLOBALS["search"]) !== false;'));
}

/* Display the form. */
$title = _("Show Calendar");
Horde::addScriptFile('stripe.js', 'kronolith', true);
require KRONOLITH_TEMPLATES . '/common-header.inc';
require KRONOLITH_TEMPLATES . '/calendars/search.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
