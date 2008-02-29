<?php

require_once 'Horde/MIME.php';

/**
 * The virtual path to use for VFS data.
 */
define('IMP_VFS_ATTACH_PATH', '.horde/imp/compose');

/**
 * The virtual path to save linked attachments.
 */
define('IMP_VFS_LINK_ATTACH_PATH', '.horde/imp/attachments');

/**
 * The IMP_Compose:: class contains functions related to generating
 * outgoing mail messages.
 *
 * $Horde: imp/lib/Compose.php,v 1.107.2.41 2007/01/02 13:54:55 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 4.0
 * @package IMP
 */
class IMP_Compose {

    /**
     * The cached attachment data.
     *
     * @var array
     */
    var $_cache = array();

    /**
     * For findBody, the MIME ID of the "body" part.
     *
     * @var string
     */
    var $_mimeid = null;

    /**
     * The aggregate size of all attachments (in bytes).
     *
     * @var integer
     */
    var $_size = 0;

    /**
     * Constructor
     *
     * @param array $params  Parameters needed.
     */
    function IMP_Compose($params = array())
    {
        if (isset($params['cacheID'])) {
            $this->_retrieveMimeCache($params['cacheID']);
        }
    }

    /**
     * Sends a message.
     *
     * @param string $email          The e-mail list to send to.
     * @param IMP_Headers &$headers  The IMP_Headers object holding this
     *                               messages headers.
     * @param mixed &$message        Either the message text (string) or a
     *                               MIME_Message object that contains the
     *                               text to send.
     * @param string $charset        The charset that was used for the headers.
     *
     * @return mixed  True on success, PEAR_Error on error.
     */
    function sendMessage($email, &$headers, &$message, $charset)
    {
        global $conf;

        require_once 'Mail.php';

        /* We don't actually want to alter the contents of the $conf['mailer']
         * array, so we make a copy of the current settings. We will apply our
         * modifications (if any) to the copy, instead. */
        $params = $conf['mailer']['params'];
        $driver = $conf['mailer']['type'];

        /* If user specifies an SMTP server on login, force SMTP mailer. */
        if (!empty($conf['server']['change_smtphost'])) {
            $driver = 'smtp';
            if (empty($params['mailer']['auth'])) {
                $params['mailer']['auth'] = '1';
            }
        }

        /* Force the SMTP host and port value to the current SMTP server if
         * one has been selected for this connection. */
        if (!empty($_SESSION['imp']['smtphost'])) {
            $params['host'] = $_SESSION['imp']['smtphost'];
        }
        if (!empty($_SESSION['imp']['smtpport'])) {
            $params['port'] = $_SESSION['imp']['smtpport'];
        }

        /* If SMTP authentication has been requested, use either the username
         * and password provided in the configuration or populate the username
         * and password fields based on the current values for the user. Note
         * that we assume that the username and password values from the
         * current IMAP / POP3 connection are valid for SMTP authentication as
         * well. */
        if (!empty($params['auth']) && empty($params['username'])) {
            $params['username'] = $_SESSION['imp']['user'];
            $params['password'] = Secret::read(Secret::getKey('imp'), $_SESSION['imp']['pass']);
        }

        /* Add the site headers. */
        $headers->addSiteHeaders();

        /* If $message is a string, we need to get a MIME_Message object to
         * encode the headers. */
        if (is_string($message)) {
            $msg = $message;
            $mime_message = &new MIME_Message($_SESSION['imp']['maildomain']);
            $headerArray = $mime_message->encode($headers->toArray(), $charset);
        } else {
            $msg = $message->toString();
            $headerArray = $message->encode($headers->toArray(), $charset);
        }

        /* Make sure the message has a trailing newline. */
        if (substr($msg, -1) != "\n") {
            $msg .= "\n";
        }

        $mailer = Mail::factory($driver, $params);
        if (is_a($mailer, 'PEAR_Error')) {
            return $mailer;
        }

        /* Properly encode the addresses we're sending to. */
        $email = MIME::encodeAddress($email, null, $_SESSION['imp']['maildomain']);
        if (is_a($email, 'PEAR_Error')) {
            return $email;
        }

        /* Validate the recipient addresses. */
        require_once 'Mail/RFC822.php';
        $parser = &new Mail_RFC822();
        $result = $parser->parseAddressList($email, $_SESSION['imp']['maildomain'], null, true);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $result = $mailer->send($email, $headerArray, $msg);

        if (is_a($result, 'PEAR_Error') &&
            $conf['mailer']['type'] == 'sendmail') {
            // Interpret return values as defined in /usr/include/sysexits.h
            switch ($result->getCode()) {
            case 64: // EX_USAGE
                $error = 'sendmail: ' . _("command line usage error") . ' (64)';
                break;

            case 65: // EX_DATAERR
                $error = 'sendmail: ' . _("data format error") . ' (65)';
                break;

            case 66: // EX_NOINPUT
                $error = 'sendmail: ' . _("cannot open input") . ' (66)';
                break;

            case 67: // EX_NOUSER
                $error = 'sendmail: ' . _("addressee unknown") . ' (67)';
                break;

            case 68: // EX_NOHOST
                $error = 'sendmail: ' . _("host name unknown") . ' (68)';
                break;

            case 69: // EX_UNAVAILABLE
                $error = 'sendmail: ' . _("service unavailable") . ' (69)';
                break;

            case 70: // EX_SOFTWARE
                $error = 'sendmail: ' . _("internal software error") . ' (70)';
                break;

            case 71: // EX_OSERR
                $error = 'sendmail: ' . _("system error") . ' (71)';
                break;

            case 72: // EX_OSFILE
                $error = 'sendmail: ' . _("critical system file missing") . ' (72)';
                break;

            case 73: // EX_CANTCREAT
                $error = 'sendmail: ' . _("cannot create output file") . ' (73)';
                break;

            case 74: // EX_IOERR
                $error = 'sendmail: ' . _("input/output error") . ' (74)';
                break;

            case 75: // EX_TEMPFAIL
                $error = 'sendmail: ' . _("temporary failure") . ' (75)';
                break;

            case 76: // EX_PROTOCOL
                $error = 'sendmail: ' . _("remote error in protocol") . ' (76)';
                break;

            case 77: // EX_NOPERM
                $error = 'sendmail: ' . _("permission denied") . ' (77)';
                break;

            case 78: // EX_CONFIG
                $error = 'sendmail: ' . _("configuration error") . ' (78)';
                break;

            case 79: // EX_NOTFOUND
                $error = 'sendmail: ' . _("entry not found") . ' (79)';
                break;

            default:
                $error = $result;
            }
            return PEAR::raiseError($error);
        }

        return $result;
    }

