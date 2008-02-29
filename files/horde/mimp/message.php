<?php
/**
 * $Horde: mimp/message.php,v 1.62.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('MIMP_BASE', dirname(__FILE__));
require_once MIMP_BASE . '/lib/base.php';
require_once MIMP_BASE . '/lib/Mailbox.php';
require_once MIMP_BASE . '/lib/MIME/Contents.php';
require_once MIMP_BASE . '/lib/MIME/Headers.php';
require_once 'Horde/Identity.php';

/* Make sure we have a valid index. */
$mimp_mailbox = new MIMP_Mailbox(Util::getFormData('index'));
if (!$mimp_mailbox->isValidIndex()) {
    header('Location: ' . Util::addParameter(Horde::applicationUrl('mailbox.php', true), 'actionID', 'message_missing', false));
    exit;
}

/* Set the current time zone. */
NLS::setTimeZone();

/* Initialize the user's identities */
$user_identity = &Identity::singleton(array('mimp', 'mimp'));

/* Run through action handlers */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'undelete_message':
    require_once MIMP_BASE . '/lib/Message.php';
    $mimp_message = new MIMP_Message();
    $mimp_message->undelete($mimp_mailbox);
    break;

case 'delete_message':
    require_once MIMP_BASE . '/lib/Message.php';
    $mimp_message = new MIMP_Message();
    $mimp_message->delete($mimp_mailbox);
    if ($prefs->getValue('mailbox_return')) {
        header('Location: ' . Util::addParameter(Horde::applicationUrl('mailbox.php', true), 'start', $mimp_mailbox->getMessageIndex(), false));
        exit;
    }
    break;

case 'move_message':
case 'copy_message':
    if (($targetMbox = Util::getFormData('targetMbox')) !== null) {
        require_once MIMP_BASE . '/lib/Message.php';
        $mimp_message = new MIMP_Message();
        if (Util::getFormData('newMbox', 0) == 1) {
            $new_mailbox = String::convertCharset(MIMP::folderPref($targetMbox, true), NLS::getCharset(), 'UTF7-IMAP');

            require_once MIMP_BASE . '/lib/Folder.php';
            if (MIMP_Folder::create($new_mailbox,
                                    $prefs->getValue('subscribe'))) {
                $mimp_message->copy($new_mailbox, $actionID, $mimp_mailbox);
            }
        } else {
            $mimp_message->copy($targetMbox, $actionID, $mimp_mailbox);
        }
    }
    if ($prefs->getValue('mailbox_return')) {
        header('Location: ' . Util::addParameter(Horde::applicationUrl('mailbox.php', true), 'start', $mimp_mailbox->getMessageIndex(), false));
        exit;
    }
    break;
}

/* We may have done processing that has taken us past the end of the
 * message array, so we will return to mailbox.php if that is the
 * case. */
if (!$mimp_mailbox->isValidIndex()) {
    header('Location: ' . Util::addParameter(Horde::applicationUrl('mailbox.php', true), 'start', $mimp_mailbox->getMessageIndex(), false));
    exit;
}

/* Now that we are done processing the messages, get the index and
 * array index of the current message. */
$index = $mimp_mailbox->getIndex();
$array_index = $mimp_mailbox->getArrayIndex();

/* If we grab the headers before grabbing body parts, we'll see when a
   message is unread. */
$mimp_headers = new MIMP_Headers($index);
$mimp_headers->buildHeaders();
$mimp_headers->buildFlags();

/* Parse MIME info. */
if (!isset($mimp_contents)) {
    $mimp_contents = &MIMP_Contents::singleton($index);
}
if (!$mimp_contents->buildMessage()) {
    header('Location: ' . Util::addParameter(Horde::applicationUrl('mailbox.php', true), 'actionID', 'message_missing', false));
    exit;
}

/* Develop the list of Headers to display now. We will deal with the
 * 'basic' header information first since there are various
 * manipulations we do to them. */
$basic_headers = array(
    'subject'   =>  _("Subject"),
    'date'      =>  _("Date"),
    'from'      =>  _("From"),
    'to'        =>  _("To"),
    'cc'        =>  _("Cc"),
    'bcc'       =>  _("Bcc"),
    'reply-to'  =>  _("Reply-To"),
    'priority'  =>  _("Priority"),
);
$msgAddresses = '';

