<?php
/**
 * $Horde: imp/compose.php,v 2.800.2.79 2007/04/10 19:40:27 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

/**
 * Determines the actionID.
 */
function _getActionID()
{
    if (!($aid = Util::getFormData('actionID'))) {
        foreach (array('send_message', 'save_draft', 'cancel_compose', 'add_attachment') as $val) {
            if (Util::getFormData('btn_' . $val)) {
                $aid = $val;
                break;
            }
        }
    }

    /* Alter the 'Send Message' action if the "Spell check before sending
     * message?" preference has been enabled. */
    if (($aid == 'send_message') &&
        (Util::getFormData('done_action') != 'send') &&
        !empty($GLOBALS['conf']['utils']['spellchecker']) &&
        $GLOBALS['prefs']->getValue('compose_spellcheck')) {
        return 'spell_check_send';
    } else {
        return $aid;
    }
}

/**
 * Return an url for mailbox.php depending on what kind of start/page
 * information we have.
 *
 * @param boolean $encode  Whether or not to encode entities in the URL.
 * @param string $url      The base URL.
 */
function _mailboxReturnURL($encode, $url = null)
{
    if (empty($url)) {
        $url = Horde::applicationUrl('mailbox.php');
    }

    foreach (array('start', 'page') as $key) {
        if (($param = Util::getFormData($key))) {
            $url = Util::addParameter($url, $key, $param, $encode);
        }
    }

    return $url;
}

/**
 * Returns a To:, Cc: or Bcc: list build from a selection based on 'expand
 * names'.
 */
function _getAddressList($field, $expand = false)
{
    $to = trim(Util::getFormData($field));
    if (!empty($to)) {
        $to = implode(', ', _cleanAddrList(array($to), false));
        return $expand ? $to : _formatAddr($to);
    }

    $to_list = Util::getFormData($field . '_list');
    $to = Util::getFormData($field . '_field');

    $tmp = array();
    if (is_array($to)) {
        foreach ($to as $key => $address) {
            $tmp[$key] = $address;
        }
    }
    if (is_array($to_list)) {
        foreach ($to_list as $key => $address) {
            if ($address != '') {
                $tmp[$key] = $address;
            }
        }
    }

    $to_new = trim(Util::getFormData($field . '_new'));
    if (!empty($to_new)) {
        $tmp[] = $to_new;
    }
    return implode(', ', $tmp);
}

/**
 * Expand addresses in a header.
 */
function _expandAddresses($header)
{
    $result = IMP::expandAddresses(_getAddressList($header, true), true);

    if (is_array($result)) {
        $GLOBALS['notification']->push(_("Please resolve ambiguous or invalid addresses."), 'horde.warning');
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
        $GLOBALS['notification']->push($error, 'horde.warning');
    }

    return $result;
}

/**
 * Checks for non-standard address formats, such as separating with spaces
 * or semicolons, and return a "cleaned address string".
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

/**
 * Generate a recipient list.
 */
function _recipientList($addr)
{
    $addrlist = _cleanAddrList($addr, true);

    if (empty($addrlist)) {
        return PEAR::raiseError(_("You must enter at least one recipient."));
    }

    foreach (MIME::bareAddress(implode(', ', $addrlist), $GLOBALS['imp']['maildomain'], true) as $val) {
        if (MIME::is8bit($val)) {
            return PEAR::raiseError(sprintf(_("Invalid character in e-mail address: %s."), $val));
        }
    }

    return $addrlist;
}

/**
 * Returns the charset to use for outgoing messages based on (by replying
 * to or forwarding) the given MIME message and the user's default settings
 * and any previously selected charset.
 */
function _getEncoding($mime_message = null)
{
    if ($GLOBALS['charset']) {
        return $GLOBALS['charset'];
    }

    $encoding = NLS::getEmailCharset();

    if (isset($mime_message)) {
        $mime_message = &Util::cloneObject($mime_message);
        $mime_part = $mime_message->getBasePart();
        if ($mime_part->getPrimaryType() == MIME::type(TYPEMULTIPART)) {
            foreach ($mime_part->getParts() as $part) {
                if ($part->getPrimaryType() == MIME::type(TYPETEXT)) {
                    $mime_part = $part;
                    break;
                }
            }
        }
        if (NLS::getCharset() == 'UTF-8') {
            $charset_upper = String::upper($mime_part->getCharset());
            if (($charset_upper != 'US-ASCII') &&
                ($charset_upper != String::upper($encoding))) {
                $encoding = 'UTF-8';
            }
        }
    }

    return $encoding;
}

/**
 * Setup the base message MIME_Part object.
 */
function _baseMessage(&$imp_compose, $charset, $final_msg = true)
{
    $message = String::convertCharset(Util::getFormData('message', ''), NLS::getCharset(), $charset);

    if ($GLOBALS['rtemode']) {
        $message_html = $message;
        require_once 'Horde/Text/Filter.php';
        $message = Text_Filter::filter($message, 'html2text', array('wrap' => false));
    }

    /* Get trailer message (if any). */
    $trailer = null;
    if ($final_msg &&
        $GLOBALS['conf']['msg']['append_trailer'] &&
        @is_readable(IMP_BASE . '/config/trailer.txt')) {
        require_once 'Horde/Text/Filter.php';
        $trailer = Text_Filter::filter("\n" . file_get_contents(IMP_BASE . '/config/trailer.txt'), 'environment');
        /* If there is a user defined function, call it with the current
           trailer as an argument. */
        if (!empty($GLOBALS['conf']['hooks']['trailer'])) {
            require_once HORDE_BASE . '/config/hooks.php';
            if (function_exists('_imp_hook_trailer')) {
                $trailer = call_user_func('_imp_hook_trailer', $trailer);
            }
        }
    }

    /* Set up the body part now. */
    $textBody = &new MIME_Part('text/plain');
    $textBody->setContents($textBody->replaceEOL($message));
    $textBody->setCharset($charset);
    if (!is_null($trailer)) {
        $textBody->appendContents($trailer);
    }

    /* Send in flowed format. */
    require_once 'Text/Flowed.php';
    $flowed = &new Text_Flowed($textBody->getContents(), $charset);
    if (method_exists($flowed, 'setDelSp')) {
        $flowed->setDelSp(true);
        $textBody->setContentTypeParameter('DelSp', 'Yes');
    }
    $textBody->setContents($flowed->toFlowed());
    $textBody->setContentTypeParameter('format', 'flowed');

    /* Determine whether or not to send a multipart/alternative
     * message with an HTML part. */
    if (!empty($message_html)) {
        $htmlBody = &new MIME_Part('text/html', String::wrap($message_html), null, 'inline');
        if (!is_null($trailer)) {
            require_once 'Horde/Text/Filter.php';
            $htmlBody->appendContents(Text_Filter::filter($trailer, 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null)));
        }
        $basepart = &new MIME_Part('multipart/alternative');
        $textBody->setDescription(_("Plaintext Version of Message"));
        $basepart->addPart($textBody);
        $htmlBody->setDescription(_("HTML Version of Message"));
        $htmlBody->setCharset($charset);

        if ($final_msg) {
            /* Any image links will be downloaded and appended to the
             * message body. */
            $htmlBody = $imp_compose->convertToMultipartRelated($htmlBody);
        }
        $basepart->addPart($htmlBody);
    } else {
        $basepart = $textBody;
    }

    /* Add attachments now. */
    if ($imp_compose->numberOfAttachments()) {
        if ((Util::getFormData('link_attachments') && $GLOBALS['conf']['compose']['link_attachments']) ||
            !empty($GLOBALS['conf']['compose']['link_all_attachments'])) {
            $body = $imp_compose->linkAttachments(Horde::applicationUrl('attachment.php', true), $basepart, Auth::getAuth());
        } else {
            $body = &new MIME_Part('multipart/mixed');
            $body->addPart($basepart);
            $imp_compose->buildAllAttachments($body, $charset);
        }
    } else {
        $body = $basepart;
    }

    return $body;
}

