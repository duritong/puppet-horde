<?php

require_once 'Horde/Identity.php';
require_once IMP_BASE . '/lib/Compose.php';

/**
 * The IMP_Spam:: class contains functions related to reporting spam
 * messages in IMP.
 *
 * $Horde: imp/lib/Spam.php,v 1.3.4.14 2007/01/02 13:54:56 jan Exp $
 *
 * Copyright 2004-2007 Michael Slusarz <slusarz@curecanti.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@curecanti.org>
 * @since   IMP 4.0
 * @package IMP
 */
class IMP_Spam {

    /**
     * The IMP_Compose:: object used by the class.
     *
     * @var IMP_Compose
     */
    var $_imp_compose;

    /**
     * The IMP_Identity:: object used by the class.
     *
     * @var IMP_Identity
     */
    var $_identity;

    /**
     * Constructor.
     */
    function IMP_Spam()
    {
        $this->_imp_compose = &new IMP_Compose();
        $this->_identity = &Identity::singleton(array('imp', 'imp'));
    }

    /**
     * Report a list of messages as spam, based on the local configuration
     * parameters.
     *
     * @param mixed &$indices  See IMP::parseIndicesList().
     * @param string $action   Either 'spam' or 'notspam'.
     */
    function reportSpam(&$indices, $action)
    {
        /* Abort immediately if spam reporting has not been enabled. */
        if (empty($GLOBALS['conf'][$action]['reporting'])) {
            return;
        }

        /* Exit if there are no messages. */
        if (!($msgList = IMP::parseIndicesList($indices))) {
            return;
        }

        require_once IMP_BASE . '/lib/MIME/Contents.php';
        require_once IMP_BASE . '/lib/IMAP.php';
        $imp_imap = &IMP_IMAP::singleton();

        /* We can report 'program' and 'bounce' messages as the same since
         * they are both meant to indicate that the message has been reported
         * to some program for analysis. */
        $email_msg_count = $report_msg_count = 0;

        foreach ($msgList as $folder => $msgIndices) {
            /* Switch folders, if necessary (only valid for IMAP). */
            $imp_imap->changeMbox($folder);

            foreach ($msgIndices as $msgnum) {
                /* Fetch the raw message contents (headers and complete
                 * body). */
                $imp_contents = &IMP_Contents::singleton($msgnum . IMP_IDX_SEP . $folder);

                $to = null;
                $report_flag = false;
                $raw_msg = null;

                /* If a (not)spam reporting program has been provided, use
                 * it. */
                if (!empty($GLOBALS['conf'][$action]['program'])) {
                    $raw_msg = $imp_contents->fullMessageText();
                    /* Use a pipe to write the message contents. This should
                     * be secure. */
                    $prog = str_replace('%u', escapeshellarg(Auth::getAuth()), $GLOBALS['conf'][$action]['program']);
                    $proc = proc_open($prog,
                                      array(0 => array('pipe', 'r'),
                                            1 => array('pipe', 'w'),
                                            2 => array('pipe', 'w')),
                                      $pipes);
                    if (!is_resource($proc)) {
                        Horde::logMessage('Cannot open process ' . $prog, __FILE__, __LINE__, PEAR_LOG_ERR);
                        return;
                    }
                    fwrite($pipes[0], $raw_msg);
                    fclose($pipes[0]);
                    $stderr = '';
                    while (!feof($pipes[2])) {
                        $stderr .= fgets($pipes[2]);
                    }
                    fclose($pipes[2]);
                    if (!empty($stderr)) {
                        Horde::logMessage('Error reporting spam: ' . $stderr, __FILE__, __LINE__, PEAR_LOG_ERR);
                    }
                    proc_close($proc);
                    $report_msg_count++;
                    $report_flag = true;
                }

                /* If a (not)spam reporting email address has been provided,
                 * use it. */
                if (!empty($GLOBALS['conf'][$action]['email'])) {
                    if (!isset($raw_msg)) {
                        $raw_msg = $imp_contents->fullMessageText();
                    }
                    $this->_sendSpamReportMessage($action, $raw_msg);
                    $email_msg_count++;
               }

                /* If a (not)spam bounce email address has been provided, use
                 * it. */
                if (!empty($GLOBALS['conf'][$action]['bounce'])) {
                    $to = $GLOBALS['conf'][$action]['bounce'];
                } elseif (!empty($GLOBALS['conf']['hooks']['spam_bounce'])) {
                    /* Call the bounce email generation hook, if requested. */
                    require_once HORDE_BASE . '/config/hooks.php';
                    if (function_exists('_imp_hook_spam_bounce')) {
                        $to = call_user_func('_imp_hook_spam_bounce', $action);
                    }
                }

                if ($to) {
                    require_once IMP_BASE . '/lib/MIME/Headers.php';
                    $imp_headers = &new IMP_Headers($msgnum);
                    $imp_headers->buildHeaders();

                    $from_addr = $this->_identity->getFromAddress();
                    $imp_headers->addResentHeaders($from_addr, $to);

                    /* We need to set the Return-Path header to the current
                     * user - see RFC 2821 [4.4]. */
                    $imp_headers->removeHeader('return-path');
                    $imp_headers->addHeader('Return-Path', $from_addr);

                    $bodytext = $imp_contents->getBody();

                    $this->_imp_compose->sendMessage($to, $imp_headers, $bodytext, NLS::getCharset());
                    if (!$report_flag) {
                        $report_msg_count++;
                    }
                }
            }
        }

        /* Report what we've done. */
        if ($report_msg_count) {
            switch ($action) {
            case 'spam':
                if ($report_msg_count > 1) {
                    $GLOBALS['notification']->push(sprintf(_("%d messages have been reported as spam."), $report_msg_count), 'horde.message');
                } else {
                    $GLOBALS['notification']->push(_("1 message has been reported as spam."), 'horde.message');
                }
                break;

            case 'notspam':
                if ($report_msg_count > 1) {
                    $GLOBALS['notification']->push(sprintf(_("%d messages have been reported as not spam."), $report_msg_count), 'horde.message');
                } else {
                    $GLOBALS['notification']->push(_("1 message has been reported as not spam."), 'horde.message');
                }
                break;
            }
        }

        if ($email_msg_count) {
            switch ($action) {
            case 'spam':
                if ($email_msg_count > 1) {
                    $GLOBALS['notification']->push(sprintf(_("%d messages have been reported as spam to your system administrator."), $email_msg_count), 'horde.message');
                } else {
                    $GLOBALS['notification']->push(_("1 message has been reported as spam to your system administrator."), 'horde.message');
                }
                break;

            case 'notspam':
                if ($email_msg_count > 1) {
                    $GLOBALS['notification']->push(sprintf(_("%d messages have been reported as not spam to your system administrator."), $email_msg_count), 'horde.message');
                } else {
                    $GLOBALS['notification']->push(_("1 message has been reported as not spam to your system administrator."), 'horde.message');
                }
                break;
            }
        }
    }

    /**
     * Send a (not)spam message to the sysadmin.
     *
     * @access private
     *
     * @param string $action  The action type.
     * @param string $data    The message data.
     */
    function _sendSpamReportMessage($action, $data)
    {
        require_once 'Horde/MIME/Message.php';

        /* Build the MIME structure. */
        $mime = &new MIME_Message();
        $mime->setType('multipart/digest');
        $mime->addPart(new MIME_Part('message/rfc822', $data));

        $spam_headers = &new IMP_Headers();
        $spam_headers->addMessageIdHeader();
        $spam_headers->addHeader('Date', date('r'));
        $spam_headers->addHeader('To', $GLOBALS['conf'][$action]['email']);
        $spam_headers->addHeader('From', $this->_identity->getFromLine());
        $spam_headers->addHeader('Subject', _("$action Report from") . ' ' . $GLOBALS['imp']['user']);
        $spam_headers->addMIMEHeaders($mime);

        /* Send the message. */
        $this->_imp_compose->sendMessage($GLOBALS['conf'][$action]['email'], $spam_headers, $mime, NLS::getCharset());
    }

}
