<?php
/**
 * $Horde: mimp/compose.php,v 1.75.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 2002-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

/**
 * Expand addresses.
 */
function _expandAddresses($header)
{
    $addresses = Util::getFormData($header);
    $result = MIMP::expandAddresses($addresses, true);

    if (is_array($result)) {
        $GLOBALS['notification']->push(_("More than one match found."), 'horde.message');
        return $addresses;
    } elseif (is_a($result, 'PEAR_Error')) {
        $error = $result;
        $result = array();
        $list = $error->getUserInfo();
        if (is_array($list)) {
            foreach ($list as $entry) {
                if (is_object($entry)) {
                    $result[] = $entry->getUserInfo();
                } else {
                    $result[] = $entry;
                }
            }
        }
        $GLOBALS['notification']->push($error->getMessage(), 'horde.message');
    }

    return $result;
}

/**
 * Checks for non-standard address formats, such as separating with
 * spaces or semicolons.
 */
function _formatAddr($addr)
{
    /* If there are angle brackets (<>), or a colon (group name
       delimiter), assume the user knew what they were doing. */
    if (!empty($addr) &&
        (strpos($addr, '>') === false) &&
        (strpos($addr, ':') === false)) {
        $addr = trim(strtr($addr, ';,', '  '));
        $addr = preg_replace('|\s+|', ', ', $addr);
    }

    return $addr;
}

@define('MIMP_BASE', dirname(__FILE__));
@define('VFS_ATTACH_PATH', '.horde/mimp/compose');
require_once MIMP_BASE . '/lib/base.php';
require_once MIMP_BASE . '/lib/Compose.php';
require_once MIMP_BASE . '/lib/MIME/Contents.php';
require_once MIMP_BASE . '/lib/MIME/Headers.php';
require_once 'Horde/Identity.php';
require_once 'Horde/MIME/Part.php';
require_once 'Horde/Text.php';

/* The message text. */
$msg = '';

/* The headers of the message. */
$header = array();
$header['to'] = '';
$header['cc'] = '';
$header['bcc'] = '';
$header['subject'] = '';
$header['in_reply_to'] = Util::getFormData('in_reply_to');
$header['references'] = Util::getFormData('references');

$identity = &Identity::singleton(array('mimp', 'mimp'));
$sent_mail_folder = $identity->getValue(Util::getFormData('identity'), Util::getFormData('sent_mail_folder'));

$actionID = Util::getFormData('actionID');
switch ($actionID) {
case _("Expand Names"):
    $actionID = 'expand_addresses';
    break;

case _("Redirect"):
    $actionID = 'redirect_message';
    break;

case _("Send"):
    $actionID = 'send_message';
    break;
}

if (($index = Util::getFormData('index'))) {
    $mimp_contents = &MIMP_Contents::singleton($index);
    $mimp_headers = &new MIMP_Headers($index);
}

/* Set the current time zone. */
NLS::setTimeZone();

/* Initialize the MIMP_Compose:: object. */
$oldMessageCacheID = Util::getFormData('messageCache');
$mimp_compose = &new MIMP_Compose(array('cacheID' => $oldMessageCacheID));