/**
 * Create the base MIME_Message for sending.
 */
function _createMimeMessage($to, $body)
{
    require_once 'Horde/MIME/Message.php';
    $mime_message = &new MIME_Message($GLOBALS['imp']['maildomain']);

    /* Set up the base message now. */
    if ($GLOBALS['usePGP'] &&
        in_array($GLOBALS['encrypt'], array(IMP_PGP_ENCRYPT, IMP_PGP_SIGN, IMP_PGP_SIGNENC))) {
        if (empty($GLOBALS['imp_pgp'])) {
            require_once IMP_BASE .'/lib/Crypt/PGP.php';
            $GLOBALS['imp_pgp'] = &new IMP_PGP();
        }
        $imp_pgp = &$GLOBALS['imp_pgp'];

        /* Get the user's passphrase, if we need it. */
        $passphrase = '';
        if (in_array($GLOBALS['encrypt'], array(IMP_PGP_SIGN, IMP_PGP_SIGNENC))) {
            /* Check to see if we have the user's passphrase yet. */
            $passphrase = $imp_pgp->getPassphrase();
            if (empty($passphrase)) {
                $GLOBALS['pgp_passphrase_dialog'] = true;
                return PEAR::raiseError(_("PGP Error: Need passphrase for personal private key."));
            }
        }

        /* Do the encryption/signing requested. */
        switch ($GLOBALS['encrypt']) {
        case IMP_PGP_SIGN:
            $body = $imp_pgp->IMPsignMIMEPart($body);
            break;

        case IMP_PGP_ENCRYPT:
            $body = $imp_pgp->IMPencryptMIMEPart($body, $to);
            break;

        case IMP_PGP_SIGNENC:
            $body = $imp_pgp->IMPsignAndEncryptMIMEPart($body, $to);
            break;
        }

        /* Check for errors. */
        if (is_a($body, 'PEAR_Error')) {
            return PEAR::raiseError(_("PGP Error: ") . $body->getMessage());
        }
    } elseif ($GLOBALS['useSMIME'] &&
              in_array($GLOBALS['encrypt'], array(IMP_SMIME_ENCRYPT, IMP_SMIME_SIGN, IMP_SMIME_SIGNENC))) {
        if (empty($GLOBALS['imp_smime'])) {
            require_once IMP_BASE. '/lib/Crypt/SMIME.php';
            $GLOBALS['imp_smime'] = &new IMP_SMIME();
        }
        $imp_smime = &$GLOBALS['imp_smime'];

        /* Check to see if we have the user's passphrase yet. */
        if (in_array($GLOBALS['encrypt'], array(IMP_SMIME_SIGN, IMP_SMIME_SIGNENC))) {
            $passphrase = $imp_smime->getPassphrase();
            if ($passphrase === false) {
                $GLOBALS['smime_passphrase_dialog'] = true;
                return PEAR::raiseError(_("S/MIME Error: Need passphrase for personal private key."));
            }
        }

        /* Do the encryption/signing requested. */
        switch ($GLOBALS['encrypt']) {
        case IMP_SMIME_SIGN:
            $body = $imp_smime->IMPsignMIMEPart($body);
            break;

        case IMP_SMIME_ENCRYPT:
            $body = $imp_smime->IMPencryptMIMEPart($body, $to[0]);
            break;

        case IMP_SMIME_SIGNENC:
            $body = $imp_smime->IMPsignAndEncryptMIMEPart($body, $to[0]);
            break;
        }

        /* Check for errors. */
        if (is_a($body, 'PEAR_Error')) {
            return PEAR::raiseError(_("S/MIME Error: ") . $body->getMessage());
        }
    }

    /* Add data to MIME_Message object. */
    $mime_message->addPart($body);

    /* Append PGP signature if set in the preferences. */
    if ($GLOBALS['usePGP'] && Util::getFormData('pgp_attach_pubkey')) {
        if (!isset($GLOBALS['imp_pgp'])) {
            require_once IMP_BASE . '/lib/Crypt/PGP.php';
            $GLOBALS['imp_pgp'] = &new IMP_PGP();
        }
        $mime_message->addPart($GLOBALS['imp_pgp']->publicKeyMIMEPart());
    }

    return array('to' => implode(', ', $to), 'msg' => &$mime_message);
}

function _cleanAddrList($addrs, $multiple)
{
    $addrlist = array();

    foreach ($addrs as $val) {
        $addr_array = MIME::rfc822Explode($val, ',');
        $addr_array = array_map('trim', $addr_array);
        foreach ($addr_array as $email) {
            if (!empty($email)) {
                $uniques = IMP::bareAddress($email, $multiple);
                if (!$multiple) {
                    $uniques = array($uniques);
                }
                foreach ($uniques as $unique) {
                    if ($unique && $unique != 'UNEXPECTED_DATA_AFTER_ADDRESS@.SYNTAX-ERROR.') {
                        $addrlist[$unique] = $multiple ? $unique : $email;
                    } else {
                        $addrlist[$email] = $email;
                    }
                }
            }
        }
    }

    return array_values($addrlist);
}

function _popupSuccess()
{
    global $registry;
    require_once 'Horde/Menu.php';
    $menu = &new Menu(HORDE_MENU_MASK_NONE);
    $menu->add(Horde::applicationUrl('compose.php'), _("Compose another message"), 'compose.png');
    $menu->add('', _("Close this window"), 'close.png', $registry->getImageDir('horde'), '', 'window.close();');
    require IMP_TEMPLATES . '/common-header.inc';
    require IMP_TEMPLATES . '/compose/success.inc';
    IMP::status();
    require $registry->get('templates', 'horde') . '/common-footer.inc';
}

@define('IMP_BASE', dirname(__FILE__));
$session_control = 'netscape';
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Compose.php';
require_once IMP_BASE . '/lib/Folder.php';
require_once 'Horde/Identity.php';
require_once 'Horde/MIME.php';
require_once 'Horde/MIME/Part.php';

/* The list of state information from compose.php that needs to persist across
 * across page loads. */
