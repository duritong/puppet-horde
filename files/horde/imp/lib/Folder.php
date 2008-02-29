<?php
/**
 * The IMP_Folder:: class provides a set of methods for dealing with folders,
 * accounting for subscription, errors, etc.
 *
 * $Horde: imp/lib/Folder.php,v 1.130.10.43 2007/07/18 20:09:50 chuck Exp $
 *
 * Copyright 2000-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2000-2007 Jon Parise <jon@csh.rit.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@csh.rit.edu>
 * @since   IMP 2.3
 * @package IMP
 */
class IMP_Folder {

    /**
     * Keep around identical lists so that we don't hit the server more that
     * once in the same page for the same thing.
     *
     * @var array
     */
    var $_listCache = array();

    /**
     * Keep track of mailbox names that we have complained about to prevent
     * giving the user identical error messages.
     *
     * @var array
     */
    var $_errorCache = array();

    /**
     * Cached results from the exists() function.
     *
     * @var array
     */
    var $_existsResults = array();

    /**
     * Returns a reference to the global IMP_Folder object, only creating it
     * if it doesn't already exist. This ensures that only one IMP_Folder
     * instance is instantiated for any given session.
     *
     * This method must be invoked as:<code>
     *   $imp_folder = &IMP_Folder::singleton();
     * </code>
     *
     * @return IMP_Folder  The IMP_Folder instance.
     */
    function &singleton()
    {
        static $folder;

        if (!isset($folder)) {
            $folder = new IMP_Folder();
        }

        return $folder;
    }

    /**
     * Lists folders.
     *
     * @param boolean $sub   Should we list only subscribed folders?
     * @param array $filter  An list of mailboxes that should be left out of
     *                       the list.
     *
     * @return array  An array of folders, where each array alement is an
     *                associative array containing three values: 'val', with
     *                entire folder name after the server specification;
     *                'label', with the full-length folder name meant for
     *                display and 'abbrev', containing a shortened (26
     *                characters max) label for display in situations where
     *                space is short.
     */
    function flist($sub = false, $filter = array())
    {
        global $conf, $notification;

        $inbox_entry = array('INBOX' => array('val' => 'INBOX', 'label' => _("Inbox"), 'abbrev' => _("Inbox")));

        if ($_SESSION['imp']['base_protocol'] == 'pop3') {
            return $inbox_entry;
        }

        $list = array();
        $subidx = intval($sub);

        /* Compute values that will uniquely identify this list. */
        $full_signature = md5(serialize(array($subidx, $filter)));

        /* Either get the list from the cache, or go to the IMAP server to
           obtain it. */
        if ($conf['server']['cache_folders']) {
            require_once 'Horde/SessionObjects.php';
            $sessionOb = &Horde_SessionObjects::singleton();
            if (!isset($_SESSION['imp']['cache']['folder_cache'])) {
                $_SESSION['imp']['cache']['folder_cache'] = array();
            }
            $folder_cache = &$_SESSION['imp']['cache']['folder_cache'];
            if (isset($folder_cache[$full_signature])) {
                $data = $sessionOb->query($folder_cache[$full_signature]);
                if ($data) {
                    return $data;
                }
            }
        }

        if (isset($this->_listCache[$subidx])) {
            $maildelim = $this->_listCache[$subidx]['d'];
            $mailsort = $this->_listCache[$subidx]['s'];
        } else {
            $maildelim = $mailsort = array();
            foreach ($this->_listFolders($sub) as $val) {
                /* Strip off the prefix only if we are dealing with a personal
                 * namespace. */
                $label = $stripped = substr($val, strpos($val, '}') + 1);
                $ns_info = IMP::getNamespace($label);
                if ($ns_info['type'] == 'personal') {
                    $label = substr($label, strlen($ns_info['name']));
                }
                if (strcasecmp('INBOX', $label) == 0) {
                    continue;
                }
                $maildelim[$stripped] = $ns_info['delimiter'];
                $mailsort[$stripped] = $label;
            }
            $this->_listCache[$subidx] = array('d' => $maildelim, 's' => $mailsort);
        }

        if (!empty($mailsort)) {
            // TODO: Fix use of delimiter here
            require_once IMP_BASE . '/lib/IMAP/Sort.php';
            $delimiter = reset($_SESSION['imp']['namespace']);
            $imap_sort = &new IMP_IMAP_Sort($delimiter['delimiter']);
            $imap_sort->sortMailboxes($mailsort, true, true);

            foreach ($mailsort as $mbox_name => $label) {
                if (in_array($mbox_name, $filter)) {
                    continue;
                }

                if (!($decoded_mailbox = String::convertCharset($mbox_name, 'UTF7-IMAP')) &&
                    empty($this->_errorCache[$mbox_name])) {
                    $notification->push(sprintf(_("The folder \"%s\" contains illegal characters in its name. It may cause problems. Please see your system administrator."), $label), 'horde.warning');
                    $this->_errorCache[$mbox_name] = true;
                }

                $parts = ($maildelim[$mbox_name]) ? explode($maildelim[$mbox_name], $label) : array($label);
                $partcount = count($parts);
                for ($i = 1; $i <= $partcount; $i++) {
                    $item = implode($maildelim[$mbox_name], array_slice($parts, 0, $i));
                    if (!isset($list[$item])) {
                        $abbrev = $folded = str_repeat(' ', 4 * ($i - 1)) . String::convertCharset($parts[($i - 1)], 'UTF7-IMAP');
                        if (strlen($abbrev) > 26) {
                            $abbrev = String::substr($abbrev, 0, 10) . '...' . String::substr($abbrev, -13, 13);
                        }
                        $list[$item] = array('val' => ($i == $partcount) ? $mbox_name : '', 'label' => $folded, 'abbrev' => $abbrev);
                    }
                }
            }
        }

        /* Add the INBOX on top of list if not in the filter list. */
        if (!in_array('INBOX', $filter)) {
            $list = $inbox_entry + $list;
        }

        /* Save in cache, if needed. */
        if ($conf['server']['cache_folders']) {
            $folder_cache[$full_signature] = $sessionOb->storeOid($list, false);
        }

        return $list;
    }

