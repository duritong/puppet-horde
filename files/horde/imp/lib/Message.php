<?php

/* Constants used in copy(). */
define('IMP_MESSAGE_MOVE', 1);
define('IMP_MESSAGE_COPY', 2);

/**
 * The IMP_Message:: class contains all functions related to handling messages
 * within IMP. Actions such as moving, copying, and deleting messages are
 * handled in here so that code need not be repeated between mailbox, message,
 * and other pages.
 *
 * Indices format:
 * ===============
 * For any function below that requires an $indices parameter, see
 * IMP::parseIndicesList() for the list of allowable inputs.
 *
 * $Horde: imp/lib/Message.php,v 1.164.8.49 2007/07/03 14:56:26 slusarz Exp $
 *
 * Copyright 2000-2007 Chris Hyde <chris@jeks.net>
 * Copyright 2000-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chris Hyde <chris@jeks.net>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 2.3
 * @package IMP
 */
class IMP_Message {

    /**
     * The IMP_IMAP object for the current server.
     *
     * @var IMP_IMAP
     */
    var $_impImap;

    /**
     * Using POP to access mailboxes?
     *
     * @var boolean
     */
    var $_usepop = false;

    /**
     * Returns a reference to the global IMP_Message object, only creating it
     * if it doesn't already exist. This ensures that only one IMP_Message
     * instance is instantiated for any given session.
     *
     * This method must be invoked as:<code>
     *   $imp_message = &IMP_Message::singleton();
     * </code>
     *
     * @return IMP_Message  The IMP_Message instance.
     */
    function &singleton()
    {
        static $message;

        if (!isset($message)) {
            $message = new IMP_Message();
        }

        return $message;
    }

    /**
     * Constructor.
     */
    function IMP_Message()
    {
        if ($GLOBALS['imp']['base_protocol'] == 'pop3') {
            $this->_usepop = true;
        }

        require_once IMP_BASE . '/lib/IMAP.php';
        $this->_impImap = &IMP_IMAP::singleton();
    }

    /**
     * Copies or moves a list of messages to a new folder.
     * Handles use of the IMP_SEARCH_MBOX mailbox and the Trash folder.
     *
     * @param string $targetMbox  The mailbox to move/copy messages to.
     * @param integer $action     Either IMP_MESSAGE_MOVE or IMP_MESSAGE_COPY.
     * @param mixed &$indices     See above.
     *
     * @return boolean  True if successful, false if not.
     */
    function copy($targetMbox, $action, &$indices)
    {
        global $imp, $notification, $prefs;

        if (!($msgList = IMP::parseIndicesList($indices))) {
            return false;
        }

        switch ($action) {
        case IMP_MESSAGE_MOVE:
            $imap_flags = CP_UID | CP_MOVE;
            $message = _("There was an error moving messages from \"%s\" to \"%s\". This is what the server said");
            break;

        case IMP_MESSAGE_COPY:
            $imap_flags = CP_UID;
            $message = _("There was an error copying messages from \"%s\" to \"%s\". This is what the server said");
            break;
        }

        $return_value = true;

        foreach ($msgList as $folder => $msgIndices) {
            $msgIdxString = implode(',', $msgIndices);

            /* Switch folders, if necessary (only valid for IMAP). */
            $this->_impImap->changeMbox($folder);

            /* Attempt to copy/move messages to new mailbox. */
            if (!@imap_mail_copy($imp['stream'], $msgIdxString, $targetMbox, $imap_flags)) {
                $notification->push(sprintf($message, IMP::displayFolder($folder), IMP::displayFolder($targetMbox)) . ': ' . imap_last_error(), 'horde.error');
                $return_value = false;
            }

            /* If moving, and using the trash, expunge immediately. */
            if ($prefs->getValue('use_trash') &&
                ($action == IMP_MESSAGE_MOVE)) {
                @imap_expunge($imp['stream']);
            }
        }

        /* Update the mailbox. */
        if (is_a($indices, 'IMP_Mailbox')) {
            if ($action == IMP_MESSAGE_COPY) {
                $indices->updateMailbox(IMP_MAILBOX_COPY, $return_value);
            } else {
                $indices->updateMailbox(IMP_MAILBOX_MOVE, $return_value);
            }
        }

        return $return_value;
    }

