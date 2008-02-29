<?php
/**
 * $Horde: mnemo/list.php,v 1.35.8.6 2007/01/02 13:55:10 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jon Parise <jon@horde.org>
 * @since   Mnemo 1.0
 * @package Mnemo
 */

@define('MNEMO_BASE', dirname(__FILE__));
require_once MNEMO_BASE . '/lib/base.php';
require_once 'Horde/Prefs/CategoryManager.php';

/* Get the current action ID. */
$actionID = Util::getFormData('actionID');

/* Sort out the sorting values. */
if (Util::getFormData('sortby') !== null) {
    $prefs->setValue('sortby', Util::getFormData('sortby'));
}
if (Util::getFormData('sortdir') !== null) {
   $prefs->setValue('sortdir', Util::getFormData('sortdir'));
}

/* Get the full, sorted notepad. */
$memos = Mnemo::listMemos($prefs->getValue('sortby'),
                          $prefs->getValue('sortdir'));

/* Page variables. */
$title = _("My Notes");

switch ($actionID) {
case 'search_memos':
    /* If we're searching, only list those notes that match the search
     * result. */
    $pattern = Util::getFormData('search_pattern');
    $search_desc = (Util::getFormData('search_desc') == 'on');
    $search_body = (Util::getFormData('search_body') == 'on');

    if (!empty($pattern) && ($search_body || $search_desc)) {
        $pattern = '/' . preg_quote($pattern, '/') . '/i';
        $search_result = array();
        foreach ($memos as $memo_id => $memo) {
            if (($search_desc && preg_match($pattern, $memo['desc'])) ||
                ($search_body && preg_match($pattern, $memo['body']))) {
                $search_result[$memo_id] = $memo;
            }
        }

        /* Reassign $memos to the search result. */
        $memos = $search_result;
        $title = _("Search Results");
    }
    break;
}

Horde::addScriptFile('prototype.js', 'mnemo', true);
Horde::addScriptFile('tooltip.js', 'horde', true);
Horde::addScriptFile('tables.js', 'mnemo', true);
require MNEMO_TEMPLATES . '/common-header.inc';
require MNEMO_TEMPLATES . '/menu.inc';
require MNEMO_TEMPLATES . '/list/header.inc';

if (count($memos)) {
    $cManager = new Prefs_CategoryManager();
    $colors = $cManager->colors();
    $fgcolors = $cManager->fgColors();
    $sortby = $prefs->getValue('sortby');
    $sortdir = $prefs->getValue('sortdir');
    require MNEMO_TEMPLATES . '/list/memo_headers.inc';

    foreach ($memos as $memo_id => $memo) {
        $color = isset($colors[$memo['category']]) ? $colors[$memo['category']] : $colors['_default_'];
        $fgcolor = isset($fgcolors[$memo['category']]) ? $fgcolors[$memo['category']] : $fgcolors['_default_'];

        $viewurl = Util::addParameter('view.php', array('memo' => $memo['memo_id'],
                                                        'memolist' => $memo['memolist_id']));

        $memourl = Util::addParameter('memo.php', array('memo' => $memo['memo_id'],
                                                        'memolist' => $memo['memolist_id']));
        $share = $GLOBALS['mnemo_shares']->getShare($memo['memolist_id']);

        require MNEMO_TEMPLATES . '/list/memo_summaries.inc';
    }

    require MNEMO_TEMPLATES . '/list/memo_footers.inc';
} else {
    require MNEMO_TEMPLATES . '/list/empty.inc';
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
