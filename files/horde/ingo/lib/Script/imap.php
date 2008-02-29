<?php
/**
 * The Ingo_Script_imap:: class represents an IMAP client-side script
 * generator.
 *
 * $Horde: ingo/lib/Script/imap.php,v 1.49.10.13 2007/03/21 19:55:16 chuck Exp $
 *
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.curecanti.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Michael Slusarz <slusarz@bigworm.curecanti.org>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Script_imap extends Ingo_Script {

    /**
     * The list of actions allowed (implemented) for this driver.
     *
     * @var array
     */
    var $_actions = array(
        INGO_STORAGE_ACTION_KEEP,
        INGO_STORAGE_ACTION_MOVE,
        INGO_STORAGE_ACTION_DISCARD,
        INGO_STORAGE_ACTION_MOVEKEEP
    );

    /**
     * The categories of filtering allowed.
     *
     * @var array
     */
    var $_categories = array(
        INGO_STORAGE_ACTION_BLACKLIST,
        INGO_STORAGE_ACTION_WHITELIST
    );

    /**
     * The list of tests allowed (implemented) for this driver.
     *
     * @var array
     */
    var $_tests = array(
        'contains', 'not contain'
    );

    /**
     * The types of tests allowed (implemented) for this driver.
     *
     * @var array
     */
    var $_types = array(
        INGO_STORAGE_TYPE_HEADER,
        INGO_STORAGE_TYPE_SIZE,
        INGO_STORAGE_TYPE_BODY
    );

    /**
     * Does the driver support setting IMAP flags?
     *
     * @var boolean
     */
    var $_supportIMAPFlags = true;

    /**
     * Does the driver support the stop-script option?
     *
     * @var boolean
     */
    var $_supportStopScript = true;

    /**
     * This driver can perform on demand filtering (in fact, that is all
     * it can do).
     *
     * @var boolean
     */
    var $_ondemand = true;

    /**
     * Perform the filtering specified in the rules.
     *
     * @param array $params  The parameter array. It MUST contain:
     * <pre>
     * 'imap'     --  An open IMAP stream.
     * 'mailbox'  --  The name of the mailbox to filter.
     * </pre>
     *
     * @return boolean  True if filtering performed, false if not.
     */
    function perform($params)
    {
        global $ingo_storage, $notification, $prefs;

        $whitelist_ids = array();

        /* Get the IMAP_Cache object. */
        require_once 'Horde/IMAP/Cache.php';
        $imap_cache = &IMAP_Cache::singleton();

        /* Only do filtering if:
           1. We have not done filtering before -or-
           2. The mailbox has changed -or-
           3. The rules have changed. */
        $cache = $imap_cache->getCache($params['imap'], $params['mailbox'], 'ingochange');
        if (($cache !== false) && ($cache == $_SESSION['ingo']['change'])) {
            return true;
        }

        require_once 'Horde/MIME.php';
        require_once INGO_BASE . '/lib/IMAP/Search.php';
        $imap_search = &Ingo_IMAP_Search::singleton(array('imap' => $params['imap']));

        /* Grab the rules list. */
        $filters = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_FILTERS);

        /* Should we filter only [un]seen messages? */
        $seen_flag = $prefs->getValue('filter_seen');

        /* Should we use detailed notification messages? */
        $detailmsg = $prefs->getValue('show_filter_msg');

        /* Parse through the rules, one-by-one. */
        foreach ($filters->getFilterlist() as $rule) {
            /* Check to make sure this is a valid rule and that the rule is
               not disabled. */
            if (!$this->_validRule($rule['action']) ||
                !empty($rule['disable'])) {
                continue;
            }

            $search_array = array();
            $stop_flag = false;

            switch ($rule['action']) {
            case INGO_STORAGE_ACTION_BLACKLIST:
            case INGO_STORAGE_ACTION_WHITELIST:
                $bl_folder = null;

                if ($rule['action'] == INGO_STORAGE_ACTION_BLACKLIST) {
                    $blacklist = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_BLACKLIST);
                    $addr = $blacklist->getBlacklist();
                    $bl_folder = $blacklist->getBlacklistFolder();
                } else {
                    $whitelist = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_WHITELIST);
                    $addr = $whitelist->getWhitelist();
                }

                /* If list is empty, move on. */
                if (empty($addr)) {
                    continue;
                }

                $query = new Ingo_IMAP_Search_Query();
                foreach ($addr as $val) {
                    $ob = new Ingo_IMAP_Search_Query();
                    $ob->deleted(false);
                    if ($seen_flag == INGO_SCRIPT_FILTER_UNSEEN) {
                        $ob->seen(false);
                    } elseif ($seen_flag == INGO_SCRIPT_FILTER_SEEN) {
                        $ob->seen(true);
                    }
                    $ob->header('from', $val);
                    $search_array[] = $ob;
                }
                $query->imapOr($search_array);
                $indices = $imap_search->searchMailbox($query, $params['imap'], $params['mailbox']);

                if ($rule['action'] == INGO_STORAGE_ACTION_BLACKLIST) {
                    $indices = array_diff($indices, $whitelist_ids);
                    if (!empty($indices)) {
                        $sequence = implode(',', $indices);
                        if (!empty($bl_folder)) {
                            @imap_mail_move($params['imap'], $sequence, $bl_folder, CP_UID);
                        } else {
                            @imap_delete($params['imap'], $sequence, FT_UID);
                        }
                        @imap_expunge($params['imap']);
                        $notification->push(sprintf(_("Filter activity: %s message(s) that matched the blacklist were deleted."), count($indices)), 'horde.message');
                    }
                } else {
                    $whitelist_ids = $indices;
                }
                break;

            case INGO_STORAGE_ACTION_KEEP:
            case INGO_STORAGE_ACTION_MOVE:
            case INGO_STORAGE_ACTION_DISCARD:
                $query = new Ingo_IMAP_Search_Query();
                foreach ($rule['conditions'] as $val) {
                    $ob = new Ingo_IMAP_Search_Query();
                    $ob->deleted(false);
                    if ($seen_flag == INGO_SCRIPT_FILTER_UNSEEN) {
                        $ob->seen(false);
                    } elseif ($seen_flag == INGO_SCRIPT_FILTER_SEEN) {
                        $ob->seen(true);
                    }
                    if (!empty($val['type']) &&
                        ($val['type'] == INGO_STORAGE_TYPE_SIZE)) {
                        if ($val['match'] == 'greater than') {
                            $operator = '>';
                        } elseif ($val['match'] == 'less than') {
                            $operator = '<';
                        }
                        $ob->size($val['value'], $operator);
                    } elseif (!empty($val['type']) &&
                              ($val['type'] == INGO_STORAGE_TYPE_BODY)) {
                        if ($val['match'] == 'contains') {
                            $ob->body($val['value'], false);
                        } elseif ($val['match'] == 'not contain') {
                            $ob->body($val['value'], true);
                        }
                    } else {
                        if ($val['match'] == 'contains') {
                            $ob->header($val['field'], $val['value'], false);
                        } elseif ($val['match'] == 'not contain') {
                            $ob->header($val['field'], $val['value'], true);
                        }
                    }
                    $search_array[] = $ob;
                }

                if ($rule['combine'] == INGO_STORAGE_COMBINE_ALL) {
                    $query->imapAnd($search_array);
                } else {
                    $query->imapOr($search_array);
                }

                $indices = $imap_search->searchMailbox($query, $params['imap'], $params['mailbox']);

                if (($indices = array_diff($indices, $whitelist_ids))) {
                    $sequence = implode(',', $indices);
                    $stop_flag = true;

                    /* Set the flags. */
                    if (!empty($rule['flags']) &&
                        ($rule['action'] != INGO_STORAGE_ACTION_DISCARD)) {
                        $flags = array();
                        if ($rule['flags'] & INGO_STORAGE_FLAG_ANSWERED) {
                            $flags[] = '\\Answered';
                        }
                        if ($rule['flags'] & INGO_STORAGE_FLAG_DELETED) {
                            $flags[] = '\\Deleted';
                        }
                        if ($rule['flags'] & INGO_STORAGE_FLAG_FLAGGED) {
                            $flags[] = '\\Flagged';
                        }
                        if ($rule['flags'] & INGO_STORAGE_FLAG_SEEN) {
                            $flags[] = '\\Seen';
                        }
                        @imap_setflag_full($params['imap'], $sequence, implode(' ', $flags), ST_UID);
                    }

                    if ($rule['action'] == INGO_STORAGE_ACTION_KEEP) {
                        /* Add these indices to the current whitelist. */
                        $whitelist_ids = array_unique($indices + $whitelist_ids);
                    } elseif ($rule['action'] == INGO_STORAGE_ACTION_MOVE) {
                        /* We need to grab the overview first. */
                        if ($detailmsg) {
                            $overview = @imap_fetch_overview($params['imap'], $sequence, FT_UID);
                        }

                        /* Move the messages to the requested mailbox. */
                        @imap_mail_move($params['imap'], $sequence, $rule['action-value'], CP_UID);
                        @imap_expunge($params['imap']);

                        /* Display notification message(s). */
                        if ($detailmsg) {
                            foreach ($overview as $msg) {
                                $notification->push(sprintf(_("Filter activity: The message \"%s\" from \"%s\" has been moved to the folder \"%s\"."),
                                                            isset($msg->subject) ? MIME::decode($msg->subject, NLS::getCharset()) : _("[No Subject]"),
                                                            MIME::decode($msg->from, NLS::getCharset()),
                                                            $rule['action-value']), 'horde.message');
                            }
                        } else {
                            $notification->push(sprintf(_("Filter activity: %s message(s) have been moved to the folder \"%s\"."),
                                                        count($indices),
                                                        $rule['action-value']), 'horde.message');
                        }
                    } elseif ($rule['action'] == INGO_STORAGE_ACTION_DISCARD) {
                        /* We need to grab the overview first. */
                        if ($detailmsg) {
                            $overview = @imap_fetch_overview($params['imap'], $sequence, FT_UID);
                        }

                        /* Delete the messages now. */
                        @imap_delete($params['imap'], $sequence, FT_UID);
                        @imap_expunge($params['imap']);

                        /* Display notification message(s). */
                        if ($detailmsg) {
                            foreach ($overview as $msg) {
                                $notification->push(sprintf(_("Filter activity: The message \"%s\" from \"%s\" has been deleted."),
                                                            isset($msg->subject) ? MIME::decode($msg->subject, NLS::getCharset()) : _("[No Subject]"),
                                                            MIME::decode($msg->from, NLS::getCharset())), 'horde.message');
                            }
                        } else {
                            $notification->push(sprintf(_("Filter activity: %s message(s) have been deleted."), count($indices)), 'horde.message');
                        }
                    } elseif ($rule['action'] == INGO_STORAGE_ACTION_MOVEKEEP) {
                        /* Copy the messages to the requested mailbox. */
                        @imap_mail_copy($params['imap'], $sequence, $rule['action-value'], CP_UID);

                        /* Display notification message(s). */
                        if ($detailmsg) {
                            $overview = @imap_fetch_overview($params['imap'], $sequence, FT_UID);
                            foreach ($overview as $msg) {
                                $notification->push(sprintf(_("Filter activity: The message \"%s\" from \"%s\" has been copied to the folder \"%s\"."), isset($msg->subject) ? MIME::decode($msg->subject, NLS::getCharset()) : _("[No Subject]"), MIME::decode($msg->from, NLS::getCharset()), $rule['action-value']), 'horde.message');
                            }
                        } else {
                            $notification->push(sprintf(_("Filter activity: %s message(s) have been copied to the folder \"%s\"."), count($indices), $rule['action-value']), 'horde.message');
                        }
                    }
                }
                break;
            }

            /* Handle stop flag. */
            if ($stop_flag && $rule['stop']) {
                break;
            }
        }

        /* Set cache flag. */
        $imap_cache->storeCache($params['imap'], $params['mailbox'], array('ingochange' => $_SESSION['ingo']['change']));

        return true;
    }

    /**
     * Is the apply() function available?
     * The 'mail/getStream' API function must be available.
     *
     * @return boolean  True if apply() is available, false if not.
     */
    function canApply()
    {
        global $registry;

        return ($this->performAvailable() && $registry->hasMethod('mail/getStream'));
    }

    /**
     * Apply the filters now.
     *
     * @return boolean  See perform().
     */
    function apply()
    {
        global $registry;

        if ($this->canApply()) {
            $res = $registry->call('mail/getStream', array('INBOX'));
            if ($res !== false) {
                $ob = @imap_check($res);
                return $this->perform(array('imap' => $res, 'mailbox' => $ob->Mailbox));
            }
        }

        return false;
    }

}
