<?php
/**
 * $Horde: imp/message.php,v 2.560.4.41 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

/**
 * Set pertinent variables for the mailbox.php script.
 *
 * @param integer $startIndex  The start index to use.
 * @param string $actID        The action ID to use.
 */
function _returnToMailbox($startIndex = null, $actID = null)
{
    global $actionID, $from_message_page, $start;

    $actionID = null;
    $from_message_page = true;
    $start = null;

    if (!is_null($startIndex)) {
        $start = $startIndex;
    }
    if (!is_null($actID)) {
        $actionID = $actID;
    }
}

@define('IMP_BASE', dirname(__FILE__));
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/MIME/Contents.php';
require_once IMP_BASE . '/lib/MIME/Headers.php';
require_once IMP_BASE . '/lib/Mailbox.php';
require_once IMP_BASE . '/lib/Search.php';
require_once 'Horde/Identity.php';

/* Make sure we have a valid index. */
$imp_mailbox = &IMP_Mailbox::singleton(Util::getFormData('index'));
if (!$imp_mailbox->isValidIndex()) {
    _returnToMailbox(null, 'message_missing');
    require IMP_BASE . '/mailbox.php';
    exit;
}

/* Are we using printer-friendly formatting? */
$printer_friendly = false;

/* Set the current time zone. */
NLS::setTimeZone();

/* Initialize the user's identities. */
$user_identity = &Identity::singleton(array('imp', 'imp'));

/* Run through action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'blacklist':
case 'whitelist':
    require_once IMP_BASE . '/lib/Filter.php';
    $imp_filter = &IMP_Filter::singleton();
    $idx = $imp_mailbox->getIMAPIndex();
    if ($actionID == 'blacklist') {
        $imp_filter->blacklistMessage(array($idx['mailbox'] => array($idx['index'])));
    } else {
        $imp_filter->whitelistMessage(array($idx['mailbox'] => array($idx['index'])));
    }
    break;

case 'print_message':
    $printer_friendly = true;
    IMP::printMode(true);
    break;

case 'delete_message':
case 'undelete_message':
    require_once IMP_BASE . '/lib/Message.php';
    $imp_message = &IMP_Message::singleton();
    if ($actionID == 'undelete_message') {
        $imp_message->undelete($imp_mailbox);
    } else {
        $imp_message->delete($imp_mailbox);
        if ($prefs->getValue('mailbox_return')) {
            _returnToMailbox($imp_mailbox->getMessageIndex());
            require IMP_BASE . '/mailbox.php';
            exit;
        }
    }
    break;

case 'move_message':
case 'copy_message':
    if (($targetMbox = Util::getFormData('targetMbox')) !== null) {
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();

        $action = ($actionID == 'move_message') ? IMP_MESSAGE_MOVE : IMP_MESSAGE_COPY;

        if ($conf['tasklist']['use_tasklist'] &&
            (strpos($targetMbox, '_tasklist_') === 0)) {
            /* If the target is a tasklist, handle the move/copy specially. */
            $tasklist = str_replace('_tasklist_', '', $targetMbox);
            $imp_message->createTasksOrNotes($tasklist, $action, $imp_mailbox, 'task');
        } elseif ($conf['notepad']['use_notepad'] &&
            (strpos($targetMbox, '_notepad_') === 0)) {
            /* If the target is a notepad, handle the move/copy specially. */
            $notepad = str_replace('_notepad_', '', $targetMbox);
            $imp_message->createTasksOrNotes($notepad, $action, $imp_mailbox, 'note');
        } else {
            /* Otherwise, the target is a standard mailbox. */
            if (Util::getFormData('newMbox', 0) == 1) {
                $new_mailbox = String::convertCharset(IMP::folderPref($targetMbox, true), NLS::getCharset(), 'UTF7-IMAP');
                require_once IMP_BASE . '/lib/Folder.php';
                $imp_folder = &IMP_Folder::singleton();
                if ($imp_folder->create($new_mailbox, $prefs->getValue('subscribe'))) {
                    $imp_message->copy($new_mailbox, $action, $imp_mailbox);
                }
            } else {
                $imp_message->copy($targetMbox, $action, $imp_mailbox);
            }
        }
    }
    if ($prefs->getValue('mailbox_return')) {
        _returnToMailbox($imp_mailbox->getMessageIndex());
        require IMP_BASE . '/mailbox.php';
        exit;
    }
    break;

