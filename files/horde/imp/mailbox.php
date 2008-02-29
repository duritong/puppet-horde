<?php
/**
 * $Horde: imp/mailbox.php,v 2.617.4.67 2007/04/06 22:19:25 slusarz Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

/**
 * Delete a list of messages.
 */
function _deleteMessages($indices)
{
    global $imp, $mailbox_url;

    if (!empty($indices)) {
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->delete($indices);

        if ($imp['base_protocol'] == 'pop3') {
            header('Location: ' . Horde::url($mailbox_url, true));
            exit;
        }

        return true;
    }

    return false;
}

/**
 * Stylize a string.
 */
function _stylize($string, $styles)
{
    if (!is_array($styles)) {
        return $string;
    }

    foreach ($styles as $style) {
        if (!empty($style)) {
            $string = '<' . $style . '>' . $string . '</' . $style . '>';
        }
    }

    return $string;
}

/**
 * Output the message summaries.
 */
function _outputSummaries($search_mbox, $msgs)
{
    $template = &new Horde_Template();
    $template->set('idx_separator', IMP_IDX_SEP);
    $template->set('search_mbox', (bool) $search_mbox, true);
    $template->set('messages', $msgs, true);

    // Some browsers have trouble with hidden overflow in table cells
    // but not in divs.
    if ($GLOBALS['browser']->hasQuirk('no_hidden_overflow_tables')) {
        $template->set('overflow_begin', '<div class="ohide">');
        $template->set('overflow_end', '</div>');
    } else {
        $template->set('overflow_begin', '');
        $template->set('overflow_end', '');
    }
    echo $template->fetch(IMP_TEMPLATES . '/mailbox/mailbox.html');
}

@define('IMP_BASE', dirname(__FILE__));
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Mailbox.php';
require_once IMP_BASE . '/lib/Search.php';
require_once 'Horde/MIME.php';
require_once 'Horde/Identity.php';
require_once 'Horde/Template.php';
require_once 'Horde/Text.php';

/* Call the mailbox redirection hook, if requested. */
if (!empty($conf['hooks']['mbox_redirect'])) {
    require_once HORDE_BASE . '/config/hooks.php';
    if (function_exists('_imp_hook_mbox_redirect')) {
        $redirect = call_user_func('_imp_hook_mbox_redirect', $imp['mailbox']);
        if (!empty($redirect)) {
            $redirect = Horde::applicationUrl($redirect, true);
            header('Location: ' . $redirect);
            exit;
        }
    }
}

/* There is a chance that this page is loaded directly via
 * message.php. If so, don't re-include config files, and the
 * following variables will already be set: $actionID, $start. */
if (isset($from_message_page)) {
    $mailbox_url = Util::addParameter(Horde::applicationURL('mailbox.php'), 'mailbox', $imp['mailbox']);
} else {
    $mailbox_url = Horde::selfUrl();
    $actionID = Util::getFormData('actionID');
    $start = Util::getFormData('start');
}

/* Get form data and make sure it's the type that we're expecting. */
$targetMbox = Util::getFormData('targetMbox');
$newMbox = Util::getFormData('newMbox');
if (!is_array(($indices = Util::getFormData('indices')))) {
    $indices = array($indices);
}
$indices_mbox = array($imp['mailbox'] => $indices);

/* Set the current time zone. */
NLS::setTimeZone();

/* Cache the charset. */
$charset = NLS::getCharset();

/* Initialize the user's identities. */
$identity = &Identity::singleton(array('imp', 'imp'));

$do_filter = $open_compose_window = false;

