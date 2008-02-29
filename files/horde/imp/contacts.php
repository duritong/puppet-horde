<?php
/**
 * $Horde: imp/contacts.php,v 2.67.10.7 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2002-2007 Marcus I. Ryan <marcus@riboflavin.net>
 *
 * See the enclosed file COPYING for license information (GPL).  If you did
 * not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
$authentication = 'horde';
require_once IMP_BASE . '/lib/base.php';

/* Get the lists of address books through the API. */
$source_list = $registry->call('contacts/sources');

/* If we self-submitted, use that source. Otherwise, choose a good
 * source. */
$source = Util::getFormData('source');
if (empty($source) || !isset($source_list[$source])) {
    /* We don't just pass the second argument to getFormData() because
     * we want to trap for invalid sources, not just no source. */
    $source = key($source_list);
}

/* Get the search as submitted (defaults to '' which should list everyone). */
$search = Util::getFormData('search');

/* Get the name of the calling form (Defaults to 'compose'). */
$formname = Util::getFormData('formname', 'compose');

/* Are we limiting to only the 'To:' field? */
$to_only = Util::getFormData('to_only');

$apiargs = array(
    'addresses' => array($search),
    'addressbooks' => array($source),
    'fields' => array()
);

if ($search_fields_pref = $prefs->getValue('search_fields')) {
    foreach (explode("\n", $search_fields_pref) as $s) {
        $s = trim($s);
        $s = explode("\t", $s);
        if (!empty($s[0]) && ($s[0] == $source)) {
            $apiargs['fields'][array_shift($s)] = $s;
            break;
        }
    }
}

$results = array();
if (Util::getFormData('searched') || $prefs->getValue('display_contact')) {
    $results = $registry->call('contacts/search', $apiargs);
}

/* The results list returns an array for each source searched - at least
 * that's how it looks to me. Make it all one array instead. */
$addresses = array();
foreach ($results as $r) {
    $addresses = array_merge($addresses, $r);
}

/* If self-submitted, preserve the currently selected users encoded by
 * javascript to pass as value|text. */
$selected_addresses = array();
$sa = explode('|', Util::getFormData('sa'));
for ($i = 0; $i < count($sa) - 1; $i += 2) {
    $selected_addresses[$sa[$i]] = $sa[$i + 1];
}

/* Register the double click events. */
if ($browser->isBrowser('msie')) {
    $select_event = ' ondblclick="addAddress(\'to\')"';
    $option_event = '';
} else {
    $select_event = '';
    $option_event = ' ondblclick="addAddress(\'to\')"';
}

/* Set the default list display (name or email). */
$display = Util::getFormData('display', 'name');

/* Display the form. */
$title = _("Address Book");
require IMP_TEMPLATES . '/common-header.inc';
require IMP_TEMPLATES . '/contacts/contacts.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