$mimp_headers->setValueByFunction('date', array('nl2br'));
$reply_to = $mimp_headers->getValue('reply_to');
if (!($from = $mimp_headers->getValue('from')) || ($from != $reply_to)) {
    $mimp_headers->setValue('Reply-to', $reply_to);
} else {
    $mimp_headers->removeHeader('reply-to');
}

/* Process the subject now. */
if (($subject = $mimp_headers->getValue('subject'))) {
    /* Filter the subject text, if requested. */
    $subject = MIMP::filterText($subject);

    require_once 'Horde/Text.php';
    $mimp_headers->setValue('subject', $subject);

    /* Generate the shortened subject text. */
    if (String::length($subject) > $conf['mailbox']['max_subj_chars']) {
        $subject = String::substr($subject, 0, $conf['mailbox']['max_subj_chars']) . '...';
    }
    $title = $subject;
    $shortsub = $subject;
} else {
    $shortsub = _("[No Subject]");
    $mimp_headers->addHeader('Subject', $shortsub);
    $title = $shortsub;
}

/* Check for the presence of mailing list information. */
if (($list_exists = $mimp_headers->listHeadersExist())) {
    /* See if the List-Post header provides an e-mail address for the list. */
    if ($mimp_headers->getValue('list-post')) {
        $reply_list = $mimp_headers->parseListHeaders('list-post', true);
    }
}

/* See if the 'X-Priority' header has been set. */
if (($priority = $mimp_headers->getValue('x-priority'))) {
    $mimp_headers->addHeader('Priority', $priority);
}

/* Build To/Cc/Bcc links. */
$address_headers = array('to' => 'toaddress', 'cc' => 'ccaddress', 'bcc' => 'bccaddress');
foreach ($address_headers as $key => $val) {
    $msgAddresses .= $mimp_headers->getOb($val);
}

/* Set the status information of the message. */
$status = '';
$identity = null;
$addresses = array();
if ($_SESSION['mimp']['base_protocol'] != 'pop3') {
    if (isset($msgAddresses)) {
        $addresses = $user_identity->getAllFromAddresses();

        $default = $user_identity->getDefault();
        if (isset($addresses[$default]) &&
            strstr(String::lower($msgAddresses), String::lower($addresses[$default]))) {
            $status .= '+';
            $identity = (int)$default;
        } else {
            unset($addresses[$default]);
            foreach ($addresses as $id => $address) {
                if (strstr(String::lower($msgAddresses), String::lower($address))) {
                    $status .= '+';
                    $identity = (int)$id;
                    break;
                }
            }
        }
    }

    /* Set status flags. */
    $flag_array = array('unseen'    => 'N',
                        'answered'  => 'r',
                        'draft'     => '',
                        'important' => '!',
                        'deleted'   => 'd');
    foreach ($flag_array as $flag => $val) {
        if ($mimp_headers->getFlag($flag)) {
            $status .= $val;
        }
    }
}

$messageUrl = Horde::applicationUrl('message.php');

/* Determine previous message index. */
if (($prev_msg = $mimp_mailbox->messageIndices(-1))) {
    $prev_link = Util::addParameter($messageUrl, 'index', $prev_msg['index']);
}

/* Determine next message index. */
if (($next_msg = $mimp_mailbox->messageIndices(1))) {
    $next_link = Util::addParameter($messageUrl, 'index', $next_msg['index']);
}

/* Get the starting index for the current message and the message count. */
$msgindex = $mimp_mailbox->getMessageIndex();
$msgcount = $mimp_mailbox->getMessageCount();

/* Generate the mailbox link. */
$mailbox_link = Util::addParameter(Horde::applicationUrl('mailbox.php'), 'start', $msgindex);

/* For the self URL link, we can't trust the index in the query string
 * as it may have changed if we deleted/copied/moved messages. We may
 * need other stuff in the query string, so we need to do an
 * add/remove of 'index'. */
$selfURL = Util::removeParameter(Horde::selfUrl(true), array('index', 'actionID'));
$self_link = Util::addParameter($selfURL, array('index' => $index, 'start' => $msgindex));

/* Create the body of the message. */
$msgText = $mimp_contents->getMessage();

