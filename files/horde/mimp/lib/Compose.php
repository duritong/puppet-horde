<?php

require_once 'Horde/MIME.php';

/** The virtual path to use for VFS data. */
define('MIMP_VFS_ATTACH_PATH', '.horde/mimp/compose');

/**
 * The MIMP_Compose:: class contains functions related to generating
 * outgoing mail messages.
 *
 * $Horde: mimp/lib/Compose.php,v 1.46.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package MIMP
 */
class MIMP_Compose {

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
     * Constructor
     *
     * @param array $params  Parameters needed.
     */
    function MIMP_Compose($params = array())
    {
        if (isset($params['cacheID'])) {
            $this->_retrieveMimeCache($params['cacheID']);
        }
    }

    /**
     * Send a message.
     *
     * @param string $email           The e-mail list to send to.
     * @param MIMP_Headers &$headers  The MIMP_Headers object holding this
     *                                message's headers.
     * @param mixed &$message         Either the message text (string) or a
     *                                MIME_Message object that contains the
     *                                text to send.
     * @param string $charset         The charset that was used for the
     *                                headers.
     *
     * @return mixed  True on success, PEAR_Error object on error.
     */
    function sendMessage($email, &$headers, &$message, $charset)
    {
        global $conf;

        require_once 'Mail.php';

        /* We don't actually want to alter the contents of the $conf['mailer']
           array, so we make a copy of the current settings. We will apply
           our modifications (if any) to the copy, instead. */
        $params = $conf['mailer']['params'];

        /* If SMTP authentication has been requested, use either the username
           and password provided in the configuration or populate the username
           and password fields based on the current values for the user.
           Note that we assume that the username and password values from the
           current IMAP / POP3 connection are valid for SMTP authentication as
           well. */
        if (!empty($params['auth']) && empty($params['username'])) {
            $params['username'] = $_SESSION['mimp']['user'];
            $params['password'] = Secret::read(Secret::getKey('mimp'), $_SESSION['mimp']['pass']);
        }

        /* Force the SMTP host and port value to the current SMTP server if
         * one has been selected for this connection. */
        if (!empty($_SESSION['mimp']['smtphost'])) {
            $params['host'] = $_SESSION['mimp']['smtphost'];
        }
        if (!empty($_SESSION['mimp']['smtpport'])) {
            $params['port'] = $_SESSION['mimp']['smtpport'];
        }

        /* Add the site headers. */
        $headers->addSiteHeaders();

        /* If $message is a string, we need to get a MIME_Message
           object to encode the headers. */
        if (is_string($message)) {
            $msg = $message;
            $mime_message = &new MIME_Message($_SESSION['mimp']['maildomain']);
            $headerArray = $mime_message->encode($headers->toArray(), $charset);
        } else {
            $msg = $message->toString();
            $headerArray = $message->encode($headers->toArray(), $charset);
        }

        /* Make sure the message has a trailing newline. */
        if (substr($msg, -1) != "\n") {
            $msg .= "\n";
        }

        $mailer = Mail::factory($conf['mailer']['type'], $params);
        if (is_a($mailer, 'PEAR_Error')) {
            return $mailer;
        }

        /* Properly encode the addresses we're sending to. */
        $email = MIME::encodeAddress($email, null, $_SESSION['mimp']['maildomain']);
        if (is_a($email, 'PEAR_Error')) {
            return $email;
        }

        /* Validate the recipient addresses. */
        require_once 'Mail/RFC822.php';
        $parser = &new Mail_RFC822();
        $result = $parser->parseAddressList($email, $_SESSION['mimp']['maildomain'], null, true);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $result = $mailer->send($email, $headerArray, $msg);

        if (is_a($result, 'PEAR_Error') &&
            $conf['mailer']['type'] == 'sendmail') {
            // Interpret return values as defined in /usr/include/sysexits.h
            switch ($result->getCode()) {
            case 64: // EX_USAGE
                $error = 'sendmail: ' . _("command line usage error") . ' (64)';                break;

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
                $error = 'sendmail: ' . _("remote error in protocol") . ' (76)';                break;

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
     * @param MIMP_Contents &$mimp_contents  A MIMP_Contents object.
     *
     * @return string  The text of the "body" part of the message.
     *                 Returns an empty string if no "body" found.
     */
    function findBody(&$mimp_contents)
    {
        if (is_null($this->_mimeid)) {
            $this->_mimeid = $mimp_contents->findBody();
            if (is_null($this->_mimeid)) {
                return '';
            }
        }

        $mime_part = &$mimp_contents->getDecodedMIMEPart($this->_mimeid);
        $body = $mime_part->getContents();

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
     * @param MIMP_Contents &$mimp_contents  A MIMP_Contents object.
     *
     * @return string  The ID of the mime part's body.
     */
    function getBodyId(&$mimp_contents)
    {
        if (is_null($this->_mimeid)) {
            $this->findBody($mimp_contents);
        }
        return $this->_mimeid;
    }

    /**
     * Determine the reply text for a message.
     *
     * @param MIMP_Contents &$mimp_contents  A MIMP_Contents object.
     * @param string $from                   The email address of the original
     *                                       sender.
     * @param MIMP_Headers &$h               The MIMP_Headers object for the
     *                                       message.
     *
     * @return string  The text of the body part of the message to use for the
     *                 reply.
     */
    function replyMessage(&$mimp_contents, $from, &$h)
    {
        global $prefs;

        $mime_message = $mimp_contents->getMIMEMessage();
        $msg = $this->findBody($mimp_contents);
        $msg = $mime_message->replaceEOL($msg, "\n");

        if (!is_null($this->_mimeid)) {
            require_once 'Text/Flowed.php';
            $old_part = $mime_message->getPart($this->_mimeid);
            if ($old_part->getContentTypeParameter('format') == 'flowed') {
                /* We need to convert the flowed text to fixed text before
                 * we begin working on it. */
                $flowed = &new Text_Flowed($msg);
                if (($mime_message->getContentTypeParameter('delsp') == 'yes') &&
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
            $msg = '[' . _("No message body text") . ']' . "\n";
        } elseif (!empty($h)) {
            $msghead = '----- ';
            if (($from = $h->getFromAddress())) {
                $msghead .= sprintf(_("Message from %s"), $from);
            } else {
                $msghead .= _("Message");
            }
            $msghead .= " -----\n";
            $msg = $msghead . "\n\n" . $msg;
        } else {
            $msg .= "\n";
        }

        return "\n\n\n" . $msg;
    }

    /**
     * Determine the text for a forwarded message.
     *
     * @param MIMP_Contents &$mimp_contents  A MIMP_Contents object.
     * @param MIMP_Headers &$h               The MIMP_Headers object for the
     *                                       message.
     *
     * @return string  The text of the body part of the message to use for the
     *                 forward.
     */
    function forwardMessage(&$mimp_contents, &$h)
    {
        require_once 'Horde/Text.php';

        $msg = "\n\n\n----- ";

        if (($from = $h->getFromAddress())) {
            $msg .= sprintf(_("Forwarded Message from %s"), $from);
        } else {
            $msg .= _("Forwarded Message");
        }

        $msg .= " -----\n";

        if (($date = $h->getOb('date', true))) {
            $msg .= _("    Date: ") . $date . "\n";
        }
        if (($from_ob = MIME::addrArray2String($h->getOb('from')))) {
            $msg .= _("    From: ") . $from_ob . "\n";
        }
        if (($rep_ob = MIME::addrArray2String($h->getOb('reply_to')))) {
            $msg .= _("Reply-To: ") . $rep_ob . "\n";
        }
        if (($subject = $h->getOb('subject', true))) {
            $msg .= _(" Subject: ") . $subject . "\n";
        }
        if (($to_ob = MIME::addrArray2String($h->getOb('to')))) {
            $msg .= _("      To: ") . $to_ob . "\n";
        }

        $msg .= "\n";

        $fwd_msg = $this->findBody($mimp_contents);
        if (!is_null($this->_mimeid)) {
            $mime_message = $mimp_contents->getMIMEMessage();
            $old_part = $mime_message->getPart($this->_mimeid);
            $fwd_msg = String::convertCharset($fwd_msg, $old_part->getCharset());
        }

        $msg .= $fwd_msg;
        $msg .= "\n\n----- " . _("End Forwarded Message") . " -----\n";

        return $msg;
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

        $vfs = $conf['compose']['use_vfs'];

        /* Decode the contents. */
        $part->transferDecodeContents();

        /* Extract the data from the currently existing MIME_Part and then
           delete it. If this is an unknown MIME part, we must save to a
           temporary file to run the file analysis on it. */
        if (!$vfs || ($part->getType() == 'application/octet-stream')) {
            $attachment = Horde::getTempFile('mimpatt', $vfs);
            $fp = fopen($attachment, 'w');
            fwrite($fp, $part->getContents());
            fclose($fp);

            if ($part->getType() == 'application/octet-stream') {
                require_once 'Horde/MIME/Magic.php';

                /* Try to determine the MIME type from 1) analysis of the file
                   (if available) and, if that fails, 2) from the extension. */
                if (!($type = MIME_Magic::analyzeFile($attachment, isset($conf['mime']['magic_db']) ? $conf['mime']['magic_db'] : null))) {
                    $type = MIME_Magic::filenameToMIME($attachment, false);
                }
                $part->setType($type);
            }
        }

        /* Set the size of the Part explicitly since we delete the contents
           later on in this function. */
        $part->setBytes($part->getBytes());

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
            VFS_GC::gc($vfs, MIMP_VFS_ATTACH_PATH, 86400);
            $cacheID = md5(mt_rand() . microtime());
            if ($vfs_file) {
                $result = $vfs->write(MIMP_VFS_ATTACH_PATH, $cacheID, $data, true);
            } else {
                $result = $vfs->writeData(MIMP_VFS_ATTACH_PATH, $cacheID, $data, true);
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
     * @return array  The list of deleted filenames.
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
                $vfs->deleteFile(MIMP_VFS_ATTACH_PATH, $filename);
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
     * Returns the number of attachments currently in this message.
     *
     * @return integer  The number of attachments in this message.
     */
    function numberOfAttachments()
    {
        return count($this->_cache);
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
     * Build the MIME_Part attachments from the temporary file data.
     *
     * @param MIME_Part &$base  The base MIME_Part object to add the
     *                          attachments to.
     */
    function buildAllAttachments(&$base)
    {
        foreach ($this->_cache as $part) {
            /* Store the data inside the current part. */
            $this->_buildPartData($part);

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
            $data = $vfs->read(MIMP_VFS_ATTACH_PATH, $filename);
        } else {
            $data = file_get_contents($filename);
        }

        /* Set the part's contents to the raw attachment data. */
        $part->setContents($data);
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
     * Adds the attachments to the message (in the case of a forward with
     * attachments).
     *
     * @param MIME_Message &$message  The MIME_Message object.
     *
     * @return array  An array of PEAR_Error object on error.
     *                An empty array if successful.
     */
    function attachFilesFromMessage(&$message)
    {
        $errors = array();

        foreach ($message->getParts() as $ref => $mime) {
            if (($ref != 1) ||
                ($mime->getPrimaryType() != MIME::type(TYPETEXT))) {
                $res = $this->addMIMEPartAttachment($mime);
                if (is_a($res, 'PEAR_Error')) {
                    $errors[] = $res;
                }
            }
        }

        return $errors;
    }

}
