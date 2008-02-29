<?php
/**
 * $Horde: imp/thread.php,v 2.10.2.19 2007/03/09 00:43:51 jan Exp $
 *
 * Copyright 2004-2007 Michael M Slusarz <slusarz@mail.curecati.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/IMAP.php';
require_once IMP_BASE . '/lib/MIME/Contents.php';
require_once IMP_BASE . '/lib/MIME/Headers.php';
require_once IMP_BASE . '/lib/Mailbox.php';
require_once IMP_BASE . '/lib/Search.php';
require_once 'Horde/Template.php';

/* What mode are we in?
 * DEFAULT/'thread' - Thread mode
 * 'msgview' - Multiple message view
 */
$mode = Util::getFormData('mode', 'thread');

$imp_mailbox = &IMP_Mailbox::singleton(Util::getFormData('index'));

$error = false;
if ($mode == 'thread') {
    /* THREAD MODE: Make sure we have a valid index. */
    if (!$imp_mailbox->isValidIndex()) {
        $error = true;
    }
} else {
    /* MSGVIEW MODE: Make sure we have a valid list of messages. */
    $cacheID = Util::getFormData('msglist');
    if (!$cacheID) {
        $error = true;
    }
    require_once 'Horde/SessionObjects.php';
    $cacheSess = &Horde_SessionObjects::singleton();
    $msglist = $cacheSess->query($cacheID);
    if ($msglist) {
        $cacheSess->setPruneFlag($cacheID, true);
    } else {
        $error = true;
    }
}

if ($error) {
    $actionID = 'message_missing';
    $from_message_page = true;
    $start = null;
    require IMP_BASE . '/mailbox.php';
    exit;
}

/* Set the current time zone. */
NLS::setTimeZone();

$imp_imap = &IMP_IMAP::singleton();

/* Run through action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'add_address':
    $contact_link = IMP::addAddress(Util::getFormData('address'), Util::getFormData('name'));
    if (is_a($contact_link, 'PEAR_Error')) {
        $notification->push($contact_link);
    } else {
        $notification->push(sprintf(_("Entry \"%s\" was successfully added to the address book"), $contact_link), 'horde.success', array('content.raw'));
    }
    break;
}

$msgs = $tree = array();
$rowct = 0;

$subject = '';
$page_label = IMP::getLabel();

require_once 'Horde/Identity.php';
$identity = &Identity::singleton(array('imp', 'imp'));

if ($mode == 'thread') {
    $threadob = $imp_mailbox->getThreadOb();
    $index_array = $imp_mailbox->getIMAPIndex();
    $thread = $threadob->getThread($index_array['index']);
    $threadtree = $threadob->getThreadImageTree($thread, false);
    $loop_array = array($imp['mailbox'] => $thread);
} else {
    $loop_array = IMP::parseIndicesList($msglist);
}

foreach ($loop_array as $mbox => $idxlist) {
    $imp_imap->changeMbox($mbox);

    foreach ($idxlist as $idx) {
        /* Get the body of the message. */
        $curr_msg = $curr_tree = array();
        $imp_mailbox->setNewIndex($idx);
        $contents = &IMP_Contents::singleton($idx . IMP_IDX_SEP . $mbox);
        $mime_id = $contents->findBody();
        $mime_part = $contents->getDecodedMIMEPart($mime_id);
        if ($contents->canDisplayInline($mime_part)) {
            $curr_msg['body'] = $contents->renderMIMEPart($mime_part);
        } else {
            $curr_msg['body'] = '<em>' . _("There is no text that can be displayed inline.") . '</em>';
        }
        $curr_msg['idx'] = $idx;

        /* Get headers for the message. */
        $headers = &new IMP_Headers($idx);
        $headers->buildHeaders();
        $headers->setValueByFunction('date', array('nl2br', array($headers, 'addLocalTime'), 'htmlspecialchars'));
        $curr_msg['date'] = $headers->getValue('date');

        $addr_type = (($GLOBALS['imp']['mailbox'] == IMP::folderPref($GLOBALS['prefs']->getValue('drafts_folder'), true)) || (in_array($GLOBALS['imp']['mailbox'], $identity->getAllSentmailFolders()))) ? 'to' : 'from';
        $addr = $headers->getValue($addr_type);
        $curr_msg['addr_to'] = ($addr_type == 'to');
        if ($curr_msg['addr_to']) {
            $addr = _("To:") . ' ' . $addr;
        }
        $headers->buildAddressLinks($addr_type, Horde::selfUrl(true), true, true);
        $curr_msg['addr'] = $headers->getValue($addr_type);;

        $subject_header = @htmlspecialchars($headers->getValue('subject'), ENT_COMPAT, NLS::getCharset());
        if ($mode == 'thread') {
            if (empty($subject)) {
                $subject = preg_replace('/^re:\s*/i', '', $subject_header);
            }
        }
        $curr_msg['subject'] = $subject_header;

        /* Create links to current message and mailbox. */
        if ($mode == 'thread') {
            $curr_msg['link'] = Horde::widget('#display', _("Back to Thread Display"), 'widget', '', '', _("Back to Thread Display"), true);
        } else {
            $curr_msg['link'] = Horde::widget('#display', _("Back to Multiple Message View Index"), 'widget', '', '', _("Back to Multiple Message View Index"), true);
        }
        $curr_msg['link'] .= ' | ' . Horde::widget(Util::addParameter(IMP::generateSearchUrl('message.php', $mbox), array('index' => $idx)), _("Go to Message"), 'widget', '', '', _("Go to Message"), true);
        $curr_msg['link'] .= ' | ' . Horde::widget(Util::addParameter(IMP::generateSearchUrl('mailbox.php', $mbox), array('start' => $imp_mailbox->getMessageIndex())), sprintf(_("Back to %s"), $page_label), 'widget', '', '', sprintf(_("Back to %s"), $page_label), true);

        $curr_tree['class'] = (++$rowct % 2) ? 'text' : 'item0';
        $curr_tree['subject'] = (($mode == 'thread') ? $threadtree[$idx] : null) . ' ' . Horde::link('#' . $idx) . $subject_header . '</a> (' . htmlspecialchars($addr) . ')';

        $msgs[] = $curr_msg;
        $tree[] = $curr_tree;
    }
}

/* Flag messages as seen. */
require_once IMP_BASE . '/lib/Message.php';
$imp_message = &IMP_Message::singleton();
$imp_message->flag('\\SEEN', $loop_array);

$template = &new Horde_Template();
$template->set('subject', ($mode == 'thread') ? @htmlspecialchars($subject, ENT_COMPAT, NLS::getCharset()) : sprintf(_("%d Messages"), count($msgs)));
$template->set('thread', ($mode == 'thread'), true);
$template->set('messages', $msgs, true);
$template->set('tree', $tree, true);

/* Output page. */
$title = ($mode == 'thread') ? _("Thread View") : _("Multiple Message View");
require IMP_TEMPLATES . '/common-header.inc';
require IMP_TEMPLATES . '/menu.inc';
IMP::status();
echo $template->fetch(IMP_TEMPLATES . '/thread/thread.html');
require $registry->get('templates', 'horde') . '/common-footer.inc';
