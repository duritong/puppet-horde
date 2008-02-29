<?php

define('MIMP_MAILBOX_COPY', 1);
define('MIMP_MAILBOX_MOVE', 2);
define('MIMP_MAILBOX_DELETE', 3);
define('MIMP_MAILBOX_UNDELETE', 4);
define('MIMP_MAILBOX_EXPUNGE', 5);

/**
 * The MIMP_Mailbox:: class contains all code related to handling mailbox
 * access.
 *
 * $Horde: mimp/lib/Mailbox.php,v 1.26.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package MIMP
 */
class MIMP_Mailbox {

    /**
     * The current message index.
     *
     * @var integer
     */
    var $_index = null;

    /**
     * The location in the sorted array we are at.
     *
     * @var integer
     */
    var $_arrayIndex = null;

    /**
     * The location of the last message we were at.
     *
     * @var integer
     */
    var $_lastArrayIndex = null;

    /**
     * The array of sorted indices.
     *
     * @var array
     */
    var $_sorted = array();

    /**
     * The array of sorted mailboxes.
     *
     * @var array
     */
    var $_sortedMbox = array();

    /**
     * The indent level for each message (if messages sorted by thread).
     *
     * @var array
     */
    var $_threadIndent = array();

    /**
     * Are we updating the message index?
     *
     * @var boolean
     */
    var $_indexset = false;

    /**
     * Has a sort already been done?
     *
     * @var boolean
     */
    var $_sortdone = false;

    /**
     * Should we show Hide/purge deleted message links?
     *
     * @var boolean
     */
    var $_showdelete = false;

    /**
     * The number of new messages in the mailbox.
     *
     * @var integer
     */
    var $_newmsgs = 0;

    /**
     * Using POP to access mailboxes?
     *
     * @var boolean
     */
    var $_usepop = false;

    /**
     * Constructor.
     *
     * @param integer $index  The index of the current message. This will cause
     *                        MIMP_Message to update the various message
     *                        arrays after each action.
     */
    function MIMP_Mailbox($index = null)
    {
        if (!is_null($index)) {
            $this->_setIndex($index);
            $this->_setSorted();
            $this->_setArrayIndex();
        }

        if ($_SESSION['mimp']['base_protocol'] == 'pop3') {
            $this->_usepop = true;
        }
    }

    /**
     * Build the array of message information.
     *
     * @param integer $begin       The beginning message number.
     * @param integer $beginIndex  The beginning index.
     * @param integer $end         The ending message number.
     *
     * @return array  An array with information on the requested messages.
     * <pre>
     * Key: array index in current sorted mailbox array
     * 'header'     --  Header information from imap_fetch_overview().
     * 'mbox'       --  Name of the mailbox.
     * </pre>
     */
    function buildMailboxArray($begin, $beginIndex, $end)
    {
        $this->_showdelete = false;

        $mboxes = array();
        $overview = array();

        /* Build the list of mailboxes and messages. */
        for ($i = $begin - 1, $j = $beginIndex; $i < $end; $i++, $j++) {
            /* Make sure that the index is actually in the slice of messages
               we're looking at. If we're hiding deleted messages, for
               example, there may be gaps here. */
            if (isset($this->_sorted[$i])) {
                if (isset($this->_sortedMbox[$i])) {
                    $mboxname = $this->_sortedMbox[$i];
                } else {
                    $mboxname = $_SESSION['mimp']['mailbox'];
                }

                if (!isset($mboxes[$mboxname])) {
                    $mboxes[$mboxname] = array();
                }
                $mboxes[$mboxname][$this->_sorted[$i]] = $i;
            }
        }

        /* Retrieve information from each mailbox. */
        require_once MIMP_BASE . '/lib/IMAP.php';
        $imap = &MIMP_IMAP::singleton();
        foreach ($mboxes as $mbox => $ids) {
            $imap->changeMbox($mbox, OP_READONLY);
            $imapOverview = @imap_fetch_overview($_SESSION['mimp']['stream'], implode(',', array_keys($ids)), FT_UID);
            foreach ($imapOverview as $header) {
                $key = $ids[$header->uid];
                $overview[$key] = array();
                $overview[$key]['header'] = $header;
                if ($header->deleted) {
                    $this->_showdelete = true;
                }
                $overview[$key]['structure'] = null;
                $overview[$key]['mbox'] = $mbox;
            }
        }

        /* Sort via the sorted array index. */
        ksort($overview);

        return $overview;
    }

