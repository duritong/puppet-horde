<?php
/**
 * $Horde: imp/search.php,v 2.128.2.23 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * URL Parameters:
 * ---------------
 * 'search_mailbox'  --  If exists, don't show the folder selection list; use
 *                       the passed in mailbox value instead.
 * 'edit_query'      --  If exists, the search query to edit.
 */

/**
 * Generates the HTML for a day selection widget.
 *
 * @param string $name      The name of the widget.
 * @param integer $default  The value to select by default. Range: 1-31
 * @param string $params    Any additional parameters to include in the <a>
 *                          tag.
 *
 * @return string  The HTML <select> widget.
 */
function buildDayWidget($name, $default = null, $params = null)
{
    $html = '<select id="' . $name . '" name="' . $name. '"';
    if (!is_null($params)) {
        $html .= ' ' . $params;
    }
    $html .= '>';

    for ($day = 1; $day <= 31; $day++) {
        $html .= '<option value="' . $day . '"';
        $html .= ($day == $default) ? ' selected="selected">' : '>';
        $html .= $day . '</option>';
    }

    return $html . "</select>\n";
}

/**
 * Generates the HTML for a month selection widget.
 *
 * @param string $name      The name of the widget.
 * @param integer $default  The value to select by default.
 * @param string $params    Any additional parameters to include in the <a>
 *                          tag.
 *
 * @return string  The HTML <select> widget.
 */
function buildMonthWidget($name, $default = null, $params = null)
{
    $html = '<select id="' . $name . '" name="' . $name. '"';
    if (!is_null($params)) {
        $html .= ' ' . $params;
    }
    $html .= '>';

    for ($month = 1; $month <= 12; $month++) {
        $html .= '<option value="' . $month . '"';
        $html .= ($month == $default) ? ' selected="selected">' : '>';
        $html .= strftime('%B', mktime(0, 0, 0, $month, 1)) . '</option>';
    }

    return $html . "</select>\n";
}

/**
 * Generates the HTML for a year selection widget.
 *
 * @param integer $name    The name of the widget.
 * @param integer $years   The number of years to include.
 *                         If (+): future years
 *                         If (-): past years
 * @param string $default  The timestamp to select by default.
 * @param string $params   Any additional parameters to include in the <a>
 *                         tag.
 *
 * @return string  The HTML <select> widget.
 */
function buildYearWidget($name, $years, $default = null, $params = null)
{
    $curr_year = date('Y');
    $yearlist = array();

    $startyear = (!is_null($default) && ($default < $curr_year) && ($years > 0)) ? $default : $curr_year;
    $startyear = min($startyear, $startyear + $years);
    for ($i = 0; $i <= abs($years); $i++) {
        $yearlist[] = $startyear++;
    }
    if ($years < 0) {
        $yearlist = array_reverse($yearlist);
    }

    $html = '<select id="' . $name . '" name="' . $name. '"';
    if (!is_null($params)) {
        $html .= ' ' . $params;
    }
    $html .= '>';

    foreach ($yearlist as $year) {
        $html .= '<option value="' . $year . '"';
        $html .= ($year == $default) ? ' selected="selected">' : '>';
        $html .= $year . '</option>';
    }

    return $html . "</select>\n";
}

define('IMP_BASE', dirname(__FILE__));
$authentication = OP_HALFOPEN;
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Folder.php';
require_once IMP_BASE . '/lib/Search.php';

$actionID = Util::getFormData('actionID');
$edit_query = Util::getFormData('edit_query');
$edit_query_id = Util::getFormData('edit_query_id', $edit_query);
$edit_query_vfolder = Util::getFormData('edit_query_vfolder');
$search_mailbox = Util::getFormData('search_mailbox');

$imp_search_fields = $imp_search->searchFields();