case 'spam_report':
case 'notspam_report':
    $action = str_replace('_report', '', $actionID);

    require_once IMP_BASE . '/lib/Spam.php';
    $imp_spam = &new IMP_Spam();
    $imp_spam->reportSpam($imp_mailbox, $action);

    /* Delete spam after report. */
    $delete_spam = $prefs->getValue('delete_spam_after_report');
    if ($action == 'spam' && ($delete_spam == 1)) {
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->delete($imp_mailbox);
        $notification->push(_("The message has been deleted."), 'horde.message');
        if ($prefs->getValue('mailbox_return')) {
            _returnToMailbox($imp_mailbox->getMessageIndex());
            require IMP_BASE . '/mailbox.php';
            exit;
        }
    } elseif ($delete_spam == 2) {
        $targetMbox = ($action == 'spam') ? IMP::folderPref($prefs->getValue('spam_folder'), true) : 'INBOX';
        if ($targetMbox) {
            require_once IMP_BASE . '/lib/Message.php';
            $imp_message = &IMP_Message::singleton();
            $imp_message->copy($targetMbox, IMP_MESSAGE_MOVE, $imp_mailbox, true);
            if ($prefs->getValue('mailbox_return')) {
                _returnToMailbox($imp_mailbox->getMessageIndex());
                require IMP_BASE . '/mailbox.php';
                exit;
            }
        } else {
            $notification->push(_("Could not move message to spam mailbox - no spam mailbox defined in preferences."), 'horde.error');
        }
    }
    break;

case 'flag_message':
    $flag = Util::getFormData('flag');
    if ($flag) {
        if ($flag{0} == '0') {
            $flag = '\\' . substr($flag, 1);
            $set = false;
        } else {
            $flag = '\\' . $flag;
            $set = true;
        }
        require_once IMP_BASE . '/lib/Message.php';
        $imp_message = &IMP_Message::singleton();
        $imp_message->flag($flag, $imp_mailbox, $set);
        if ($prefs->getValue('mailbox_return')) {
            _returnToMailbox($imp_mailbox->getMessageIndex());
            require IMP_BASE . '/mailbox.php';
            exit;
        }
        $imp_mailbox->moveNext();
    }
    break;

case 'add_address':
    $contact_link = IMP::addAddress(Util::getFormData('address'), Util::getFormData('name'));
    if (is_a($contact_link, 'PEAR_Error')) {
        $notification->push($contact_link);
    } else {
        $notification->push(sprintf(_("Entry \"%s\" was successfully added to the address book"), $contact_link), 'horde.success', array('content.raw'));
    }
    break;

case 'strip_attachment':
    require_once IMP_BASE . '/lib/Message.php';
    $imp_message = &IMP_Message::singleton();
    $result = $imp_message->stripPart($imp_mailbox, Util::getFormData('imapid'));
    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result, 'horde.error');
    }

    break;
}

if ($conf['user']['allow_folders']) {
    $options = IMP::flistSelect(_("This message to"), true, array(), null, true, true, false, true);
}

/* We may have done processing that has taken us past the end of the
 * message array, so we will return to mailbox.php if that is the
 * case. */
if (!$imp_mailbox->isValidIndex()) {
    _returnToMailbox($imp_mailbox->getMessageIndex());
    require IMP_BASE . '/mailbox.php';
    exit;
}

/* Now that we are done processing the messages, get the index and
 * array index of the current message. */