    /**
     * Finds the main "body" text part (if any) in a message.
     *
     * @param IMP_Contents &$imp_contents  An IMP_Contents object.
     *
     * @return string  The text of the "body" part of the message.
     *                 Returns an empty string if no "body" found.
     */
    function findBody(&$imp_contents)
    {
        if (is_null($this->_mimeid)) {
            $this->_mimeid = $imp_contents->findBody();
            if (is_null($this->_mimeid)) {
                return '';
            }
        }

        $mime_part = &$imp_contents->getDecodedMIMEPart($this->_mimeid);
        $body = $mime_part->getContents();

        //if ($mime_message->getType() == 'multipart/encrypted') {
            /* TODO: Maybe someday I can figure out how to show embedded
             * text parts here.  But for now, just output this message. */
        //    return '[' . _("Original message was encrypted") . ']';
        //}

        if ($mime_part->getSubType() == 'html') {
            require_once 'Horde/Text/Filter.php';
            return Text_Filter::filter($body, 'html2text', array('wrap' => ($mime_part->getContentTypeParameter('format') != 'flowed'), 'charset' => $mime_part->getCharset()));
        } else {
            return $body;
        }
    }

    /**
     * Returns the ID of the MIME part containing the "body".
     *
     * @param IMP_Contents &$imp_contents  An IMP_Contents object.
     *
     * @return string  The ID of the mime part's body.
     */
    function getBodyId(&$imp_contents)
    {
        if (is_null($this->_mimeid)) {
            $this->findBody($imp_contents);
        }
        return $this->_mimeid;
    }