    /**
     * Deletes a list of messages taking into account whether or not a
     * Trash folder is being used.
     * Handles use of the IMP_SEARCH_MBOX mailbox and the Trash folder.
     *
     * @param mixed &$indices   See above.
     * @param boolean $nuke     Override user preferences and nuke (i.e.
     *                          permanently delete) the messages instead?
     * @param boolean $keeplog  Should any history information of the
     *                          message be kept?
     *
     * @return boolean  True if successful, false if not.
     */
    function delete(&$indices, $nuke = false, $keeplog = false)
    {
        global $conf, $imp, $notification, $prefs;

        if (!($msgList = IMP::parseIndicesList($indices))) {
            return false;
        }

        $trash = IMP::folderPref($prefs->getValue('trash_folder'), true);
        $use_trash = $prefs->getValue('use_trash');
        if ($use_trash && !$trash) {
            $notification->push(_("Can not move messages to Trash - no Trash mailbox set in preferences."), 'horde.error');
            return false;
        }

        $return_value = true;
        $use_vtrash = $prefs->getValue('use_vtrash');
        $maillog_update = (!$keeplog && !empty($conf['maillog']['use_maillog']));

        /* If the folder we are deleting from has changed. */
        foreach ($msgList as $folder => $msgIndices) {
            $sequence = implode(',', $msgIndices);

            /* Switch folders, if necessary (only valid for IMAP). */
            $this->_impImap->changeMbox($folder);

            /* Trash is only valid for IMAP mailboxes. */
            if (!$this->_usepop &&
                !$nuke &&
                !$use_vtrash &&
                $use_trash &&
                ($folder != $trash)) {
                if (!isset($imp_folder)) {
                    include_once IMP_BASE . '/lib/Folder.php';
                    $imp_folder = &IMP_Folder::singleton();
                }

                if (!$imp_folder->exists($trash)) {
                    if (!$imp_folder->create($trash, $prefs->getValue('subscribe'))) {
                        return false;
                    }
                }

                if (!@imap_mail_move($imp['stream'], $sequence, $trash, CP_UID)) {
                    $error_msg = imap_last_error();
                    $error = true;

                    /* Handle the case when the mailbox is overquota (moving
                     * message to trash would fail) by first deleting then
                     * copying message to Trash. */
                    if ((stristr($error_msg, 'over quota') !== false) ||
                        (stristr($error_msg, 'quota exceeded') !== false) ||
                        (stristr($error_msg, 'exceeded your mail quota') !== false)) {
                        $error = false;
                        $msg_text = array();

                        /* Get text of deleted messages. */
                        foreach ($msgIndices as $val) {
                            $msg_text[] = imap_fetchheader($imp['stream'], $val, FT_UID | FT_PREFETCHTEXT) . imap_body($imp['stream'], $val, FT_UID);
                        }
                        @imap_delete($imp['stream'], $sequence, FT_UID);
                        @imap_expunge($imp['stream']);

                        /* Save messages in Trash folder. */
                        foreach ($msg_text as $val) {
                            if (!@imap_append($imp['stream'], IMP::serverString($trash), $val)) {
                                $error = true;
                                break;
                            }
                        }
                    }

                    if ($error) {
                        $notification->push(sprintf(_("There was an error deleting messages from the folder \"%s\". This is what the server said"), IMP::displayFolder($folder)) . ': ' . $error_msg, 'horde.error');
                        $return_value = false;
                    }
                } else {
                    @imap_expunge($imp['stream']);
                }
            } else {
                /* Get the list of Message-IDs for the deleted messages if
                 * using maillogging. */
                if ($maillog_update) {
                    $overview = @imap_fetch_overview($imp['stream'], $sequence, FT_UID);
                }

                /* Delete the messages. */
                if (!@imap_delete($imp['stream'], $sequence, FT_UID)) {
                    if ($this->_usepop) {
                        $notification->push(sprintf(_("There was an error deleting messages. This is what the server said: %s"), imap_last_error()), 'horde.error');
                    } else {
                        $notification->push(sprintf(_("There was an error deleting messages from the folder \"%s\". This is what the server said"), IMP::displayFolder($folder)) . ': ' . imap_last_error(), 'horde.error');
                    }
                    $return_value = false;
                } else {
                    $delIndices = array($folder => $msgIndices);
                    $flag_str = '\\DELETED';

                    if ($this->_usepop ||
                        $nuke ||
                        ($use_trash && ($folder == $trash))) {
                        /* Purge messages in the trash folder immediately. */
                        $this->flag($flag_str, $delIndices);
                        @imap_expunge($imp['stream']);
                    } else {
                        /* If we are using vitrual trash, we must mark the 
                         * message as seen or else it will appear as an
                         * 'unseen' message for purposes of new message
                         * counts. */
                        if ($use_vtrash) {
                            $this->flag($flag_str . ' \\SEEN', $delIndices);
                        }
                    }

                    /* Get the list of Message-IDs deleted, and remove
                     * the information from the mail log. */
                    if ($maillog_update) {
                        $msg_ids = array();
                        foreach ($overview as $val) {
                            if (!empty($val->message_id)) {
                                $msg_ids[] = $val->message_id;
                            }
                        }
                        require_once IMP_BASE . '/lib/Maillog.php';
                        IMP_Maillog::deleteLog($msg_ids);
                    }
                }
            }
        }

        /* Update the mailbox. */
        if (is_a($indices, 'IMP_Mailbox')) {
            $indices->updateMailbox(IMP_MAILBOX_DELETE, $return_value);
        }

        return $return_value;
    }