$index_array = $imp_mailbox->getIMAPIndex();
$index = $index_array['index'];
$mailbox_name = $index_array['mailbox'];
$array_index = $imp_mailbox->getArrayIndex();

/* If we grab the headers before grabbing body parts, we'll see when a
 * message is unread. */
$imp_headers = &new IMP_Headers($index);
$imp_headers->buildHeaders();
$imp_headers->buildFlags();
$imp_headers_copy = &Util::cloneObject($imp_headers);

/* Parse MIME info and create the body of the message. */
$imp_contents = &IMP_Contents::singleton($index . IMP_IDX_SEP . $mailbox_name);

/* Update the message flag, if necessary. */
$use_pop = ($imp['base_protocol'] == 'pop3');
if (!$use_pop && !$imp_headers->getFlag('seen')) {
    require_once IMP_BASE . '/lib/Message.php';
    $imp_message = &IMP_Message::singleton();
    $imp_message->flag('\\SEEN', $imp_mailbox, true);
}

/* Determine if we should generate the attachment strip links or
 * not. */
if ($prefs->getValue('strip_attachments')) {
    $imp_contents->setStripLink(true);
}

/* Don't show summary links if we are printing the message. */
$imp_contents->showSummaryLinks(!$printer_friendly);

if (!$imp_contents->buildMessage()) {
    _returnToMailbox(null, 'message_missing');
    require IMP_BASE . '/mailbox.php';
    exit;
}

$attachments = $imp_contents->getAttachments();
$msgText = $imp_contents->getMessage();

/* Develop the list of Headers to display now. We will deal with the
 * 'basic' header information first since there are various
 * manipulations we do to them. */
$basic_headers = array(
    'date'      =>  _("Date"),
    'from'      =>  _("From"),
    'to'        =>  _("To"),
    'cc'        =>  _("Cc"),
    'bcc'       =>  _("Bcc"),
    'reply-to'  =>  _("Reply-To"),
    'subject'   =>  _("Subject")
);
$msgAddresses = array();

$imp_headers->setValueByFunction('date', array('nl2br', array($imp_headers, 'addLocalTime'), 'htmlspecialchars'));

/* Get the title/mailbox label of the mailbox page. */
$page_label = IMP::getLabel();

/* Process the subject now. */
if (($subject = $imp_headers->getValue('subject'))) {
    /* Filter the subject text, if requested. */
    $subject = IMP::filterText($subject);

    require_once 'Horde/Text.php';
    $imp_headers->setValue('subject', Text::htmlSpaces($subject));

    $title = sprintf(_("%s: %s"), $page_label, $subject);
    $shortsub = htmlspecialchars($subject);
} else {
    $shortsub = _("[No Subject]");
    $imp_headers->addHeader('Subject', $shortsub);
    $title = sprintf(_("%s: %s"), $page_label, $shortsub);
}

/* See if the 'X-Priority' header has been set. */
if (($priority = $imp_headers->getValue('x-priority'))) {
    if (preg_match("/\s*(\d+)\s*/", $priority, $matches)) {
        if (($matches[1] == '1') || ($matches[1] == '2')) {
            $imp_headers->addHeader('Priority', Horde::img('mail_priority_high.png', _("High Priority")) . '&nbsp;' . $priority);
        } elseif (($matches[1] == '4') || ($matches[1] == '5')) {
            $imp_headers->addHeader('Priority', Horde::img('mail_priority_low.png', _("Low Priority")) . '&nbsp;' . $priority);
        }
    }
}

/* Determine if all/list headers needed. */
$all_headers = Util::getFormData('show_all_headers');
$list_headers = Util::getFormData('show_list_headers');