    /**
     * Determine the reply text for a message.
     *
     * @param IMP_Contents $imp_contents  An IMP_Contents object.
     * @param string $from                The email address of the
     *                                    original sender.
     * @param IMP_Headers $h              The IMP_Headers object for
     *                                    the message.
     *
     * @return string  The text of the body part of the message to use
     *                 for the reply.
     */
    function replyMessage(&$imp_contents, $from, &$h)
    {
        global $prefs;

        if (!$prefs->getValue('reply_quote')) {
            return '';
        }

        $mime_message = $imp_contents->getMIMEMessage();
        $msg = $this->findBody($imp_contents);
        $msg = $mime_message->replaceEOL($msg, "\n");

        if (!is_null($this->_mimeid)) {
            require_once 'Text/Flowed.php';
            $old_part = $mime_message->getPart($this->_mimeid);
            if ($old_part->getContentTypeParameter('format') == 'flowed') {
                /* We need to convert the flowed text to fixed text before
                 * we begin working on it. */
                $flowed = &new Text_Flowed($msg);
                if ((String::lower($mime_message->getContentTypeParameter('delsp')) == 'yes') &&
                    method_exists($flowed, 'setDelSp')) {
                    $flowed->setDelSp(true);
                }
                $msg = $flowed->toFixed(false);
            } else {
                /* If the input is *not* in flowed format, make sure there is
                 * no padding at the end of lines. */
                $msg = preg_replace("/\s*\n/U", "\n", $msg);
            }

            $msg = String::convertCharset($msg, $old_part->getCharset());

            $flowed = &new Text_Flowed($msg);
            if (method_exists($flowed, 'setDelSp')) {
                $flowed->setDelSp(true);
            }
            $msg = $flowed->toFlowed(true);
        }

        if (empty($msg)) {
            $msg = '[' . _("No message body text") . ']';
        } elseif ($prefs->getValue('reply_headers') && !empty($h)) {
            $msghead = '----- ';
            if (($from = $h->getFromAddress())) {
                $msghead .= sprintf(_("Message from %s"), $from);
            } else {
                $msghead .= _("Message");
            }

            /* Extra '-'s line up with "End Message" below. */
            $msghead .= " ---------\n";
            $msghead .= $this->_getMsgHeaders($h);
            $msg = $msghead . "\n\n" . $msg;
            if (!empty($from)) {
                $msg .= "\n\n" . '----- ' . sprintf(_("End message from %s"), $from) . " -----\n";
            } else {
                $msg .= "\n\n" . '----- ' . _("End message") . " -----\n";
            }
        } else {
            $msg = $this->_expandAttribution($prefs->getValue('attrib_text'), $from, $h) . "\n\n" . $msg;
        }

        return $msg . "\n";
    }

    /**
     * Determine the text for a forwarded message.
     *
     * @param IMP_Contents &$imp_contents  An IMP_Contents object.
     * @param IMP_Headers &$h              The IMP_Headers object for
     *                                     the message.
     *
     * @return string  The text of the body part of the message to use
     *                 for the forward.
     */
    function forwardMessage(&$imp_contents, &$h)
    {
        require_once 'Horde/Text.php';

        $msg = "\n\n\n----- ";

        if (($from = $h->getFromAddress())) {
            $msg .= sprintf(_("Forwarded message from %s"), $from);
        } else {
            $msg .= _("Forwarded message");
        }

        $msg .= " -----\n";
        $msg .= $this->_getMsgHeaders($h);
        $msg .= "\n";

        $fwd_msg = $this->findBody($imp_contents);
        if (!is_null($this->_mimeid)) {
            $mime_message = $imp_contents->getMIMEMessage();
            $old_part = $mime_message->getPart($this->_mimeid);
            $fwd_msg = String::convertCharset($fwd_msg, $old_part->getCharset());
        }

        $msg .= $fwd_msg;
        $msg .= "\n\n----- " . _("End forwarded message") . " -----\n";

        return $msg;
    }

    /**
     * Determine the header information to display in the forward/reply.
     *
     * @access private
     *
     * @param IMP_Headers &$h  The IMP_Headers object for the message.
     *
     * @return string  The header information for the original message.
     */
    function _getMsgHeaders(&$h)
    {
        $text = '';

        if (($date_ob = $h->getOb('date', true))) {
            $text .= _("    Date: ") . $date_ob . "\n";
        }
        if (($from_ob = MIME::addrArray2String($h->getOb('from')))) {
            $text .= _("    From: ") . $from_ob . "\n";
        }
        if (($rep_ob = MIME::addrArray2String($h->getOb('reply_to')))) {
            $text .= _("Reply-To: ") . $rep_ob . "\n";
        }
        if (($sub_ob = $h->getOb('subject', true))) {
            $text .= _(" Subject: ") . $sub_ob . "\n";
        }
        if (($to_ob = MIME::addrArray2String($h->getOb('to')))) {
            $text .= _("      To: ") . $to_ob . "\n";
        }
        if (($cc_ob = MIME::addrArray2String($h->getOb('cc')))) {
            $text .= _("      Cc: ") . $cc_ob . "\n";
        }

        return $text;
    }

