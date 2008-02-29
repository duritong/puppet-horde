<?php

$block_name = _("Folder Summary");

/**
 * $Horde: imp/lib/Block/summary.php,v 1.54.2.10 2006/09/27 12:04:00 jan Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_imp_summary extends Horde_Block {

    var $_app = 'imp';

    function _title()
    {
        global $registry;

        require_once dirname(__FILE__) . '/../IMP.php';

        /* Start output buffering to make sure that any output, especially
         * script tags, are contained in the block content. */
        ob_start();
        $title = Horde::link(Horde::url($registry->getInitialPage(), true)) .
            $registry->get('name') . '</a> <small>' .
            Horde::link(IMP::composeLink()) .
            Horde::img('compose.png', _("New Message")) . ' ' .
            _("New Message") . '</a></small>';
        $title = ob_get_clean() . $title;

        return $title;
    }

    function _params()
    {
        return array('show_unread' => array('type' => 'boolean',
                                            'name' => _("Only display folders with unread messages in them?"),
                                            'default' => 0),
                     'show_total' => array('type' => 'boolean',
                                           'name' => _("Show total number of mails in folder?"),
                                           'default' => 0)
                     );
    }

    function _content()
    {
        global $notification, $prefs, $registry;

        $GLOBALS['authentication'] = 'none';
        require dirname(__FILE__) . '/../base.php';

        $html = '<table border="0" cellpadding="0" cellspacing="0" width="100%">';

        $auth = false;
        if (IMP::checkAuthentication(OP_HALFOPEN, true)) {
            $auth = true;

            /* Get list of mailboxes to poll. */
            require_once IMP_BASE . '/lib/IMAP/Tree.php';
            $imptree = &IMP_Tree::singleton();
            $folders = $imptree->getPollList();

            /* Filter on INBOX display, if requested. */
            if ($prefs->getValue('filter_on_display')) {
                require_once IMP_BASE . '/lib/Filter.php';
                $imp_filter = &IMP_Filter::singleton();
                $imp_filter->filter('INBOX');
            }

            /* Quota info, if available. */
            if (isset($_SESSION['imp']['quota']) &&
                is_array($_SESSION['imp']['quota'])) {
                require_once IMP_BASE . '/lib/Quota.php';
                $quotaDriver = &IMP_Quota::singleton($_SESSION['imp']['quota']['driver'], $_SESSION['imp']['quota']['params']);
                if ($quotaDriver !== false) {
                    $quota = $quotaDriver->getQuota();
                    if (!is_a($quota, 'PEAR_Error') &&
                        isset($quota['usage']) &&
                        isset($quota['limit'])) {
                        if ($quota['limit'] != 0) {
                            $html .= '<tr><td colspan="4" align="center"';
                            $quota['usage'] = $quota['usage'] / (1024 * 1024.0);
                            $quota['limit'] = $quota['limit'] / (1024 * 1024.0);
                            $percent = ($quota['usage'] * 100) / $quota['limit'];
                            if ($percent >= 90) {
                                $html .= ' style="color:red"';
                            }
                            $html .= '>' . sprintf(_("%.2fMB / %.2fMB  (%.2f%%)"), $quota['usage'], $quota['limit'], $percent);
                            $html .= '</td></tr>';
                        }
                    }
                }
            }

            $newmsgs = array();
            $anyUnseen = false;

            foreach (array_keys($folders) as $folder) {
                if (($folder == 'INBOX') ||
                    ($_SESSION['imp']['base_protocol'] != 'pop3')) {
                    $info = $imptree->getElementInfo($folder);
                    if (!empty($info)) {
                        if (empty($this->_params['show_unread']) ||
                            !empty($info['unseen'])) {
                            if (!empty($info['newmsg'])) {
                                $newmsgs[$folder] = $info['newmsg'];
                            }
                            $url = Util::addParameter(Horde::applicationUrl('mailbox.php', true), 'no_newmail_popup', 1);
                            $url = Util::addParameter($url, 'mailbox', $folder);
                            $html .= '<tr style="cursor:pointer" class="text" onclick="self.location=\'' . $url . '\'"><td>';
                            if (!empty($info['unseen'])) {
                                $html .= '<strong>';
                                $anyUnseen = true;
                            }
                            $html .= Horde::link($url, IMP::displayFolder($folder)) . IMP::displayFolder($folder) . '</a>';
                            if (!empty($info['unseen'])) {
                                $html .= '</strong>';
                            }
                            $html .= '</td><td>&nbsp;&nbsp;&nbsp;</td><td>';
                            $html .= !empty($info['unseen']) ? '<strong>' . $info['unseen'] . '</strong>' : '0';
                            $html .= !empty($this->_params['show_total']) ? '</td><td>(' . $info['messages'] . ')' : '';
                            $html .= '</td></tr>';
                        }
                    }
                }
            }
        } else {
            $html .= '<tr><td class="text">' . Horde::link(Horde::applicationUrl('index.php', true), sprintf(_("Log in to %s"), $registry->applications['imp']['name'])) . sprintf(_("Log in to %s"), $registry->applications['imp']['name']) . '</a></td></tr>';
        }

        $html .= '</table>';

        /* Check to see if user wants new mail notification, but only
         * if the user is logged into IMP. */
        if ($auth && $prefs->getValue('nav_popup')) {
            $notification->push(IMP::getNewMessagePopup($newmsgs), 'javascript');
        }
        if ($auth &&
            class_exists('Notification_Listener_audio') &&
            $prefs->getValue('nav_audio')) {
            $found = false;
            foreach ($newmsgs as $mbox => $nm) {
                if ($nm > 0) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $notification->push($registry->getImageDir() .
                                    '/audio/theetone.wav', 'audio');
                $html .= Util::bufferOutput(array($notification, 'notify'), array('listeners' => 'audio'));
            }
        }

        if ($auth && (count($newmsgs) == 0 &&
                      !empty($this->_params['show_unread']))) {
            if (count($folders) == 0) {
                $html .= _("No folders are being checked for new mail.");
            } else {
                if (!$anyUnseen) {
                    $html .= '<em>' . _("No folders with unseen messages") . '</em>';
                } else {
                    if ($prefs->getValue('nav_popup')) {
                        $html .= '<em>' . _("No folders with new messages") . '</em>';
                    }
                }
            }
        }

        return $html;
    }

}
