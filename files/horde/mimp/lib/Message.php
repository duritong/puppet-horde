<?php
/**
 * The MIMP_Message:: class contains all functions related to handling messages
 * within MIMP. Actions such as moving, copying, and deleting messages are
 * handled in here so that code need not be repeated between mailbox, message,
 * and other pages.
 *
 * Indices format:
 * ===============
 * For any function below that requires an $indices parameter, the
 * following inputs are allowed:
 * 1. An array of messages indices.
 * 2. A MIMP_Mailbox object, which will use the current index/folder
 *    as determined by the object. If a MIMP_Mailbox object is used, it
 *    will be updated after the action is performed.
 *
 * $Horde: mimp/lib/Message.php,v 1.22.2.1 2007/01/02 13:55:09 jan Exp $
 *
 * Copyright 2000-2007 Chris Hyde <chris@jeks.net>
 * Copyright 2000-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2002-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chris Hyde <chris@jeks.net>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package MIMP
 */
class MIMP_Message {

    /**
     * The MIMP_IMAP object for the current server.
     *
     * @var MIMP_IMAP
     */
    var $_mimpImap;

    /**
     * Using POP to access mailboxes?
     *
     * @var boolean
     */
    var $_usepop = false;

    /**
     * Constructor.
     */
    function MIMP_Message()
    {
        if ($_SESSION['mimp']['base_protocol'] == 'pop3') {
            $this->_usepop = true;
        }

        require_once MIMP_BASE . '/lib/IMAP.php';
        $this->_mimpImap = &MIMP_IMAP::singleton();
    }

    /**
     * Copies or moves an array of messages to a new folder. Handles use of
     * the Trash folder.
     *
     * @param string $targetMbox   The mailbox to move/copy messages to.
     * @param string $action       Either 'move_message' or 'copy_message'.
     * @param mixed &$indices      See above.
     *
     * @return boolean  True if successful, false if not.
     */
    function copy($targetMbox, $action, &$indices)
    {
        global $notification, $prefs;

        if (!($msgList = $this->_getMessageIndices($indices))) {
            return false;
        }

        switch ($action) {
        case 'move_message':
            $imap_flags = CP_UID | CP_MOVE;
            $message = _("There was an error moving messages from \"%s\" to \"%s\". This is what the server said");
            break;

        case 'copy_message':
            $imap_flags = CP_UID;
            $message = _("There was an error copying messages from \"%s\" to \"%s\". This is what the server said");
            break;
        }

        $return_value = true;

        foreach ($msgList as $folder => $msgIdxs) {
            $msgIdxs = implode(',', $msgIdxs);

            /* Switch folders, if necessary (only valid for IMAP). */
            $this->_mimpImap->changeMbox($folder);

            /* Attempt to copy/move messages to new mailbox. */
            if (!@imap_mail_copy($_SESSION['mimp']['stream'], $msgIdxs, $targetMbox, $imap_flags)) {
                $notification->push(sprintf($message, MIMP::displayFolder($folder), MIMP::displayFolder($targetMbox)) . ': ' . imap_last_error(), 'horde.error');
                $return_value = false;
            }

            /* If moving, and using the trash, expunge immediately. */
            if ($prefs->getValue('use_trash') && ($action == 'move_message')) {
                @imap_expunge($_SESSION['mimp']['stream']);
            }
        }

        /* Update the mailbox. */
        if (is_a($indices, 'MIMP_Mailbox')) {
            if ($action == 'copy_message') {
                $indices->updateMailbox(MIMP_MAILBOX_COPY, $return_value);
            } else {
                $indices->updateMailbox(MIMP_MAILBOX_MOVE, $return_value);
            }
        }

        return $return_value;
    }