    /**
     * Adds an attachment to a MIME_Part from an uploaded file.
     * The actual attachment data is stored in a separate file - the
     * MIME_Part information entries 'temp_filename' and 'temp_filetype'
     * are set with this information.
     *
     * @param string $name         The input field name from the form.
     * @param string $disposition  The disposition to use for the file.
     *
     * @return mixed  Returns the filename on success.
     *                Returns PEAR_Error on error.
     */
    function addUploadAttachment($name, $disposition)
    {
        global $conf;

        $res = Browser::wasFileUploaded($name, _("attachment"));
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        $filename = $_FILES[$name]['name'];
        $tempfile = $_FILES[$name]['tmp_name'];

        /* Check for filesize limitations. */
        if (!empty($conf['compose']['attach_size_limit']) &&
            (($conf['compose']['attach_size_limit'] - $this->sizeOfAttachments() - $_FILES[$name]['size']) < 0)) {
            return PEAR::raiseError(sprintf(_("Attached file \"%s\" exceeds the attachment size limits. File NOT attached."), $filename), 'horde.error');
        }

        /* Store the data in a MIME_Part. Some browsers do not send the MIME
           type so try an educated guess. */
        if (!empty($_FILES[$name]['type']) &&
            ($_FILES[$name]['type'] != 'application/octet-stream')) {
            $part = &new MIME_Part($_FILES[$name]['type']);
        } else {
            require_once 'Horde/MIME/Magic.php';
            /* Try to determine the MIME type from 1) analysis of the file
             * (if available) and, if that fails, 2) from the extension. We
             * do it in this order here because, most likely, if a browser
             * can't identify the type of a file, it is because the file
             * extensions isn't available and/or recognized. */
            if (!($type = MIME_Magic::analyzeFile($tempfile, !empty($conf['mime']['magic_db']) ? $conf['mime']['magic_db'] : null))) {
                $type = MIME_Magic::filenameToMIME($filename, false);
            }
            $part = &new MIME_Part($type);
        }
        $part->setCharset(NLS::getCharset());
        $part->setName(MIME::encode($filename));
        $part->setBytes($_FILES[$name]['size']);
        if ($disposition) {
            $part->setDisposition($disposition);
        }

        if ($conf['compose']['use_vfs']) {
            $attachment = $tempfile;
        } else {
            $attachment = Horde::getTempFile('impatt', false);
            move_uploaded_file($tempfile, $attachment);
        }

        /* Store the data. */
        $result = $this->_storeAttachment($part, $attachment);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $filename;
    }

    /**
     * Adds an attachment to a MIME_Part from data existing in the part.
     *
     * @param MIME_Part &$part  The MIME_Part object that contains the
     *                          attachment data.
     *
     * @return PEAR_Error  Returns a PEAR_Error object on error.
     */
    function addMIMEPartAttachment(&$part)
    {
        global $conf;

        $type = $part->getType();
        $vfs = $conf['compose']['use_vfs'];

        /* Decode the contents. */
        $part->transferDecodeContents();

        /* Try to determine the MIME type from 1) the extension and
         * then 2) analysis of the file (if available). */
        if ($type == 'application/octet-stream') {
            require_once 'Horde/MIME/Magic.php';
            $type = MIME_Magic::filenameToMIME($part->getName(true), false);
        }

        /* Extract the data from the currently existing MIME_Part and then
           delete it. If this is an unknown MIME part, we must save to a
           temporary file to run the file analysis on it. */
        if (!$vfs) {
            $attachment = Horde::getTempFile('impatt', $vfs);
            $fp = fopen($attachment, 'w');
            fwrite($fp, $part->getContents());
            fclose($fp);

            if (($type == 'application/octet-stream') &&
                ($analyzetype = MIME_Magic::analyzeFile($attachment, !empty($conf['mime']['magic_db']) ? $conf['mime']['magic_db'] : null))) {
                $type = $analyzetype;
            }
        } elseif (($type == 'application/octet-stream') &&
                  ($analyzetype = MIME_Magic::analyzeData($part->getContents(), !empty($conf['mime']['magic_db']) ? $conf['mime']['magic_db'] : null))) {
            $type = $analyzetype;
        }

        $part->setType($type);

        /* Set the size of the Part explicitly since we delete the contents
           later on in this function. */
        $part->setBytes($part->getBytes());

        /* Check for filesize limitations. */
        if (!empty($conf['compose']['attach_size_limit']) &&
            (($conf['compose']['attach_size_limit'] - $this->sizeOfAttachments() - $part->getBytes()) < 0)) {
            return PEAR::raiseError(sprintf(_("Attached file \"%s\" exceeds the attachment size limits. File NOT attached."), $part->getName()), 'horde.error');
        }

        /* Store the data. */
        if ($vfs) {
            $vfs_data = $part->getContents();
            $part->clearContents();
            $this->_storeAttachment($part, $vfs_data, false);
        } else {
            $part->clearContents();
            $this->_storeAttachment($part, $attachment);
        }
    }