    /**
     * Undeletes a list of messages.
     * Handles the IMP_SEARCH_MBOX mailbox.
     * This function works with IMAP only, not POP3.
     *
     * @param mixed &$indices  See above.
     *
     * @return boolean  True if successful, false if not.
     */
    function undelete(&$indices)
    {
        global $imp, $notification;

        if (!($msgList = IMP::parseIndicesList($indices))) {
            return false;
        }

        $return_value = true;

        foreach ($msgList as $folder => $msgIndices) {
            $msgIdxString = implode(',', $msgIndices);

            /* Switch folders, if necessary. */
            $this->_impImap->changeMbox($folder);

            if ($imp['stream'] &&
                !@imap_undelete($imp['stream'], $msgIdxString, FT_UID)) {
                $notification->push(sprintf(_("There was an error deleting messages in the folder \"%s\". This is what the server said"), IMP::displayFolder($folder)) . ': ' . imap_last_error(), 'horde.error');
                $return_value = false;
            }
        }

        /* Update the mailbox. */
        if (is_a($indices, 'IMP_Mailbox')) {
            $indices->updateMailbox(IMP_MAILBOX_UNDELETE, $return_value);
        }

        return $return_value;
    }

    /**
     * Copies or moves a list of messages to a tasklist or notepad.
     * Handles use of the IMP_SEARCH_MBOX mailbox and the Trash folder.
     *
     * @param string $list      The list in which the task or note will be
     *                          created.
     * @param integer $action   Either IMP_MESSAGE_MOVE or IMP_MESSAGE_COPY.
     * @param mixed $indices    See above.
     * @param string $type      The object type to create, defaults to task.
     *
     * @return boolean  True if successful, false if not.
     */
    function createTasksOrNotes($list, $action, &$indices, $type = 'task')
    {
        global $registry, $notification, $prefs;

        if (!($msgList = IMP::parseIndicesList($indices))) {
            return false;
        }

        require_once IMP_BASE . '/lib/Compose.php';
        require_once IMP_BASE . '/lib/MIME/Contents.php';
        require_once IMP_BASE . '/lib/MIME/Headers.php';
        require_once 'Text/Flowed.php';
        require_once 'Horde/iCalendar.php';

        foreach ($msgList as $folder => $msgIndices) {
            foreach ($msgIndices as $index) {
                /* Fetch the message headers. */
                $imp_headers = &new IMP_Headers($index);
                $imp_headers->buildHeaders();
                $subject = $imp_headers->getValue('subject');

                /* Fetch the message contents. */
                $imp_contents = &IMP_Contents::singleton($index . IMP_IDX_SEP . $folder);
                $imp_contents->buildMessage();

                /* Extract the message body. */
                $imp_compose = &new IMP_Compose();
                $mime_message = $imp_contents->getMIMEMessage();
                $body_id = $imp_compose->getBodyId($imp_contents);
                $body = $imp_compose->findBody($imp_contents);

                /* Re-flow the message for prettier formatting. */
                $flowed = &new Text_Flowed($mime_message->replaceEOL($body, "\n"));
                if (($mime_message->getContentTypeParameter('delsp') == 'yes') &&
                    method_exists($flowed, 'setDelSp')) {
                    $flowed->setDelSp(true);
                }
                $body = $flowed->toFlowed(false);

                /* Convert to current charset */
                /* TODO: When Horde_iCalendar supports setting of charsets
                 * we need to set it there instead of relying on the fact
                 * that both Nag and IMP use the same charset. */
                $body_part = $mime_message->getPart($body_id);
                $body = String::convertCharset($body, $body_part->getCharset(), NLS::getCharset());

                /* Create a new iCalendar. */
                $vCal = &new Horde_iCalendar();
                $vCal->setAttribute('PRODID', '-//The Horde Project//IMP ' . IMP_VERSION . '//EN');
                $vCal->setAttribute('METHOD', 'PUBLISH');

                switch ($type) {
                case 'task':
                    /* Create a new vTodo object using this message's
                     * contents. */
                    $vTodo = &Horde_iCalendar::newComponent('vtodo', $vCal);
                    $vTodo->setAttribute('SUMMARY', $subject);
                    $vTodo->setAttribute('DESCRIPTION', $body);
                    $vTodo->setAttribute('PRIORITY', '3');

                    /* Get the list of editable tasklists. */
                    $lists = $registry->call('tasks/listTasklists',
                                             array(false, PERMS_EDIT));

                    /* Attempt to add the new vTodo item to the requested
                     * tasklist. */
                    $res = $registry->call('tasks/import',
                                           array($vTodo, 'text/x-vtodo', $list));
                    break;

                case 'note':
                    /* Create a new vNote object using this message's
                     * contents. */
                    $vNote = &Horde_iCalendar::newComponent('vnote', $vCal);
                    $vNote->setAttribute('BODY', $subject . "\n". $body);

                    /* Get the list of editable notepads. */
                    $lists = $registry->call('notes/listNotepads',
                                             array(false, PERMS_EDIT));

                    /* Attempt to add the new vNote item to the requested
                     * notepad. */
                    $res = $registry->call('notes/import',
                                           array($vNote, 'text/x-vnote', $list));
                    break;
                }

                if (is_a($res, 'PEAR_Error')) {
                    $notification->push($res, $res->getCode());
                } elseif (!$res) {
                    switch ($type) {
                    case 'task': $notification->push(_("An unknown error occured while creating the new task."), 'horde.error'); break;
                    case 'note': $notification->push(_("An unknown error occured while creating the new note."), 'horde.error'); break;
                    }
                } else {
                    $name = '"' . htmlspecialchars($subject) . '"';

                    /* Attempt to convert the object name into a hyperlink. */
                    switch ($type) {
                    case 'task':
                        $link = $registry->link('tasks/show',
                                                array('uid' => $res));
                        break;
                    case 'note':
                        if ($registry->hasMethod('notes/show')) {
                            $link = $registry->link('notes/show',
                                                    array('uid' => $res));
                        } else {
                            $link = false;
                        }
                        break;
                    }
                    if ($link && !is_a($link, 'PEAR_Error')) {
                        $name = sprintf('<a href="%s">%s</a>',
                                        Horde::url($link),
                                        $name);
                    }

                    $notification->push(sprintf(_("%s was successfully added to \"%s\"."), $name, htmlspecialchars($lists[$list]->get('name'))), 'horde.success', array('content.raw'));
                }
            }
        }

        /* Delete the original messages if this is a "move" operation. */
        if ($action == IMP_MESSAGE_MOVE) {
            $this->delete($indices);
        }

        return true;
    }