/* Run through the action handlers. */
switch ($actionID) {
case 'draft':
    if (empty($index)) {
        break;
    }

    if (($fromaddr = $mimp_headers->getOb('fromaddress'))) {
        $from_list = $identity->getAllFromAddresses();
        $default = $identity->getDefault();
        if (isset($from_list[$default]) &&
            strstr(String::lower($fromaddr), String::lower($from_list[$default]))) {
            $_GET['identity'] = $default;
        } else {
            unset($from_list[$default]);
            foreach ($from_list as $id => $from_item) {
                if (strstr(String::lower($fromaddr), String::lower($from_item))) {
                    $_GET['identity'] = $id;
                    break;
                }
            }
        }
        $sent_mail_folder = $identity->getValue(Util::getFormData('identity'));
    }
    $header['to'] = MIME::addrArray2String($mimp_headers->getOb('to'));
    $header['cc'] = MIME::addrArray2String($mimp_headers->getOb('cc'));
    $header['bcc'] = MIME::addrArray2String($mimp_headers->getOb('bcc'));
    $header['subject'] = $mimp_headers->getOb('subject', true);

    $mime_message = $mimp_contents->rebuildMessage();
    $res = $mimp_compose->attachFilesFromMessage($mime_message);
    if (!empty($res)) {
        foreach ($res as $val) {
            $notification->push($val, 'horde.error');
        }
    }
    $body_id = $mimp_compose->getBodyId($mimp_contents);
    $body_part = $mime_message->getPart($body_id);
    $msg = "\n" . String::convertCharset($mimp_compose->findBody($mimp_contents), $body_part->getCharset());
    break;

case 'expand_addresses':
    $header['to'] = _expandAddresses('to');
    $header['cc'] = _expandAddresses('cc');
    $header['bcc'] = _expandAddresses('bcc');
    if (($action = Util::getFormData('action')) !== null) {
        $actionID = $action;
    }
    break;

case 'reply':
case 'reply_list':
case 'reply_all':
    if (!empty($index)) {
        /* Set the message_id and references headers. */
        if (($msg_id = $mimp_headers->getOb('message_id'))) {
            $header['in_reply_to'] = chop($msg_id);
            if (($header['references'] = $mimp_headers->getOb('references'))) {
                $header['references'] .= ' ' . $header['in_reply_to'];
            } else {
                $header['references'] = $header['in_reply_to'];
            }
        }

        if ($actionID == 'reply') {
            ($header['to'] = MIME::addrArray2String($mimp_headers->getOb('reply_to'))) ||
            ($header['to'] = MIME::addrArray2String($mimp_headers->getOb('from')));
        } elseif ($actionID == 'reply_all') {
            /* Filter out our own address from the addresses we reply to. */
            $me = $identity->getAllFromAddresses();

            /* Build the To: header. */
            $from_arr = $mimp_headers->getOb('from');
            $to_arr = $mimp_headers->getOb('reply_to');
            $reply = '';
            if (!empty($to_arr)) {
                $reply = MIME::addrArray2String($to_arr);
            } elseif (!empty($from_arr)) {
                $reply = MIME::addrArray2String($from_arr);
            }
            $header['to'] = MIME::addrArray2String(array_merge($to_arr, $from_arr));

            /* Build the Cc: header. */
            $cc_arr = $mimp_headers->getOb('to');
            if (!empty($cc_arr) &&
                ($reply != MIME::addrArray2String($cc_arr))) {
                $cc_arr = array_merge($cc_arr, $mimp_headers->getOb('cc'));
            } else {
                $cc_arr = $mimp_headers->getOb('cc');
            }

            if ($conf['compose']['allow_cc']) {
                $header['cc'] = MIME::addrArray2String($cc_arr, array_merge($me, array(MIMP::bareAddress($header['to']))));
            } else {
                $cc = MIME::addrArray2String($cc_arr, array_merge($me, array(MIMP::bareAddress($header['to']))));
                if (!empty($cc)) {
                    if (!empty($header['to'])) {
                        $header['to'] .= ', ';
                    }
                    $header['to'] .= $cc;
                }
            }

            /* Build the Bcc: header. */
            $header['bcc'] = MIME::addrArray2String($mimp_headers->getOb('bcc'), $me);
        } elseif ($actionID == 'reply_list') {
            $header['to'] = Util::getFormData('to');
        }

        $header['subject'] = $mimp_headers->getOb('subject', true);
        if (!empty($header['subject'])) {
            if (String::lower(substr($header['subject'], 0, 3)) != 're:') {
                $header['subject'] = 'Re: ' . $header['subject'];
            }
        } else {
            $header['subject'] = 'Re: ';
        }
    }
    break;

case 'forward':
    if (!empty($index)) {
        $header['subject'] = $mimp_headers->getOb('subject', true);
        if (!empty($header['subject'])) {
            /* If the subject line already has signals indicating this message
               is a forward, do not add an additional signal. */
            $fwd_signal = false;
            foreach (array('fwd:', 'fw:', '(fwd)', '[fwd]') as $signal) {
                if (stristr($header['subject'], $signal) !== false) {
                    $fwd_signal = true;
                    break;
                }
            }
            if (!$fwd_signal) {
                $header['subject'] = _("Fwd:") . ' ' . $header['subject'];
            }
        } else {
            $header['subject'] = _("Fwd:");
        }
    }
    break;

case 'redirect_message':
    if (!empty($index) && ($f_to = Util::getFormData('to'))) {
        $recipients = $f_to = _formatAddr($f_to);

        $mimp_headers->buildHeaders();
        $mimp_headers->addResentHeaders($identity->getFromAddress(Util::getFormData('identity')), $f_to);

        $bodytext = $mimp_contents->getBody();
        $status = $mimp_compose->sendMessage($recipients, $mimp_headers, $bodytext, NLS::getEmailCharset());
        if (!is_a($status, 'PEAR_Error')) {
            $entry = sprintf("%s Redirected message sent to %s from %s",
                             $_SERVER['REMOTE_ADDR'], $recipients, $_SESSION['mimp']['user']);
            Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_INFO);

            if ($prefs->getValue('compose_confirm')) {
                $notification->push(_("Message bounced successfully."), 'horde.success');
            }
            require MIMP_BASE . '/mailbox.php';
            exit;
        } else {
            Horde::logMessage($status->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
        }
        $actionID = 'redirect_compose';
        $notification->push(_("Redirecting failed."), 'horde.error');
    }
    break;

case 'send_message':
    $browser_charset = NLS::getCharset();
    $sending_charset = NLS::getEmailCharset();
    $message = Util::getFormData('message', '');

    switch (Util::getFormData('ctype')) {
    case 'reply':
        $qfrom = MIME::addrArray2String($mimp_headers->getOb('from'));
        if (empty($qfrom)) {
            $qfrom = '<>';
        }

        $mime_message = $mimp_contents->getMIMEMessage();
        $message .= $mimp_compose->replyMessage($mimp_contents, $qfrom, $mimp_headers);
        break;

    case 'forward':
        /* We need to make sure that all the parts have their contents
           stored within them. */
        $mime_message = $mimp_contents->rebuildMessage();
        $res = $mimp_compose->attachFilesFromMessage($mime_message);
        if (!empty($res)) {
            foreach ($res as $val) {
                $notification->push($val, 'horde.error');
            }
        }
        $message .= $mimp_compose->forwardMessage($mimp_contents, $mimp_headers);
        break;
    }

    $message = String::convertCharset($message, $browser_charset, $sending_charset);

    $sig = $identity->getSignature(Util::getFormData('identity'));
    if (!empty($sig)) {
        $message .= "\n" . $sig;
    }

    $f_to = Util::getFormData('to');
    $f_cc = $f_bcc = null;
    if ($conf['compose']['allow_cc']) {
        $f_cc = Util::getFormData('cc');
    }
    if ($conf['compose']['allow_bcc']) {
        $f_bcc = Util::getFormData('bcc');
    }

    if (empty($f_to) && empty($f_cc) && empty($f_bcc)) {
        $notification->push(_("You must have at least one recipient."), 'horde.error');
        break;
    } else {
        require_once 'Horde/MIME/Message.php';
        $mime = &new MIME_Message($_SESSION['mimp']['maildomain']);

        /* Get trailer message (if any). */
        if ($conf['msg']['append_trailer'] &&
            is_readable(MIMP_BASE . '/config/trailer.txt')) {
            require_once 'Horde/Text/Filter.php';
            $trailer = Text_Filter::filter("\n" . file_get_contents(MIMP_BASE . '/config/trailer.txt'), 'environment');
            /* If there is a user defined function, call it with the current
               trailer as an argument. */
            if (!empty($conf['hooks']['trailer'])) {
                require_once HORDE_BASE . '/config/hooks.php';
                if (function_exists('_mimp_hook_trailer')) {
                    $trailer = call_user_func('_mimp_hook_trailer', $trailer);
                }
            }
        }

        /* Set up the body part now. */
        $textBody = &new MIME_Part('text/plain');
        $textBody->setContents($textBody->replaceEOL($message));
        $textBody->setCharset($sending_charset);
        if (isset($trailer)) {
            $textBody->appendContents($trailer);
        }

        /* We need to get the from address now because it is used in some
           of the PGP code. */
        $recipients = '';
        $from = $identity->getFromLine(Util::getFormData('identity'), Util::getFormData('from'));
        $barefrom = MIMP::bareAddress($from);

        $mime->addPart($textBody);

        /* Add attachments now. */
        $mimp_compose->buildAllAttachments($mime);

        /* Initalize a header object for the outgoing message. */
        $msg_headers = &new MIMP_Headers();

        /* Add a Received header for the hop from browser to server. */
        $msg_headers->addReceivedHeader();
        $msg_headers->addMessageIdHeader();

        $msg_headers->addHeader('Date', date('r'));
        $msg_headers->addHeader('From', String::convertCharset($from, $browser_charset, $sending_charset));

        $identity->setDefault(Util::getFormData('identity'));
        $replyto = $identity->getValue('replyto_addr');
        if (!empty($replyto) && ($replyto != $barefrom)) {
            $msg_headers->addHeader('Reply-to', String::convertCharset($replyto, $browser_charset, $sending_charset));
        }
        if (!empty($f_to)) {
            $f_to = $recipients = _formatAddr($f_to);
            $msg_headers->addHeader('To', String::convertCharset($f_to, $browser_charset, $sending_charset));
        }
        if ($conf['compose']['allow_cc'] && !empty($f_cc)) {
            $f_cc = _formatAddr($f_cc);
            $msg_headers->addHeader('Cc', String::convertCharset($f_cc, $browser_charset, $sending_charset));
            $recipients = (empty($recipients)) ? $f_cc : "$recipients, " . $f_cc;
        }
        if ($conf['compose']['allow_bcc'] && !empty($f_bcc)) {
            $f_bcc = _formatAddr($f_bcc);
            if (empty($recipients)) {
                $msg_headers->addHeader('To', 'undisclosed-recipients:;');
                $recipients = $f_bcc;
            } else {
                $recipients = $recipients . ', ' . $f_bcc;
            }
        }
        $header['subject'] = Util::getFormData('subject');
        if (!empty($header['subject'])) {
            $msg_headers->addHeader('Subject', String::convertCharset($header['subject'], $browser_charset, $sending_charset));
        }
        if ($ref = Util::getFormData('references')) {
            $msg_headers->addHeader('References', implode("\n\t", explode(' ', trim($ref))));
        }
        if ($irt = Util::getFormData('in_reply_to')) {
            $msg_headers->addHeader('In-Reply-To', $irt);
        }
        $msg_headers->addMIMEHeaders($mime);

        $status = $mimp_compose->sendMessage($recipients, $msg_headers, $mime, $sending_charset);
        $sent_saved = true;
        if (!is_a($status, 'PEAR_Error')) {
            if (Util::getFormData('is_reply') && $index) {
                imap_setflag_full($_SESSION['mimp']['stream'], $index, '\\ANSWERED', SE_UID);
            }

            $entry = sprintf("%s Message sent to %s from %s",
                             $_SERVER['REMOTE_ADDR'], $recipients, $_SESSION['mimp']['user']);
            Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_INFO);

            /* Delete the attachment data. */
            $mimp_compose->deleteAllAttachments();

            /* Should we save this message in the sent mail folder? */
            if (!empty($sent_mail_folder) &&
                 $prefs->getValue('save_sent_mail')) {
                /* Keep Bcc: headers on saved messages. */
                if (!empty($f_bcc)) {
                    $msg_headers->addHeader('Bcc', $f_bcc);
                }

                /* Loop through the envelope and add headers. */
                $headerArray = $mime->encode($msg_headers->toArray(), $browser_charset);
                foreach ($headerArray as $key => $value) {
                    $msg_headers->addHeader($key, $value);
                }
                $fcc = $msg_headers->toString();
                $fcc .= $mime->toString();

                // Make absolutely sure there are no bare newlines.
                $fcc = preg_replace("|([^\r])\n|", "\\1\r\n", $fcc);
                $fcc = str_replace("\n\n", "\n\r\n", $fcc);

                require_once MIMP_BASE . '/lib/Folder.php';
                $mimp_folder = &MIMP_Folder::singleton();
                if (!$mimp_folder->exists($sent_mail_folder)) {
                    $mimp_folder->create($sent_mail_folder, $prefs->getValue('subscribe'));
                }
                if (!@imap_append($_SESSION['mimp']['stream'], MIMP::serverString($sent_mail_folder), $fcc, '\\Seen')) {
                    $notification->push(sprintf(_("Message sent successfully, but not saved to %s"), MIMP::displayFolder($sent_mail_folder)));
                    $sent_saved = false;
                }
            }

            if ($sent_saved) {
                if ($prefs->getValue('compose_confirm')) {
                    $notification->push(_("Message sent successfully."), 'horde.success');
                }
                require MIMP_BASE . '/mailbox.php';
                exit;
            }
        } else {
            Horde::logMessage($status, __FILE__, __LINE__, PEAR_LOG_ERR);
            $notification->push(sprintf(_("There was an error sending your message: %s"), $status->getMessage()), 'horde.error');
        }
    }
    unset($msg);
    break;
}

