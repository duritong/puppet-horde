<?php

$block_name = _("Notes Summary");

/**
 * Implementation of Horde_Block api to show notes summary.
 *
 * $Horde: mnemo/lib/Block/summary.php,v 1.22.8.6 2006/02/08 14:43:47 jan Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_Mnemo_summary extends Horde_Block {

    var $_app = 'mnemo';

    function _title()
    {
        global $registry;
        return Horde::link(Horde::url($registry->getInitialPage(), true)) .
            $registry->get('name') . '</a> <small>' .
            Horde::link(Horde::applicationUrl('memo.php?actionID=add_memo', true)) .
            Horde::img('mnemo.png', _("New Note")) . ' ' . _("New Note") .
            '</a></small>';
    }

    function _params()
    {
        require_once dirname(__FILE__) . '/../base.php';
        require_once 'Horde/Prefs/CategoryManager.php';
        $cManager = &new Prefs_CategoryManager();
        $categories = array();
        foreach ($cManager->get() as $c) {
            $categories[$c] = $c;
        }

        return array('show_actions' => array('type' => 'checkbox',
                                             'name' => _("Show action buttons?"),
                                             'default' => 1),
                     'show_notepad' => array('type' => 'checkbox',
                                             'name' => _("Show notepad name?"),
                                             'default' => 1),
                     'show_categories' => array('type' => 'multienum',
                                                'name' => _("Show notes from these categories"),
                                                'default' => array(),
                                                'values' => $categories)
               );
    }

    function _content()
    {
        require_once dirname(__FILE__) . '/../base.php';
        global $prefs;

        require_once 'Horde/Prefs/CategoryManager.php';
        $cManager = &new Prefs_CategoryManager();
        $colors = $cManager->colors();
        $fgcolors = $cManager->fgColors();

        if (!empty($this->_params['show_notepad'])) {
            $shares = &Horde_Share::singleton('mnemo');
        }

        $html = '';
        $memos = Mnemo::listMemos($prefs->getValue('sortby'),
                                  $prefs->getValue('sortdir'));
        foreach ($memos as $id => $memo) {
            if (!empty($this->_params['show_categories']) &&
                !in_array($memo['category'], $this->_params['show_categories'])) {
                continue;
            }

            $html .= '<tr>';

            if (!empty($this->_params['show_actions'])) {
                $editurl = Util::addParameter('memo.php', array('memo' => $memo['memo_id'],
                                                                'memolist' => $memo['memolist_id']));
                $html .= '<td width="1%">' . Horde::link(htmlspecialchars(Horde::applicationUrl(Util::addParameter($editurl, 'actionID', 'modify_memo'), true)), _("Edit Note")) . Horde::img('edit.png', _("Edit Note"), '', $GLOBALS['registry']->getImageDir('horde')) . '</a></td>';
            }

            if (!empty($this->_params['show_notepad'])) {
                $owner = $memo['memolist_id'];
                $share = $shares->getShare($owner);
                if (!is_a($share, 'PEAR_Error')) {
                    $owner = $share->get('name');
                }
                $html .= '<td>' . htmlspecialchars($owner) . '</td>';
            }

            $html .= '<td>';
            $viewurl = Util::addParameter('view.php', array('memo' => $memo['memo_id'],
                                                            'memolist' => $memo['memolist_id']));
            $html .= Horde::link(htmlspecialchars(Horde::applicationUrl($viewurl, true))) . htmlspecialchars($memo['desc']) . '</a></td>';

            $color = isset($colors[$memo['category']]) ? $colors[$memo['category']] : $colors['_default_'];
            $fgcolor = isset($fgcolors[$memo['category']]) ? $fgcolors[$memo['category']] : $fgcolors['_default_'];
            $html .= '<td width="1%" style="background:' .
                $color . ';color:' . $fgcolor . '">&nbsp;' .
                htmlspecialchars($memo['category'] ? $memo['category'] : _("Unfiled")) .
                "&nbsp;</td></tr>\n";
        }

        if (!$memos) {
            return '<p><em>' . _("No notes to display") . '</em></p>';
        }
        return '<table cellspacing="0" width="100%" class="linedRow">' . $html . '</table>';
    }

}