    /**
     * Strips a MIME Part out of a message.
     * Handles the IMP_SEARCH_MBOX mailbox.
     *
     * @param IMP_Mailbox &$imp_mailbox  The IMP_Mailbox object with the
     *                                   current index set to the message to be
     *                                   processed.
     * @param string $partid             The MIME ID of the part to strip.
     *
     * @return boolean  Returns true on success, or PEAR_Error on error.
     */
    function stripPart(&$imp_mailbox, $partid)
    {
        global $imp;

        /* Return error if no index was provided. */
        if (!($msgList = IMP::parseIndicesList($imp_mailbox))) {
            return PEAR::raiseError('No index provided to IMP_Message::stripPart().');
        }

        /* If more than one index provided, return error. */
        reset($msgList);
        list($folder, $index) = each($msgList);
        if (each($msgList) || (count($index) > 1)) {
            return PEAR::raiseError('More than 1 index provided to IMP_Message::stripPart().');
        }
        $index = implode('', $index);

        require_once 'Horde/MIME/Part.php';
        require_once IMP_BASE . '/lib/MIME/Contents.php';
        require_once IMP_BASE . '/lib/MIME/Headers.php';

        /* Get a local copy of the message and strip out the desired
           MIME_Part object. */
        $contents = &IMP_Contents::singleton($index . IMP_IDX_SEP . $folder);
        $contents->rebuildMessage();
        $message = $contents->getMIMEMessage();
        $oldPart = $message->getPart($partid);
        $newPart = new MIME_Part('text/plain');

        /* We need to make sure all text is in the correct charset. */
        $newPart->setCharset(NLS::getCharset());
        $newPart->setContents(sprintf(_("[Attachment stripped: Original attachment type: %s, name: %s]"), $oldPart->getType(), $oldPart->getName(true, true)), '8bit');
        $message->alterPart($partid, $newPart);

        /* We need to make sure we add "\r\n" after every line for
           imap_append() - some servers require it (e.g. Cyrus). */
        $message->setEOL(MIME_PART_RFC_EOL);

        /* Get the headers for the message. */
        $headers = &new IMP_Headers($index);
        $headers->buildFlags();
        $flags = array();
        foreach (array('answered', 'deleted', 'draft', 'flagged', 'seen') as $flag) {
            if ($headers->getFlag($flag)) {
                $flags[] = '\\' . $flag;
            }
        }
        $flags = implode(' ', $flags);

        /* This is a (sort-of) hack. Right before we append the new message
           we check imap_status() to determine the next available UID. We
           use this UID as the new index of the message. */
        $folderstring = IMP::serverString($folder);
        $headerstring = $headers->getHeaderText();
        $this->_impImap->changeMbox($folder);
        $status = @imap_status($imp['stream'], $folderstring, SA_UIDNEXT);
        if (@imap_append($imp['stream'], $folderstring, $headerstring . $contents->toString($message, true), $flags)) {
            $this->delete($imp_mailbox, true, true);
            $imp_mailbox->updateMailbox(IMP_MAILBOX_UPDATE);
            $imp_mailbox->setNewIndex($status->uidnext);

            /* We need to replace the old index in the query string with the
               new index. */
            $_SERVER['QUERY_STRING'] = preg_replace('/' . $index . '/', $status->uidnext, $_SERVER['QUERY_STRING']);

            return true;
        } else {
            return PEAR::raiseError(_("An error occured while attempting to strip the attachment. The IMAP server said: ") . imap_last_error());
        }
    }