$s_var = array('to', 'cc', 'bcc', 'to_list', 'cc_list', 'bcc_list',
               'to_field', 'cc_field', 'bcc_field', 'to_new', 'cc_new',
               'bcc_new', 'link_attachments', 'rtemode', 'pgp_attach_pubkey',
               'in_reply_to', 'references', 'identity', 'charset',
               'messageCache', 'popup', 'oldrtemode', 'mailto', 'from',
               'encrypt_options', 'x_priority', 'request_read_receipt',
               'subject', 'reply_index', 'reply_type', 'save_sent_mail',
               'save_attachments_select', 'reloaded', 'sent_mail_folder');

/* The message text. */
$msg = '';

/* The headers of the message. */
$header = array();
$header['to'] = '';
$header['cc'] = '';
$header['bcc'] = '';
$header['subject'] = '';
$header['inreplyto'] = Util::getFormData('in_reply_to');
$header['references'] = Util::getFormData('references');

$get_sig = true;
$pgp_passphrase_dialog = false;
$smime_passphrase_dialog = false;

/* Some global values. */
$imp_pgp = $imp_smime = $usePGP = $useSMIME = null;

$identity = &Identity::singleton(array('imp', 'imp'));
$sent_mail_folder = $identity->getValue('sent_mail_folder', Util::getFormData('identity'));
$actionID = _getActionID();
$reply_index = Util::getFormData('reply_index');
$thismailbox = Util::getFormData('thismailbox', $imp['mailbox']);

/* Check for duplicate submits. */
require_once 'Horde/Token.php';
if (isset($conf['token'])) {
    /* If there is a configured token system, set it up. */
    $tokenSource = &Horde_Token::singleton($conf['token']['driver'], Horde::getDriverConfig('token', $conf['token']['driver']));
} else {
    /* Default to the file system if no config. */
    $tokenSource = &Horde_Token::singleton('file');
}
if ($token = Util::getFormData('__formToken_compose')) {
    $verified = $tokenSource->verify($token);
    /* Notify and reset the actionID. */
    if (is_a($verified, 'PEAR_Error')) {
        $notification->push($verified);
        $actionID = null;
    } elseif (!$verified) {
        $notification->push(_("You have already submitted this page."), 'horde.error');
        $actionID = null;
    }
}

if (($index = Util::getFormData('index'))) {
    require_once IMP_BASE . '/lib/MIME/Contents.php';
    require_once IMP_BASE . '/lib/MIME/Headers.php';
    $imp_contents = &IMP_Contents::singleton($index . IMP_IDX_SEP . $_SESSION['imp']['thismailbox']);
    $imp_headers = &new IMP_Headers($index);
}
$imp_folder = &IMP_Folder::singleton();

/* Set the current time zone. */
NLS::setTimeZone();

/* Set the default charset & encoding.
 * $charset holds the charset to use when sending messages, $encoding the best
 * guessed charset offered to the user as the default value in the charset
 * dropdown list. */
if ($prefs->isLocked('sending_charset')) {
    $charset = NLS::getEmailCharset();
} else {
    $charset = Util::getFormData('charset');
}
$encoding = _getEncoding();

/* Initialize the IMP_Compose:: object. */
$oldMessageCacheID = Util::getFormData('messageCache');
$imp_compose = &new IMP_Compose(array('cacheID' => $oldMessageCacheID));

/* Is this a popup window? */
$isPopup = (($prefs->getValue('compose_popup') || Util::getFormData('popup')) && $browser->hasFeature('javascript'));

/* Determine the composition type - text or HTML.
   $rtemode is null if browser does not support it. */
$rtemode = null;
if ($browser->hasFeature('rte')) {
    $rtemode = false;
    if ($prefs->isLocked('compose_html')) {
        $rtemode = $prefs->getValue('compose_html');
    } else {
        $rtemode = Util::getFormData('rtemode');
        if (is_null($rtemode)) {
            $rtemode = $prefs->getValue('compose_html');
        } else {
            $oldrtemode = Util::getFormData('oldrtemode');
            $get_sig = false;
        }
    }
}

/* Load stationery. */
if (!$prefs->isLocked('stationery')) {
    $stationery = null;
    $all_stationery = @unserialize($prefs->getValue('stationery', false));
    if (is_array($all_stationery)) {
        $all_stationery = String::convertCharset($all_stationery, $prefs->getCharset());
        $stationery_list = array();
        foreach ($all_stationery as $stationery_id => $stationery_choice) {
            if ($stationery_choice['t'] == 'plain' ||
                ($stationery_choice['t'] == 'html' && $rtemode)) {
                if ($rtemode && $stationery_choice['t'] == 'plain') {
                    require_once 'Horde/Text/Filter.php';
                    $stationery_choice['c'] = Text_Filter::Filter($stationery_choice['c'], 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null));
                }
                $stationery_list[$stationery_id] = $stationery_choice;
            }
        }
    } else {
        $stationery_list = null;
    }
}

/* Update the file attachment information. */
if ($imp['file_upload']) {
    /* Only notify if we are reloading the compose screen. */
    $notify = ($actionID != 'send_message') && ($actionID != 'save_draft');

    $deleteList = Util::getPost('delattachments', array());

    /* Update the attachment information. */
    for ($i = 1; $i <= $imp_compose->numberOfAttachments(); $i++) {
        if (!in_array($i, $deleteList)) {
            $disposition = Util::getFormData('file_disposition_' . $i);
            $description = Util::getFormData('file_description_' . $i);
            $imp_compose->updateAttachment($i, array('disposition' => $disposition, 'description' => $description));
        }
    }

    /* Delete attachments. */
    if (!empty($deleteList)) {
        $filenames = $imp_compose->deleteAttachment($deleteList);
        if ($notify) {
            foreach ($filenames as $val) {
                $notification->push(sprintf(_("Deleted the attachment \"%s\"."), MIME::decode($val)), 'horde.success');
            }
        }
    }

    /* Add new attachments. */
    for ($i = 1; $i <= count($_FILES); $i++) {
        $key = 'upload_' . $i;
        if (isset($_FILES[$key]) &&
            ($_FILES[$key]['error'] != 4)) {
            if ($_FILES[$key]['size'] == 0) {
                $actionID = null;
                $notification->push(sprintf(_("Did not attach \"%s\" as the file was empty."), $_FILES[$key]['name']), 'horde.warning');
            } else {
                $result = $imp_compose->addUploadAttachment($key, Util::getFormData('upload_disposition_' . $i));
                if (is_a($result, 'PEAR_Error')) {
                    /* Don't send message immediately if we hit an error. */
                    $actionID = null;
                    $notification->push($result, 'horde.error');
                } elseif ($notify) {
                    $notification->push(sprintf(_("Added \"%s\" as an attachment."), $result), 'horde.success');
                }
            }
        }
    }
}