    /**
     * Returns an array of folders. This is a wrapper around the flist()
     * function which reduces the number of arguments needed if we can assume
     * that IMP's full environment is present.
     *
     * @param array $filter  An array of mailboxes to ignore.
     * @param boolean $sub   If set, will be used to determine if we should
     *                       list only subscribed folders.
     *
     * @return array  The array of mailboxes returned by flist().
     */
    function flist_IMP($filter = array(), $sub = null)
    {
        return $this->flist(is_null($sub) ? $GLOBALS['prefs']->getValue('subscribe') : $sub, $filter);
    }

    /**
     * Deletes one or more folders.
     *
     * @param array $folder_array  An array of full utf encoded folder names
     *                             to be deleted.
     * @param boolean $subscribe   A boolean describing whether or not to use
     *                             folder subscriptions.
     *
     * @return boolean  Whether or not the folders were successfully deleted.
     */
    function delete($folder_array, $subscribe)
    {
        global $conf, $notification;

        $server = IMP::serverString();
        $return_value = true;
        $deleted = array();

        if ($subscribe) {
            $sub_folders = $this->_listFolders(true);
        }

        foreach ($folder_array as $folder) {
            if (!imap_deletemailbox($_SESSION['imp']['stream'], $server . $folder)) {
                $notification->push(sprintf(_("The folder \"%s\" was not deleted. This is what the server said"), IMP::displayFolder($folder)) .
                                    ': ' . imap_last_error(), 'horde.error');
                $return_value = false;
            } else {
                if ($subscribe &&
                    in_array($server . $folder, $sub_folders) &&
                    !imap_unsubscribe($_SESSION['imp']['stream'], $server . $folder)) {
                    $notification->push(sprintf(_("The folder \"%s\" was deleted but you were not unsubscribed from it."), IMP::displayFolder($folder)), 'horde.warning');
                    $return_value = false;
                } else {
                    $notification->push(sprintf(_("The folder \"%s\" was successfully deleted."), IMP::displayFolder($folder)), 'horde.success');
                }

                $deleted[] = $folder;
                unset($this->_existsResults[$folder]);
            }
        }

        if (!empty($deleted)) {
            /* Update the IMAP_Tree cache. */
            require_once IMP_BASE . '/lib/IMAP/Tree.php';
            $imaptree = &IMP_Tree::singleton();
            if ($imaptree) {
                $imaptree->delete($deleted);
            }

            /* Reset the folder cache. */
            if ($conf['server']['cache_folders']) {
                unset($_SESSION['imp']['cache']['folder_cache']);
            }

            /* Recreate Virtual Folders. */
            $GLOBALS['imp_search']->sessionSetup();
        }

        return $return_value;
    }