    /**
     * Stores the attachment data in its correct location.
     *
     * @access private
     *
     * @param MIME_Part &$part   The MIME_Part of the attachment.
     * @param string $data       Either the filename of the attachment or, if
     *                           $vfs_file is false, the attachment data.
     * @param boolean $vfs_file  If using VFS, is $data a filename?
     */
    function _storeAttachment(&$part, $data, $vfs_file = true)
    {
        global $conf;

        /* Store in VFS. */
        if ($conf['compose']['use_vfs']) {
            require_once 'VFS.php';
            require_once 'VFS/GC.php';
            $vfs = &VFS::singleton($conf['vfs']['type'], Horde::getDriverConfig('vfs', $conf['vfs']['type']));
            VFS_GC::gc($vfs, IMP_VFS_ATTACH_PATH, 86400);
            $cacheID = md5(mt_rand() . microtime());
            if ($vfs_file) {
                $result = $vfs->write(IMP_VFS_ATTACH_PATH, $cacheID, $data, true);
            } else {
                $result = $vfs->writeData(IMP_VFS_ATTACH_PATH, $cacheID, $data, true);
            }
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
            $part->setInformation('temp_filename', $cacheID);
            $part->setInformation('temp_filetype', 'vfs');
        } else {
            chmod($data, 0600);
            $part->setInformation('temp_filename', $data);
            $part->setInformation('temp_filetype', 'file');
        }

        /* Add the size information to the counter. */
        $this->_size += $part->getBytes();

        $this->_cache[] = $part;
    }

    /**
     * Delete attached files.
     *
     * @param mixed $number  Either a single integer or an array of integers
     *                       corresponding to the attachment position.
     *
     * @return array  The list of deleted filenames (MIME encoded).
     */
    function deleteAttachment($number)
    {
        global $conf;

        $names = array();

        if (!is_array($number)) {
            $number = array($number);
        }

        foreach ($number as $val) {
            $val--;
            $part = &$this->_cache[$val];
            $filename = $part->getInformation('temp_filename');
            if ($part->getInformation('temp_filetype') == 'vfs') {
                /* Delete from VFS. */
                require_once 'VFS.php';
                $vfs = &VFS::singleton($conf['vfs']['type'], Horde::getDriverConfig('vfs', $conf['vfs']['type']));
                $vfs->deleteFile(IMP_VFS_ATTACH_PATH, $filename);
            } else {
                /* Delete from filesystem. */
                @unlink($filename);
            }

            $part->setInformation('temp_filename', '');
            $part->setInformation('temp_filetype', '');

            $names[] = $part->getName(false, true);

            /* Remove the size information from the counter. */
            $this->_size -= $part->getBytes();

            unset($this->_cache[$val]);
        }

        /* Reorder the attachments. */
        $this->_cache = array_values($this->_cache);

        return $names;
    }

    /**
     * Deletes all attachments.
     */
    function deleteAllAttachments()
    {
        $numbers = array();

        for ($i = 1; $i <= $this->numberOfAttachments(); $i++) {
            $numbers[] = $i;
        }

        $this->deleteAttachment($numbers);
    }

    /**
     * Updates information in a specific attachment.
     *
     * @param integer $number  The attachment to update.
     * @param array $params    An array of update information.
     * <pre>
     * 'disposition'  --  The Content-Disposition value.
     * 'description'  --  The Content-Description value.
     * </pre>
     */
    function updateAttachment($number, $params)
    {
        $number--;
        $this->_cache[$number]->setDisposition($params['disposition']);
        $this->_cache[$number]->setDescription($params['description']);
    }

    /**
     * Returns the list of current attachments.
     *
     * @return array  The list of attachments.
     */
    function getAttachments()
    {
        return $this->_cache;
    }

    /**
     * Returns the number of attachments currently in this message.
     *
     * @return integer  The number of attachments in this message.
     */
    function numberOfAttachments()
    {
        return count($this->_cache);
    }