/* Run through the action handlers. */
$title = _("Message Composition");
switch ($actionID) {
case 'recompose':
    // Extract the stored form data.
    $formData = @unserialize($_SESSION['formData']);
    unset($_SESSION['formData']);

    if (!empty($formData['post'])) {
        $_POST = $formData['post'];
    }
    if (!empty($formData['get'])) {
        $_GET = $formData['get'];
    }

    $get_sig = false;
    break;

case 'mailto':
    if (!empty($index)) {
        $header['to'] = '';
        if (Util::getFormData('mailto')) {
            $header['to'] = $imp_headers->getOb('toaddress', true);
        }
        if (empty($header['to'])) {
            ($header['to'] = MIME::addrArray2String($imp_headers->getOb('from'))) ||
            ($header['to'] = MIME::addrArray2String($imp_headers->getOb('reply_to')));
        }
        $title = _("Message Composition");
    }
    break;

case 'draft':
    if (!empty($index)) {
        $mime_message = $imp_contents->rebuildMessage();
        $imp_compose->forwardMessage($imp_contents, $imp_headers);
        $res = $imp_compose->attachFilesFromMessage($imp_contents);
        if (!empty($res)) {
            foreach ($res as $val) {
                $notification->push($val, 'horde.error');
            }
        }

        $body_text = null;
        $draft_mode = 'text';
        if (($rtemode !== null) &&
            ($mime_message->getType() == 'multipart/alternative')) {
            $type_map = $mime_message->contentTypeMap();
            $html_key = array_search('text/html', $type_map);
            if ($html_key !== false) {
                $body_part = &$imp_contents->getDecodedMIMEPart($html_key);
                $body_text = $body_part->getContents();
                foreach ($imp_compose->getAttachments() as $key => $val) {
                    if ($val->getMIMEId() == $html_key) {
                        $imp_compose->deleteAttachment(++$key);
                        break;
                    }
                }
                $draft_mode = 'html';
            }
        }

        if ($rtemode !== null) {
            $rtemode = ($draft_mode == 'html');
        }

        if (is_null($body_text)) {
            $body_id = $imp_compose->getBodyId($imp_contents);
            $body_part = $mime_message->getPart($body_id);
            $body_text = $imp_compose->findBody($imp_contents);
        }
        $msg = "\n" . String::convertCharset($body_text, $body_part->getCharset());

        if ($rtemode && ($body_part->getType() != 'text/html')) {
            require_once 'Horde/Text/Filter.php';
            $msg = Text_Filter::filter($msg, 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null));
        }

        if (($fromaddr = $imp_headers->getOb('fromaddress'))) {
            $_GET['identity'] = $identity->getMatchingIdentity($fromaddr);
            $sent_mail_folder = $identity->getValue('sent_mail_folder', Util::getFormData('identity'));
        }
        $header['to'] = MIME::addrArray2String($imp_headers->getOb('to'));
        $header['cc'] = MIME::addrArray2String($imp_headers->getOb('cc'));
        $header['bcc'] = MIME::addrArray2String($imp_headers->getOb('bcc'));
        $header['subject'] = $imp_headers->getOb('subject', true);

        $title = _("Message Composition");
    }
    $get_sig = false;
    break;

case 'compose_expand_addr':
    $header['to'] = _expandAddresses('to');
    $header['cc'] = _expandAddresses('cc');
    $header['bcc'] = _expandAddresses('bcc');
    $get_sig = false;
    break;

case 'redirect_expand_addr':
    $header['to'] = _expandAddresses('to');
    $get_sig = false;
    break;

case 'reply':
case 'reply_all':
case 'reply_list':
    if (!empty($index)) {
        /* Set the message_id and references headers. */
        if (($msg_id = $imp_headers->getOb('message_id'))) {
            $header['inreplyto'] = chop($msg_id);
            if (($header['references'] = $imp_headers->getOb('references'))) {
                $header['references'] .= ' ' . $header['inreplyto'];
            } else {
                $header['references'] = $header['inreplyto'];
            }
        }

        if ($actionID == 'reply') {
            ($header['to'] = Util::getFormData('to')) ||
            ($header['to'] = MIME::addrArray2String($imp_headers->getOb('reply_to'))) ||
            ($header['to'] = MIME::addrArray2String($imp_headers->getOb('from')));
        } elseif ($actionID == 'reply_all') {
            /* Filter out our own address from the addresses we reply to. */
            $me = array_keys($identity->getAllFromAddresses(true));

            /* Build the To: header. */
            $from_arr = $imp_headers->getOb('from');
            $to_arr = $imp_headers->getOb('reply_to');
            $reply = '';
            if (!empty($to_arr)) {
                $reply = MIME::addrArray2String($to_arr);
            } elseif (!empty($from_arr)) {
                $reply = MIME::addrArray2String($from_arr);
            }
            $header['to'] = MIME::addrArray2String(array_merge($to_arr, $from_arr));
            $me[] = IMP::bareAddress($header['to']);

            /* Build the Cc: header. */
            $cc_arr = $imp_headers->getOb('to');
            if (!empty($cc_arr) &&
                ($reply != MIME::addrArray2String($cc_arr))) {
                $cc_arr = array_merge($cc_arr, $imp_headers->getOb('cc'));
            } else {
                $cc_arr = $imp_headers->getOb('cc');
            }
            $header['cc'] = MIME::addrArray2String($cc_arr, $me);

            /* Build the Bcc: header. */
            $header['bcc'] = MIME::addrArray2String($imp_headers->getOb('bcc') + $identity->getBccAddresses(), $me);
        } elseif ($actionID == 'reply_list') {
            $header['to'] = Util::getFormData('to');
        }

        $qfrom = MIME::addrArray2String($imp_headers->getOb('from'));
        if (empty($qfrom)) {
            $qfrom = '&lt;&gt;';
        }

        $mime_message = $imp_contents->getMIMEMessage();
        $encoding = _getEncoding($mime_message);
        $msg = $imp_compose->replyMessage($imp_contents, $qfrom, $imp_headers);
        if ($rtemode) {
            require_once 'Horde/Text/Filter.php';
            $msg = Text_Filter::filter($msg, 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null));
        }

        $header['subject'] = $imp_headers->getOb('subject', true);
        if (!empty($header['subject'])) {
            if (String::lower(String::substr($header['subject'], 0, 3)) != 're:') {
                $header['subject'] = 'Re: ' . $header['subject'];
            }
        } else {
            $header['subject'] = 'Re: ';
        }

        if ($actionID == 'reply') {
            $title = _("Reply:") . ' ' . $header['subject'];
        } elseif ($actionID == 'reply_all') {
            $title = _("Reply to All:") . ' ' . $header['subject'];
        } elseif ($actionID == 'reply_list') {
            $title = _("Reply to List:") . ' ' . $header['subject'];
        }

        $reply_index = $index;
    }
    break;

case 'forward':
    if (!empty($index)) {
        $mime_message = $imp_contents->rebuildMessage();
        $encoding = _getEncoding($mime_message);
        $msg = $imp_compose->forwardMessage($imp_contents, $imp_headers);
        if ($rtemode) {
            require_once 'Horde/Text/Filter.php';
            $msg = Text_Filter::filter($msg, 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null));
        }

        $res = $imp_compose->attachFilesFromMessage($imp_contents);
        if (!empty($res)) {
            foreach ($res as $val) {
                $notification->push($val, 'horde.error');
            }
        }

        /* We need the Message-Id so we can log this event. */
        if (is_array($message_id = $imp_headers->getOb('message_id'))) {
            $message_id = reset($message_id);
        }
        $header['inreplyto'] = chop($message_id);

        $header['subject'] = $imp_headers->getOb('subject', true);
        if (!empty($header['subject'])) {
            $title = _("Forward:") . ' ' . $header['subject'];
            /* If the subject line already has signals indicating this
               message is a forward, do not add an additional
               signal. */
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
            $title = _("Forward");
            $header['subject'] = _("Fwd:");
        }
    }
    break;