    /**
     * Sets or clears a given flag for a list of messages.
     * Handles use of the IMP_SEARCH_MBOX mailbox.
     * This function works with IMAP only, not POP3.
     *
     * Valid flags are:
     *   \\SEEN
     *   \\FLAGGED
     *   \\ANSWERED
     *   \\DELETED
     *   \\DRAFT
     *
     * @param string $flag     The IMAP flag(s) to set or clear.
     * @param mixed &$indices  See above.
     * @param boolean $action  If true, set the flag(s), otherwise clear the
     *                         flag(s).
     *
     * @return boolean  True if successful, false if not.
     */
    function flag($flag, &$indices, $action = true)
    {
        if (!($msgList = IMP::parseIndicesList($indices))) {
            return false;
        }

        $function = ($action) ? 'imap_setflag_full' : 'imap_clearflag_full';
        $return_value = true;

        foreach ($msgList as $folder => $msgIndices) {
            $msgIdxString = implode(',', $msgIndices);

            /* Switch folders, if necessary. */
            $this->_impImap->changeMbox($folder);

            /* Flag/unflag the messages now. */
            if (!call_user_func($function, $GLOBALS['imp']['stream'], $msgIdxString, $flag, ST_UID)) {
                $GLOBALS['notification']->push(sprintf(_("There was an error flagging messages in the folder \"%s\". This is what the server said"), IMP::displayFolder($folder)) . ': ' . imap_last_error(), 'horde.error');
                $return_value = false;
            }
        }

        return $return_value;
    }

