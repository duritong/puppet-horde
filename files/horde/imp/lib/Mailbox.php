<?php

define('IMP_MAILBOX_MOVE', 1);
define('IMP_MAILBOX_COPY', 2);
define('IMP_MAILBOX_DELETE', 3);
define('IMP_MAILBOX_UNDELETE', 4);
define('IMP_MAILBOX_EXPUNGE', 5);
define('IMP_MAILBOX_EMPTY', 6);
define('IMP_MAILBOX_UPDATE', 7);

/**
 * The IMP_Mailbox:: class contains all code related to handling mailbox
 * access.
 *
 * $Horde: imp/lib/Mailbox.php,v 1.76.10.61 2007/01/02 13:54:56 jan Exp $
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
class IMP_Mailbox {

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
     * The threading information for the current mailbox.
     *
     * @var IMP_Thread
     */
    var $_thread = null;

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
     * The sort method for this mailbox.
     *
     * @var integer
     */
    var $_sortby;

    /**
     * Are we hiding deleted messages?
     *
     * @var boolean
     */
    var $_delhide;

    /**
     * Special folder flag.
     *
     * @var boolean
     */
    var $_specialFolder = null;

    /**
     * Has the sorting limit been reached?
     *
     * @var boolean
     */
    var $_sortLimit = false;

    /**
     * Variables relating to showing message previews.
     *
     * @var array
     */
    var $_previews = array();

    /**
     * The 'no_newmail_popup' flag.
     *
     * @var boolean
     */
    var $_no_newmail_popup = false;

    /**
     * Attempts to return a reference to a concrete IMP_Mailbox instance.
     * It will only create a new instance if no IMP_Mailbox instance with
     * the same parameters currently exists.
     *
     * This method must be invoked as: $var = &IMP_Mailbox::singleton();
     *
     * @param integer $index  See IMP_Mailbox constructor.
     * @param boolean $force  Force the creation of a new instance.
     *
     * @return mixed  The created concrete IMP_Mailbox instance, or false
     *                on error.
     */
    function &singleton($index = null, $force = false)
    {
        static $instances = array();

        $signature = serialize($index);
        if ($force || !isset($instances[$signature])) {
            $instances[$signature] = new IMP_Mailbox($index);
        }

        return $instances[$signature];
    }

    /**
     * Constructor.
     *
     * @param integer $index  The index of the current message. This will cause
     *                        IMP_Message to update the various message arrays
     *                        after each action.
     */
    function IMP_Mailbox($index = null)
    {
        if (!is_null($index)) {
            $this->setNewIndex($index);
        }

        if ($GLOBALS['imp']['base_protocol'] == 'pop3') {
            $this->_usepop = true;
        }

        /* Determine whether we're hiding deleted messages; let the
           trash folder take precedence. */
        if ($GLOBALS['prefs']->getValue('use_vtrash')) {
            $this->_delhide = !$GLOBALS['imp_search']->isVTrashFolder();
        } else {
            $this->_delhide = ($GLOBALS['prefs']->getValue('delhide') &&
                               !$GLOBALS['prefs']->getValue('use_trash') &&
                               ($GLOBALS['imp_search']->isSearchMbox() ||
                               ($GLOBALS['prefs']->getValue('sortby') != SORTTHREAD)));
        }
    }

    /**
     * Sets the value of the 'no_newmail_popup' flag.
     *
     * @param boolean $nopopup  The value to set the flag.
     */
    function setNoNewmailPopup($nopopup)
    {
        $this->_no_newmail_popup = (bool) $nopopup;
    }

    /**
     * Sets a new index.
     *
     * @param integer $index  The index of the new current message.
     */
    function setNewIndex($index)
    {
        $this->_setSorted();
        $this->_setArrayIndex($index, 'uid');
    }

    /**
     * Determines if mail previews should be generated.
     *
     * @return boolean  True if mail previews should be generated.
     */
    function showPreviews()
    {
        if (!empty($this->_previews)) {
            return true;
        }

        if (!$GLOBALS['conf']['mailbox']['show_preview'] ||
            !$GLOBALS['prefs']->getValue('preview_enabled')) {
            return false;
        }

        $this->_previews['unread'] = $GLOBALS['prefs']->getValue('preview_show_unread');
        $this->_previews['maxlen'] = $GLOBALS['prefs']->getValue('preview_maxlen');
        if ($this->_previews['maxlen'] != -1) {
            $this->_previews['maxlen'] = max(5, $this->_previews['maxlen'] - 3);
        }
        $this->_previews['strip'] = $GLOBALS['prefs']->getValue('preview_strip_nl');
        $this->_previews['tooltip'] = $GLOBALS['prefs']->getValue('preview_show_tooltip');

        return true;
    }

    /**
     * Build the array of message information.
     *
     * @param integer $begin  The beginning message number.
     * @param integer $end    The ending message number.
     *
     * @return array  An array with information on the requested messages.
     * <pre>
     * Key: array index in current sorted mailbox array
     * 'header'     --  Header information from imap_fetch_overview().
     * 'mbox'       --  Name of the mailbox.
     * 'preview'    --  The message preview (if the 'show_preview' preference
     *                  is active).
     * 'structure'  --  Structure of the message (if the 'show_attachments'
     *                  configuration option is active).
     * </pre>
     */
    function buildMailboxArray($begin, $end)
    {
        $this->_showdelete = false;

        $mboxes = array();
        $overview = array();
        $show_preview = $this->showPreviews();

        /* Build the list of mailboxes and messages. */
        for ($i = $begin - 1; $i < $end; $i++) {
            /* Make sure that the index is actually in the slice of messages
               we're looking at. If we're hiding deleted messages, for
               example, there may be gaps here. */
            if (isset($this->_sorted[$i])) {
                if (isset($this->_sortedMbox[$i])) {
                    $mboxname = $this->_sortedMbox[$i];
                } else {
                    $mboxname = $GLOBALS['imp']['mailbox'];
                }

                if (!isset($mboxes[$mboxname])) {
                    $mboxes[$mboxname] = array();
                }
                $mboxes[$mboxname][$this->_sorted[$i]] = $i;
            }
        }

        require_once IMP_BASE . '/lib/IMAP.php';
        $imp_imap = &IMP_IMAP::singleton();

        /* Retrieve information from each mailbox. */
        foreach ($mboxes as $mbox => $ids) {
            $imp_imap->changeMbox($mbox, OP_READONLY);
            $imapOverview = @imap_fetch_overview($GLOBALS['imp']['stream'], implode(',', array_keys($ids)), FT_UID);
            foreach ($imapOverview as $header) {
                $key = $ids[$header->uid];
                $overview[$key] = array();
                $overview[$key]['header'] = $header;
                if ($header->deleted) {
                    $this->_showdelete = true;
                }

                if ($GLOBALS['conf']['fetchmail']['show_account_colors']) {
                    $hdr = @imap_fetchheader($GLOBALS['imp']['stream'], $header->uid, FT_UID);
                    if (preg_match("/X-color:(.*)\n/", $hdr, $color)) {
                        $overview[$key]['color'] = trim($color[1]);
                    }
                }

                $overview[$key]['mbox'] = $mbox;

                /* Add preview information. */
                if ($show_preview &&
                    (!$this->_previews['unread'] ||
                     !$header->seen ||
                     $header->recent)) {
                    require_once IMP_BASE . '/lib/MIME/Contents.php';
                    $imp_contents = &IMP_Contents::singleton($header->uid . IMP_IDX_SEP . $mbox);
                    if (($mimeid = $imp_contents->findBody(false)) !== null) {
                        $pmime = &$imp_contents->getDecodedMIMEPart($mimeid);
                        $ptext = $pmime->getContents();
                        $ptext = String::convertCharset($ptext, $pmime->getCharset());
                        if ($pmime->getType() == 'text/html') {
                            require_once 'Horde/Text/Filter.php';
                            $ptext = Text_Filter::filter($ptext, 'html2text');
                        }
                        if ($this->_previews['maxlen'] != -1) {
                            if (String::length($ptext) > $this->_previews['maxlen']) {
                                $ptext = String::substr($ptext, 0, $this->_previews['maxlen']) . ' ...';
                            } else {
                                $ptext .= '[[' . _("END") . ']]';
                            }
                        }

                        if ($this->_previews['strip'] &&
                            !$this->_previews['tooltip']) {
                            $ptext = str_replace("\r", "\n", $ptext);
                            $ptext = preg_replace('/\n/', ' ', $ptext);
                            $ptext = preg_replace('/(\s)+/', '$1', $ptext);
                        } else {
                            $ptext = str_replace("\r", '', $ptext);
                        }
                        if (!$this->_previews['tooltip']) {
                            require_once 'Horde/Text/Filter.php';
                            $ptext = Text_Filter::filter($ptext, 'text2html', array('parselevel' => TEXT_HTML_NOHTML, 'charset' => '', 'class' => ''));
                        }
                    } else {
                        $ptext = '[[' . _("No Preview Text") . ']]';
                    }

                    $overview[$key]['structure'] = $imp_contents->getMIMEMessage();
                    $overview[$key]['preview'] = $ptext;
                } else {
                    require_once 'Horde/MIME/Structure.php';
                    $overview[$key]['structure'] = ($GLOBALS['conf']['mailbox']['show_attachments']) ? MIME_Structure::parse(@imap_fetchstructure($GLOBALS['imp']['stream'], $header->uid, FT_UID)) : null;
                    $overview[$key]['preview'] = null;
                }
            }
        }

        /* Sort via the sorted array index. */
        ksort($overview);

        return $overview;
    }

    /**
     * Builds the list of messages in the mailbox.
     *
     * @access private
     *
     * @return array  The sorted index.
     */
    function _buildMailbox()
    {
        $this->_newmsgs = 0;

        if ($GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox'])) {
            $query = null;
            if ($this->_delhide) {
                require_once IMP_BASE . '/lib/IMAP/Search.php';
                $query = &new IMP_IMAP_Search_Query();
                $query->deleted(false);
            }
            $sorted = $GLOBALS['imp_search']->runSearch($query, $GLOBALS['imp']['mailbox']);

            /* Return to the search page if this is not a virtual folder. */
            if (empty($sorted) &&
                !$GLOBALS['imp_search']->isVFolder($GLOBALS['imp']['mailbox'])) {
                $GLOBALS['notification']->push(_("No messages matched your search."), 'horde.warning');
                header('Location: ' . Util::addParameter(Horde::applicationUrl('search.php', true), 'no_match', 1));
                exit;
            }
        } else {
            $this->_getSortedIndex();
            if (!empty($this->_sorted) &&
                (($GLOBALS['prefs']->getValue('nav_popup') ||
                  $GLOBALS['prefs']->getValue('nav_audio')) &&
                 !$this->_no_newmail_popup)) {
                $newQuery = 'RECENT';
                if ($this->_delhide) {
                    $newQuery .= ' UNDELETED';
                }
                if (($new = @imap_search($GLOBALS['imp']['stream'], $newQuery))) {
                    $this->_newmsgs = count($new);
                }
            }
            $sorted = $this->_sorted;
        }

        /* Store the sorted range in IMP_Message. */
        $this->_setRange($sorted);

        return $sorted;
    }

    /**
     * The number of new messages in the mailbox (IMAP RECENT flag,
     * with UNDELETED if we're hiding deleted messages).
     *
     * @return integer The number of new messages in the mailbox.
     */
    function newMessageCount()
    {
        return $this->_newmsgs;
    }

    /**
     * Get the list of unseen messages in the mailbox (IMAP UNSEEN flag).
     *
     * @return array  The list of unseen messages.
     */
    function unseenMessages()
    {
        $newQuery = 'UNSEEN';
        if ($this->_delhide) {
            $newQuery .= ' UNDELETED';
        }
        $ret = @imap_search($GLOBALS['imp']['stream'], 'ALL ' . $newQuery, SE_UID);
        return (empty($ret)) ? array() : $ret;
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
        return !is_null($this->_arrayIndex);
    }

    /**
     * Returns IMAP mbox/UID information on a message.
     *
     * @param integer $offset  The offset from the current message.
     *
     * @return array  'index'   -- The message index.
     *                'mailbox' -- The mailbox.
     */
    function getIMAPIndex($offset = 0)
    {
        $return_array = array();
        $index = $this->_arrayIndex + $offset;

        /* If the offset would put us out of array index, return now. */
        if (!isset($this->_sorted[$index])) {
            return $return_array;
        }

        $return_array['index'] = $this->_sorted[$index];
        if ($GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox'])) {
            $return_array['mailbox'] = $this->_sortedMbox[$index];
        } else {
            $return_array['mailbox'] = $GLOBALS['imp']['mailbox'];
        }

        return $return_array;
    }

    /**
     * Update the current mailbox if an action has been performed on the
     * current message index.
     *
     * @param integer $action   The action to perform.
     * @param boolean $success  Was the action successful?
     */
    function updateMailbox($action, $success = true)
    {
        switch ($action) {
        case IMP_MAILBOX_COPY:
            /* Just move to the next message. */
            $this->moveNext();

            /* Determine if we need to resort. */
            $this->_sortIfNeeded();
            break;

        case IMP_MAILBOX_MOVE:
        case IMP_MAILBOX_DELETE:
            /* If we are using POP, we need to resort every time. */
            if ($success && $this->_usepop) {
                $this->_getSortedIndex();
            } elseif ($success &&
                      ($this->_delhide ||
                       $GLOBALS['prefs']->getValue('use_trash'))) {
                /* Nuke message from sorted list if sent to trash or
                   hidden. */
                $this->_removeCurrent();
            } else {
                /* Either we failed, or the message is still in the
                   mailbox and has been marked as deleted - just move
                   to the next message. */
                $this->moveNext();

                /* Determine if we need to resort. */
                $this->_sortIfNeeded();
            }
            break;

        case IMP_MAILBOX_UNDELETE:
            $this->moveNext();
            break;

        case IMP_MAILBOX_EXPUNGE:
        case IMP_MAILBOX_EMPTY:
        case IMP_MAILBOX_UPDATE:
            if (!$GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox'])) {
                $this->_getSortedIndex();
            }
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
     * 'anymsg'     -  Are there any messages at all in mailbox? E.g. If
     *                 'msgcount' is 0, there may still be hidden deleted
     *                 messages.
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
        $sorted = $this->_buildMailbox();
        $msgcount = $this->getMessageCount();

        $maxmsgs = $GLOBALS['prefs']->getValue('max_msgs');
        $sortdir = $GLOBALS['prefs']->getValue('sortdir');

        $this->_determineSort();

        if ($msgcount > $maxmsgs) {
            $pageCount = ceil($msgcount / (($maxmsgs > 0) ? $maxmsgs : 20));

            /* Determine which page to display. */
            if (empty($page) || strcspn($page, '0123456789')) {
                if (!empty($start)) {
                    /* Messages set this when returning to a mailbox. */
                    $page = ceil($start / $maxmsgs);
                } else {
                    $startpage = $GLOBALS['prefs']->getValue('mailbox_start');
                    switch ($startpage) {
                    case IMP_MAILBOXSTART_FIRSTPAGE:
                        $page = 1;
                        break;

                    case IMP_MAILBOXSTART_LASTPAGE:
                        $page = $pageCount;
                        break;

                    case IMP_MAILBOXSTART_FIRSTUNSEEN:
                    case IMP_MAILBOXSTART_LASTUNSEEN:
                        if (!$this->aboveSortLimit() &&
                            !$GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox']) &&
                            ($query = $this->unseenMessages())) {
                            $sortednew = array_keys(array_intersect($sorted, $query));
                            $first_new = ($startpage == IMP_MAILBOXSTART_FIRSTUNSEEN) ?
                                array_shift($sortednew) :
                                array_pop($sortednew);
                            $page = ceil(($first_new + 1) / $maxmsgs);
                        }
                        break;
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

            $begin = (($page - 1) * $maxmsgs) + 1;
            $end = $begin + $maxmsgs - 1;
            if ($end > $msgcount) {
                $end = $msgcount;
            }
        } else {
            $begin = 1;
            $end = $msgcount;
            $page = 1;
            $pageCount = 1;
        }

        if ($GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox'])) {
            $beginIndex = $begin - 1;
        } else {
            $beginIndex = $this->getArrayIndex();
        }

        /* If there are no viewable messages, check for deleted messages in
           the mailbox. */
        $anymsg = true;
        if (($msgcount == 0) &&
            !$GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox'])) {
            $status = @imap_status($GLOBALS['imp']['stream'], IMP::serverString($GLOBALS['imp']['mailbox']), SA_MESSAGES);
            if (is_object($status) && ($status->messages == 0)) {
                $anymsg = false;
            }
        }

        $ob = new stdClass;
        $ob->anymsg    = $anymsg;
        $ob->begin     = $begin;
        $ob->end       = $end;
        $ob->index     = $beginIndex;
        $ob->msgcount  = $msgcount;
        $ob->page      = $page;
        $ob->pagecount = $pageCount;

        return $ob;
    }

    /**
     * Return the current sort method used.
     *
     * @return integer  The sort method.
     */
    function sortby()
    {
        static $orig_sort;

        $sortpref = $GLOBALS['prefs']->getValue('sortby');
        if (!isset($this->_sortby) || ($orig_sort != $sortpref)) {
            $this->_sortby = $orig_sort = $sortpref;

            /* The search mailbox doesn't support thread sort. */
            if ($GLOBALS['imp_search']->isSearchMbox($_SESSION['imp']['mailbox']) &&
                ($this->_sortby == SORTTHREAD)) {
                $this->_sortby = SORTDATE;
            }
        }

        return $this->_sortby;
    }

    /**
     * Determine whether the sort limit has been reached.
     *
     * @return boolean  Has the sort limit been reached?
     */
    function aboveSortLimit()
    {
        return $this->_sortLimit;
    }

    /**
     * Is this a 'special' folder (e.g. 'drafts' or 'sent-mail' folder)?
     *
     * @return boolean  Is this a 'special' folder?
     */
    function isSpecialFolder()
    {
        if (is_null($this->_specialFolder)) {
            /* Get the identities. */
            require_once 'Horde/Identity.php';
            $identity = &Identity::singleton(array('imp', 'imp'));

            $this->_specialFolder = (($GLOBALS['imp']['mailbox'] == IMP::folderPref($GLOBALS['prefs']->getValue('drafts_folder'), true)) || (in_array($GLOBALS['imp']['mailbox'], $identity->getAllSentmailFolders())));
        }

        return $this->_specialFolder;
    }

    /**
     * Updates the sorted messages array.
     *
     * @access private
     */
    function _setSorted()
    {
        $this->_sorted = array();
        $this->_sortedMbox = array();

        if (empty($GLOBALS['imp']['msgl'])) {
            $this->_sorted = array();
        } else {
            $msglist = explode(IMP_MSG_SEP, $GLOBALS['imp']['msgl']);
            if ($GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox'])) {
                foreach ($msglist as $val) {
                    list($idx, $mbox) = explode(IMP_IDX_SEP, $val);
                    $this->_sorted[] = $idx;
                    $this->_sortedMbox[] = $mbox;
                }
            } else {
                $this->_sorted = $msglist;
            }
        }
    }

    /**
     * Updates the message array index.
     *
     * @access private
     *
     * @param integer $data  If $type is 'offset', the number of messages to
     *                       increase array index by.  If type is 'uid',
     *                       sets array index to the value of the current
     *                       current message index.
     * @param string $type   Either 'offset' or 'uid'.
     */
    function _setArrayIndex($data, $type)
    {
        if ($type == 'offset') {
            $this->_lastArrayIndex = $this->_arrayIndex;
            $this->_arrayIndex += $data;
            if (empty($this->_sorted[$this->_arrayIndex])) {
                $this->_arrayIndex = null;
            }
        } elseif ($type == 'uid') {
            if ($GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox'])) {
                /* Need to compare both mbox name and message UID to
                   obtain the correct array index since there may be
                   duplicate UIDs. */
                $this->_arrayIndex = null;
                foreach (array_keys($this->_sorted, $data) as $key) {
                    if ($this->_sortedMbox[$key] == $_SESSION['imp']['thismailbox']) {
                        $this->_arrayIndex = $key;
                        break;
                    }
                }
            } else {
                /* array_search() returns false on no result. We will
                   set an unsuccessful result to NULL. */
                if (($this->_arrayIndex = array_search($data, $this->_sorted)) === false) {
                    $this->_arrayIndex = null;
                }
            }
            $this->_lastArrayIndex = $this->_arrayIndex;
        }

        if (is_null($this->_arrayIndex) && !empty($this->_lastArrayIndex)) {
            $this->_lastArrayIndex--;
        }
    }

    /**
     * Get the sorted list of messages for a mailbox. This function
     * correctly handles sort preferences, deletion preferences
     * (e.g. hide deleted messages), and protocol (e.g. 'pop3'
     * vs. 'imap').
     *
     * The following variables are updated by this method:
     *   msgl
     *
     * @access private
     */
    function _getSortedIndex()
    {
        $sortdir = $GLOBALS['prefs']->getValue('sortdir');
        $sorted = array();

        $this->_determineSort(true);

        if ($this->sortby() == SORTTHREAD) {
            $sorted = $this->_threadSort((bool)$sortdir);
        } else {
            $delhidecmd = (!$this->_usepop && $this->_delhide) ? 'UNDELETED' : '';
            if ($this->sortby() == SORTARRIVAL) {
                $sorted = imap_search($GLOBALS['imp']['stream'], ($delhidecmd) ? $delhidecmd : 'ALL', SE_UID);
                if ($sorted === false) {
                    return;
                }
                if ($sortdir) {
                    $sorted = array_reverse($sorted);
                }
            } else {
                require_once IMP_BASE . '/lib/IMAP/Search.php';
                $imap_search = &IMP_IMAP_Search::singleton(array('pop3' => $this->_usepop));
                $query = &new IMP_IMAP_Search_Query();
                if ($delhidecmd) {
                    $query->deleted(false);
                }
                $sorted = $imap_search->searchSortMailbox($query, $_SESSION['imp']['stream'], $_SESSION['imp']['mailbox'], $this->sortby(), $sortdir);
                if (is_a($sorted, 'PEAR_Error')) {
                    $sorted = array();
                }
            }
        }

        $this->_setRange($sorted);
    }

    /**
     * Sets the current page of messages based on the current index.
     * The following session variables are updated by this method:
     *   'msgl'
     *
     * @access private
     *
     * @param array $arr  The array of message indices.
     */
    function _setRange($arr)
    {
        $GLOBALS['imp']['msgl'] = implode(IMP_MSG_SEP, $arr);

        /* Set the new sorted message list. */
        $this->_setSorted();

        /* Update the current array index to its new position in the message
         * array. */
        $this->_setArrayIndex(0, 'offset');
    }

    /**
     * Perform re-threading sort.
     *
     * @access private
     *
     * @param boolean $new   True for newest messages first, false for oldest
     *                       messages first.
     *
     * @return array  The sorted list of messages.
     */
    function _threadSort($new)
    {
        $ref_array = @imap_thread($GLOBALS['imp']['stream'], SE_UID);
        if (!is_array($ref_array)) {
            return array();
        }

        require_once IMP_BASE . '/lib/IMAP/Thread.php';
        $this->_thread = &new IMP_Thread($ref_array);
        return $this->_thread->messageList($new);
    }

    /**
     * Get the IMP_Thread object for the current mailbox.
     *
     * @return IMP_Thread  The IMP_Thread object for the current mailbox.
     */
    function getThreadOb()
    {
        if (is_null($this->_thread)) {
            $this->_threadSort(false);
        }
        return $this->_thread;
    }

    /**
     * Determines if a resort is needed, and, if necessary, performs
     * the resort.
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
        /* If array index is null, we have reached the beginning/end
           of the mailbox so we shouldn't sort anything. There is also
           no need to sort the search results. */
        if (!is_null($this->_arrayIndex) &&
            ($this->_arrayIndex != $this->_lastArrayIndex) &&
            !$GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox']) &&
            !$this->getIMAPIndex(1)) {
            $this->_getSortedIndex();
        }
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
        /* Remove the current entry and recalculate the range. */
        unset($this->_sorted[$this->_arrayIndex]);
        if ($GLOBALS['imp_search']->isSearchMbox($GLOBALS['imp']['mailbox'])) {
            unset($this->_sortedMbox[$this->_arrayIndex]);
            $arr = array_map(create_function('$a, $b', 'return $a . IMP_IDX_SEP . $b;'), array_values($this->_sorted), array_values($this->_sortedMbox));
        } else {
            $arr = array_values($this->_sorted);
        }

        $this->_setRange($arr);
    }

    /**
     * Determine the correct sort method for this mailbox.
     *
     * @access private
     *
     * @param boolean  Run the sort_limit check?
     */
    function _determineSort($check = false)
    {
        $sortby = $this->sortby();
        if ($check &&
            ($sortby != SORTARRIVAL) &&
            !empty($GLOBALS['conf']['server']['sort_limit']) &&
            (@imap_num_msg($GLOBALS['imp']['stream']) > $GLOBALS['conf']['server']['sort_limit'])) {
            $this->_sortLimit = true;
            $this->_sortby = SORTARRIVAL;
        } else {
            if ($check) {
                $this->_sortLimit = false;
            }
            if ($this->isSpecialFolder()) {
                /* If the preference is to sort by From Address, when we are
                   in the Drafts or Sent folders, sort by To Address. */
                if ($sortby == SORTFROM) {
                    $this->_sortby = SORTTO;
                }
            } elseif ($sortby == SORTTO) {
                $this->_sortby = SORTFROM;
            }
        }
    }

    /**
     * Move current pointer to the next index.
     */
    function moveNext()
    {
        $this->_setArrayIndex(1, 'offset');
    }

}