/* Get the rest of the headers if all headers are requested. */
$user_hdrs = $user_identity->getValue('mail_hdr');
if ($all_headers || !empty($user_hdrs)) {
    $full_h = $imp_headers->getAllHeaders();
    foreach ($full_h as $head => $val) {
        /* Skip the X-Priority header if we have already dealt with
         * it. */
        if ((stristr($head, 'x-priority') !== false) &&
            $imp_headers->getValue('priority')) {
            unset($full_h[$head]);
        } elseif ($imp_headers->alteredHeader($head)) {
            $full_h[$head] = $imp_headers->getValue($head);
        } elseif (is_array($val)) {
            $val = array_map('htmlspecialchars', $val);
            $full_h[$head] = '<ul style="margin:0px;padding-left:15px"><li>' . implode("</li>\n<li>", $val) . '</li></ul>';
        } else {
            $full_h[$head] = htmlspecialchars($val);
        }
    }
    ksort($full_h);
}

/* Display the user-specified headers for the current identity. */
$custom_hdrs = array();
if (!empty($user_hdrs) && !$all_headers) {
    foreach ($user_hdrs as $user_hdr) {
        foreach ($full_h as $head => $val) {
            if (stristr($head, $user_hdr) !== false) {
                $custom_hdrs[$head] = $val;
            }
        }
    }
}

/* Generate the list of search parameters. */
$search_params = IMP::getSearchParameters($mailbox_name, $index);

/* For the self URL link, we can't trust the index in the query string as it
 * may have changed if we deleted/copied/moved messages. We may need other
 * stuff in the query string, so we need to do an add/remove of 'index'. */
$selfURL = Util::removeParameter(Horde::selfUrl(true), array('index', 'actionID'));
$selfURL = Util::addParameter($selfURL, 'index', $index);
$headersURL = Util::removeParameter($selfURL, array('show_all_headers', 'show_list_headers', 'mailbox', 'thismailbox'));
$headersURL = Util::addParameter($headersURL, $search_params);

/* Determine previous message index. */
if (($prev_msg = $imp_mailbox->getIMAPIndex(-1))) {
    $prev_url = IMP::generateSearchUrl('message.php', $prev_msg['mailbox']);
    $prev_url = Util::addParameter($prev_url, 'index', $prev_msg['index']);
}

/* Determine next message index. */
if (($next_msg = $imp_mailbox->getIMAPIndex(1))) {
    $next_url = IMP::generateSearchUrl('message.php', $next_msg['mailbox']);
    $next_url = Util::addParameter($next_url, 'index', $next_msg['index']);
}

/* Get the starting index for the current message and the message
 * count. */
$msgindex = $imp_mailbox->getMessageIndex();
$msgcount = $imp_mailbox->getMessageCount();

/* Generate the mailbox link. */
$mailbox_url = Util::addParameter(Horde::applicationUrl('mailbox.php'), 'start', $msgindex);

/* Generate the view link. */
$view_link = IMP::generateSearchUrl('view.php', $mailbox_name);
$view_link = Util::addParameter($view_link, 'index', $index);

/* Generate the Save Message link. */
$save_link_array = array_merge(array('actionID' => 'save_message', 'index' => $index), $search_params);
$save_link = Horde::downloadUrl($subject, $save_link_array);

/* Generate the Message Source link. */
if (!empty($conf['user']['allow_view_source'])) {
    $base_part = $imp_contents->getMIMEMessage();
    $source_link = $imp_contents->linkViewJS($base_part, 'view_source', _("_Message Source"), _("Message Source"), 'widget', array(), true);
}

/* Generate the link to ourselves. */
$self_link = IMP::generateSearchUrl('message.php', $mailbox_name);
$self_link = Util::addParameter($self_link, array('index' => $index, 'start' => $msgindex));

/* Generate the print link. */
$print_params = array('actionID' => 'print_message', 'index' => $index);
$print_link = Horde::applicationUrl('message.php');
$print_link = Util::addParameter($print_link, array_merge($print_params, $search_params));

/* Generate the thread view link. */
$thread_link = Util::addParameter(Horde::applicationUrl('thread.php'), array('index' => $index, 'start' => $msgindex));