/* Run through the action handlers */
switch ($actionID) {
case 'change_sort':
    if (($sortby = Util::getFormData('sortby')) !== null) {
        $prefs->setValue('sortby', $sortby);
    }
    if (($sortdir = Util::getFormData('sortdir')) !== null) {
        $prefs->setValue('sortdir', $sortdir);
    }
    $imp_mailbox = &IMP_Mailbox::singleton(null, true);
    break;

case 'blacklist':
    require_once IMP_BASE . '/lib/Filter.php';
    $imp_filter = &IMP_Filter::singleton();
    $imp_filter->blacklistMessage($indices_mbox);
    break;

case 'whitelist':
    require_once IMP_BASE . '/lib/Filter.php';
    $imp_filter = &IMP_Filter::singleton();
    $imp_filter->whitelistMessage($indices_mbox);
    break;

case 'spam_report':
case 'notspam_report':
    $action = str_replace('_report', '', $actionID);
    require_once IMP_BASE . '/lib/Spam.php';
    $imp_spam = &new IMP_Spam();
    $imp_spam->reportSpam($indices_mbox, $action);

    /* Delete spam after report. */
    $delete_spam = $prefs->getValue('delete_spam_after_report');
    if (($action == 'spam') && ($delete_spam == 1)) {
        if (_deleteMessages($indices_mbox)) {
            if (count($indices) == 1) {
                $notification->push(_("1 message has been deleted."), 'horde.message');
            } else {
                $notification->push(sprintf(_("%d messages have been deleted."),
                                            count($indices)), 'horde.message');
            }
        }
    } elseif ($delete_spam == 2) {
        $targetMbox = ($action == 'spam') ? IMP::folderPref($prefs->getValue('spam_folder'), true) : 'INBOX';
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->copy($targetMbox, IMP_MESSAGE_MOVE, $indices_mbox, true);
    }
    break;

case 'message_missing':
    $notification->push(_("Requested message not found."), 'horde.error');
    break;

case 'fwd_digest':
    $options = array('fwddigest' => serialize($indices), 'actionID' => 'fwd_digest');
    $open_compose_window = IMP::openComposeWin($options);
    break;

case 'delete_messages':
    _deleteMessages($indices_mbox);
    break;

case 'undelete_messages':
    if (!empty($indices_mbox)) {
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->undelete($indices_mbox);
    }
    break;

case 'move_messages':
case 'copy_messages':
    if (!empty($indices) && !empty($targetMbox)) {
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $action = ($actionID == 'move_messages') ? IMP_MESSAGE_MOVE : IMP_MESSAGE_COPY;

        if ($conf['tasklist']['use_tasklist'] &&
            (strpos($targetMbox, '_tasklist_') === 0)) {
            /* If the target is a tasklist, handle the move/copy specially. */
            $tasklist = str_replace('_tasklist_', '', $targetMbox);
            $imp_message->createTasksOrNotes($tasklist, $action, $indices_mbox, 'task');
        } elseif ($conf['notepad']['use_notepad'] &&
            (strpos($targetMbox, '_notepad_') === 0)) {
            /* If the target is a notepad, handle the move/copy specially. */
            $notepad = str_replace('_notepad_', '', $targetMbox);
            $imp_message->createTasksOrNotes($notepad, $action, $indices_mbox, 'note');
        } else {
            /* Otherwise, the target is a standard mailbox. */
            if (!empty($newMbox) && ($newMbox == 1)) {
                $new_mailbox = String::convertCharset(IMP::folderPref($targetMbox, true), $charset, 'UTF7-IMAP');

                require_once IMP_BASE . '/lib/Folder.php';
                $imp_folder = &IMP_Folder::singleton();
                if ($imp_folder->create($new_mailbox, $prefs->getValue('subscribe'))) {
                    $imp_message->copy($new_mailbox, $action, $indices_mbox);
                }
            } else {
                $imp_message->copy($targetMbox, $action, $indices_mbox);
            }
        }
    }
    break;

case 'flag_messages':
    $flag = Util::getPost('flag');
    if (!empty($indices) && !empty($flag)) {
        if ($flag{0} == '0') {
            $_POST['flag'] = '\\' . substr($flag, 1);
            $set = false;
        } else {
            $_POST['flag'] = '\\' . $flag;
            $set = true;
        }
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->flag($_POST['flag'], $indices_mbox, $set);
    }
    break;

case 'hide_deleted':
    $prefs->setValue('delhide', !$prefs->getValue('delhide'));
    break;

case 'expunge_mailbox':
    require_once IMP_BASE . '/lib/Message.php';
    $imp_message = &IMP_Message::singleton();
    $imp_message->expungeMailbox(array($imp['mailbox']));
    break;

case 'filter':
    $do_filter = true;
    break;

case 'empty_mailbox':
    require_once IMP_BASE . '/lib/Message.php';
    $imp_message = &IMP_Message::singleton();
    $imp_message->emptyMailbox(array($imp['mailbox']));
    break;

case 'view_messages':
    require_once 'Horde/SessionObjects.php';
    $cacheSess = &Horde_SessionObjects::singleton();
    $redirect = Util::addParameter(Horde::applicationUrl('thread.php', true), array('mode' => 'msgview', 'msglist' => $cacheSess->storeOid($indices_mbox)), null, false);
    header("Location: " . $redirect);
    exit;
    break;

case 'login_compose':
    $open_compose_window = IMP::openComposeWin();
    break;
}