/* Get URL parameter data. */
$search = array();
if (Util::getFormData('no_match')) {
    $search = $imp_search->retrieveUIQuery();
} elseif (!is_null($edit_query) && $imp_search->isSearchMbox($edit_query)) {
    if ($imp_search->isVFolder($edit_query)) {
        $edit_query_vfolder = true;
        if (!$imp_search->isEditableVFolder($edit_query)) {
            $notification->push(_("Special Virtual Folders cannot be edited."), 'horde.error');
            header('Location: ' . Horde::applicationUrl('mailbox.php', true));
            exit;
        }
    }
    $search = $imp_search->retrieveUIQuery($edit_query);
}
if (empty($search)) {
    if (empty($actionID) &&
        ($def_search = $prefs->getValue('default_search'))) {
        $search['field'] = array($def_search);
    } else {
        $search['field'] = Util::getFormData('field', array());
        if (!empty($search['field']) && !end($search['field'])) {
            array_pop($search['field']);
        }
    }
    $search['field_end'] = count($search['field']);
    $search['match'] = Util::getFormData('search_match');
    $search['text'] = Util::getFormData('search_text');
    $search['text_not'] = Util::getFormData('search_text_not');
    $search['date'] = Util::getFormData('search_date');
    $search['flag'] = Util::getFormData('search_flag');
    $search['folders'] = Util::getFormData('search_folders', array());
    $search['save_vfolder'] = Util::getFormData('save_vfolder');
    $search['vfolder_label'] = Util::getFormData('vfolder_label');
    $search['mbox'] = Util::getFormData('mbox', $search_mailbox);
}

$curr_date = getdate();

/* Run through the action handlers. */
switch ($actionID) {
case 'do_search':
    /* Create the search query. */
    require_once IMP_BASE . '/lib/IMAP/Search.php';
    $query = &new IMP_IMAP_Search_Query();

    /* Flag searches. */
    $flag_names = array(
        IMP_SEARCH_FLAG_SEEN => 'seen',
        IMP_SEARCH_FLAG_ANSWERED => 'answered',
        IMP_SEARCH_FLAG_FLAGGED => 'flagged',
        IMP_SEARCH_FLAG_DELETED => 'deleted'
    );

    foreach ($search['flag'] as $key => $val) {
        $flag = $flag_names[$key];
        switch ($val) {
        case 0:
            $query->$flag(true);
            break;
        case 1:
            $query->$flag(false);
            break;
        }
    }

    /* Field searches. */
    $search_array = array();
    foreach ($search['field'] as $key => $val) {
        $ob = &new IMP_IMAP_Search_Query();
        switch ($imp_search_fields[$val]['type']) {
        case IMP_SEARCH_HEADER:
            if (!empty($search['text'][$key])) {
                $ob->header($val, $search['text'][$key], $search['text_not'][$key]);
                $search_array[] = $ob;
            }
            break;

        case IMP_SEARCH_BODY:
            if (!empty($search['text'][$key])) {
                $ob->body($search['text'][$key], $search['text_not'][$key]);
                $search_array[] = $ob;
            }
            break;

        case IMP_SEARCH_TEXT:
            if (!empty($search['text'][$key])) {
                $ob->text($search['text'][$key], $search['text_not'][$key]);
                $search_array[] = $ob;
            }
            break;

        case IMP_SEARCH_DATE:
            if (!empty($search['date'][$key]['day']) &&
                !empty($search['date'][$key]['month']) &&
                !empty($search['date'][$key]['year'])) {
                if ($val == 'received_on') {
                    $ob->on($search['date'][$key]['day'], $search['date'][$key]['month'], $search['date'][$key]['year']);
                } elseif ($val == 'received_until') {
                    $ob->before($search['date'][$key]['day'], $search['date'][$key]['month'], $search['date'][$key]['year']);
                } elseif ($val == 'received_since') {
                    $ob->since($search['date'][$key]['day'], $search['date'][$key]['month'], $search['date'][$key]['year']);
                }
                $search_array[] = $ob;
            }
            break;
        }
    }

    /* Search match. */
    if ($search['match'] == 'and') {
        $query->imapAnd($search_array);
    } elseif ($search['match'] == 'or') {
        $query->imapOr($search_array);
    }

    /* Save the search as a virtual folder if requested. */
    if (!empty($search['save_vfolder'])) {
        if (empty($search['vfolder_label'])) {
            $notification->push(_("Virtual Folders require a label."), 'horde.error');
            break;
        }

        if ($edit_query_id) {
            $imp_search->deleteSearchQuery($edit_query_id);
        }
        $id = $imp_search->addVFolder($query, $search['folders'], $search, $search['vfolder_label']);
        $notification->push(sprintf(_("Virtual Folder \"%s\" created succesfully."), $search['vfolder_label']), 'horde.success');
    } else {
        /* Set the search in the IMP session. */
        $id = $imp_search->createSearchQuery($query, $search['folders'], $search, _("Search Results"));
    }

    /* Redirect to the Mailbox Screen. */
    header('Location: ' . Util::addParameter(Horde::applicationUrl('mailbox.php', true), 'mailbox', $GLOBALS['imp_search']->createSearchID($id), false));
    exit;

case 'reset_search':
    if ($def_search = $prefs->getValue('default_search')) {
        $search['field'] = array($def_search);
        $search['field_end'] = 1;
    } else {
        $search['field'] = array();
        $search['field_end'] = 0;
    }
    $search['match'] = null;
    $search['date'] = $search['text'] = $search['text_not'] = $search['flag'] = array();
    $search['folders'] = array();
    break;

case 'delete_field':
    $key = Util::getFormData('delete_field_id');

    /* Unset all entries in array input and readjust ids. */
    $vars = array('field', 'text', 'text_not', 'date');
    foreach ($vars as $val) {
        unset($search[$val][$key]);
        if (!empty($search[$val])) {
            $search[$val] = array_values($search[$val]);
        }
    }
    $search['field_end'] = count($search['field']);
    break;
}