    /**
     * Builds the list of messages in the mailbox.
     *
     * @return array  The sorted index.
     */
    function buildMailbox()
    {
        global $notification, $prefs;

        $this->_newmsgs = 0;

        $this->_getSortedIndex();
        if (!empty($this->_sorted)) {
            $newQuery = 'RECENT';
            if ($this->_delhide()) {
                $newQuery .= ' UNDELETED';
            }
            if (($new = @imap_search($_SESSION['mimp']['stream'], $newQuery))) {
                $this->_newmsgs = count($new);
            }
        }

        /* Store the sorted range in MIMP_Message. */
        $this->setRange($this->_sorted);

        return $this->_sorted;
    }

    /**
     * The number of new messages in the mailbox.
     */
    function newMessageCount()
    {
        return $this->_newmsgs;
    }

    /**
     * Should delete message links be shown? Only show if the configuration
     * requires it, or there deleted messages exist in the current mailbox.
     */
    function showDeleteLinks()
    {
        if (!$GLOBALS['prefs']->getValue('use_trash') &&
            ($_SESSION['mimp']['base_protocol'] == 'imap')) {
            return true;
        } else {
            return $this->_showdelete;
        }
    }

    /**
     * Sets the private index variable.
     *
     * @access private
     *
     * @param integer $index  The index of the current message. If empty, will
     *                        update the current index to the current array
     *                        index value.
     */
    function _setIndex($index = null)
    {
        if (empty($index)) {
            if (!is_null($this->_arrayIndex)) {
                if (isset($this->_sorted[$this->_arrayIndex])) {
                    $this->_index = $this->_sorted[$this->_arrayIndex];
                } else {
                    $this->_index = null;
                }
            }
        } else {
            $this->_index = $index;
        }
        $this->_indexset = true;
    }

    /**
     * Updates the sorted messages array.
     *
     * @access private
     */
    function _setSorted()
    {
        $this->_sortedMbox = array();
        if (empty($_SESSION['mimp']['msgl'])) {
            $this->_sorted = array();
        } else {
            $msglist = explode(MIMP_MSG_SEP, $_SESSION['mimp']['msgl']);
            $this->_sorted = $msglist;
        }
    }

    /**
     * Updates the message array index.
     *
     * @access private
     *
     * @param $offset  The number of messages to increase array index by. If
     *                 null, sets array index to the value of the current
     *                 message index, if any.
     */
    function _setArrayIndex($offset = null)
    {
        if (is_null($this->_index)) {
            $this->_arrayIndex = null;
        } else {
            if (!is_null($offset)) {
                $this->_lastArrayIndex = $this->_arrayIndex;
                $this->_arrayIndex += $offset;
                if (!isset($this->_sorted[$this->_arrayIndex])) {
                    $this->_arrayIndex = null;
                }
                $this->_setIndex();
            } else {
                /* array_search() returns false on no result. We will
                   set an unsuccessful result to NULL. */
                if (($this->_arrayIndex = array_search($this->_index, $this->_sorted)) === false) {
                    $this->_arrayIndex = null;
                }
                $this->_lastArrayIndex = $this->_arrayIndex;
            }
        }
    }

    /**
     * Returns the current message index.
     *
     * @return integer  The message index.
     */
    function getIndex()
    {
        return $this->_index;
    }

    /**
     * Returns the current array index.
     *
     * @return integer  The array index.
     */
    function getArrayIndex()
    {
        return $this->_arrayIndex;
    }