/* Display the first 250 characters, or display the entire message? */
if ($prefs->getValue('preview_msg') && !Util::getFormData('fullmsg')) {
    $msgText = String::substr($msgText, 0, 250) . " [...]\n";
    $fullmsg_link = new Horde_Mobile_link(_("Download Full Message Text"), Util::addParameter($self_link, 'fullmsg', 1));
}

/* Create message menu. */
$menu = new Horde_Mobile_card('o', _("Menu"));
$mset = &$menu->add(new Horde_Mobile_linkset());

if ($mimp_headers->getFlag('deleted')) {
    $mset->add(new Horde_Mobile_link(_("Undelete"), Util::addParameter($self_link, 'actionID', 'undelete_message')));
} else {
    $mset->add(new Horde_Mobile_link(_("Delete"), Util::addParameter($self_link, 'actionID', 'delete_message')));
}

/* Check for the presence of mailing list information. */
$reply_author = array();
$reply_list = null;
if (($list_exists = $mimp_headers->listHeadersExist())) {
    /* See if the List-Post header provides an e-mail address for the list. */
    if ($mimp_headers->getValue('list-post')) {
        $reply_list = $mimp_headers->parseListHeaders('list-post', true);
    }

    /* If the Reply-to: address is the same as the list address, use
     * the user's from address for the "Reply" action. */
    if (!is_null($reply_list) &&
        MIMP::bareAddress($reply_list) == MIMP::bareAddress(MIME::addrArray2String($mimp_headers->getOb('reply_to')))) {
        $reply_author = MIME::addrArray2String($mimp_headers->getOb('from'));
    }
}

/* Add compose actions (Reply, Reply List, Reply All, Forward,
 * Redirect). */
$items = array(MIMP::composeLink($reply_author, array('actionID' => 'reply', 'index' => $index, 'identity' => $identity, 'array_index' => $array_index)) => _("Reply"));

if (!is_null($reply_list)) {
    $items[MIMP::composeLink($reply_list, array('actionID' => 'reply_list', 'index' => $index, 'identity' => $identity, 'array_index' => $array_index))] = _("Reply to List");
}

if (MIME::addrArray2String(array_merge($mimp_headers->getOb('to'), $mimp_headers->getOb('cc')), $addresses)) {
    $items[MIMP::composeLink(array(), array('actionID' => 'reply_all', 'index' => $index, 'identity' => $identity, 'array_index' => $array_index))] = _("Reply All");
}

$items[MIMP::composeLink(array(), array('actionID' => 'forward', 'index' => $index, 'identity' => $identity, 'array_index' => $array_index))] = _("Forward");
$items[MIMP::composeLink(array(), array('actionID' => 'redirect_compose', 'index' => $index, 'array_index' => $array_index))] = _("Redirect");

foreach ($items as $link => $label) {
    $mset->add(new Horde_Mobile_link($label, $link));
}

if (isset($next_link)) {
    $mset->add(new Horde_Mobile_link(_("Next"), $next_link));
}
if (isset($prev_link)) {
    $mset->add(new Horde_Mobile_link(_("Prev"), $prev_link));
}

$mset->add(new Horde_Mobile_link(sprintf(_("Back to %s"), MIMP::getLabel()), $mailbox_link));

MIMP::addMIMPMenu($mset);

$m->set('title', $title);

$c = &$m->add(new Horde_Mobile_card('m', $status . ' ' . $title . ' ' . sprintf(_("(%d of %d)"), $msgindex, $msgcount)));
$c->softkey('#o', _("Menu"));

$l->setMobileObject($c);
$notification->notify(array('listeners' => 'status'));

$null = null;
$hb = &$c->add(new Horde_Mobile_block($null));

foreach ($basic_headers as $head => $str) {
    if ($val = $mimp_headers->getValue($head)) {
        $hb->add(new Horde_Mobile_text($str . ': ', array('b')));
        $t = &$hb->add(new Horde_Mobile_text($val . "\n"));
        $t->set('linebreaks', true);
    }
}

$mimp_contents->getAttachments($hb);

$t = &$c->add(new Horde_Mobile_text($msgText));
$t->set('linebreaks', true);

if (isset($fullmsg_link)) {
    $c->add($fullmsg_link);
}

$m->add($menu);
$m->display();