/* Is this a search mailbox? */
$search_mbox = $imp_search->searchMboxID();

/* Deal with filter options. */
if ($imp['filteravail']) {
    /* Only allow filter on display for INBOX. */
    if (($imp['mailbox'] == 'INBOX') && $prefs->getValue('filter_on_display')) {
        $do_filter = true;
    } elseif (($imp['mailbox'] == 'INBOX') ||
              ($prefs->getValue('filter_any_mailbox') && !$search_mbox)) {
        $filter_url = Util::addParameter($mailbox_url, 'actionID', 'filter');
    }
}

/* Run filters now. */
if ($do_filter) {
    require_once IMP_BASE . '/lib/Filter.php';
    $imp_filter = &IMP_Filter::singleton();
    $imp_filter->filter($imp['mailbox']);
}

/* Generate folder options list. */
if ($conf['user']['allow_folders']) {
    $folder_options = IMP::flistSelect(_("Messages to"), true, array(), null, true, true, false, true);
}

/* Build the list of messages in the mailbox. */
if (!isset($imp_mailbox)) {
    $imp_mailbox = &IMP_Mailbox::singleton();
}
$imp_mailbox->setNoNewmailPopup(Util::getFormData('no_newmail_popup'));
$pageOb = $imp_mailbox->buildMailboxPage(Util::getFormData('page'), $start);
$mailboxOverview = $imp_mailbox->buildMailboxArray($pageOb->begin, $pageOb->end);

/* Cache this value since we use it alot on this page. */
$graphicsdir = $registry->getImageDir('horde');

/* Generate First/Previous page links. */
if ($pageOb->page == 1) {
    $pages_first = Horde::img('nav/first-grey.png', null, null, $graphicsdir);
    $pages_prev = Horde::img('nav/left-grey.png', null, null, $graphicsdir);
} else {
    $first_url = Util::addParameter($mailbox_url, 'page', 1);
    $pages_first = Horde::link($first_url, _("First Page")) . Horde::img('nav/first.png', '<<', null, $graphicsdir) . '</a>';
    $prev_url = Util::addParameter($mailbox_url, 'page', $pageOb->page - 1);
    $pages_prev = Horde::link($prev_url, _("Previous Page"), '', '', '', '', '', array('id' => 'prev')) . Horde::img('nav/left.png', '<', null, $graphicsdir) . '</a>';
}

/* Generate Next/Last page links. */
if ($pageOb->page == $pageOb->pagecount) {
    $pages_last = Horde::img('nav/last-grey.png', null, null, $graphicsdir);
    $pages_next = Horde::img('nav/right-grey.png', null, null, $graphicsdir);
} else {
    $next_url = Util::addParameter($mailbox_url, 'page', $pageOb->page + 1);
    $pages_next = Horde::link($next_url, _("Next Page"), '', '', '', '', '', array('id' => 'next')) . Horde::img('nav/right.png', '>', null, $graphicsdir) . '</a>';
    $last_url = Util::addParameter($mailbox_url, 'page', $pageOb->pagecount);
    $pages_last = Horde::link($last_url, _("Last Page")) . Horde::img('nav/last.png', '>>', null, $graphicsdir) . '</a>';
}