case 'redirect_compose':
    $title = _("Redirect this message");
    $rtemode = false;
    break;

case 'redirect_send':
    $f_to = Util::getFormData('to', _getAddressList('to'));
    if (!empty($index) && $f_to) {
        $recipients = _recipientList(array($f_to));
        if (is_a($recipients, 'PEAR_Error')) {
            $notification->push($recipients, 'horde.error');
            $get_sig = false;
            break;
        }
        $recipients = implode(', ', $recipients);

        $imp_headers->buildHeaders(false);
        $imp_headers->addResentHeaders($identity->getFromAddress(), $f_to);

        /* We need to set the Return-Path header to the current user - see
           RFC 2821 [4.4]. */
        $imp_headers->removeHeader('return-path');
        $imp_headers->addHeader('Return-Path', $identity->getFromAddress());

        $bodytext = str_replace("\r\n", "\n", $imp_contents->getBody());
        $status = $imp_compose->sendMessage($recipients, $imp_headers, $bodytext, (isset($charset) ? $charset : $encoding));
        if (!is_a($status, 'PEAR_Error')) {
            $entry = sprintf("%s Redirected message sent to %s from %s",
                             $_SERVER['REMOTE_ADDR'], $recipients, $imp['user']);
            Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_INFO);

            /* Store history information. */
            if (!empty($conf['maillog']['use_maillog'])) {
                require_once IMP_BASE . '/lib/Maillog.php';
                IMP_Maillog::log('redirect', $imp_headers->getOb('message_id'), $recipients);
            }

            if ($isPopup) {
                if ($prefs->getValue('compose_confirm')) {
                    $notification->push(_("Message redirected successfully."), 'horde.success');
                    _popupSuccess();
                } else {
                    Util::closeWindowJS();
                }
            } else {
                if ($prefs->getValue('compose_confirm')) {
                    $notification->push(_("Message redirected successfully."), 'horde.success');
                }
                header('Location: ' . _mailboxReturnURL(false));
            }
            exit;
        } else {
            Horde::logMessage($status->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
        }
        $actionID = 'redirect_compose';
        $notification->push(_("Redirecting failed."), 'horde.error');
    }
    break;

