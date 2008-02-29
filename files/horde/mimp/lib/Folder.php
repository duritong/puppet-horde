<?php
/**
 * The MIMP_Folder:: class provides a set of methods for dealing with
 * folders, accounting for subscription, errors, etc.
 *
 * $Horde: mimp/lib/Folder.php,v 1.28.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 2000-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2000-2007 Jon Parise <jon@csh.rit.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@csh.rit.edu>
 * @package MIMP
 */
class MIMP_Folder {

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
     * Returns a reference to the global MIMP_Folder object, only
     * creating it if it doesn't already exist. This ensures that only
     * one MIMP_Folder instance is instantiated for any given session
     *
     * This method must be invoked as:
     *   $mimp_folder = &MIMP_Folder::singleton();
     *
     * @return MIMP_Folder  The MIMP_Folder instance.
     */
    function &singleton()
    {
        static $folder;

        if (!isset($folder)) {
            $folder = new MIMP_Folder();
        }

        return $folder;
    }

    /**
     * Create a new IMAP folder if it does not already exist, and
     * subcribe to it as well if requested.
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

        /* Make sure we are not trying to create a duplicate folder */
        if ($this->exists($folder)) {
            $notification->push(sprintf(_("The folder \"%s\" already exists"), MIMP::displayFolder($folder)), 'horde.warning');
            return false;
        }

        /* Attempt to create the mailbox */
        if (!imap_createmailbox($_SESSION['mimp']['stream'], MIMP::serverString($folder))) {
            $notification->push(sprintf(_("The folder \"%s\" was not created. This is what the server said"), MIMP::displayFolder($folder)) .
                            ': ' . imap_last_error(), 'horde.error');
            return false;
        }

        /* If the user uses SUBSCRIBE, then add to the subscribe list */
        if ($subscribe &&
            !imap_subscribe($_SESSION['mimp']['stream'], MIMP::serverString($folder))) {
            $notification->push(sprintf(_("The folder \"%s\" was created but you were not subscribed to it."), MIMP::displayFolder($folder)), 'horde.warning');
        } else {
            /* The folder creation has been successful. */
            $notification->push(sprintf(_("The folder \"%s\" was successfully created."), MIMP::displayFolder($folder)), 'horde.success');
        }

        /* Update the MIMP_Tree object. */
        require_once MIMP_BASE . '/lib/IMAP/Tree.php';
        $mimptree = &MIMP_Tree::singleton();
        if ($mimptree) {
            $mimptree->insert($folder);
        }

        return true;
    }

    /**
     * Find out if a specific folder exists or not.
     *
     * @param string $folder  The full utf encoded folder name to be checked.
     *
     * @return boolean  Whether or not the folder exists.
     */
    function exists($folder)
    {
        /* Try the IMAP_Tree object first. */
        require_once MIMP_BASE . '/lib/IMAP/Tree.php';
        $mimptree = &MIMP_Tree::singleton();
        if ($mimptree) {
            $elt = $mimptree->get($folder);
            if ($elt && !$mimptree->isContainer($elt)) {
                return true;
            }
        }

        if (!isset($this->_existsResults[$folder])) {
            $res = @imap_list($_SESSION['mimp']['stream'], MIMP::serverString(), $folder);
            $this->_existsResults[$folder] = is_array($res);
        }

        return $this->_existsResults[$folder];
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

        if ($_SESSION['mimp']['base_protocol'] == 'pop3') {
            return $inbox_entry;
        }

        $list = array();
        $subidx = intval($sub);

        /* Compute values that will uniquely identify this list. */
        $full_signature = md5(serialize(array($subidx, $filter)));

        if (isset($this->_listCache[$subidx])) {
            $maildelim = $this->_listCache[$subidx]['d'];
            $mailsort = $this->_listCache[$subidx]['s'];
        } else {
            $maildelim = $mailsort = array();
            foreach ($this->_listFolders($sub) as $val) {
                /* Strip off the prefix only if we are dealing with a personal
                 * namespace. */
                $label = $stripped = substr($val, strpos($val, '}') + 1);
                $ns_info = MIMP::getNamespace($label);
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
            require_once MIMP_BASE . '/lib/IMAP/Sort.php';
            $delimiter = reset($_SESSION['mimp']['namespace']);
            $imap_sort = &new MIMP_IMAP_Sort($delimiter['delimiter']);
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

        return $list;
    }

    /**
     * Returns an array of folders. This is a wrapper around the flist()
     * function which reduces the number of arguments needed if we can assume
     * that MIMP's full environment is present.
     *
     * @param array $filter  An array of mailboxes to ignore.
     * @param boolean $sub   If set, will be used to determine if we should
     *                       list only subscribed folders.
     *
     * @return array  The array of mailboxes returned by flist().
     */
    function flist_MIMP($filter = array(), $sub = null)
    {
        return $this->flist(is_null($sub) ? $GLOBALS['prefs']->getValue('subscribe') : $sub, $filter);
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
        $server = MIMP::serverString();

        /* According to RFC 3501 [6.3.8], the '*' wildcard doesn't
         * necessarily match all visible mailboxes.  So we have to go
         * through each namespace separately, even though we may duplicate
         * mailboxes. */
        foreach ($_SESSION['mimp']['namespace'] as $val) {
            $hiddenboxes = $listcmd($_SESSION['mimp']['stream'], $server, $val['name'] . '*');
            if (is_array($hiddenboxes)) {
                $list = array_unique(array_merge($list, $hiddenboxes));
            }
        }

        return array_values($list);
    }

}