    /**
     * Returns the size of the attachments in bytes.
     *
     * @return integer  The size of the attachments (in bytes).
     */
    function sizeOfAttachments()
    {
        return $this->_size;
    }

    /**
     * Build a single attachment part with its data.
     *
     * @param integer $id  The ID of the part to rebuild.
     *
     * @return MIME_Part  The MIME_Part with its contents.
     */
    function buildAttachment($id)
    {
        $part = $this->_cache[($id - 1)];
        $this->_buildPartData($part);
        return $part;
    }

    /**
     * Build the MIME_Part attachments from the temporary file data.
     *
     * @param MIME_Part &$base  The base MIME_Part object to add the
     *                          attachments to.
     * @param string $charset   The charset to use for the filename.
     */
    function buildAllAttachments(&$base, $charset)
    {
        foreach ($this->_cache as $part) {
            /* Store the data inside the current part. */
            $this->_buildPartData($part);

            /* Convert the charset of the filename. */
            $name = String::convertCharset($part->getName(true), NLS::getCharset(), $charset);
            $part->setName(MIME::encode($name, $charset));

            /* Add to the base part. */
            $base->addPart($part);
        }
    }

    /**
     * Takes the temporary data for a single part and puts it into the
     * contents of that part.
     *
     * @access private
     *
     * @param MIME_Part &$part  The part to rebuild data into.
     */
    function _buildPartData(&$part)
    {
        global $conf;

        $filename = $part->getInformation('temp_filename');
        if ($part->getInformation('temp_filetype') == 'vfs') {
            require_once 'VFS.php';
            $vfs = &VFS::singleton($conf['vfs']['type'], Horde::getDriverConfig('vfs', $conf['vfs']['type']));
            $data = $vfs->read(IMP_VFS_ATTACH_PATH, $filename);
        } else {
            $data = file_get_contents($filename);
        }

        /* Set the part's contents to the raw attachment data. */
        $part->setContents($data);
    }

    /**
     * Expand macros in attribution text when replying to messages.
     *
     * @access private
     *
     * @param string $line     The line of attribution text.
     * @param string $from     The email address of the original
     *                         sender.
     * @param IMP_Headers &$h  The IMP_Headers object for the message.
     *
     * @return string  The attribution text.
     */
    function _expandAttribution($line, $from, &$h)
    {
        $addressList = '';
        $nameList = '';

        /* First we'll get a comma seperated list of email addresses
           and a comma seperated list of personal names out of $from
           (there just might be more than one of each). */
        foreach (IMP::parseAddressList($from) as $entry) {
            if (isset($entry->mailbox) && isset($entry->host)) {
                if (strlen($addressList) > 0) {
                    $addressList .= ', ';
                }
                $addressList .= $entry->mailbox . '@' . $entry->host;
            } elseif (isset($entry->mailbox)) {
                if (strlen($addressList) > 0) {
                    $addressList .= ', ';
                }
                $addressList .= $entry->mailbox;
            }
            if (isset($entry->personal)) {
                if (strlen($nameList) > 0) {
                    $nameList .= ', ';
                }
                $nameList .= $entry->personal;
            } elseif (isset($entry->mailbox)) {
                if (strlen($nameList) > 0) {
                    $nameList .= ', ';
                }
                $nameList .= $entry->mailbox;
            }
        }

        /* Define the macros. */
        if (is_array($message_id = $h->getOb('message_id'))) {
            $message_id = reset($message_id);
        }
        if (!($subject = $h->getOb('subject', true))) {
            $subject = _("[No Subject]");
        }
        $udate = strtotime($h->getOb('date', true));

        $match = array(
            /* New line. */
            '/%n/' => "\n",

            /* The '%' character. */
            '/%%/' => '%',

            /* Name and email address of original sender. */
            '/%f/' => $from,

            /* Senders email address(es). */
            '/%a/' => $addressList,

            /* Senders name(s). */
            '/%p/' => $nameList,

            /* RFC 822 date and time. */
            '/%r/' => $h->getOb('date', true),

            /* Date as ddd, dd mmm yyyy. */
            '/%d/' => String::convertCharset(@strftime("%a, %d %b %Y", $udate), NLS::getExternalCharset()),

            /* Date in locale's default. */
            '/%x/' => String::convertCharset(@strftime("%x", $udate), NLS::getExternalCharset()),

            /* Date and time in locale's default. */
            '/%c/' => String::convertCharset(@strftime("%c", $udate), NLS::getExternalCharset()),

            /* Message-ID. */
            '/%m/' => $message_id,

            /* Message subject. */
            '/%s/' => $subject
        );

        return (preg_replace(array_keys($match), array_values($match), $line));
    }