$subscribe = 0;
if (!$conf['user']['allow_folders']) {
    $search['mbox'] = 'INBOX';
    $search['folders'][] = 'INBOX';
    $subscribe = false;
} elseif ($subscribe = $prefs->getValue('subscribe')) {
    $shown = Util::getFormData('show_subscribed_only', $subscribe);
}

$title = _("Message Search");
if ($edit_query_id) {
    $submit_label = _("Save");
} else {
    $submit_label = _("Submit");
}
require IMP_TEMPLATES . '/common-header.inc';
require IMP_TEMPLATES . '/menu.inc';
IMP::status();
require IMP_TEMPLATES . '/search/javascript.inc';
require IMP_TEMPLATES . '/search/header.inc';

/* Process the list of fields. */
for ($i = 0; $i <= $search['field_end']; $i++) {
    $last_field = ($i == $search['field_end']);
    $curr_field = (isset($search['field'][$i])) ? $search['field'][$i] : null;
    require IMP_TEMPLATES . '/search/fields.inc';
}

$newcol = 1;
$numcolumns = 1;
$folderlist = '';
$imp_folder = &IMP_Folder::singleton();

if (empty($search['mbox'])) {
    $mailboxes = $imp_folder->flist_IMP(array(), (isset($shown)) ? (bool) $shown : null);
    $total = ceil(count($mailboxes) / 3);

    if (empty($search['folders']) && ($actionID != 'update_search')) {
        /* Default to Inbox search. */
        $search['folders'][] = 'INBOX';
    }

    $count = 0;
    foreach ($mailboxes as $mbox) {
        $newcol++;
        if (!empty($mbox['val'])) {
            $folderlist .= '<input id="folder' . $count . '" type="checkbox" name="search_folders[]" value="' . htmlspecialchars($mbox['val']) . '"';
            if (in_array($mbox['val'], $search['folders'])) {
                $folderlist .= ' checked="checked"';
            }
        } else {
            $folderlist .= '<input id="folder' . $count . '" type="checkbox" disabled="disabled"';
        }

        $folderlist .= " />\n" . Horde::label('folder' . $count++, str_replace(' ', '&nbsp;', $mbox['label']), '') . "<br />\n";

        if (($newcol > $total) && ($numcolumns != 3)) {
            $newcol = 1;
            $numcolumns++;
            $folderlist .= "</td>\n" . '<td class="item leftAlign" valign="top">';
        }
    }
}

require IMP_TEMPLATES . '/search/main.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