    /**
     * Returns the current message array index. If the array index has
     * run off the end of the message array, will return the last index.
     *
     * @return integer  The message array index.
     */
    function getMessageIndex()
    {
        if (is_null($this->_arrayIndex)) {
            if (is_null($this->_lastArrayIndex)) {
                $index = 0;
            } else {
                $index = $this->_lastArrayIndex;
            }
        } else {
            $index = $this->_arrayIndex;
        }

        return $index + 1;
    }

    /**
     * Returns the current message count of the mailbox.
     *
     * @return integer  The mailbox message count.
     */
    function getMessageCount()
    {
        return count($this->_sorted);
    }

    /**
     * Checks to see if the current index is valid.
     * This function is only useful if an index was passed to the constructor.
     *
     * @return boolean  True if index is valid, false if not.
     */
    function isValidIndex()
    {
        $this->_sortIfNeeded();

        if (is_null($this->_index) || is_null($this->_arrayIndex)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Updates the UID validity flag.
     * The following variables are updated by this method:
     *   $_SESSION['mimp']['uidvalidity']
     *
     * @return boolean  True if UID is valid, false if not.
     */
    function updateUidValidity()
    {
        $valid = true;

        $status = @imap_status($_SESSION['mimp']['stream'], MIMP::serverString($_SESSION['mimp']['mailbox']), SA_UIDVALIDITY);
        if (empty($_SESSION['mimp']['uidvalidity']) ||
            ($_SESSION['mimp']['uidvalidity'] != $status->uidvalidity)) {
            $valid = false;
            $_SESSION['mimp']['uidvalidity'] = $status->uidvalidity;
        }

        return $valid;
    }

    /**
     * Get the sorted list of messages for a mailbox. This function correctly
     * handles sort preferences, deletion preferences (e.g. hide deleted
     * messages), and protocol (e.g. 'pop3' vs. 'imap').
     * The following variables are updated by this method:
     *   $_SESSION['mimp']['msgl']
     *
     * @access private
     *
     * @return array  An array containing the sorted messages
     */
    function _getSortedIndex()
    {
        global $prefs;

        $sorted = array();

        if ($prefs->getValue('sortby') == SORTTHREAD) {
            $sorted = $this->_threadSort($prefs->getValue('sortdir'));
        } else {
            if (!$this->_usepop && $this->_delhide()) {
                $sorted = @imap_sort($_SESSION['mimp']['stream'], $prefs->getValue('sortby'), $prefs->getValue('sortdir'), SE_UID, 'UNDELETED');
            } else {
                $sorted = @imap_sort($_SESSION['mimp']['stream'], $prefs->getValue('sortby'), $prefs->getValue('sortdir'), SE_UID);
            }
        }

        $this->setRange($sorted);
    }

    /**
     * Sets the current page of messages based on the current index.
     * The following variables are updated by this method:
     *   $_SESSION['mimp']['msgl']
     *
     * @param array $arr  The array of message indices.
     */
    function setRange($arr)
    {
        $_SESSION['mimp']['msgl'] = implode(MIMP_MSG_SEP, $arr);

        /* Set the new sorted message list. */
        $this->_setSorted();

        /* Update the internal indices, if necessary. */
        if ($this->_indexset) {
            /* Update the current array index to its new position in
               the message array. */
            $this->_setArrayIndex();
        }

        /* Set the _sortdone flag. */
        $this->_sortdone = true;
     }

    /**
     * Perform re-threading sort.
     *
     * @access private
     *
     * @param $direction    1 if newest first, 0 if newest last.
     *
     * @return array  The sorted list of messages.
     */
    function _threadSort($direction)
    {
        global $prefs;

        $sorted = array();
        $branchsOpen = array();

        $ref_array = @imap_thread($_SESSION['mimp']['stream'], SE_UID);
        if (!is_array($ref_array)) {
            @imap_close($_SESSION['mimp']['stream']);
            MIMP::authenticate();
            $ref_array = @imap_thread($_SESSION['mimp']['stream'], SE_UID);
            if (!is_array($ref_array)) {
                return array();
            }
        }
        foreach ($ref_array as $key => $val) {
            if (strpos($key, '.num')) {
                $branchsOpen[substr($key, 0, -4)] = 'Open';

                if ($val > 0) {
                    $this->_threadIndent[$val] = count($branchsOpen);

                    if ($this->_delhide()) {
                        $overview = @imap_fetch_overview($_SESSION['mimp']['stream'], $val, FT_UID);
                        if (is_array($overview) && ($overview[0]->deleted == 0)) {
                            $sorted[] = $val;
                        }
                    } else {
                        $sorted[] = $val;
                    }
                }
            }
            if (strpos($key, '.branch')) {
                unset($branchsOpen[substr($key, 0, -7)]);
            }
        }
        if ($direction === '1') {
            $sorted = array_reverse($sorted);
        }

        return $sorted;
    }

    /**
     * Determines if a resort is needed, and, if necessary, performs the
     * resort.
     *
     * The sorted range needs to be updated in the following cases:
     *   + Indexes are being tracked
     *   + There is a valid array index
     *   + This is not a search mailbox
     *   + The UIDs may no longer be valid (Optional)
     *   + This is the last message in the mailbox
     *   + A sort has not already occurred by some method in this class
     *
     * @access private
     */
    function _sortIfNeeded()
    {
        /* If array index is null, we have reached the beginning/end of the
           mailbox so we shouldn't sort anything. There is also no need to
           sort the search results. */
        if ($this->_indexset &&
            !is_null($this->_arrayIndex) &&
            !$this->messageIndices(1)) {
            $this->_getSortedIndex();
        }
    }

    /**
     * Gets the indention level for a message ID.
     *
     * @param integer $msgid  The message ID.
     *
     * @return mixed  Returns the thread indent level if $msgid found.
     *                Returns false on failure.
     */
    function getThreadIndent($msgid)
    {
        if (isset($this->_threadIndent[$msgid])) {
            return $this->_threadIndent[$msgid];
        } else {
            return false;
        }
    }

    /**
     * Returns information on a message offset from the current message.
     *
     * @param integer  The offset from the current message.
     *
     * @return array  'index'    --  The message index.
     *                'mailbox'  --  The mailbox.
     */
    function messageIndices($offset)
    {
        $return_array = array();
        $index = $this->_arrayIndex + $offset;

        /* If the offset would put us out of array index, return now. */
        if (!isset($this->_sorted[$index])) {
            return $return_array;
        }

        $return_array['index'] = $this->_sorted[$index];
        $return_array['mailbox'] = $_SESSION['mimp']['mailbox'];

        return $return_array;
    }

    /**
     * Returns the current sorted array without the current index message.
     *
     * @access private
     *
     * @return array  The sorted array without the current index in it.
     */
    function _removeCurrent()
    {
        /* We need to set index to the next index (if any) in the currently
           sorted list. */
        if (($nextindex = $this->messageIndices(1))) {
            $this->_setIndex($nextindex['index']);
        } else {
            $this->_index = null;
        }

        /* Remove the current entry and recalculate the range. */
        $arr = array_merge(array_slice($this->_sorted, 0, $this->_arrayIndex),
                           array_slice($this->_sorted, $this->_arrayIndex + 1));
        $this->setRange($arr);

        $this->_setArrayIndex();
        $this->_setLastValidIndex();
    }

    /**
     * Hide deleted can be set even if we are using a trash folder -
     * the trash folder should take preference.
     *
     * @access private
     */
    function _delhide()
    {
        global $prefs;

        if ($prefs->getValue('delhide') && !$prefs->getValue('use_trash')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Update the current mailbox.
     *
     * @param integer $action   The action to perform.
     * @param boolean $success  Was the action successful?
     */
    function updateMailbox($action, $success = true)
    {
        if (!$this->_indexset) {
            return;
        }

        switch ($action) {
        case MIMP_MAILBOX_COPY:
            /* Just move to the next message. */
            $this->_setArrayIndex(1);

            /* Determine if we need to resort. */
            $this->_sortIfNeeded();
            break;

        case MIMP_MAILBOX_MOVE:
        case MIMP_MAILBOX_DELETE:
            /* If we are using POP, we need to resort every time. */
            if ($success && $this->_usepop) {
                $this->_getSortedIndex();
            } elseif ($success &&
                      ($this->_delhide() ||
                       $GLOBALS['prefs']->getValue('use_trash'))) {
                /* Nuke message from sorted list if sent to trash or hidden. */
                $this->_removeCurrent();
            } else {
                /* Either we failed, or the message is still in the
                   mailbox and has been marked as deleted - just move
                   to the next message. */
                $this->_setArrayIndex(1);

                /* Determine if we need to resort. */
                $this->_sortIfNeeded();
            }
            break;

        case MIMP_MAILBOX_UNDELETE:
            $this->_setArrayIndex(1);
            break;

        case MIMP_MAILBOX_EXPUNGE:
        case MIMP_MAILBOX_EMPTY:
            $this->_getSortedIndex();
            break;
        }
    }

    /**
     * Using the preferences and the current mailbox, determines the messages
     * to view on the current page.
     *
     * @param integer $page   The page number currently being displayed.
     * @param integer $start  The starting message number.
     *
     * @return stdClass  An object with the following fields:
     * <pre>
     * 'begin'      -  The beginning message number of the page.
     * 'end'        -  The ending message number of the page.
     * 'index'      -  The index of the starting message.
     * 'msgcount'   -  The number of viewable messages in the current mailbox.
     * 'page'       -  The current page number.
     * 'pagecount'  -  The number of pages in this mailbox.
     * </pre>
     */
    function buildMailboxPage($page = 0, $start = 0)
    {
        global $prefs;

        $sorted = $this->buildMailbox();
        $msgcount = $this->getMessageCount();
        $page_size = $GLOBALS['prefs']->getValue('max_msgs');
        $sortby = $GLOBALS['prefs']->getValue('sortby');
        $sortdir = $GLOBALS['prefs']->getValue('sortdir');

        if ($msgcount > $page_size) {
            $pageCount = ceil($msgcount / (($page_size > 0) ? $page_size : 20));

            /* Determine which page to display. */
            if (empty($page) || strcspn($page, '0123456789')) {
                if (!empty($start)) {
                    /* Messages set this when returning to a mailbox. */
                    $page = ceil($start / $page_size);
                } elseif ($sortby != SORTTHREAD) {
                    /* Use imap_sort to find the first unread message */
                    $search = 'UNSEEN';
                    if ($this->_delhide()) {
                        $search .= ' UNDELETED';
                    }
                    $new = @imap_sort($_SESSION['mimp']['stream'], $sortby, $sortdir, SE_UID, $search);
                    if (!empty($new)) {
                        $msg_keys = array_flip($sorted);
                        $first_new = $msg_keys[$new[0]] + 1;
                        $page = ceil($first_new / $page_size);
                    }
                }

                if (empty($page)) {
                    $page = $sortdir ? 1 : $pageCount;
                }
            }

            /* Make sure we're not past the end or before the beginning, and
               that we have an integer value. */
            $page = intval($page);
            if ($page > $pageCount) {
                $page = $pageCount;
            } elseif ($page < 1) {
                $page = 1;
            }

            $begin = intval(($page - 1) * $page_size) + 1;
            $end = $begin + $page_size - 1;
            if ($end > $msgcount) {
                $end = $msgcount;
            }
        } else {
            $begin = 1;
            $end = $msgcount;
            $page = 1;
            $pageCount = 1;
        }

        $beginIndex = $this->getArrayIndex();

        $ob = new stdClass;
        $ob->begin     = $begin;
        $ob->end       = $end;
        $ob->index     = $beginIndex;
        $ob->msgcount  = $msgcount;
        $ob->page      = $page;
        $ob->pagecount = $pageCount;

        return $ob;
    }

    /**
     * If array index is null, we have deleted the last message. If this
     * is the case, set index to the last valid index.
     *
     * @access private
     */
    function _setLastValidIndex()
    {
        if (is_null($this->_arrayIndex) && !empty($this->_lastArrayIndex)) {
            $this->_lastArrayIndex--;
        }
    }

}