case 'send_message':
    $f_cc = $f_bcc = null;

    $f_to = _getAddressList('to');
    if ($conf['compose']['allow_cc']) {
        $f_cc = _getAddressList('cc');
    }
    if ($conf['compose']['allow_bcc']) {
        $f_bcc = _getAddressList('bcc');
    }

    /* We need at least one recipient & RFC 2822 requires that no
     * 8-bit characters can be in the address fields. */
    $recipientArray = _recipientList(array($f_to, $f_cc, $f_bcc));
    if (is_a($recipientArray, 'PEAR_Error')) {
        $notification->push($recipientArray, 'horde.error');
        $get_sig = false;
        break;
    }
    $recipients = implode(', ', $recipientArray);

    /* Get identity information now as it is needed for some of the encryption
     * code. */
    $identity_form = Util::getFormData('identity');
    $from = $identity->getFromLine($identity_form, Util::getFormData('from'));
    $barefrom = IMP::bareAddress($from);
    $identity->setDefault($identity_form);
    $replyto = $identity->getValue('replyto_addr');

    /* Set up the base message now. */
    $body = _baseMessage($imp_compose, $charset);
    if (is_a($body, 'PEAR_Error')) {
        $notification->push($body, 'horde.error');
        break;
    }

    /* Prepare the array of messages to send out.  May be more than one if
     * we are encrypting for multiple recipients are storing an encrypted
     * message locally. */
    $messagesToSend = array();

    /* Do encryption. */
    $encrypt = Util::getFormData('encrypt_options');
    $usePGP = ($prefs->getValue('use_pgp') && !empty($conf['utils']['gnupg']));
    $useSMIME = $prefs->getValue('use_smime');
    $multiple_pgp_msg = false;

    if ($usePGP &&
        in_array($encrypt, array(IMP_PGP_ENCRYPT, IMP_PGP_SIGNENC))) {
        if (empty($imp_pgp)) {
            require_once IMP_BASE .'/lib/Crypt/PGP.php';
            $imp_pgp = &new IMP_PGP();
        }
        if (empty($imp_pgp->multipleRecipientEncryption)) {
            $multiple_pgp_msg = true;
        }
    }

    if ($multiple_pgp_msg ||
        ($useSMIME &&
         in_array($encrypt, array(IMP_SMIME_ENCRYPT, IMP_SMIME_SIGNENC)))) {
        /* Must encrypt & send the message one recipient at a time. */
        foreach ($recipientArray as $val) {
            $res = _createMimeMessage(array($val), $body);
            if (is_a($res, 'PEAR_Error')) {
                $get_sig = false;
                $notification->push($res, 'horde.error');
                break 2;
            }
            $messagesToSend[] = $res;
        }

        /* Must target the encryption for the sender before saving message in
         * sent-mail. */
        $messageToSave = _createMimeMessage(array($from), $body);
        if (is_a($messageToSave, 'PEAR_Error')) {
            $get_sig = false;
            $notification->push($messageToSave, 'horde.error');
            break;
        }
    } else {
        /* No encryption - can send in clear-text all at once. */
        $res = _createMimeMessage($recipientArray, $body);
        if (is_a($res, 'PEAR_Error')) {
            $get_sig = false;
            $notification->push($res, 'horde.error');
            break;
        }
        $messagesToSend[] = $messageToSave = $res;
    }

    /* Initalize a header object for the outgoing message. */
    require_once IMP_BASE . '/lib/MIME/Headers.php';
    $msg_headers = &new IMP_Headers();

    /* Add a Received header for the hop from browser to server. */
    $msg_headers->addReceivedHeader();
    $msg_headers->addMessageIdHeader();

    /* Add the X-Priority header, if requested. This appears here since
       this is the "general" location that other mail clients insert
       this header. */
    if ($prefs->getValue('set_priority') &&
        Util::getFormData('x_priority')) {
        $msg_headers->addHeader('X-Priority', Util::getFormData('x_priority'));
    }

    $msg_headers->addHeader('Date', date('r'));

    /* Add Return Receipt Headers. */
    if ($conf['compose']['allow_receipts']) {
        if (Util::getFormData('request_read_receipt')) {
            require_once 'Horde/MIME/MDN.php';
            $mdn = &new MIME_MDN();
            $mdn->addMDNRequestHeaders($msg_headers, $barefrom);
        }
    }

    $browser_charset = NLS::getCharset();

    $msg_headers->addHeader('From', String::convertCharset($from, $browser_charset, $charset));

    if (!empty($replyto) && ($replyto != $barefrom)) {
        $msg_headers->addHeader('Reply-to', String::convertCharset($replyto, $browser_charset, $charset));
    }
    if (!empty($f_to)) {
        $msg_headers->addHeader('To', String::convertCharset($f_to, $browser_charset, $charset));
    } elseif (empty($f_to) && empty($f_cc)) {
        $msg_headers->addHeader('To', 'undisclosed-recipients:;');
    }
    if (!empty($f_cc)) {
        $msg_headers->addHeader('Cc', String::convertCharset($f_cc, $browser_charset, $charset));
    }
    $msg_headers->addHeader('Subject', String::convertCharset(Util::getFormData('subject', ''), $browser_charset, $charset));

    /* Add necessary headers for replies. */
    $reply_type = Util::getFormData('reply_type');
    $irt = Util::getFormData('in_reply_to');
    if ($reply_type == 'reply') {
        if ($ref = Util::getFormData('references')) {
            $msg_headers->addHeader('References', implode(' ', preg_split('|\s+|', trim($ref))));
        }
        if ($irt) {
            $msg_headers->addHeader('In-Reply-To', $irt);
        }
    }

    /* Send the messages out now. */
    foreach ($messagesToSend as $val) {
        $headers = &Util::cloneObject($msg_headers);
        $headers->addMIMEHeaders($val['msg']);
        $res = $imp_compose->sendMessage($val['to'], $headers, $val['msg'], $charset);
        if (is_a($res, 'PEAR_Error')) {
            /* Unsuccessful send. */
            Horde::logMessage($res->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
            $notification->push(sprintf(_("There was an error sending your message: %s"), $res->getMessage()), 'horde.error');
            $get_sig = false;
            break 2;
        }
    }

    $sent_saved = true;

    /* Log the reply. */
    if ($reply_type && $irt) {
        if (!empty($conf['maillog']['use_maillog'])) {
            require_once IMP_BASE . '/lib/Maillog.php';
            IMP_Maillog::log($reply_type, $irt, $recipients);
        }

        if ($reply_index && ($reply_type == 'reply')) {
            /* Make sure to set the IMAP reply flag. */
            require_once IMP_BASE . '/lib/Message.php';
            $imp_message = &IMP_Message::singleton();
            $flag = array($_SESSION['imp']['thismailbox'] => array($reply_index));
            $imp_message->flag('\\ANSWERED', $flag);
        }
    }

    $entry = sprintf("%s Message sent to %s from %s", $_SERVER['REMOTE_ADDR'], $recipients, $imp['user']);
    Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_INFO);

    /* Delete the attachment data. */
    $num_attachments = $imp_compose->numberOfAttachments();
    $imp_compose->deleteAllAttachments();

    /* Should we save this message in the sent mail folder? */
    if ($smf = Util::getFormData('sent_mail_folder')) {
        $sent_mail_folder = $smf;
    }
    if (!empty($sent_mail_folder) &&
        ((!$prefs->isLocked('save_sent_mail') &&
          Util::getFormData('save_sent_mail')) ||
         ($prefs->isLocked('save_sent_mail') &&
          $prefs->getValue('save_sent_mail')))) {

        $mime_message = $messageToSave['msg'];
        $msg_headers->addMIMEHeaders($mime_message);

        /* Keep Bcc: headers on saved messages. */
        if (!empty($f_bcc)) {
            $msg_headers->addHeader('Bcc', $f_bcc);
        }

        /* Loop through the envelope and add headers. */
        $headerArray = $mime_message->encode($msg_headers->toArray(), $charset);
        foreach ($headerArray as $key => $value) {
            $msg_headers->addHeader($key, $value);
        }
        $fcc = $msg_headers->toString();

        /* Strip attachments if requested. */
        $save_attach = $prefs->getValue('save_attachments');
        if (($save_attach == 'never') ||
            ((strpos($save_attach, 'prompt') === 0) &&
             (Util::getFormData('save_attachments_select') == 0))) {
            for ($i = 1; $i <= $num_attachments; $i++) {
                $oldPart = &$mime_message->getPart($i + 1);
                if ($oldPart !== false) {
                    $replace_part = &new MIME_Part('text/plain');
                    $replace_part->setCharset($charset);
                    $replace_part->setContents('[' . _("Attachment stripped: Original attachment type") . ': "' . $oldPart->getType() . '", ' . _("name") . ': "' . $oldPart->getName(true, true) . '"]', '8bit');
                    $mime_message->alterPart($i + 1, $replace_part);
                }
            }
        }

        /* Add the body text to the message string. */
        $fcc .= $mime_message->toString();

        /* Make absolutely sure there are no bare newlines. */
        $fcc = preg_replace("|([^\r])\n|", "\\1\r\n", $fcc);
        $fcc = str_replace("\n\n", "\n\r\n", $fcc);

        if (!$imp_folder->exists($sent_mail_folder)) {
            $imp_folder->create($sent_mail_folder, $prefs->getValue('subscribe'));
        }
        if (!@imap_append($imp['stream'], IMP::serverString($sent_mail_folder), $fcc, '\\Seen')) {
            $notification->push(sprintf(_("Message sent successfully, but not saved to %s"), IMP::displayFolder($sent_mail_folder)));
            $sent_saved = false;
        }
    }

    /* Save recipients to address book? */
    if ($prefs->getValue('save_recipients') &&
        $registry->hasMethod('contacts/import') &&
        $registry->hasMethod('contacts/search')) {

        $abook = $prefs->getValue('add_source');
        if (!empty($abook)) {
            require_once 'Mail/RFC822.php';
            $parser = &new Mail_RFC822();
            $recipientArray = $parser->parseAddressList(MIME::encodeAddress($recipients, null, $_SESSION['imp']['maildomain']));

            /* Filter out anyone that matches an email address already
             * in the address book. */
            $emails = array();
            foreach ($recipientArray as $recipient) {
                $emails[] = $recipient->mailbox . '@' . $recipient->host;
            }
            $results = $registry->call('contacts/search', array($emails, array($abook), array($abook => array('email'))));

            foreach ($recipientArray as $recipient) {
                /* Skip email addresses that already exist in the
                 * add_source. */
                if (isset($results[$recipient->mailbox . '@' . $recipient->host]) &&
                    count($results[$recipient->mailbox . '@' . $recipient->host])) {
                    continue;
                }

                /* Remove surrounding quotes and make sure that $name
                 * is non-empty. */
                $name = trim($recipient->personal);
                if (preg_match('/^(["\']).*\1$/', $name)) {
                    $name = substr($name, 1, -1);
                }
                if (empty($name)) {
                    $name = $recipient->mailbox;
                }
                $name = MIME::decode($name, $browser_charset);

                $result = $registry->call('contacts/import', array(array('name' => $name, 'email' => $recipient->mailbox . '@' . $recipient->host),
                                                                   'array', $abook));
                if (is_a($result, 'PEAR_Error')) {
                    if ($result->getCode() == 'horde.error') {
                        $notification->push($result, $result->getCode());
                    }
                } else {
                    $notification->push(sprintf(_("Entry \"%s\" was successfully added to the address book"), $name), 'horde.success');
                }
            }
        }
    }

    if ($isPopup) {
        if ($prefs->getValue('compose_confirm') || !$sent_saved) {
            if ($sent_saved) {
                $notification->push(_("Message sent successfully."), 'horde.success');
            }
            _popupSuccess();
        } else {
            Util::closeWindowJS();
        }
    } else {
        if ($prefs->getValue('compose_confirm') && $sent_saved) {
            $notification->push(_("Message sent successfully."), 'horde.success');
        }
        header('Location: ' . _mailboxReturnURL(false));
    }
    exit;