/* Determine if we are going to show the Hide/Purge Deleted Message links. */
$sortby = $imp_mailbox->sortby();
if (!$prefs->getValue('use_trash') &&
    !$prefs->getValue('use_vtrash') &&
    !$GLOBALS['imp_search']->isVINBOXFolder()) {
    $showdelete = array('hide' => ($sortby != SORTTHREAD), 'purge' => true);
} else {
    $showdelete = array('hide' => false, 'purge' => false);
}
if ($showdelete['hide'] && !$prefs->isLocked('delhide')) {
    if ($prefs->getValue('delhide')) {
        $deleted_prompt = _("Show Deleted");
    } else {
        $deleted_prompt = _("Hide Deleted");
    }
}

/* Generate mailbox summary string. */
if (!empty($pageOb->end)) {
    $msg_count = sprintf(_("%d to %d of %d Messages"), $pageOb->begin, $pageOb->end, $pageOb->msgcount);
} else {
    $msg_count = sprintf(_("No Messages"));
}

/* If user wants the mailbox to be refreshed, set time here. */
$refresh_time = $prefs->getValue('refresh_time');
$refresh_url = Util::addParameter($mailbox_url, 'page', $pageOb->page);
if (isset($filter_url)) {
    $filter_url = Util::addParameter($filter_url, 'page', $pageOb->page);
}

/* Set the folder for the sort links. */
$sortdir = $prefs->getValue('sortdir');
$sort_url = Util::addParameter($mailbox_url, 'sortdir', ($sortdir) ? 0 : 1);

/* Determine if we are showing previews. */
$show_preview = $imp_mailbox->showPreviews();
$preview_tooltip = ($show_preview) ? $prefs->getValue('preview_show_tooltip') : false;
if ($preview_tooltip) {
    Horde::addScriptFile('tooltip.js', 'horde', true);
} else {
    $strip_preview = $prefs->getValue('preview_strip_nl');
}

$vtrash = null;
if ($search_mbox) {
    $unread = 0;
    if ($imp_search->isVINBOXFolder()) {
        $unread = $imp_mailbox->getMessageCount();
    } elseif ($imp_search->isVTrashFolder()) {
        $vtrash = $imp_search->createSearchID($search_mbox);
   }
} else {
    $unread = count($imp_mailbox->unseenMessages());
}

/* Play audio. */
if ($prefs->getValue('nav_audio') &&
    ($newmsgs = $imp_mailbox->newMessageCount()) &&
    ($newmsgs > 0) &&
    !Util::getFormData('no_newmail_popup')) {
    $notification->push($registry->getImageDir() . '/audio/theetone.wav', 'audio');
}

$title = IMP::getLabel();
$refresh_title = sprintf(_("_Refresh %s"), $title);
$refresh_ak = Horde::getAccessKey($refresh_title);
$refresh_title = Horde::stripAccessKey($refresh_title);
if (!empty($refresh_ak)) {
    $refresh_title .= sprintf(_(" (Accesskey %s)"), $refresh_ak);
}
if ($unread) {
    $title .= ' (' . $unread . ')';
}
Horde::addScriptFile('keybindings.js', 'horde');
require IMP_TEMPLATES . '/common-header.inc';
require IMP_TEMPLATES . '/menu.inc';
if ($browser->hasFeature('javascript')) {
    require IMP_TEMPLATES . '/mailbox/javascript.inc';
}
IMP::status();

/* Print quota information. */
if (isset($imp['quota']) && is_array($imp['quota'])) {
    require_once IMP_BASE . '/lib/Quota.php';
    $quotaDriver = &IMP_Quota::singleton($imp['quota']['driver'], $imp['quota']['params']);
    if ($quotaDriver !== false) {
        $quota = $quotaDriver->getQuota();
        require IMP_TEMPLATES . '/quota/quota.inc';
    }
}