    /**
     * Deletes a set of messages, taking into account whether or not a
     * Trash folder is being used. Will delete messages from the currently
     * open mailbox ($_SESSION['mimp']['mailbox']).
     *
     * @param mixed &$indices  See above.
     *
     * @return boolean  True if successful, false if not.
     */
    function delete(&$indices)
    {
        global $notification, $prefs;

        if (!($msgList = $this->_getMessageIndices($indices))) {
            return false;
        }

        $return_value = true;
        $trash = MIMP::folderPref($prefs->getValue('trash_folder'), true);

        /* If the folder we are deleting from has changed. */
        foreach ($msgList as $folder => $msgIdxs) {
            $msgIdxs = implode(',', $msgIdxs);

            /* Switch folders, if necessary (only valid for IMAP). */
            $this->_mimpImap->changeMbox($folder);

            /* Trash is only valid for IMAP mailboxes. */
            if (!$this->_usepop &&
                $prefs->getValue('use_trash') &&
                ($folder != $trash)) {
                include_once MIMP_BASE . '/lib/Folder.php';
                $mimp_folder = &MIMP_Folder::singleton();
                if (!$mimp_folder->exists($trash)) {
                    $mimp_folder->create($trash, $prefs->getValue('subscribe'));
                }

                if (!@imap_mail_copy($_SESSION['mimp']['stream'], $msgIdxs, $trash, CP_UID | CP_MOVE)) {
                    $notification->push(sprintf(_("There was an error moving messages to the trash. This is what the server said")) . ': ' . imap_last_error(), 'horde.error');
                    $return_value = false;
                } else {
                    @imap_expunge($_SESSION['mimp']['stream']);
                }
            } else {
                if (!@imap_delete($_SESSION['mimp']['stream'], $msgIdxs, FT_UID)) {
                    if ($this->_usepop) {
                        $notification->push(sprintf(_("There was an error deleting messages. This is what the server said: %s"), imap_last_error()), 'horde.error');
                    } else {
                        $notification->push(sprintf(_("There was an error deleting messages from the folder \"%s\". This is what the server said"), MIMP::displayFolder($folder)) . ': ' . imap_last_error(), 'horde.error');
                    }
                    $return_value = false;
                } elseif ($prefs->getValue('use_trash') &&
                          ($folder == $trash)) {
                    /* Purge messages in the trash folder immediately. */
                    @imap_expunge($_SESSION['mimp']['stream']);
                } elseif ($this->_usepop) {
                    @imap_expunge($_SESSION['mimp']['stream']);
                    @imap_close($_SESSION['mimp']['stream']);
                    MIMP::authenticate();
                }
            }
        }

        /* Update the mailbox. */
        if (is_a($indices, 'MIMP_Mailbox')) {
            $indices->updateMailbox(MIMP_MAILBOX_DELETE, $return_value);
        }

        return $return_value;
    }

    /**
     * Undeletes a set of messages.
     * This function works with IMAP only, not POP3.
     *
     * @param mixed &$indices  See above.
     *
     * @return boolean  True if successful, false if not.
     */
    function undelete(&$indices)
    {
        global $notification;

        if (!($msgList = $this->_getMessageIndices($indices))) {
            return false;
        }

        $return_value = true;

        foreach ($msgList as $folder => $msgIdxs) {
            $msgIdxs = implode(',', $msgIdxs);

            /* Switch folders, if necessary. */
            $this->_mimpImap->changeMbox($folder);

            if ($_SESSION['mimp']['stream'] &&
                !@imap_undelete($_SESSION['mimp']['stream'], $msgIdxs, FT_UID)) {
                $notification->push(sprintf(_("There was an error deleting messages in the folder \"%s\". This is what the server said"), MIMP::displayFolder($folder)) . ': ' . imap_last_error(), 'horde.error');
                $return_value = false;
            }
        }

        /* Update the mailbox. */
        if (is_a($indices, 'MIMP_Mailbox')) {
            $indices->updateMailbox(MIMP_MAILBOX_UNDELETE, $return_value);
        }

        return $return_value;
    }

    /**
     * Expunges all deleted messages from the currently opened mailbox.
     */
    function expungeMailbox()
    {
        if (!@imap_expunge($_SESSION['mimp']['stream'])) {
            global $notification;
            $notification->push(_("There was a problem expunging the mailbox. This is what the server said") . ': ' . imap_last_error(), 'horde.error');
        }
    }

    /**
     * Get message indices list.
     *
     * @access private
     *
     * @param mixed $indices  See above.
     *
     * @return mixed  Returns an array with the folder as key and an array
     *                of message indices as the value (See #2 above).
     *                Else, returns false.
     */
    function _getMessageIndices($indices)
    {
        $msgList = array();

        if (is_a($indices, 'MIMP_Mailbox')) {
            $msgIdx = $indices->getIndex(true);
            if (empty($msgIdx)) {
                return false;
            }
            $msgList[$_SESSION['mimp']['mailbox']][] = $msgIdx;
            return $msgList;
        }

        if (!is_array($indices)) {
            return false;
        }
        if (!count($indices)) {
            return array();
        }

        foreach ($indices as $val) {
            $msgList[$_SESSION['mimp']['mailbox']][] = $val;
        }

        return $msgList;
    }

}