    /**
     * Obtains the cached array of MIME_Parts to be attached to this message.
     *
     * @access private
     *
     * @param string $cacheID  The cacheID of the session object.
     */
    function _retrieveMimeCache($cacheID)
    {
        if ($cacheID) {
            require_once 'Horde/SessionObjects.php';
            $cacheSess = &Horde_SessionObjects::singleton();
            $result = $cacheSess->query($cacheID);
            $cacheSess->setPruneFlag($cacheID, true);
            $this->_cache = &$result['cache'];
            $this->_size = &$result['size'];
        }
    }

    /**
     * Obtains the cache ID for the session object that contains the
     * MIME_Part objects to be attached to this message.
     * This function needs to be run at least once per pageload to save the
     * session object.
     *
     * @return string  The message cache ID if the object needs to be saved.
     *                 Else, false is returned.
     */
    function getMessageCacheId()
    {
        if (!empty($this->_cache)) {
            require_once 'Horde/SessionObjects.php';
            $cacheSess = &Horde_SessionObjects::singleton();
            $store = array(
                'cache' => $this->_cache,
                'size' => $this->_size
            );
            return $cacheSess->storeOid($store);
        } else {
            return false;
        }
    }

    /**
     * How many more attachments are allowed?
     *
     * @return mixed  Returns true if no attachment limit.
     *                Else returns the number of additional attachments
     *                allowed.
     */
    function additionalAttachmentsAllowed()
    {
        global $conf;

        if (!empty($conf['compose']['attach_count_limit'])) {
            return $conf['compose']['attach_count_limit'] - $this->numberOfAttachments();
        } else {
            return true;
        }
    }

    /**
     * What is the maximum attachment size allowed?
     *
     * @return integer  The maximum attachment size allowed (in bytes).
     */
    function maxAttachmentSize()
    {
        global $conf, $imp;

        $size = $imp['file_upload'];

        if (!empty($conf['compose']['attach_size_limit'])) {
            $size = min($size, max($conf['compose']['attach_size_limit'] - $this->sizeOfAttachments(), 0));
        }

        return $size;
    }