$delete_warning = '';
if ($use_pop) {
    $delete_warning = "return window.confirm('" . addslashes(_("Are you sure you wish to PERMANENTLY delete these messages?")) . "');";
}

Horde::addScriptFile('popup.js', 'horde', true);
Horde::addScriptFile('keybindings.js', 'horde');
require IMP_TEMPLATES . '/common-header.inc';

/* Check for the presence of mailing list information. */
$reply_author = array();
$reply_list = null;
if (($list_exists = $imp_headers->listHeadersExist())) {
    /* See if the List-Post header provides an e-mail address for the
     * list. */
    if ($imp_headers->getValue('list-post')) {
        $reply_list = $imp_headers->parseListHeaders('list-post', true);
    }

    /* If the Reply-to: address is the same as the list address, use
     * the user's from address for the "Reply" action. */
    if (!is_null($reply_list) &&
        IMP::bareAddress($reply_list) == IMP::bareAddress(MIME::addrArray2String($imp_headers->getOb('reply_to')))) {
        $reply_author = MIME::addrArray2String($imp_headers->getOb('from'));
    }

    /* See if the mailing list information has been requested to be
     * displayed. */
    if ($list_headers || $all_headers) {
        $imp_headers->parseAllListHeaders();
    }
}

/* Build From address links. */
$imp_headers->buildAddressLinks('from', $self_link, true, !$printer_friendly);

/* Add country/flag image. Try X-Originating-IP first, then fall back
 * on the sender's domain name. */
if (!$printer_friendly) {
    $from_img = '';
    $origin_host = str_replace(array('[', ']'), '', $imp_headers->getValue('X-Originating-IP'));
    if (is_array($origin_host)) {
        $from_img = '';
        foreach ($origin_host as $host) {
            $from_img .= NLS::generateFlagImageByHost($host) . ' ';
        }
        trim($from_img);
    } elseif ($origin_host) {
        $from_img = NLS::generateFlagImageByHost($origin_host);
    }
    if (empty($from_img)) {
        $from_ob = imap_rfc822_parse_adrlist($imp_headers->getFromAddress(), '');
        $from_ob = array_shift($from_ob);
        $origin_host = $from_ob->host;
        $from_img = NLS::generateFlagImageByHost($origin_host);
    }
    if (!empty($from_img)) {
        $imp_headers->setValue('from', $imp_headers->getValue('from') . '&nbsp;' . $from_img);
    }
}

/* Build To/Cc/Bcc links. */
$address_headers = array('to' => 'toaddress', 'cc' => 'ccaddress', 'bcc' => 'bccaddress');
foreach ($address_headers as $key => $val) {
    if ($imp_headers->buildAddressLinks($key, $self_link, true, !$printer_friendly)) {
        $msgAddresses[] = $imp_headers->getOb($val);
    }
}

/* Build Reply-To address links. */
if (($reply_to = $imp_headers->buildAddressLinks('reply-to', $self_link, false, !$printer_friendly))) {
    if (!($from = $imp_headers->getValue('from')) || ($from != $reply_to)) {
        $imp_headers->setValue('Reply-to', $reply_to);
    } else {
        $imp_headers->removeHeader('reply-to');
    }
}

/* Set the status information of the message. */
$addresses = array_keys($user_identity->getAllFromAddresses(true));
$identity = null;
$status = '';
if (!$use_pop) {
    if (count($msgAddresses)) {
        $identity = $user_identity->getMatchingIdentity($msgAddresses);
        if (!is_null($identity) ||
            $user_identity->getMatchingIdentity($msgAddresses, false) !== null) {
            $status .= Horde::img('mail_personal.png', _("Personal"));
        }
        if (is_null($identity)) {
            $identity = $user_identity->getDefault();
        }
    }

    /* Set status flags. */
    $flag_array = array(
        'unseen'   => _("Unseen"),
        'answered' => _("Answered"),
        'draft'    => _("Draft"),
        'flagged'  => _("Important"),
        'deleted'  => _("Deleted")
    );
    foreach ($flag_array as $flag => $desc) {
        if ($imp_headers->getFlag($flag)) {
            $status .= Horde::img('mail_' . $flag . '.png', $desc);
        }
    }
}

