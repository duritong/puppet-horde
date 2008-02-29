<?php
/**
 * $Horde: mimp/mailbox.php,v 1.57.2.2 2007/01/02 13:55:08 jan Exp $
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
require_once 'Horde/MIME.php';
require_once 'Horde/Identity.php';

/* Get form data and make sure it's the type that we're expecting. */
$targetMbox = Util::getFormData('targetMbox');
$newMbox = Util::getFormData('newMbox');
if (!is_array(($indices = Util::getFormData('indices')))) {
    $indices = array($indices);
}

/* Set the current time zone. */
NLS::setTimeZone();

/* Initialize the user's identities. */
$identity = &Identity::singleton(array('mimp', 'mimp'));

/* Get the base URL for this page. */
$mailbox_url = Horde::selfUrl();

/* Run through the action handlers */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'message_missing':
    $notification->push(_("There was an error viewing the requested message."), 'horde.error');
    break;

case 'expunge_mailbox':
    require_once MIMP_BASE . '/lib/Message.php';
    $mimp_message = new MIMP_Message();
    $mimp_message->expungeMailbox();
    break;
}

/* Get the start index. */
$start = Util::getFormData('start');

/* Build the list of messages in the mailbox. */
$mimp_mailbox = new MIMP_Mailbox();
$pageOb = $mimp_mailbox->buildMailboxPage(Util::getFormData('page'), $start);

/* Generate First/Previous page links. */
$pages_first = $pages_prev = null;
if ($pageOb->page != 1) {
    $prev = $pageOb->page - 1;
    $first_url = Util::addParameter($mailbox_url, 'page', 1);
    $prev_url  = Util::addParameter($mailbox_url, 'page', $prev);
    $pages_first = new Horde_Mobile_link(_("First Page"), $first_url);
    $pages_prev = new Horde_Mobile_link(_("Previous Page"), $prev_url);
}

/* Generate Next/Last page links. */
$pages_last = $pages_next = null;
if ($pageOb->page != $pageOb->pagecount) {
    $next = $pageOb->page + 1;
    $next_url = Util::addParameter($mailbox_url, 'page', $next);
    $last_url = Util::addParameter($mailbox_url, 'page', $pageOb->pagecount);
    $pages_next = new Horde_Mobile_link(_("Next Page"), $next_url);
    $pages_last = new Horde_Mobile_link(_("Last Page"), $last_url);
}

/* Generate mailbox summary string. */
if (!empty($pageOb->end)) {
    $msg_count = sprintf(_("%d to %d of %d"), $pageOb->begin, $pageOb->end, $pageOb->msgcount);
} else {
    $msg_count = sprintf(_("No Messages"));
}

/* Build the array of message information. */
$mailboxOverview = $mimp_mailbox->buildMailboxArray($pageOb->begin, $pageOb->index, $pageOb->end);

/* Are we currently in the trash mailbox? */
$trashMbox = ($prefs->getValue('use_trash') && ($_SESSION['mimp']['mailbox'] == MIMP::folderPref($prefs->getValue('trash_folder'), true)));