    /**
     * Adds the attachments to the message (in the case of a forward with
     * attachments).
     * This function MUST be called after IMP_Compose::forwardMessage().
     *
     * @param IMP_Contents &$contents  An IMP_Contents object.
     *
     * @return array  An array of PEAR_Error object on error.
     *                An empty array if successful.
     */
    function attachFilesFromMessage(&$contents)
    {
        $errors = array();

        $dl_list = $contents->getDownloadAllList(true);
        $mime_message = $contents->getMIMEMessage();

        foreach ($dl_list as $val) {
            if (is_null($this->_mimeid) || ($val != $this->_mimeid)) {
                $mime = $mime_message->getPart($val);
                if (!empty($mime)) {
                    $res = $this->addMIMEPartAttachment($mime);
                    if (is_a($res, 'PEAR_Error')) {
                        $errors[] = $res;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Convert a text/html MIME_Part message with embedded image links to
     * a multipart/related MIME_Part with the image data embedded in the part.
     *
     * @param MIME_Part $mime_part  The text/html MIME_Part object.
     *
     * @return MIME_Part  The modified MIME_Part.
     */
    function convertToMultipartRelated($mime_part)
    {
        /* Return immediately if HTTP_Request is not available. */
        $inc = include_once 'HTTP/Request.php';
        if ($inc === false) {
            return $mime_part;
        }

        /* Return immediately if not an HTML part. */
        if ($mime_part->getType() != 'text/html') {
            return $mime_part;
        }

        /* Scan for 'img' tags - specifically the 'src' parameter. If
         * none, return the original MIME_Part. */
        if (!preg_match_all('/<img[^>]+src\s*\=\s*([^\s]+)\s+/iU', $mime_part->getContents(), $results)) {
            return $mime_part;
        }

        /* Go through list of results, download the image, and create
         * MIME_Part objects with the data. */
        $img_data = array();
        $img_parts = array();
        foreach ($results[1] as $url) {
            /* Strip any quotation marks and convert '&amp;' to '&' (since
             * HTTP_Request doesn't handle the former correctly). */
            $img_url = str_replace('&amp;', '&', trim($url, '"\''));

            /* Attempt to download the image data. */
            $request = &new HTTP_Request($img_url, array('timeout' => 5));
            $request->sendRequest();

            if ($request->getResponseCode() == '200') {
                /* We need to determine the image type.  Try getting
                 * that information from the returned HTTP
                 * content-type header.  TODO: Use MIME_Magic if this
                 * fails (?) */
                $part = &new MIME_Part($request->getResponseHeader('content-type'), $request->getResponseBody(), null, 'attachment', '8bit');
                $img_data[$url] = '"cid:' . $part->setContentID() . '"';
                $img_parts[] = $part;
            }
        }

        /* If we could not successfully download any data, return the
         * original MIME_Part now. */
        if (empty($img_data)) {
            return $mime_part;
        }

        /* Replace the URLs with with CID tags. */
        $text = $mime_part->getContents();
        $text = str_replace(array_keys($img_data), array_values($img_data), $text);
        $mime_part->setContents($text);

        /* Create new multipart/related part. */
        $related = &new MIME_Part('multipart/related');

        /* Get the CID for the 'root' part. Although by default the
         * first part is the root part (RFC 2387 [3.2]), we may as
         * well be explicit and put the CID in the 'start'
         * parameter. */
        $related->setContentTypeParameter('start', $mime_part->setContentID());

        /* Add the root part and the various images to the multipart
         * object. */
        $related->addPart($mime_part);
        foreach ($img_parts as $val) {
            $related->addPart($val);
        }

        return $related;
    }

    /**
     * Remove all attachments from an email message and replace with
     * urls to downloadable links. Should properly save all
     * attachments to a new folder and remove the MIME_Parts for the
     * attachments.
     *
     * @param string    $baseurl    The base URL for creating the links.
     * @param MIME_Part $base_part  The body of the message.
     * @param string    $auth       The authorized user who owns the attachments.
     *
     * @return MIME_Part  Modified part with links to attachments. Returns
     *                    PEAR_Error on error.
     */
    function linkAttachments($baseurl, $base_part, $auth)
    {
        global $conf, $prefs;

        if (!$conf['compose']['link_attachments']) {
            return PEAR::raiseError(_("Linked attachments are forbidden."));
        }

        require_once 'VFS.php';
        $vfs = &VFS::singleton($conf['vfs']['type'], Horde::getDriverConfig('vfs', $conf['vfs']['type']));

        $ts = gmmktime();
        $fullpath = sprintf('%s/%s/%d', IMP_VFS_LINK_ATTACH_PATH, $auth, $ts);

        $trailer = String::convertCharset(_("Attachments"), NLS::getCharset(), $base_part->getCharset());

        if ($prefs->getValue('delete_attachments_monthly')) {
            /* Determine the first day of the month in which the current
             * attachments will be ripe for deletion, then subtract 1 second
             * to obtain the last day of the previous month. */
            $del_time = gmmktime(0, 0, 0, date('n') + $prefs->getValue('delete_attachments_monthly_keep') + 1, 1, date('Y')) - 1;
            $trailer .= String::convertCharset(' (' . sprintf(_("Links will expire on %s"), strftime('%x', $del_time)) . ')', NLS::getCharset(), $base_part->getCharset());
        }

        foreach ($this->_cache as $att) {
            $trailer .= "\n" . Util::addParameter($baseurl, array('u' => $auth,
                                                                  't' => $ts,
                                                                  'f' => $att->getName()),
                                                  null, false);
            if ($conf['compose']['use_vfs']) {
                $res = $vfs->rename(IMP_VFS_ATTACH_PATH, $att->getInformation('temp_filename'), $fullpath, escapeshellcmd($att->getName()));
            } else {
                $data = file_get_contents($att->getInformation('temp_filename'));
                $res = $vfs->writeData($fullpath, escapeshellcmd($att->getName()), $data, true);
            }
            if (is_a($res, 'PEAR_Error')) {
                return $res;
            }
        }

        $this->deleteAllAttachments();

        if ($base_part->getPrimaryType() == 'multipart') {
            $mixed_part = &new MIME_Part('multipart/mixed');
            $mixed_part->addPart($base_part);
            $link_part = &new MIME_Part('text/plain', $trailer, $base_part->getCharset(), 'inline', $base_part->getCurrentEncoding());
            $link_part->setDescription(_("Attachment Information"));
            $mixed_part->addPart($link_part);
            return $mixed_part;
        } else {
            $base_part->appendContents("\n-----\n" . $trailer, $base_part->getCurrentEncoding());
            return $base_part;
        }

    }

}