/* Show the [black|white]list link if we have that functionality
 * enabled. */
$show_blacklist_link = false;
$show_whitelist_link = false;
if ($registry->hasMethod('mail/blacklistFrom')) {
    $show_blacklist_link = true;
}
if ($registry->hasMethod('mail/whitelistFrom')) {
    $show_whitelist_link = true;
}

/* Determine if we need to show the Reply to All link. */
$show_reply_all = true;
if (!MIME::addrArray2String(array_merge($imp_headers->getOb('to'), $imp_headers->getOb('cc')), $addresses)) {
    $show_reply_all = false;
}

/* Retrieve any history information for this message. */
if (!$printer_friendly && !empty($conf['maillog']['use_maillog'])) {
    require_once IMP_BASE . '/lib/Maillog.php';
    $msg_id = $imp_headers->getOb('message_id');
    IMP_Maillog::displayLog($msg_id);

    /* Do MDN processing now. */
    if ($prefs->getValue('disposition_send_mdn')) {
        /* Check to see if an MDN has been requested. */
        require_once 'Horde/MIME/MDN.php';
        $mdn = &new MIME_MDN($imp_headers_copy);
        if ($mdn->getMDNReturnAddr()) {
            /* See if we have already processed this message. */
            if (!IMP_Maillog::sentMDN($msg_id, 'displayed')) {
                $mdn_confirm = Util::getFormData('mdn_confirm');
                /* See if we need to query the user. */
                if ($mdn->userConfirmationNeeded() && !$mdn_confirm) {
                    $confirm_link = Horde::link(Util::addParameter($selfURL, 'mdn_confirm', 1)) . _("HERE") . '</a>';
                    $notification->push(sprintf(_("The sender of this message is requesting a Message Disposition Notification from you when you have read this message. Please click %s to send the notification message."), $confirm_link), 'horde.message', array('content.raw'));
                } else {
                    /* Send out the MDN now. */
                    $result = $mdn->generate(false, $mdn_confirm, 'displayed');
                    if (!is_a($result, 'PEAR_Error')) {
                        IMP_Maillog::log('mdn', $msg_id, 'displayed');
                    }
                }
            }
        }
    }
}

if (!$printer_friendly) {
    require IMP_TEMPLATES . '/menu.inc';
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

    require IMP_TEMPLATES . '/message/navbar_top.inc';
    $nav_id = 1;
    require IMP_TEMPLATES . '/message/navbar_navigate.inc';

    /* Cache the results of the navbar_actions.inc require. */
    ob_start();
    require IMP_TEMPLATES . '/message/navbar_actions.inc';
    $actions_output = ob_get_contents();
    ob_end_clean();
    echo $actions_output . '</table>';
}

/* Generate some variables needed for the templates. */
$downloadall_link = $imp_contents->getDownloadAllLink();
$atc_display = $prefs->getValue('attachment_display');
$show_parts = (!empty($attachments) && (($atc_display == 'list') || ($atc_display == 'both')));

require IMP_TEMPLATES . '/message/headers.inc';
require IMP_TEMPLATES . '/message/message.inc';

if (!$printer_friendly) {
    echo '<table width="100%" cellspacing="0">' . $actions_output;
    $nav_id = 2;
    require IMP_TEMPLATES . '/message/navbar_navigate.inc';
    echo '</table></form>';
}
if ($browser->hasFeature('javascript')) {
    require_once IMP_TEMPLATES . '/message/javascript.inc';
    require_once $registry->get('templates', 'horde') . '/contents/open_view_win.js';
    if ($printer_friendly) {
        require_once $registry->get('templates', 'horde') . '/javascript/print.js';
    }
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