    /**
     * Sets or clears a given flag(s) for all messages in a list of mailboxes.
     * This function works with IMAP only, not POP3.
     *
     * See flag() for the list of valid flags.
     *
     * @param string $flag     The IMAP flag(s) to set or clear.
     * @param array $mboxes    The list of mailboxes to flag.
     * @param boolean $action  If true, set the flag(s), otherwise, clear the
     *                         flag(s).
     *
     * @return boolean  True if successful, false if not.
     */
    function flagAllInMailbox($flag, $mboxes, $action = true)
    {
        if (empty($mboxes) || !is_array($mboxes)) {
            return false;
        }

        $return_value = true;

        foreach ($mboxes as $val) {
            /* Switch folders, if necessary. */
            $this->_impImap->changeMbox($val);
            if ($uids = @imap_search($GLOBALS['imp']['stream'], 'ALL', SE_UID)) {
                $indices = array($val => $uids);
                if (!$this->flag($flag, $indices, $action)) {
                    $return_value = false;
                }
            }
        }

        return $return_value;
    }

    /**
     * Expunges all deleted messages from the list of mailboxes.
     *
     * @param array $mbox_list  The list of mailboxes to empty.
     */
    function expungeMailbox($mbox_list)
    {
        global $imp;

        foreach ($mbox_list as $val) {
            if ($GLOBALS['imp_search']->isSearchMbox($val)) {
                foreach ($GLOBALS['imp_search']->getSearchFolders($val) as $folder) {
                    $stream = $this->_impImap->openIMAPStream($folder);
                    @imap_expunge($stream);
                    @imap_close($stream);
                }
            } else {
                $this->_impImap->changeMbox($val);
                @imap_expunge($imp['stream']);
            }
        }
    }

    /**
     * Empties an entire mailbox.
     *
     * @param array $mbox_list  The list of mailboxes to empty.
     */
    function emptyMailbox($mbox_list)
    {
        global $imp, $notification;

        foreach ($mbox_list as $mbox) {
            if ($GLOBALS['imp_search']->isVTrashFolder($mbox)) {
                $this->expungeMailbox($GLOBALS['imp_search']->getSearchFolders($mbox));
                $notification->push(_("Emptied all messages from Virtual Trash Folder."), 'horde.success');
                continue;
            }

            $display_mbox = IMP::displayFolder($mbox);

            if (($this->_impImap->changeMbox($mbox)) !== true) {
                $notification->push(sprintf(_("Could not delete messages from %s. The server said: %s"), $display_mbox, imap_last_error()), 'horde.error');
                continue;
            }

            /* Make sure there is at least 1 message before attempting to
               delete. */
            $delete_array = array($mbox => array('1:*'));
            if (!@imap_num_msg($imp['stream'])) {
                $notification->push(sprintf(_("The mailbox %s is already empty."), $display_mbox), 'horde.message');
            } elseif (!$this->delete($delete_array, true)) {
                $notification->push(sprintf(_("There was a problem expunging the mailbox. The server said: %s"), imap_last_error()), 'horde.error');
                continue;
            } else {
                @imap_expunge($imp['stream']);
                $notification->push(sprintf(_("Emptied all messages from %s."), $display_mbox), 'horde.success');
            }
        }
    }

}