/* Store the message cache, if there is anything in it. */
$messageCacheID = $mimp_compose->getMessageCacheId();

$title = _("Message Composition");
$m->set('title', $title);

$select_list = $identity->getSelectList();

if (Util::getFormData('from') || $prefs->isLocked('default_identity')) {
    $from = $identity->getFromLine(Util::getFormData('identity'), Util::getFormData('from'));
} else {
    $from_selected = Util::getFormData('identity');
    if (isset($from_selected)) {
        $identity->setDefault($from_selected);
    }
}

/* Grab any data that we were supplied with. */
if (empty($msg)) {
    $msg = Util::getFormData('message', '');
}
foreach (array('to', 'cc', 'bcc', 'subject') as $val) {
    if (empty($header[$val])) {
        $header[$val] = Util::getFormData($val);
    }
}

$menu = &new Horde_Mobile_card('o', _("Menu"));
$mset = &$menu->add(new Horde_Mobile_linkset());
MIMP::addMIMPMenu($mset);

if (isset($actionID) && ($actionID == 'redirect_compose')) {
    $mailbox = $_SESSION['mimp']['mailbox'];
    require MIMP_TEMPLATES . '/compose/redirect.inc';
} else {
    $tabindex = 1;
    require MIMP_TEMPLATES . '/compose/compose.inc';
}
