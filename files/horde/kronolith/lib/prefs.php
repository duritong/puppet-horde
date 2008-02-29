<?php
/**
 * $Horde: kronolith/lib/prefs.php,v 1.5.10.7 2007/03/15 17:58:56 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @package Kronolith
 */

function handle_remote_cal_management($updated)
{
    global $prefs;

    $calName = Util::getFormData('remote_name');
    $calUrl  = trim(Util::getFormData('remote_url'));
    $calActionID = Util::getFormData('remote_action', 'add');

    if ($calActionID == 'add') {
        if (!empty($calName) && !empty($calUrl)) {
            $cals = unserialize($prefs->getValue('remote_cals'));
            $cals[] = array('name' => $calName,
                            'url'  => $calUrl);
            $prefs->setValue('remote_cals', serialize($cals));
            $updated = true;
            return false;
        }
    } elseif ($calActionID == 'delete') {
        $cals = unserialize($prefs->getValue('remote_cals'));
        foreach ($cals as $key => $cal) {
            if ($cal['url'] == $calUrl) {
                unset($cals[$key]);
                break;
            }
        }
        $prefs->setValue('remote_cals', serialize($cals));
        $updated = true;
        return false;
    }
    return true;
}

function handle_shareselect($updated)
{
    $default_share = Util::getFormData('default_share');
    if (!is_null($default_share)) {
        $sharelist = Kronolith::listCalendars();
        if ((is_array($sharelist)) > 0 && isset($sharelist[$default_share])) {
            $GLOBALS['prefs']->setValue('default_share', $default_share);
            return true;
        }
    }

    return false;
}

function handle_search_abook_select($updated)
{
    $address_bookSelected = Util::getFormData('search_abook');
    $address_books = $GLOBALS['registry']->call('contacts/sources');
    $address_bookFiltered = array();

    if (isset($address_bookSelected) && is_array($address_bookSelected)) {
        foreach ($address_bookSelected as $address_book) {
            $address_bookFiltered[] = $address_book;
        }
    }

    $GLOBALS['prefs']->setValue('search_abook', serialize($address_bookFiltered));

    return true;
}

if (!$prefs->isLocked('day_hour_start') || !$prefs->isLocked('day_hour_end')) {
    $day_hour_start_options = array();
    for ($i = 0; $i <= 48; $i++) {
        $day_hour_start_options[$i] = date(($prefs->getValue('twentyFour')) ? 'G:i' : 'g:ia', mktime(0, $i * 30, 0));
    }
    $day_hour_end_options = $day_hour_start_options;
}

function handle_fb_cals_select($updated)
{
    $fb_calsSelected = Util::getFormData('fb_cals');
    $fb_cals = Kronolith::listCalendars();
    $fb_calsFiltered = array();

    if (isset($fb_calsSelected) && is_array($fb_calsSelected)) {
        foreach ($fb_calsSelected as $fb_cal) {
            $fb_calsFiltered[] = $fb_cal;
        }
    }

    $GLOBALS['prefs']->setValue('fb_cals', serialize($fb_calsFiltered));

    return true;
}