    /**
     * Create a new IMAP folder if it does not already exist, and subcribe to
     * it as well if requested.
     *
     * @param string $folder      The full utf encoded folder to be created.
     * @param boolean $subscribe  A boolean describing whether or not to use
     *                            folder subscriptions.
     *
     * @return boolean  Whether or not the folder was successfully created.
     */
    function create($folder, $subscribe)
    {
        global $conf, $notification;

        /* Check permissions. */
        if (!IMP::hasPermission('create_folders')) {
            $message = @htmlspecialchars(_("You are not allowed to create folders."), ENT_COMPAT, NLS::getCharset());
            if (!empty($conf['hooks']['permsdenied'])) {
                $message = Horde::callHook('_perms_hook_denied', array('imp:create_folders'), 'horde', $message);
            }
            $notification->push($message, 'horde.error', array('content.raw'));
            return false;
        } elseif (!IMP::hasPermission('max_folders')) {
            $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d folders."), IMP::hasPermission('max_folders', true)), ENT_COMPAT, NLS::getCharset());
            if (!empty($conf['hooks']['permsdenied'])) {
                $message = Horde::callHook('_perms_hook_denied', array('imp:max_folders'), 'horde', $message);
            }
            $notification->push($message, 'horde.error', array('content.raw'));
            return false;
        }

        /* Make sure we are not trying to create a duplicate folder */
        if ($this->exists($folder)) {
            $notification->push(sprintf(_("The folder \"%s\" already exists"), IMP::displayFolder($folder)), 'horde.warning');
            return false;
        }

        /* Attempt to create the mailbox */
        if (!imap_createmailbox($_SESSION['imp']['stream'], IMP::serverString($folder))) {
            $notification->push(sprintf(_("The folder \"%s\" was not created. This is what the server said"), IMP::displayFolder($folder)) .
                            ': ' . imap_last_error(), 'horde.error');
            return false;
        }

        /* Reset the folder cache. */
        if ($conf['server']['cache_folders']) {
            unset($_SESSION['imp']['cache']['folder_cache']);
        }

        /* If the user uses SUBSCRIBE, then add to the subscribe list */
        $res = imap_subscribe($_SESSION['imp']['stream'], IMP::serverString($folder));
        if ($subscribe && !$res) {
            $notification->push(sprintf(_("The folder \"%s\" was created but you were not subscribed to it."), IMP::displayFolder($folder)), 'horde.warning');
        } else {
            /* The folder creation has been successful */
            $notification->push(sprintf(_("The folder \"%s\" was successfully created."), IMP::displayFolder($folder)), 'horde.success');
        }

        /* Update the IMAP_Tree object. */
        require_once IMP_BASE . '/lib/IMAP/Tree.php';
        $imaptree = &IMP_Tree::singleton();
        if ($imaptree) {
            $imaptree->insert($folder);
        }

        /* Recreate Virtual Folders. */
        $GLOBALS['imp_search']->sessionSetup();

        return true;
    }

