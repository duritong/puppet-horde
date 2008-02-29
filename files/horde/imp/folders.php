<?php
/**
 * $Horde: imp/folders.php,v 2.309.2.35 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2000-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 2000-2007 Jon Parise <jon@horde.org>
 * Copyright 2000-2007 Anil Madhavapeddy <avsm@horde.org>
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

/**
 * Utility function to return a url for the various images.
 */
function _image($name, $alt, $type)
{
    static $cache;

    if (!isset($cache)) {
        $cache = array();
    } elseif (!empty($cache[$type][$name])) {
        return $cache[$type][$name];
    }

    if ($type == 'folder') {
        $cache[$type][$name] = Horde::img('folders/' . $name, $alt, 'style="vertical-align:middle"');
    } else {
        $cache[$type][$name] = Horde::img('tree/' . $name, $alt, 'style="vertical-align:middle"', $GLOBALS['registry']->getImageDir('horde'));
    }

    return $cache[$type][$name];
}

@define('IMP_BASE', dirname(__FILE__));
$authentication = OP_HALFOPEN;
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/IMAP/Tree.php';
require_once IMP_BASE . '/lib/Folder.php';
require_once 'Horde/Identity.php';
require_once 'Horde/Template.php';
Horde::addScriptFile('folders.js', 'imp');