/* Are we currently in the trash mailbox? */
$use_trash = $prefs->getValue('use_trash');
$use_vtrash = $prefs->getValue('use_vtrash');
$trashMbox = ($use_trash && ($imp['mailbox'] == (IMP::folderPref($prefs->getValue('trash_folder'), true)))) ||
             ($use_vtrash && !is_null($vtrash));

/* Show the [black|white]list link if we have functionality enabled. */
$show_blacklist_link = $registry->hasMethod('mail/blacklistFrom');
$show_whitelist_link = $registry->hasMethod('mail/whitelistFrom');

require IMP_TEMPLATES . '/mailbox/header.inc';

/* If no messages, exit immediately. */
if (empty($pageOb->end)) {
    if ($pageOb->anymsg && isset($deleted_prompt)) {
        /* Show 'Show Deleted' prompt if mailbox has no viewable message but
           has hidden, deleted messages. */
        require IMP_TEMPLATES . '/mailbox/actions_deleted.inc';
    }
    require IMP_TEMPLATES . '/mailbox/empty_mailbox.inc';
    require IMP_TEMPLATES . '/mailbox/footer.inc';
    require $registry->get('templates', 'horde') . '/common-footer.inc';
    exit;
}

/* If there are more than 15 messages, cache the actions output. */
if ($pageOb->msgcount != 0) {
    $navform = 1;
    require IMP_TEMPLATES . '/mailbox/navbar.inc';
    if ($pageOb->msgcount > 15) {
        ob_start();
        require IMP_TEMPLATES . '/mailbox/actions.inc';
        $actions_output = ob_get_contents();
        ob_end_clean();
        echo $actions_output;
    } else {
        require IMP_TEMPLATES . '/mailbox/actions.inc';
    }
}

/* Define some variables now so we don't have to keep redefining in the
   foreach() loop or the templates. */
$curr_time = time();
$curr_time -= $curr_time % 60;
$ltime_val = localtime();
$today_start = mktime(0, 0, 0, $ltime_val[4] + 1, $ltime_val[3], 1900 + $ltime_val[5]);
$today_end = $today_start + 86400;
$drafts_sm_folder = $imp_mailbox->isSpecialFolder();
$lastMbox = '';
$messages = array();
$threadlevel = array();

/* Determine sorting preferences. */
$thread_sort = ($sortby == SORTTHREAD);
$sortlimit = $imp_mailbox->aboveSortLimit();

/* Get thread object, if necessary. */
if ($thread_sort) {
    $threadob = $imp_mailbox->getThreadOb();
    $uid_list = array();
    foreach ($mailboxOverview as $val) {
        $uid_list[] = $val['header']->uid;
    }
    $threadtree = $threadob->getThreadImageTree($uid_list, $sortdir);
}

/* Don't show header row if this is a search mailbox or if no messages in the
   current mailbox. */
if (!$search_mbox && ($pageOb->msgcount != 0)) {
    require IMP_TEMPLATES . '/mailbox/message_headers.inc';
}

/* Cache some repetitively used variables. */
$datefmt = $prefs->getValue('date_format');
$timefmt = $prefs->getValue('time_format');
$fromlinkstyle = $prefs->getValue('from_link');
$localeinfo = NLS::getLocaleInfo();