    /**
     * Finds out if a specific folder exists or not.
     *
     * @param string $folder  The full utf encoded folder name to be checked.
     *
     * @return boolean  Whether or not the folder exists.
     */
    function exists($folder)
    {
        /* Try the IMAP_Tree object first. */
        require_once IMP_BASE . '/lib/IMAP/Tree.php';
        $imaptree = &IMP_Tree::singleton();
        if ($imaptree) {
            $elt = $imaptree->get($folder);
            if ($elt && !$imaptree->isContainer($elt)) {
                return true;
            }
        }

        if (!isset($this->_existsResults[$folder])) {
            $res = @imap_list($_SESSION['imp']['stream'], IMP::serverString(), $folder);
            $this->_existsResults[$folder] = is_array($res);
        }

        return $this->_existsResults[$folder];
    }

    /**
     * Renames an IMAP folder. The subscription status remains the same.  All
     * subfolders will also be renamed.
     *
     * @param string $old  The old utf encoded folder name.
     * @param string $new  The new utf encoded folder name.
     *
     * @return boolean  Whether or not all folder(s) were successfully renamed.
     */
    function rename($old, $new)
    {
        global $conf, $notification;

        /* Don't try to rename from or to an empty string. */
        if (strlen($old) == 0 || strlen($new) == 0) {
            return false;
        }

        $namespace_info = IMP::getNamespace($old);
        $server = IMP::serverString();
        $success = true;
        $deleted = $inserted = array();

        /* Get list of any folders that are underneath this one. */
        $all_folders = array($server . $old);
        $folder_list = imap_list($_SESSION['imp']['stream'], $server, $old . $namespace_info['delimiter'] . '*');
        if (is_array($folder_list)) {
            $all_folders = array_merge($folder_list, $all_folders);

            /* Sort the folders in reverse order because some IMAP servers
             * will automatically rename all folders when the base folder is
             * renamed which will result in error messages if we rename from
             * the bottom up. */
            require_once IMP_BASE . '/lib/IMAP/Sort.php';
            $ns_new = IMP::getNamespace($new);
            $imap_sort = &new IMP_IMAP_Sort($ns_new['delimiter']);
            $imap_sort->sortMailboxes($all_folders);
            $all_folders = array_reverse($all_folders);
        }

        $sub_folders = $this->_listFolders(true);

        foreach ($all_folders as $folder_old) {
            $subscribe = false;

            $old_pos = strpos($folder_old, '}') + 1;

            /* Get the new folder name. */
            $folder_new = preg_replace('/' . preg_quote($old, '/') . '/', $new, $folder_old, 1);

            /* Get the folder names without the server prefix. */
            $name_old = substr($folder_old, $old_pos);
            $name_new = substr($folder_new, strpos($folder_new, '}') + 1);

            /* Unsubscribe from current folder. */
            if (in_array($folder_old, $sub_folders)) {
                $subscribe = true;
                imap_unsubscribe($_SESSION['imp']['stream'], $folder_old);
            }

            if (imap_renamemailbox($_SESSION['imp']['stream'], $folder_old, $folder_new)) {
                if ($subscribe) {
                    imap_subscribe($_SESSION['imp']['stream'], $folder_new);
                }

                $deleted[] = $name_old;
                $inserted[] = $name_new;

                $notification->push(sprintf(_("The folder \"%s\" was successfully renamed to \"%s\"."), IMP::displayFolder($name_old), IMP::displayFolder($name_new)), 'horde.success');

                unset($this->_existsResults[$name_old]);

                // Change current mailbox if current mailbox was renamed.
                if ($_SESSION['imp']['mailbox'] == $name_old) {
                    $_SESSION['imp']['mailbox'] = $name_new;
                }
            } else {
                $notification->push(sprintf(_("Renaming \"%s\" to \"%s\" failed. This is what the server said"), IMP::displayFolder($name_old), IMP::displayFolder($name_new)) . ': ' . imap_last_error(), 'horde.error');
                $success = false;
            }
        }

        if (!empty($deleted)) {
            /* Update the IMP_Tree cache. */
            require_once IMP_BASE . '/lib/IMAP/Tree.php';
            $imaptree = &IMP_Tree::singleton();
            if ($imaptree) {
                $imaptree->rename($deleted, $inserted);
            }

            /* Reset the folder cache. */
            if ($conf['server']['cache_folders']) {
                unset($_SESSION['imp']['cache']['folder_cache']);
            }

            /* Recreate Virtual Folders. */
            $GLOBALS['imp_search']->sessionSetup();
        }

        return $success;
    }