/* Redirect back to the mailbox if folder use is not allowed. */
if (!$conf['user']['allow_folders']) {
    $notification->push(_("Folder use is not enabled."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('mailbox.php', true));
    exit;
}

/* Get quota information. */
if (isset($imp['quota']) && is_array($imp['quota'])) {
    require_once IMP_BASE . '/lib/Quota.php';
    $quotaDriver = &IMP_Quota::singleton($imp['quota']['driver'], $imp['quota']['params']);
    if ($quotaDriver !== false) {
        $quota = $quotaDriver->getQuota();
    }
    IMP::checkAuthentication(OP_HALFOPEN, true);
}

/* Initialize the user's identities. */
$identity = &Identity::singleton(array('imp', 'imp'));

/* Decide whether or not to show all the unsubscribed folders */
$subscribe = $prefs->getValue('subscribe');
$showAll = (!$subscribe || $imp['showunsub']);

/* Get the base URL for this page. */
$folders_url = Horde::selfUrl();

/* Initialize the IMP_Folder object. */
$imp_folder = &IMP_Folder::singleton();

/* Initialize the IMP_Tree object. */
$imptree = &IMP_Tree::singleton();

$folder_list = Util::getFormData('folder_list', array());
$refresh_time = $prefs->getValue('refresh_time');

/* Run through the action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'collapse_folder':
case 'expand_folder':
    $folder = Util::getFormData('folder');
    if (!empty($folder)) {
        ($actionID == 'expand_folder') ? $imptree->expand($folder) : $imptree->collapse($folder);
    }
    break;

case 'expand_all_folders':
    $imptree->expandAll();
    break;

case 'collapse_all_folders':
    $imptree->collapseAll();
    break;

case 'rebuild_tree':
    $imptree->init();
    break;

case 'expunge_folder':
    if (!empty($folder_list)) {
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->expungeMailbox($folder_list);
    }
    break;

case 'delete_folder':
    if (!empty($folder_list)) {
        $imp_folder->delete($folder_list, $subscribe);
    }
    break;

case 'delete_search_query':
    $queryid = Util::getFormData('queryid');
    if (!empty($queryid)) {
        $imp_search->deleteSearchQuery($queryid);
    }
    break;

case 'download_folder':
case 'download_folder_zip':
    if (!empty($folder_list)) {
        $mbox = $imp_folder->generateMbox($folder_list, false);
        if ($actionID == 'download_folder') {
            $browser->downloadHeaders($folder_list[0] . '.mbox', null, false, strlen($mbox));
        } else {
            require_once 'Horde/Compress.php';
            $horde_compress = &Horde_Compress::singleton('zip');
            $mbox = $horde_compress->compress(array(array('data' => $mbox, 'name' => $folder_list[0] . '.mbox')));
            $browser->downloadHeaders($folder_list[0] . '.zip', 'application/zip', false, strlen($mbox));
        }
        echo $mbox;
        exit;
    }
    break;

case 'import_mbox':
    $import_folder = Util::getFormData('import_folder');
    if (!empty($import_folder)) {
        $res = Browser::wasFileUploaded('mbox_upload', _("mailbox file"));
        if (!is_a($res, 'PEAR_Error')) {
            $res = $imp_folder->importMbox($import_folder, $_FILES['mbox_upload']['tmp_name']);
            if ($res === false) {
                $notification->push(sprintf(_("There was an error importing %s."), basename($_FILES['mbox_upload']['name'])), 'horde.error');
            } else {
                $notification->push(sprintf(_("Imported %d messages from %s."), $res,  basename($_FILES['mbox_upload']['name'])), 'horde.success');
            }
        } else {
            $notification->push($res, 'horde.error');
        }
        $actionID = null;
    } else {
        $refresh_time = null;
    }
    break;

case 'create_folder':
    $new_mailbox = Util::getFormData('new_mailbox');
    if (!empty($new_mailbox)) {
        $new_mailbox = String::convertCharset($new_mailbox, NLS::getCharset(), 'UTF7-IMAP');
        if (count($folder_list) == 1) {
            $namespace_info = IMP::getNamespace($folder_list[0]);
            $new_mailbox = $folder_list[0] . $namespace_info['delimiter'] . $new_mailbox;
        } else {
            $new_mailbox = IMP::appendNamespace($new_mailbox);
        }
        $imp_folder->create($new_mailbox, $subscribe);
    }
    break;

case 'rename_folder':
    $new_names = explode("\n", Util::getFormData('new_names'));
    $old_names = explode("\n", Util::getFormData('old_names'));
    $iMax = count($new_names);
    if (!empty($new_names) &&
        !empty($old_names) &&
        ($iMax == count($old_names))) {
        for ($i = 0; $i < $iMax; $i++) {
            $oldname = trim($old_names[$i], "\r\n");
            $newname = trim($new_names[$i], "\r\n");
            $newname = String::convertCharset($newname, NLS::getCharset(), 'UTF7-IMAP');
            $imp_folder->rename($oldname, IMP::appendNamespace($newname));
        }
    }
    break;

case 'subscribe_folder':
case 'unsubscribe_folder':
    if (!empty($folder_list)) {
        if ($actionID == 'subscribe_folder') {
            $imp_folder->subscribe($folder_list);
        } else {
            $imp_folder->unsubscribe($folder_list);
        }
    } else {
        $notification->push(_("No folders were specified"), 'horde.message');
    }
    break;

case 'toggle_subscribed_view':
    if ($subscribe) {
        $showAll = !$showAll;
        $imp['showunsub'] = $showAll;
        $imptree->showUnsubscribed($showAll);
    }
    break;

case 'poll_folder':
    if (!empty($folder_list)) {
        $imptree->addPollList($folder_list);
        $imp_search->createVINBOXFolder();
    }
    break;

case 'nopoll_folder':
    if (!empty($folder_list)) {
        $imptree->removePollList($folder_list);
        $imp_search->createVINBOXFolder();
    }
    break;

case 'folders_empty_mailbox':
    if (!empty($folder_list)) {
        include_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->emptyMailbox($folder_list);
    }
    break;

case 'mark_folder_seen':
case 'mark_folder_unseen':
    if (!empty($folder_list)) {
        include_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->flagAllInMailbox("\\SEEN", $folder_list, ($actionID == 'mark_folder_seen'));
    }
    break;

case 'login_compose':
    $open_compose_window = IMP::openComposeWin();
    break;

case 'delete_folder_confirm':
case 'folders_empty_mailbox_confirm':
    if (!empty($folder_list)) {
        $title = _("Folder Actions - Confirmation");
        require IMP_TEMPLATES . '/common-header.inc';
        require IMP_TEMPLATES . '/menu.inc';

        $loop = array();
        $rowct = 0;

        foreach ($folder_list as $val) {
            $data = array(
                'class' => (++$rowct % 2) ? 'item0' : 'item1',
                'name' => IMP::displayFolder($val),
                'val' => $val
            );
            $loop[] = $data;
        }

        $template = &new Horde_Template();
        $template->setOption('gettext', true);
        $template->set('cancel', _("Cancel"));
        $template->set('delete', ($actionID == 'delete_folder_confirm') ? _("Delete Selected Folders") : '', true);
        $template->set('empty', ($actionID == 'folders_empty_mailbox_confirm') ? _("Empty Selected Folders") : '', true);
        $template->set('folders', $loop);
        $template->set('folders_url', $folders_url);
        echo $template->fetch(IMP_TEMPLATES . '/folders/folders_confirm.html');

        require $registry->get('templates', 'horde') . '/common-footer.inc';
        exit;
    }
    break;
}

/* Display the correct message on the action bar */
$subToggleText = $showAll ? _("Hide Unsubscribed") : _("Show Unsubscribed");

/* Set the URL to refresh the page to in the META tag */
$refresh_url = Horde::applicationUrl('folders.php', true);

$title = _("Folder Navigator");
require IMP_TEMPLATES . '/common-header.inc';
require IMP_TEMPLATES . '/menu.inc';
IMP::status();

/* Print quota information. */
if (isset($quota)) {
    require IMP_TEMPLATES . '/quota/quota.inc';
}

$i = $rowct = 0;
$displayNames = $newmsgs = array();

if ($imp['file_upload'] && ($actionID == 'import_mbox')) {
    require IMP_TEMPLATES . '/folders/import.inc';
    require $registry->get('templates', 'horde') . '/common-footer.inc';
    exit;
} else {
    require IMP_TEMPLATES . '/folders/head.inc';
    require IMP_TEMPLATES . '/folders/actions.inc';
}

/* Get special folders. */
$trash = IMP::folderPref($prefs->getValue('trash_folder'), true);
$draft = IMP::folderPref($prefs->getValue('drafts_folder'), true);
$sent = $identity->getAllSentmailFolders();

$name_url = Util::addParameter(Horde::applicationUrl('mailbox.php'), 'no_newmail_popup', 1);

$mbox_icons = $morembox = $rows = array();

/* Call the mailbox icon hook, if requested. */
if (!empty($conf['hooks']['mbox_icon'])) {
    require_once HORDE_BASE . '/config/hooks.php';
    if (function_exists('_imp_hook_mbox_icons')) {
        $mbox_icons = call_user_func('_imp_hook_mbox_icons');
    }
}

/* Start iterating through the list of mailboxes, displaying them. */
$mailbox = $imptree->reset();
do {
    $msgs_info = $row = array();
    $row['show_msgs'] = false;
    $row['nocheckbox'] = false;
    $row['vfolder'] = false;
    $mailbox['label'] = htmlspecialchars($mailbox['label']);

    if (!$imptree->isContainer($mailbox)) {
        /* We are dealing with mailboxes here.
         * Determine if we need to poll this mailbox for new messages. */
        if ($imptree->isPolled($mailbox)) {
            $row['show_msgs'] = true;
            /* If we need message information for this folder, update it
             * now. */
            $msgs_info = $imptree->getElementInfo($mailbox['value']);
            if (!empty($msgs_info)) {
                /* Populate the $newmsgs hash with the new msgs count. */
                if (!empty($msgs_info['newmsg'])) {
                    $newmsgs[$mailbox['value']] = $msgs_info['newmsg'];
                }

                /* Identify the number of messages in the folder. */
                $row['msgs'] = $msgs_info['messages'];

                /* Highlight mailboxes with unread messages in bold. */
                $row['new'] = $msgs_info['unseen'];
                if (!empty($row['new'])) {
                    $mailbox['label'] = '<strong>' . $mailbox['label'] . '</strong>';
                }
            }
        }

        $row['name'] = Horde::link(Util::addParameter($name_url, 'mailbox', $mailbox['value']), sprintf(_("View messages in %s"), ($imptree->isVFolder($mailbox)) ? $mailbox['label'] : IMP::displayFolder($mailbox['value']))) . $mailbox['label'] . '</a>';

        switch ($mailbox['value']) {
        case 'INBOX':
            $dir2 = _image('inbox.png', _("Inbox"), 'folder');
            break;

        case $trash:
            $dir2 = ($prefs->getValue('use_vtrash')) ? _image(($imptree->isOpen($mailbox)) ? 'folder_open.png' : 'folder.png', _("Mailbox"), 'folder') :_image('trash.png', _("Trash folder"), 'folder');
            break;

        case $draft:
            $dir2 = _image('drafts.png', _("Draft folder"), 'folder');
            break;

        default:
            if (in_array($mailbox['value'], $sent)) {
                $dir2 = _image('sent.png', _("Sent mail folder"), 'folder');
            } else {
                if (isset($mbox_icons[$mailbox['value']])) {
                    $dir2 = $mbox_icons[$mailbox['value']];
                } else {
                    $dir2 = _image(($imptree->isOpen($mailbox)) ? 'folder_open.png' : 'folder.png', _("Mailbox"), 'folder');
                }
            }
            break;
        }

        /* Virtual folders. */
        if ($imptree->isVFolder($mailbox)) {
            $row['nocheckbox'] = true;
            if ($imp_search->isVTrashFolder($mailbox['value'])) {
                $row['vfolder'] = false;
                $dir2 = _image('trash.png', _("Virtual Trash Folder"), 'folder');
            } elseif ($imp_search->isVINBOXFolder($mailbox['value'])) {
                $row['vfolder'] = false;
                $dir2 = _image('inbox.png', _("Virtual INBOX Folder"), 'folder');
            } else {
                $row['vfolder'] = true;
                $row['delvfolder'] = Horde::link($imp_search->deleteURL($mailbox['value']), _("Delete Virtual Folder")) . _("Delete") . '</a>';
                $row['editvfolder'] = Horde::link($imp_search->editURL($mailbox['value']), _("Edit Virtual Folder")) . _("Edit") . '</a>';
            }
        }
    } else {
        /* We are dealing with folders here. */
        $row['name'] = $mailbox['label'];
        if ($imptree->isOpen($mailbox)) {
            $dir2 = _image('folder_open.png', _("Opened Folder"), 'folder');
        } else {
            $dir2 = _image('folder.png', _("Closed Folder"), 'folder');
        }

        /* Virtual folders. */
        if ($imptree->isVFolder($mailbox)) {
            $row['nocheckbox'] = true;
        }
    }

    $peek = $imptree->peek();

    if ($imptree->hasChildren($mailbox, true)) {
        $dir = Util::addParameter($folders_url, 'folder', $mailbox['value']);
        if ($imptree->isOpen($mailbox)) {
            $dir = Util::addParameter($dir, 'actionID', 'collapse_folder');
            if ($mailbox['value'] == 'INBOX') {
                $minus_img = 'minustop.png';
            } else {
                $minus_img = ($peek) ? 'minus.png' : 'minusbottom.png';
            }
            $dir = Horde::link($dir, _("Collapse Folder")) . _image($minus_img, _("Collapse"), 'tree') . "</a>$dir2";
        } else {
            $dir = Util::addParameter($dir, 'actionID', 'expand_folder');
            if ($mailbox['value'] == 'INBOX') {
                $plus_img = 'plustop.png';
            } else {
                $plus_img = ($peek) ? 'plus.png' : 'plusbottom.png';
            }
            $dir = Horde::link($dir, _("Expand Folder")) . _image($plus_img, _("Expand"), 'tree') . "</a>$dir2";
        }
    } else {
        if ($mailbox['value'] == 'INBOX') {
            $join_img = ($peek) ? 'joinbottom-down.png' : 'blank.png';
        } else {
            $join_img = ($peek) ? 'join.png' : 'joinbottom.png';
        }
        $dir = _image($join_img, '', 'tree') . $dir2;
    }

    /* Highlight line differently if folder/mailbox is
     * unsubscribed. */
    $row['class'] = (++$rowct % 2) ? 'item0' : 'item1';
    if ($showAll && $subscribe && !$imptree->isSubscribed($mailbox) && !$imptree->isContainer($mailbox)) {
        $row['class'] .= ' folderunsub';
    }

    $row['mbox_val'] = htmlspecialchars($mailbox['value']);
    $row['line'] = '';
    $morembox[$mailbox['level']] = $peek;
    for ($i = 0; $i < $mailbox['level']; $i++) {
        $row['line'] .= _image(($morembox[$i]) ? 'line.png' : 'blank.png', '', 'tree');
    }
    $row['line'] .= $dir;

    /* Hide folder prefixes from the user. */
    if ($mailbox['level'] >= 0) {
        $rows[] = $row;
        $displayNames[] = addslashes(IMP::displayFolder($mailbox['value']));
    }

} while (($mailbox = $imptree->next()));

/* Check to see if user wants new mail notification */
if ($prefs->getValue('nav_popup')) {
    $notification->push(IMP::getNewMessagePopup($newmsgs), 'javascript');
}
if ($prefs->getValue('nav_audio')) {
    $play = false;
    foreach ($newmsgs as $mb => $nm) {
        if ($nm > 0) {
            $play = true;
            break;
        }
    }
    if ($play) {
        $notification->push($registry->getImageDir() . '/audio/theetone.wav',
                            'audio');
    }
}

/* Render the rows now. */
$template = &new Horde_Template();
$template->set('rows', $rows, true);
echo $template->fetch(IMP_TEMPLATES . '/folders/folders.html');
if ($rowct > 10) {
    $i++;
    require IMP_TEMPLATES . '/folders/actions.inc';
}

require IMP_TEMPLATES . '/folders/foot.inc';

if (!empty($open_compose_window)) {
    $args = 'popup=1';
    if (!isset($options)) {
        $options = array();
    }
    foreach (array_merge($options, IMP::getComposeArgs()) as $arg => $value) {
        $args .= !empty($value) ? '&' . $arg . '=' . urlencode($value) : '';
    }
    Horde::addScriptFile('popup.js');
    echo "<script type='text/javascript'>popup_imp('" . Horde::applicationUrl('compose.php') . "',700,650,'" . addslashes($args) . "');</script>";
}

/* BC check. */
if (class_exists('Notification_Listener_audio')) {
    $notification->notify(array('listeners' => 'audio'));
}
require $registry->get('templates', 'horde') . '/common-footer.inc';