/* Display message information. */
$curr_time = time();
$curr_time -= $curr_time % 60;
$lastMbox = '';
$msgs = array();
foreach ($mailboxOverview as $msgIndex => $message) {
    /* Initialize the header fields. */
    $msg = array();
    $msg['from'] = '&nbsp;';
    $msg['fullfrom'] = '';
    $msg['to'] = '';
    $msg['subject'] = _("[No Subject]");

    /* Now pull the IMAP header values into them, decoding them at the
       same time. */
    $h = $message['header'];

    /* Format the from header. */
    if (isset($h->from)) {
        $tmp = imap_rfc822_parse_adrlist($h->from, '');
        $tmp = array_shift($tmp);
        $from_adr = MIMP::bareAddress($h->from);

        if (isset($tmp->personal)) {
            $msg['fullfrom'] = $msg['from'] = trim(MIME::decode($tmp->personal, NLS::getCharset()), '"');
        } else {
            $msg['from'] = $from_adr;
        }
        $msg['fullfrom'] .= $from_adr;
    }

    if ($identity->hasAddress($msg['fullfrom'])) {
        if (isset($h->to)) {
            $tmp = imap_rfc822_parse_adrlist($h->to, '');
            $tmp = array_shift($tmp);
            if (isset($tmp->personal)) {
                $msg['to'] = MIME::decode($tmp->personal, NLS::getCharset());
            } else {
                $msg['to'] = MIMP::bareAddress($h->to);
            }
        } else {
            $msg['to'] = _("Undisclosed Recipients");
        }
        $msg['from'] = _("To") . ': ' . $msg['to'];
    }

    if (String::length($msg['from']) > $conf['mailbox']['max_from_chars']) {
        $msg['from'] = String::substr($msg['from'], 0, $conf['mailbox']['max_from_chars']) . '...';
    }

    if (!empty($h->subject)) {
        $msg['subject'] = MIME::decode($h->subject, NLS::getCharset());
    }

    if (($prefs->getValue('sortby') != SORTTHREAD)) {
        if (String::length($msg['subject']) > $conf['mailbox']['max_subj_chars']) {
            $msg['subject'] = String::substr($msg['subject'], 0, $conf['mailbox']['max_subj_chars']) . '...';
        }
    } else {
        $maxlen = $conf['mailbox']['max_subj_chars'] - 3 * $mimp_mailbox->getThreadIndent($h->uid);
        if ($maxlen < 5) {
            $maxlen = 5;
        }
        if (String::length($msg['subject']) > $maxlen) {
            $msg['subject'] = String::substr($msg['subject'], 0, $maxlen) . '...';
        }
    }

    /* Filter the subject text, if requested. */
    $msg['subject'] = MIMP::filterText($msg['subject']);

    /* Generate the target link. */
    $target = Util::addParameter(Horde::applicationUrl('message.php'), 'index', $h->uid);

    /* Get flag information. */
    $status = '';
    if ($_SESSION['mimp']['base_protocol'] != 'pop3') {
        if (!empty($h->to) && $identity->hasAddress($h->to)) {
            $status .= '+';
        }
        if (!$h->seen) {
            $status .= 'N';
        }
        if ($h->answered) {
            $status .= 'r';
        }
        if ($h->draft) {
            $target = MIMP::composeLink(array(), array('actionID' => 'draft', 'mailbox' => $_SESSION['mimp']['mailbox'], 'index' => $h->uid, 'bodypart' => 1));
        }
        if ($h->flagged) {
            $status .= 'I';
        }
        if ($h->deleted) {
            $status .= 'D';
        }
    }

    require_once 'Horde/Text.php';

    /* Set attributes. */
    $msg['number'] = $h->msgno;
    $msg['target'] = $target;
    $msg['status'] = $status;

    $msgs[] = $msg;
}

/* Create mailbox menu. */
$menu = new Horde_Mobile_card('o', _("Menu"));
$mset = &$menu->add(new Horde_Mobile_linkset());

$mailbox = Util::addParameter(Horde::applicationUrl('mailbox.php'), 'page', $pageOb->page);
$items = array($mailbox => _("Refresh"));

/* Determine if we are going to show the Hide/Purge Deleted Message links. */
if ($mimp_mailbox->showDeleteLinks()) {
    $items[Util::addParameter($mailbox, 'actionID', 'expunge_mailbox')] = _("Purge Deleted");
}

foreach ($items as $link => $label) {
    $mset->add(new Horde_Mobile_link($label, $link));
}

$nav = array('pages_first', 'pages_prev', 'pages_next', 'pages_last');
foreach ($nav as $n) {
    if (Util::nonInputVar($n)) {
        $mset->add($$n);
    }
}

MIMP::addMIMPMenu($mset);

$title = MIMP::getLabel();
$m->set('title', $title);
require MIMP_TEMPLATES . '/mailbox/mailbox.inc';