    /**
     * Subscribes to one or more IMAP folders.
     *
     * @param array $folder_array  An array of full utf encoded folder names
     *                             to be subscribed.
     *
     * @return boolean  Whether or not the folders were successfully
     *                  subscribed to.
     */
    function subscribe($folder_array)
    {
        global $conf, $notification;

        $return_value = true;
        $subscribed = array();

        if (!is_array($folder_array)) {
            $notification->push(_("No folders were specified"), 'horde.warning');
            return false;
        }

        foreach ($folder_array as $folder) {
            if ($folder != ' ') {
                if (!imap_subscribe($_SESSION['imp']['stream'], IMP::serverString($folder))) {
                    $notification->push(sprintf(_("You were not subscribed to \"%s\". Here is what the server said"), IMP::displayFolder($folder)) . ': ' . imap_last_error(), 'horde.error');
                    $return_value = false;
                } else {
                    $notification->push(sprintf(_("You were successfully subscribed to \"%s\""), IMP::displayFolder($folder)), 'horde.success');
                    $subscribed[] = $folder;
                }
            }
        }

        if (!empty($subscribed)) {
            /* Initialize the IMAP_Tree object. */
            require_once IMP_BASE . '/lib/IMAP/Tree.php';
            $imaptree = &IMP_Tree::singleton();
            if ($imaptree) {
                $imaptree->subscribe($subscribed);
            }

            /* Reset the folder cache. */
            if ($conf['server']['cache_folders']) {
                unset($_SESSION['imp']['cache']['folder_cache']);
            }
        }

        return $return_value;
    }

    /**
     * Unsubscribes from one or more IMAP folders.
     *
     * @param array $folder_array  An array of full utf encoded folder names
     *                             to be unsubscribed.
     *
     * @return boolean  Whether or not the folders were successfully
     *                  unsubscribed from.
     */
    function unsubscribe($folder_array)
    {
        global $conf, $notification;

        $return_value = true;
        $unsubscribed = array();

        if (!is_array($folder_array)) {
            $notification->push(_("No folders were specified"), 'horde.message');
            return false;
        }

        foreach ($folder_array as $folder) {
            if ($folder != ' ') {
                if (strcasecmp($folder, 'INBOX') == 0) {
                    $notification->push(sprintf(_("You cannot unsubscribe from \"%s\"."), IMP::displayFolder($folder)), 'horde.error');
                } elseif (!imap_unsubscribe($_SESSION['imp']['stream'], IMP::serverString($folder))) {
                    $notification->push(sprintf(_("You were not unsubscribed from \"%s\". Here is what the server said"), IMP::displayFolder($folder)) . ': ' . imap_last_error(), 'horde.error');
                    $return_value = false;
                } else {
                    $notification->push(sprintf(_("You were successfully unsubscribed from \"%s\""), IMP::displayFolder($folder)), 'horde.success');
                    $unsubscribed[] = $folder;
                }
            }
        }

        if (!empty($unsubscribed)) {
            /* Initialize the IMAP_Tree object. */
            require_once IMP_BASE . '/lib/IMAP/Tree.php';
            $imaptree = &IMP_Tree::singleton();
            if ($imaptree) {
                $imaptree->unsubscribe($unsubscribed);
            }

            /* Reset the folder cache. */
            if ($conf['server']['cache_folders']) {
                unset($_SESSION['imp']['cache']['folder_cache']);
            }
        }

        return $return_value;
    }