case 'save_draft':
    $drafts_folder = IMP::folderPref($prefs->getValue('drafts_folder'), true);
    if (!empty($drafts_folder)) {
        require_once 'Horde/MIME/Message.php';
        $mime = &new MIME_Message($imp['maildomain']);

        /* We need to make sure we add "\r\n" after every line for
         * imap_append() - some servers require it (e.g. Cyrus). */
        $mime->setEOL(MIME_PART_RFC_EOL);

        /* Set up the base message now. */
        $body = _baseMessage($imp_compose, $charset, false);
        if (is_a($body, 'PEAR_Error')) {
            $notification->push($res, 'horde.error');
            break;
        }

        $mime->addPart($body);
        $body = $mime->toString();

        $from = $identity->getFromLine(Util::getFormData('identity'), Util::getFormData('from'));

        /* Initalize a header object for the draft. */
        require_once IMP_BASE . '/lib/MIME/Headers.php';
        $draft_headers = &new IMP_Headers();

        $draft_headers->addHeader('Date', date('r'));
        if (!empty($from)) {
            $draft_headers->addHeader('From', $from);
        }
        if (($header['to'] = Util::getFormData('to'))) {
            $draft_headers->addHeader('To', MIME::encodeAddress(_formatAddr($header['to']), null, $imp['maildomain']));
        }
        if (($header['cc'] = Util::getFormData('cc'))) {
            $draft_headers->addHeader('Cc', MIME::encodeAddress(_formatAddr($header['cc']), null, $imp['maildomain']));
        }
        if (($header['bcc'] = Util::getFormData('bcc'))) {
            $draft_headers->addHeader('Bcc', MIME::encodeAddress(_formatAddr($header['bcc']), null, $imp['maildomain']));
        }
        if (($sub = Util::getFormData('subject'))) {
            $draft_headers->addHeader('Subject', MIME::encode($sub, NLS::getCharset()));
        }
        if (isset($mime)) {
            $draft_headers->addMIMEHeaders($mime);
        }

        $body = $draft_headers->toString() . $body;

        // Make absolutely sure there are no bare newlines.
        $body = preg_replace("|([^\r])\n|", "\\1\r\n", $body);
        $body = str_replace("\n\n", "\n\r\n", $body);

        if ($prefs->getValue('unseen_drafts')) {
            $append_flags = '\\Draft';
        } else {
            $append_flags = '\\Draft \\Seen';
        }

        $draft_success = false;

        if ($imp_folder->exists($drafts_folder)) {
            $draft_success = true;
        } elseif ($imp_folder->create($drafts_folder, $prefs->getValue('subscribe'))) {
            $draft_success = true;
        }

        if ($draft_success) {
            if (!@imap_append($imp['stream'], IMP::serverString($drafts_folder), $body, $append_flags)) {
                $notification->push(sprintf(_("Saving the draft failed. This is what the server said: %s"), imap_last_error()), 'horde.error');
            } elseif ($prefs->getValue('close_draft')) {
                if ($isPopup) {
                    Util::closeWindowJS();
                } else {
                    header('Location: ' . _mailboxReturnURL(false));
                }
                exit;
            } else {
                $notification->push(sprintf(_("The draft has been saved to the \"%s\" folder."),
                                            IMP::displayFolder($drafts_folder)));
                $get_sig = false;
                break;
            }
        }
    }
    $get_sig = false;
    $notification->push(_("There was an error saving this message as a draft."), 'horde.error');
    break;

case 'fwd_digest':
    $indices = Util::getFormData('fwddigest');
    if (!empty($indices)) {
        require_once IMP_BASE . '/lib/MIME/Contents.php';
        $msglist = unserialize(urldecode($indices));
        foreach ($msglist as $index) {
            if (strstr($index, IMP_IDX_SEP)) {
                require_once IMP_BASE . '/lib/base.php';
                $imp_imap = &IMP_IMAP::singleton();
                list($index, $mailbox) = explode(IMP_IDX_SEP, $index);
                $imp_imap->changeMbox($mailbox);
            } else {
                $mailbox = $_SESSION['imp']['thismailbox'];
            }
            $part = &new MIME_Part('message/rfc822');
            $contents = &IMP_Contents::singleton($index . IMP_IDX_SEP . $mailbox);
            $digest_headers = &$contents->getHeaderOb();
            if (!($name = $digest_headers->getOb('subject', true))) {
                $name = _("[No Subject]");
            }
            $part->setName($name);
            $part->setContents($contents->fullMessageText());
            $res = $imp_compose->addMIMEPartAttachment($part);
            if (is_a($res, 'PEAR_Error')) {
                $notification->push($res, 'horde.error');
            }
        }
        if (count($msglist) == 1) {
            $header['subject'] = _("Fwd: ") . $name;
        } else {
            $header['subject'] = sprintf(_("Fwd: %u Forwarded Messages"), count($msglist));
        }
    }
    break;

case 'cancel_compose':
    $imp_compose->deleteAllAttachments();
    if ($isPopup) {
        Util::closeWindowJS();
    } else {
        header('Location: ' . _mailboxReturnURL(false));
    }
    exit;

case 'spell_check_cancel':
    $msg = "\n" . Util::getFormData('message');
    $expanded = Util::getFormData('to_list');
    if (!empty($expanded)) {
        $header['to'] = _expandAddresses('to');
        $header['cc'] = _expandAddresses('cc');
        $header['bcc'] = _expandAddresses('bcc');
    }
    $get_sig = false;
    break;

case 'spell_check_done':
    $msg = "\n";
    $msg .= Util::getFormData('newmsg');
    $msg .= Util::getFormData('currmsg');
    $expanded = Util::getFormData('to_list');
    if (!empty($expanded)) {
        $header['to'] = _expandAddresses('to');
        $header['cc'] = _expandAddresses('cc');
        $header['bcc'] = _expandAddresses('bcc');
    }
    $get_sig = false;
    break;

case 'spell_check':
case 'spell_check_send':
case 'spell_check_forward':
    require IMP_BASE . '/spelling.php';
    break;

case 'selectlist_process':
    $select_id = Util::getFormData('selectlist_selectid');
    if (!empty($select_id)) {
        if ($registry->hasMethod('files/selectlistResults') &&
            $registry->hasMethod('files/returnFromSelectlist')) {
            $filelist = $registry->call('files/selectlistResults', array($select_id));
            if ($filelist && !is_a($filelist, 'PEAR_Error')) {
                $i = 0;
                foreach ($filelist as $val) {
                    $data = $registry->call('files/returnFromSelectlist', array($select_id, $i++));
                    if ($data && !is_a($data, 'PEAR_Error')) {
                        $part = new MIME_Part();
                        $part->setContents($data);
                        $part->setName(reset($val));
                        $res = $imp_compose->addMIMEPartAttachment($part);
                        if (is_a($res, 'PEAR_Error')) {
                            $notification->push($res, 'horde.error');
                        }
                    }
                }
            }
        }
    }
    break;

