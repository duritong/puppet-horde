<?php

$block_name = _("Menu Folder List");
$block_type = 'tree';

/**
 * $Horde: imp/lib/Block/tree_folders.php,v 1.28.2.17 2006/03/08 07:20:34 slusarz Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_imp_tree_folders extends Horde_Block {

    var $_app = 'imp';

    function _buildTree(&$tree, $indent = 0, $parent = null)
    {
        global $registry, $prefs;

        $GLOBALS['authentication'] = 'none';
        @define('IMP_BASE', dirname(__FILE__) . '/../..');
        require_once IMP_BASE . '/lib/base.php';
        require_once IMP_BASE . '/lib/IMAP/Tree.php';
        require_once 'Horde/Identity.php';

        /* Abort immediately if we're not currently logged in. */
        if (IMP::checkAuthentication(OP_HALFOPEN, true) !== true) {
            return;
        }

        /* Call the mailbox icon hook, if requested. */
        $mbox_icons = array();
        if (!empty($GLOBALS['conf']['hooks']['mbox_icon'])) {
            require_once HORDE_BASE . '/config/hooks.php';
            if (function_exists('_imp_hook_mbox_icons')) {
                $mbox_icons = call_user_func('_imp_hook_mbox_icons');
            }
        }

        /* Cache some additional values. */
        $image_dir = $registry->getImageDir();
        $use_vtrash = $prefs->getValue('use_vtrash');

        /* Initialize the user's identities. */
        $identity = &Identity::singleton(array('imp', 'imp'));

        $tree->addNode($parent . 'compose', $parent, _("New Message"),
                       $indent, false,
                       array('icon' => 'compose.png',
                             'icondir' => $image_dir,
                             'url' => IMP::composeLink(),
                             'target' => $prefs->getValue('compose_popup') ? 'horde_menu' : 'horde_main'));

        /* Add link to the search page. */
        $tree->addNode($parent . 'search', $parent, _("Search"),
                       $indent, false,
                       array('icon' => 'search.png',
                             'icondir' => $registry->getImageDir('horde'),
                             'url' => Horde::applicationUrl('search.php')));

        if ($_SESSION['imp']['base_protocol'] == 'pop3') {
            return;
        }

        /* Get special folders. */
        $trash = IMP::folderPref($prefs->getValue('trash_folder'), true);
        $draft = IMP::folderPref($prefs->getValue('drafts_folder'), true);
        $sent = $identity->getAllSentmailFolders();

        $name_url = Util::addParameter(Horde::applicationUrl('mailbox.php'), 'no_newmail_popup', 1);

        /* Initialize the IMP_Tree object. */
        $imptree = &IMP_Tree::singleton();
        $mask = IMAPTREE_NEXT_SHOWCLOSED;
        if ($prefs->getValue('subscribe') &&
            defined('IMAPTREE_NEXT_SHOWSUB')) {
            $mask |= IMAPTREE_NEXT_SHOWSUB;
        }

        /* Start iterating through the list of mailboxes, displaying them. */
        $unseen = 0;
        $inbox = null;
        $mailbox = $imptree->reset();
        if ($mailbox) {
            do {
                $node_params = array();

                if (!$imptree->isContainer($mailbox)) {
                    /* We are dealing with mailboxes here. Determine if we
                     * need to poll this mailbox for new messages. */
                    if ($imptree->isPolled($mailbox)) {
                        /* If we need message information for this folder,
                         * update it now. */
                        $msgs_info = $imptree->getElementInfo($mailbox['value']);
                        if (!empty($msgs_info)) {
                            /* Highlight mailboxes with unread messages in
                             * bold. */
                            if (!empty($msgs_info['unseen'])) {
                                $unseen += $msgs_info['unseen'];
                                $mailbox['label'] = '<span dir="ltr"><strong>' . $mailbox['label'] . '</strong> (' . $msgs_info['unseen'] . ') </span>';
                            }
                        }
                    }

                    /* If this is the INBOX, save it to later rewrite our parent
                     * node to include new mail notification. */
                    if (strcasecmp($mailbox['value'], 'INBOX') == 0) {
                        $inbox = $mailbox;
                    }

                    switch ($mailbox['value']) {
                    case 'INBOX':
                        $dir2 = 'inbox.png';
                        break;

                    case $trash:
                        $dir2 = ($use_vtrash) ? 'folder.png' : 'trash.png';
                        break;

                    case $draft:
                        $dir2 = 'drafts.png';
                        break;

                    default:
                        if ($imptree->isVFolder($mailbox) &&
                            $GLOBALS['imp_search']->isVTrashFolder($mailbox['value'])) {
                            $dir2 = 'trash.png';
                        } elseif ($imptree->isVFolder($mailbox) &&
                                  $GLOBALS['imp_search']->isVINBOXFolder($mailbox['value'])) {
                            $dir2 = 'inbox.png';
                        } elseif (in_array($mailbox['value'], $sent)) {
                            $dir2 = 'sent.png';
                        } else {
                            $dir2 = 'folder.png';
                        }
                        break;
                    }
                } else {
                    /* We are dealing with folders here. */
                    $dir2 = 'folder.png';
                }

                if (isset($mbox_icons[$mailbox['value']]) &&
                    preg_match('/src="([^"]+)"/', $mbox_icons[$mailbox['value']], $match)) {
                    $icon = $match[1];
                    $icondir = '';
                } else {
                    $icon = 'folders/' . $dir2;
                    $icondir = $image_dir;
                }

                $node_params += array('icon' => $icon,
                                      'icondir' => $icondir,
                                      'url' => ($imptree->isContainer($mailbox)) ? null : Util::addParameter($name_url, 'mailbox', $mailbox['value']),
                                     );
                $tree->addNode($parent . $mailbox['value'],
                               $parent . $mailbox['parent'],
                               $mailbox['label'], $indent + $mailbox['level'], $imptree->isOpenSidebar($mailbox['value']), $node_params);
            } while (($mailbox = $imptree->next($mask)));
        }

        /* We want to rewrite the parent node of the INBOX to include new mail
         * notification. */
        if ($inbox) {
            $url = $registry->get('url', $parent);
            if (empty($url)) {
                if (($registry->get('status', $parent) == 'heading') ||
                    !$registry->get('webroot')) {
                    $url = null;
                } else {
                    $url = Horde::url($registry->getInitialPage($parent));
                }
            }

            $node_params = array('url' => $url,
                                 'icon' => $registry->get('icon', $parent),
                                 'icondir' => '');
            $menu_parent = $registry->get('menu_parent', $parent);
            $name = $registry->get('name', $parent);
            if ($unseen) {
                $node_params['icon'] = 'newmail.png';
                $node_params['icondir'] = $image_dir;
                $name = sprintf('<strong>%s</strong> (%s)', $name, $unseen);
            }
            $tree->addNode($parent, $menu_parent, $name, $indent - 1, $imptree->isOpenSidebar($parent), $node_params);
        }
    }

}