    /**
     * Generates a string that can be saved out to an mbox format mailbox file
     * for a folder or set of folders, optionally including all subfolders of
     * the selected folders as well. All folders will be put into the same
     * string.
     *
     * @author Didi Rieder <adrieder@sbox.tugraz.at>
     *
     * @param array $folder_list  A list of full utf encoded folder names to
     *                            generate an mbox file for.
     * @param boolean $recursive  Include subfolders?
     *
     * @return string  An mbox format mailbox file.
     */
    function &generateMbox($folder_list, $recursive = false)
    {
        $body = '';

        if (is_array($folder_list)) {
            require_once IMP_BASE . '/lib/IMAP.php';
            $imp_imap = &IMP_IMAP::singleton();
            foreach ($folder_list as $folder) {
                $imp_imap->changeMbox($folder, OP_READONLY);
                $count = imap_num_msg($_SESSION['imp']['stream']);
                for ($i = 1; $i <= $count; $i++) {
                    $h = imap_header($_SESSION['imp']['stream'], $i);
                    $from = '<>';
                    if (isset($h->from[0])) {
                        if (isset($h->from[0]->mailbox) && isset($h->from[0]->host)) {
                            $from = $h->from[0]->mailbox . '@' . $h->from[0]->host;
                        }
                    }

                    /* We need this long command since some MUAs (e.g. pine)
                       require a space in front of single digit days. */
                    $date = sprintf('%s %2s %s', date('D M', $h->udate), date('j', $h->udate), date('H:i:s Y', $h->udate));
                    $body .= 'From ' . $from . ' ' . $date . "\n";
                    $body .= str_replace("\r\n", "\n", imap_fetchheader($_SESSION['imp']['stream'], $i, FT_PREFETCHTEXT));
                    $body .= str_replace("\r\n", "\n", imap_body($_SESSION['imp']['stream'], $i, FT_PEEK) . "\n");
                }
            }
        }

        return $body;
    }

    /**
     * Imports messages into a given folder from a mbox format mailbox file.
     *
     * @param string $folder  The folder to put the messages into.
     * @param string $mbox    String containing the mbox filename.
     *
     * @return mixed  False (boolean) on fail or the number of messages
     *                imported (integer) on success.
     */
    function importMbox($folder, $mbox)
    {
        $target = IMP::ServerString() . $folder;

        $message = '';
        $msgcount = 0;

        $fd = fopen($mbox, "r");
        while (!feof($fd)) {
            $line = fgets($fd, 4096);

            if (preg_match('/From (.+@.+|- )/A', $line)) {
                if (!empty($message)) {
                    // Make absolutely sure there are no bare newlines.
                    $message = preg_replace("|([^\r])\n|", "\\1\r\n", $message);
                    $message = str_replace("\n\n", "\n\r\n", $message);

                    if (imap_append($_SESSION['imp']['stream'], $target, $message)) {
                        $msgcount++;
                    }
                }
                $message = '';
            } else {
                $message .= $line;
            }
        }
        fclose($fd);

        if (!empty($message)) {
            // Make absolutely sure there are no bare newlines.
            $message = preg_replace("|([^\r])\n|", "\\1\r\n", $message);
            $message = str_replace("\n\n", "\n\r\n", $message);

            if (imap_append($_SESSION['imp']['stream'], $target, $message)) {
                $msgcount++;
            }
        }

        return ($msgcount > 0) ? $msgcount : false;
    }

    /**
     * Get a list of all folders on the server (including any folders in
     * hidden namespaces).
     *
     * @access private
     *
     * @param boolean $sub  List only subscribed folders?
     *
     * @return array  An array of folders names.
     */
    function _listFolders($sub = false)
    {
        $list = array();
        $listcmd = ($sub) ? 'imap_lsub' : 'imap_list';
        $server = IMP::serverString();

        /* According to RFC 3501 [6.3.8], the '*' wildcard doesn't
         * necessarily match all visible mailboxes.  So we have to go
         * through each namespace separately, even though we may duplicate
         * mailboxes. */
        foreach ($_SESSION['imp']['namespace'] as $val) {
            $hiddenboxes = $listcmd($_SESSION['imp']['stream'], $server, $val['name'] . '*');
            if (is_array($hiddenboxes)) {
                $list = array_unique(array_merge($list, $hiddenboxes));
            }
        }

        return array_values($list);
    }

}