/* Display message information. */
$msgs = array();
foreach ($mailboxOverview as $msgIndex => $message) {
    if ($search_mbox) {
        if (empty($lastMbox) || ($message['mbox'] != $lastMbox)) {
            if (!empty($lastMbox)) {
                _outputSummaries($search_mbox, $msgs);
                $msgs = array();
            }
            $folder_link = Horde::url(Util::addParameter('mailbox.php', 'mailbox', $message['mbox']));
            $folder_link = Horde::link($folder_link, sprintf(_("View messages in %s"), IMP::displayFolder($message['mbox'])), 'smallheader') . IMP::displayFolder($message['mbox']) . '</a>';
            require IMP_TEMPLATES . '/mailbox/searchfolder.inc';
            require IMP_TEMPLATES . '/mailbox/message_headers.inc';
        }
        $lastMbox = $message['mbox'];
    }

    /* Initialize the header fields. */
    $msg = array(
        'color' => (isset($message['color'])) ? htmlspecialchars($message['color']) : '',
        'date' => '&nbsp;',
        'from' => '&nbsp;',
        'mbox' => $message['mbox'],
        'preview' => '',
        'size' => '?',
        'subject' => _("[No Subject]")
    );

    /* Initialize some other variables. */
    $attachment = false;
    $from_adr = null;
    $mailto_link = false;
    $showfromlink = true;

    /* Now pull the IMAP header values into them, decoding them at
       the same time. */
    $h = $message['header'];
    $messages[] = $h->uid;

    /* Formats the header date string nicely. */
    if (empty($h->date)) {
        $udate = false;
    } else {
        $h->date = preg_replace('/\s+\(\w+\)$/', '', $h->date);
        $udate = strtotime($h->date, $curr_time);
    }
    if ($udate === false || $udate === -1) {
        $msg['date'] = _("Unknown Date");
    } elseif (($udate < $today_start) || ($udate > $today_end)) {
        /* Not today, use the date. */
        $msg['date'] = strftime($datefmt, $udate);
    } else {
        /* Else, it's today, use the time. */
        $msg['date'] = strftime($timefmt, $udate);
    }

    /* Format the from header. */
    if (isset($h->from)) {
        $from_adr = IMP::bareAddress($h->from);
        $from_ob = IMP::parseAddressList($h->from, '');
        if (!is_a($from_ob, 'PEAR_Error')) {
            $from_ob = array_shift($from_ob);
        }
        if (is_null($from_adr)) {
            $msg['from'] = _("Invalid Address");
            $showfromlink = false;
        } elseif ($identity->hasAddress($from_adr)) {
            if (isset($h->to)) {
                if (strstr($h->to, 'undisclosed-recipients:')) {
                    $msg['from'] = _("Undisclosed Recipients");
                    $showfromlink = false;
                } else {
                    $tmp = IMP::parseAddressList($h->to, '');
                    if (!is_a($tmp, 'PEAR_Error')) {
                        $tmp = array_shift($tmp);
                    }
                    if (isset($tmp->personal)) {
                        $msg['from'] = stripslashes(MIME::decode($tmp->personal, $charset));
                    } else {
                        $msg['from'] = IMP::bareAddress($h->to);
                    }
                    $msg['fullfrom'] = MIME::decode($h->to, $charset);
                    if (empty($msg['from'])) {
                        $msg['from'] = $msg['fullfrom'];
                    }
                }
            } else {
                $msg['from'] = _("Undisclosed Recipients");
                $showfromlink = false;
            }
            if (!$drafts_sm_folder) {
                $msg['from'] = _("To") . ': ' . $msg['from'];
            }
            $mailto_link = true;
        } elseif (isset($from_ob->personal)) {
            $msg['from'] = trim(stripslashes(MIME::decode($from_ob->personal, $charset)), '"');
            if (!trim($msg['from'], chr(160) . ' ')) {
                $msg['from'] = $from_adr;
            }
            if ($drafts_sm_folder) {
                $msg['from'] = _("From") . ': ' . $msg['from'];
            }
            $msg['fullfrom'] = MIME::decode($h->from, $charset);
        } else {
            if (!isset($from_ob->host) ||
                (strstr($from_ob->host, 'SYNTAX-ERROR') !== false)) {
                $msg['from'] = (!empty($from_adr)) ? $from_adr : _("Unknown Recipient");
                $showfromlink = false;
            } else {
                $msg['from'] = $from_adr;
                $msg['fullfrom'] = MIME::decode($h->from, $charset);
            }
        }
    } else {
        $msg['from'] = _("Invalid Address");
        $showfromlink = false;
    }

    if (!empty($h->subject)) {
        $subject = MIME::decode($h->subject, $charset);
        if (!empty($subject)) {
            $msg['subject'] = strtr($subject, "\t", ' ');
        }
    }
    $msg['fullsubject'] = $msg['subject'];

    if (isset($h->size)) {
        $msg['size'] = ($h->size > 1024)
            ? sprintf(_("%s KB"), number_format($h->size / 1024, 0, $localeinfo['decimal_point'], $localeinfo['thousands_sep']))
            : $h->size;
    }

    /* Filter the subject text, if requested. */
    $msg['subject'] = IMP::filterText($msg['subject']);
    $msg['fullsubject'] = IMP::filterText($msg['fullsubject']);

    if (isset($message['structure']) &&
        ($message['structure']->getPrimaryType() == 'multipart')) {
        switch ($message['structure']->getSubType()) {
        case 'signed':
            $attachment = 'signed';
            $attachment_alt = _("Message is signed");
            break;

        case 'encrypted':
            $attachment = 'encrypted';
            $attachment_alt = _("Message is encrypted");
            break;

        case 'alternative':
            /* Treat this as no attachments. */
            break;

        default:
            $attachment = 'attachment';
            $attachment_alt = _("Message has attachments");
            break;
        }
    }

    /* Generate the target link. */
    $msgMbox = isset($message['mbox']) ? $message['mbox'] : '';
    $target = IMP::generateSearchUrl('message.php', $msgMbox);
    $target = Util::addParameter($target, 'index', $h->uid);

    /* Get all the flag information. */
    $msg['bg'] = 'seen';
    $msg['status'] = '';
    $flagbits = 0;
    $style = array();
    $xprio = false;

    /* Check for X-Priority information. */
    if ($conf['mailbox']['show_xpriority']) {
        require_once IMP_BASE . '/lib/MIME/Headers.php';
        $imp_headers = &new IMP_Headers($h->uid);
        $imp_headers->buildHeaders();
        if (($priority = $imp_headers->getValue('x-priority'))) {
            if (preg_match('/\s*(\d+)\s*/', $priority, $matches)) {
                if (($matches[1] == '1') || ($matches[1] == '2')) {
                    $xprio = 'high';
                } elseif (($matches[1] == '4') || ($matches[1] == '5')) {
                    $xprio = 'low';
                }
            }
        }
    }

    if ($imp['base_protocol'] != 'pop3') {
        if (!empty($h->to) && $identity->hasAddress(IMP::bareAddress($h->to))) {
            $msg['status'] .= Horde::img('mail_personal.png', _("Personal"));
            $flagbits |= IMP_PERSONAL;
        }
        if (!$h->seen || $h->recent) {
            $flagbits |= IMP_UNSEEN;
            $msg['status'] .= Horde::img('mail_unseen.png', _("Unseen"));
            $style[] = 'b';
            $msg['bg'] = 'unseen';
        }
        if ($h->answered) {
            $flagbits |= IMP_ANSWERED;
            $msg['status'] .= Horde::img('mail_answered.png', _("Answered"));
            $msg['bg'] = 'answered';
        }
        if ($h->draft) {
            $flagbits |= IMP_DRAFT;
            $msg['status'] .= Horde::img('mail_draft.png', _("Draft"));
            $target = IMP::composeLink(array(), array('actionID' => 'draft', 'mailbox' => $message['mbox'], 'index' => $h->uid));
        }
        if ($xprio == 'high') {
            $flagbits |= IMP_FLAGGED;
            $msg['status'] .= Horde::img('mail_priority_high.png', _("High Priority"));
            $msg['bg'] = 'important';
        } elseif ($xprio == 'low') {
            $msg['status'] .= Horde::img('mail_priority_low.png', _("Low Priority"));
            if (isset($style[0]) && $style[0] == 'b') {
                $style = array();
            }
        }
        if ($h->flagged) {
            $flagbits |= IMP_FLAGGED;
            $msg['status'] .= Horde::img('mail_flagged.png', _("Important"));
            $style[] = 'i';
            $msg['bg'] = 'important';
        }
        if ($h->deleted) {
            $flagbits |= IMP_DELETED;
            $msg['status'] .= Horde::img('mail_deleted.png', _("Deleted"));
            $style[] = 'strike';
            $msg['bg'] = 'deleted';
        }
        $flags[] = $flagbits;
    } else {
        $flags[] = 0;
    }

    if ($conf['mailbox']['show_attachments'] && $attachment) {
        $msg['status'] .= Horde::img($attachment . '.png', $attachment_alt);
    }

    /* Show message preview? */
    if ($show_preview) {
        $msg['preview'] = $message['preview'];
    }

    /* Set the message number. */
    $msg['number'] = _stylize($h->msgno, $style);

    /* Format the Date: Header. */
    $msg['date'] = _stylize($msg['date'], $style);

    /* Format message size. */
    $msg['size'] = _stylize($msg['size'], $style);

    /* Format the From: Header. */
    $msg['from'] = _stylize(htmlspecialchars($msg['from']), $style);
    switch ($fromlinkstyle) {
    case 0:
        if ($showfromlink) {
            $extra = array('actionID' => 'mailto', 'mailbox' => $imp['mailbox'], 'index' => $h->uid, 'mailto' => $mailto_link);
            if ($search_mbox) {
                $extra['thismailbox'] = $message['mbox'];
            }
            $msg['from'] = Horde::link(IMP::composeLink(array(), $extra), sprintf(_("New Message to %s"), stripslashes($msg['fullfrom']))) . $msg['from'] . '</a>';
        }
        break;

    case 1:
        if (!isset($msg['fullfrom'])) {
            $msg['fullfrom'] = $msg['from'];
        }
        $from_uri = IMP::generateSearchUrl('message.php', $msgMbox);
        $from_uri = Util::addParameter($from_uri, 'index', $h->uid);
        $msg['from'] = Horde::link($from_uri, $msg['fullfrom']) . $msg['from'] . '</a>';
        break;
    }

    /* Format the Subject: Header. */
    $msg['subject'] = _stylize(Text::htmlSpaces($msg['subject']), $style);
    if ($preview_tooltip) {
        $msg['subject'] = substr(Horde::linkTooltip($target, $msg['fullsubject'], '', '', '', $msg['preview']), 0, -1) . ' id="subject' . $h->uid . '">' . $msg['subject'] . '</a>';
    } else {
        $msg['subject'] = substr(Horde::link($target, $msg['fullsubject']), 0, -1) . ' id="subject' . $h->uid . '">' . $msg['subject'] . '</a>' . (!empty($msg['preview']) ? '<br /><small>' . $msg['preview'] . '</small>' : '');
    }

    /* Set up threading tree now. */
    if ($thread_sort) {
        if (!empty($threadtree[$h->uid])) {
            $msg['subject'] = $threadtree[$h->uid] . ' ' . $msg['subject'];
        }
    }

    /* We need the uid so the checkboxes will work. */
    $msg['uid'] = $h->uid;

    $msgs[] = $msg;
}

_outputSummaries($search_mbox, $msgs);
require IMP_TEMPLATES . '/mailbox/message_footers.inc';

if ($prefs->getValue('show_legend') && ($imp['base_protocol'] != 'pop3')) {
    require IMP_TEMPLATES . '/mailbox/legend.inc';
}

/* If there are 15 messages or less, don't show the actions/navbar
 * again. */
if (isset($actions_output)) {
    echo $actions_output;
    $navform = 2;
    require IMP_TEMPLATES . '/mailbox/navbar.inc';
} else {
    echo '<tr><td class="control" colspan="6"></td></tr>';
}

require IMP_TEMPLATES . '/mailbox/footer.inc';

if ($prefs->getValue('nav_popup') &&
    ($newmsgs = $imp_mailbox->newMessageCount())
    && ($newmsgs > 0) &&
    !Util::getFormData('no_newmail_popup')) {
    require IMP_TEMPLATES . '/mailbox/alert.inc';
}

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

require $registry->get('templates', 'horde') . '/common-footer.inc';