case 'change_stationery':
    if (!$prefs->isLocked('stationery')) {
        $stationery = Util::getFormData('stationery');
        if (strlen($stationery)) {
            $stationery = (int)$stationery;
            $stationery_content = $stationery_list[$stationery]['c'];
            $msg = Util::getFormData('message', '');
            if (strpos($stationery_content, '%s') !== false) {

                if (!is_null($selected_identity = Util::getFormData('identity'))) {
                    $sig = $identity->getSignature($selected_identity);
                } else {
                    $sig = $identity->getSignature();
                }
                if ($rtemode) {
                    require_once 'Horde/Text/Filter.php';
                    $sig = Text_Filter::filter($sig, 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null));
                    $stationery_content = Text_Filter::filter($stationery_content, 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null));
                }
                $msg = str_replace(array("\r\n", $sig), array("\n", ''), $msg);
                $stationery_content = str_replace('%s', $sig, $stationery_content);
            }
            if (strpos($stationery_content, '%c') === false) {
                $msg .= $stationery_content;
            } else {
                $msg = str_replace('%c', $msg, $stationery_content);
            }
        }
    }
    $get_sig = false;
    break;

case 'add_attachment':
    $get_sig = false;
    break;

}

if ($rtemode) {
    require_once 'Horde/Editor.php';
    $editor = &Horde_Editor::singleton('htmlarea', array('id' => 'message'));
}

/* Get the message cache ID. */
$messageCacheID = $imp_compose->getMessageCacheId();

/* Has this page been reloaded? */
$reloaded = Util::getFormData('reloaded');

/* Set the 'save_sent_mail' checkbox for the form. */
if ($reloaded) {
    $ssm_check = Util::getFormData('save_sent_mail') == 'on';
} else {
    $ssm_check = $identity->saveSentmail(Util::getFormData('identity'));
}

if ($browser->isBrowser('msie')) {
    Horde::addScriptFile('ieEscGuard.js', 'imp', true);
}
require IMP_TEMPLATES . '/common-header.inc';

if ($isPopup) {
    /* If the attachments cache is not empty, we must reload this page
     * and delete the attachments. */
    if ($messageCacheID) {
        $url = Util::addParameter(Horde::selfUrl(), array('actionID' => 'cancel_compose', 'messageCache' => $messageCacheID, 'popup' => 1), null, false);
        $cancel_js = 'self.location.href=\'' . $url . '\';';
    } else {
        $cancel_js = 'self.close();';
    }
} else {
    /* If the attachments cache is not empty, we must reload this page and
       delete the attachments. */
    if ($messageCacheID) {
        $url = Util::addParameter(_mailboxReturnURL(true, Horde::selfUrl()), array('actionID' => 'cancel_compose', 'messageCache' => $messageCacheID), null, false);
        $cancel_js = 'window.location = \'' . $url . '\';';
    } else {
        $cancel_js = 'window.location = \'' . _mailboxReturnURL(true) . '\';';
    }
    require IMP_TEMPLATES . '/menu.inc';
}

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
    if ($browser->hasQuirk('double_linebreak_textarea')) {
        $msg = preg_replace('/(\r?\n){3}/', '$1', $msg);
    }
    $msg = "\n" . $msg;

    /* Convert from Text -> HTML or vice versa if RTE mode changed. */
    if (isset($oldrtemode) && ($oldrtemode != $rtemode)) {
        require_once 'Horde/Text/Filter.php';
        if ($rtemode) {
            $msg = Text_Filter::filter($msg, 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null));
        } else {
            $msg = Text_Filter::filter($msg, 'html2text');
        }
    }
}

/* If this is the first page load for this compose item, add auto BCC
 * addresses. */
if (empty($reloaded) && ($actionID != 'draft')) {
    $header['bcc'] = MIME::addrArray2String($identity->getBccAddresses());
}

foreach (array('to', 'cc', 'bcc', 'subject') as $val) {
    if (empty($header[$val])) {
        $header[$val] = Util::getFormData($val, _getAddressList($val));
    }
}

$all_sigs = $identity->getAllSignatures();
foreach ($all_sigs as $ident => $sig) {
    $identities[$ident] = array($sig,
                                $identity->getValue('sig_first', $ident),
                                $identity->getValue('sent_mail_folder', $ident),
                                $identity->saveSentmail($ident),
                                MIME::addrArray2String($identity->getBccAddresses($ident)));
}
$sig = $identity->getSignature();

if ($get_sig && isset($msg) && !empty($sig)) {
    if ($rtemode) {
        require_once 'Horde/Text/Filter.php';
        $sig = '<p>' . Text_Filter::filter(trim($sig), 'text2html', array('parselevel' => TEXT_HTML_MICRO_LINKURL, 'class' => null, 'callback' => null)) . '</p>';
    }

    if ($identity->getValue('sig_first')) {
        $msg = "\n" . $sig . $msg;
    } else {
        $msg .= "\n" . $sig;
    }
}

/* Define some variables used in the templates. */
$timeout = ini_get('session.gc_maxlifetime');

/* Reload nls.php to get the translated charset names. */
require HORDE_BASE . '/config/nls.php';

switch ($actionID) {
case 'redirect_compose':
case 'redirect_expand_addr':
    $mailbox = Util::getFormData('thismailbox', $imp['mailbox']);
    require_once IMP_TEMPLATES . '/compose/compose_expand.js';
    require IMP_TEMPLATES . '/compose/redirect.inc';
    break;

case 'spell_check':
case 'spell_check_send':
case 'spell_check_forward':
    require_once IMP_TEMPLATES . '/compose/spelling.js';
    require IMP_TEMPLATES . '/compose/spelling.inc';
    break;

default:
    /* We need to define $num_attachments and $upload_list as it is used in
       both compose.inc and attachments.js. */
    if ($imp['file_upload']) {
        $attachments = $imp_compose->additionalAttachmentsAllowed();
    }

    require_once IMP_TEMPLATES . '/compose/compose.js';
    require_once IMP_TEMPLATES . '/compose/compose_expand.js';
    require IMP_TEMPLATES . '/compose/compose.inc';

    /* Insert javascript code. */
    if ($imp['file_upload']) {
        require_once IMP_TEMPLATES . '/compose/attachments.js';
    }
    break;
}

/* Open the passphrase window here. */
if ($pgp_passphrase_dialog) {
    Horde::addScriptFile('popup.js');
    echo '<script type="text/javascript">' . $imp_pgp->getJSOpenWinCode('open_passphrase_dialog', "opener.focus();opener.document.compose.actionID.value='send_message';opener.uniqSubmit();") . '</script>';
} elseif ($smime_passphrase_dialog) {
    Horde::addScriptFile('popup.js');
    echo '<script type="text/javascript">' . $imp_smime->getJSOpenWinCode('open_passphrase_dialog', "opener.focus();opener.document.compose.actionID.value='send_message';opener.uniqSubmit();") . '</script>';
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
