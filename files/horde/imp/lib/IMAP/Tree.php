<?php

require_once 'Horde/IMAP/Tree.php';

/** Identify an element as a virtual folder. */
define('IMPTREE_ELT_VFOLDER', 8192);

/**
 * The IMP_tree class provides a tree view of the folders in an IMAP/POP3
 * repository.  It provides access functions to iterate through this tree and
 * query information about individual mailboxes.
 *
 * $Horde: imp/lib/IMAP/Tree.php,v 1.25.2.45 2007/04/18 13:20:53 slusarz Exp $
 *
 * Copyright 2000-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2000-2007 Jon Parise <jon@horde.org>
 * Copyright 2000-2007 Anil Madhavapeddy <avsm@horde.org>
 * Copyright 2003-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Anil Madhavapeddy <avsm@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @since   IMP 2.3
 * @package IMP
 */
class IMP_Tree extends IMAP_Tree {

    /**
     * Mapping for virtual folders to their label.
     *
     * @var array
     */
    var $_vfolders = array();

    /**
     * Are we inheriting from the new (i.e. Horde 3.1+) version of IMAP_Tree?
     *
     * @var boolean
     */
    var $_newimaptree = false;

    /**
     * Singleton method.
     * By default, the tree will contain only currently viewable items and
     * will be stored in the session under 'imp'.  However, if you would
     * rather pass different parameters to IMAP_Tree, you may declare a
     * global variable named $imp_tree_singleton with the array of parameters
     * you want to pass to IMAP_Tree::singleton().
     */
    function &singleton()
    {
        if (empty($GLOBALS['imp_tree_singleton'])) {
            return IMAP_Tree::singleton('imp', 'IMP_Tree', true);
        } else {
            $ret = call_user_func_array(array('IMAP_Tree', 'singleton'), $GLOBALS['imp_tree_singleton']);
            return $ret;
        }
    }

    /**
     * Constructor.
     *
     * @see IMAP_Tree::IMAP_Tree()
     */
    function IMP_Tree($init = IMAPTREE_INIT_SUB, $cachename = null)
    {
        global $imp;

        $this->_app = 'imp';
        $this->_mode = IMAPTREE_MODE_MAIL;
        $this->_server = IMP::serverString();

        if ($imp['base_protocol'] != 'pop3') {
            $ptr = reset($_SESSION['imp']['namespace']);
            $this->_delimiter = $ptr['delimiter'];
            if (method_exists($this, 'extendedNamespaceSupport')) {
                $this->_newimaptree = true;
                $this->IMAPchildrenSupport($_SESSION['imp']['imap_server']['children']);
                $this->_namespaces = (empty($GLOBALS['conf']['user']['allow_folders'])) ? array() : $_SESSION['imp']['namespace'];
            } else {
                // BC for Horde < 3.1.0
                $this->_delimiter = $ptr['delimiter'];
                $this->_prefix = $ptr['name'];
            }
        }

        parent::IMAP_Tree(null);
    }

    /**
     * Initalizes the list at the top level of the hierarchy.
     *
     * @see IMAP_Tree::_init()
     */
    function init($init = null)
    {
        static $already_init = null;

        $initmask = (($GLOBALS['imp']['base_protocol'] == 'pop3') ||
                     !$GLOBALS['prefs']->getValue('subscribe') ||
                     $GLOBALS['imp']['showunsub'])
            ? IMAPTREE_INIT_UNSUB : IMAPTREE_INIT_SUB;
        if ($GLOBALS['prefs']->getValue('show_sidebar')) {
            $initmask |= IMAPTREE_INIT_FETCHALL;
        }

        if ($already_init == $initmask) {
            return;
        } else {
            $already_init = $initmask;
        }
        parent::init($initmask);

        /* Convert 'INBOX' to localized name. */
        $this->_tree['INBOX']['l'] = _("Inbox");

        /* Add virtual folders to the tree. */
        $this->_vfolders = array();
        $this->insertVFolders($GLOBALS['imp_search']->listQueries(true));
    }

    /**
     * Inserts virtual folders into the tree.
     *
     * @param array $id_list  An array with the folder IDs to add as the key
     *                        and the labels as the value.
     */
    function insertVFolders($id_list)
    {
        if (empty($id_list)) {
            return;
        }

        $id = array();
        foreach ($id_list as $key => $val) {
            $id[$GLOBALS['imp_search']->createSearchID($key)] = $val;
        }

        if (empty($this->_vfolders)) {
            $this->_vfolders = $id;
            $id = array_merge(array(_("Virtual Folders")), array_keys($id));
        } else {
            $this->_vfolders = array_merge($this->_vfolders, $id);
            $id = array_keys($id);
        }

        $this->_nonimapelt = true;
        $this->_initmode = IMAPTREE_INIT_SUB;
        $this->insert($id);
        $this->_initmode = 0;
        $this->_nonimapelt = false;

        /* Sort the Virtual Folder list in the object, if necessary. */
        if ($this->_needSort($this->_tree[_("Virtual Folders")])) {
            $vsort = array();
            foreach ($this->_parent[_("Virtual Folders")] as $val) {
                $vsort[$val] = $this->_tree[$val]['l'];
            }
            natcasesort($vsort);
            $this->_parent[_("Virtual Folders")] = array_keys($vsort);
            $this->_setNeedSort($this->_tree[_("Virtual Folders")], false);
            $this->_changed = true;
        }
    }

    /**
     * Subclass specific initialization tasks.
     *
     * @see IMAP_Tree::_init()
     */
    function _init()
    {
        $boxes = array();

        if (empty($GLOBALS['conf']['user']['allow_folders'])) {
            $boxes['INBOX'] = $this->_getMailbox('INBOX');
            return $boxes;
        }

        foreach ($_SESSION['imp']['namespace'] as $val) {
            /* We only need to provide the list of folders in the base
             * personal namespace.  Else, just use the base namespace entry. */
            if (($val['type'] == 'personal') || empty($val['name'])) {
                $query = $val['name'] . '%';
            } else {
                if (empty($val['delimiter'])) {
                    $query = $val['name'];
                } else {
                    $query = rtrim($val['name'], $val['delimiter']);
                }
                if (isset($tmp[$query])) {
                    continue;
                }
            }
            $tmp = $this->_getList($query);
            if (!empty($tmp)) {
                if ($val['type'] == 'personal') {
                    /* IMAP servers put the INBOX in the personal namespace -
                     * simply rename to 'INBOX' since that is where we
                     * always access the mailbox. */
                    $inbox_str = $val['name'] . 'INBOX';
                    if (!empty($val['name']) && isset($tmp[$inbox_str])) {
                        $tmp = array('INBOX' => $tmp[$inbox_str]) + $tmp;
                        $tmp['INBOX']->name = 'INBOX';
                        unset($tmp[$inbox_str]);
                    }
                }
                $boxes = array_merge($boxes, $tmp);
            }
        }

        if (!isset($boxes['INBOX'])) {
            $boxes['INBOX'] = $this->_getMailbox('INBOX');
        }

        /* Do a sort to make sure that 'INBOX' always appears as the first
         * element. */
        require_once HORDE_BASE . '/lib/version.php';
        if (version_compare(HORDE_VERSION, '3.0.4') == -1) {
            $this->_sortList($boxes, true);
        }

        return $boxes;
    }

    /**
     * Adds aliases to a tree element and returns the resulting array.
     *
     * @access protected
     *
     * @param array $elt  A tree element.
     *
     * @return array  A tree element with the aliases added.
     */
    function _addAliases($elt)
    {
        $elt = parent::_addAliases($elt);
        if ($elt['label'] == 'INBOX') {
            $elt['label'] = _("Inbox");
        }

        return $elt;
    }

    /**
     * Returns a reference to a currently open IMAP stream.
     *
     * @see IMAP_Tree::_getStream()
     */
    function &_getStream()
    {
        return $_SESSION['imp']['stream'];
    }

    /**
     * Initializes the expanded folder list.
     *
     * @see IMAP_Tree::_initExpandedList()
     */
    function _initExpandedList()
    {
        if (is_null($this->_expanded)) {
            $serialized = $GLOBALS['prefs']->getValue('expanded_folders');
            $this->_expanded = ($serialized) ? unserialize($serialized) : array();
        }
    }

    /**
     * Adds an element to the expanded list.
     *
     * @see IMAP_Tree::_addExpandedList()
     */
    function _addExpandedList($id)
    {
        $this->_initExpandedList();
        $this->_expanded[$id] = true;
        $GLOBALS['prefs']->setValue('expanded_folders', serialize($this->_expanded));
    }

    /**
     * Removes an element from the expanded list.
     *
     * @see IMAP_Tree::_removeExpandedList()
     */
    function _removeExpandedList($id)
    {
        $this->_initExpandedList();
        unset($this->_expanded[$id]);
        $GLOBALS['prefs']->setValue('expanded_folders', serialize($this->_expanded));
    }

    /**
     * Initializes and returns the list of mailboxes to poll.
     *
     * @see IMAP_Tree::getPollList()
     */
    function getPollList()
    {
        if (is_null($this->_poll)) {
            /* We ALWAYS poll the INBOX. */
            $this->_poll = array('INBOX' => 1);

            /* Add the list of polled mailboxes from the prefs. */
            $navPollList = @unserialize($GLOBALS['prefs']->getValue('nav_poll'));
            if ($navPollList) {
                $this->_poll += $navPollList;
            }
        }

        return $this->_poll;
    }

    /**
     * Adds element(s) to the poll list.
     *
     * @see IMAP_Tree::addPollList()
     */
    function addPollList($id)
    {
        if (!is_array($id)) {
            $id = array($id);
        }

        if (!empty($id) && !$GLOBALS['prefs']->isLocked('nav_poll')) {
            require_once IMP_BASE . '/lib/Folder.php';
            $imp_folder = &IMP_Folder::singleton();
            $this->getPollList();
            foreach ($id as $val) {
                if (!$this->isSubscribed($this->_tree[$val])) {
                    $imp_folder->subscribe(array($val));
                }
                $this->_poll[$val] = true;
                $this->_setPolled($this->_tree[$val], true);
            }
            $GLOBALS['prefs']->setValue('nav_poll', serialize($this->_poll));
            $this->_changed = true;
        }
    }

    /**
     * Removes element(s) from the poll list.
     *
     * @see IMAP_Tree::removePollList()
     */
    function removePollList($id)
    {
        if (!is_array($id)) {
            $id = array($id);
        }

        $removed = false;

        if (!$GLOBALS['prefs']->isLocked('nav_poll')) {
            $this->getPollList();
            foreach ($id as $val) {
                if ($val != 'INBOX') {
                    unset($this->_poll[$val]);
                    if (isset($this->_tree[$val])) {
                        $this->_setPolled($this->_tree[$val], false);
                    }
                    $removed = true;
                }
            }
            if ($removed) {
                $GLOBALS['prefs']->setValue('nav_poll', serialize($this->_poll));
                $this->_changed = true;
            }
        }
    }

    /**
     * Returns the currently selected initialization expanded mode.
     *
     * @see IMAP_Tree::_getInitExpandedMode()
     */
    function _getInitExpandedMode()
    {
        return $GLOBALS['prefs']->getValue('nav_expanded');
    }

    /**
     * Creates the virtual folder container.
     *
     * @access private
     *
     * @return array  A mailbox element.
     */
    function _createVFolderContainer()
    {
        $base = _("Virtual Folders");

        $ob = &new stdClass;
        $ob->delimiter = $this->_delimiter;
        $ob->attributes = LATT_NOSELECT | LATT_HASCHILDREN | IMAPTREE_ELT_IS_DISCOVERED | IMAPTREE_ELT_IS_SUBSCRIBED | IMPTREE_ELT_VFOLDER;
        $ob->fullServerPath = $ob->name = $base;

        $elt = $this->_makeMailboxTreeElt($ob);
        $elt['l'] = $elt['v'] = $base;

        return $elt;
    }

    /**
     * Creates a virtual folder element.
     *
     * @access private
     *
     * @param string $vfolder  Virtual folder ID.
     *
     * @return array  A mailbox element.
     */
    function _createVFolderElt($vfolder)
    {
        $base = _("Virtual Folders");

        $ob = &new stdClass;
        $ob->delimiter = $this->_delimiter;
        $ob->attributes = LATT_HASNOCHILDREN | IMAPTREE_ELT_IS_DISCOVERED | IMAPTREE_ELT_IS_SUBSCRIBED | IMPTREE_ELT_VFOLDER;
        $ob->name = $base . $this->_delimiter . $vfolder;
        $ob->fullServerPath = $ob->name;

        $elt = $this->_makeMailboxTreeElt($ob);
        $elt['l'] = $this->_vfolders[$vfolder];
        $elt['v'] = $vfolder;

        return $elt;
    }

    /**
     * Returns whether this element is a virtual folder.
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the element is a virtual folder.
     */
    function isVFolder($elt)
    {
        return $elt['a'] & IMPTREE_ELT_VFOLDER;
    }

    /**
     * Returns a non-IMAP mailbox element given an element identifier.
     *
     * @access private
     *
     * @param string $id  The element identifier.
     *
     * @return array  A mailbox element.
     */
    function _getNonIMAPElt($id)
    {
        if ($id == _("Virtual Folders")) {
            return $this->_createVfolderContainer();
        } else {
            return $this->_createVfolderElt($id);
        }
    }

    /**
     * Deletes an element from the tree.
     *
     * @see IMAP_Tree::delete()
     */
    function delete($id)
    {
        if (!is_array($id)) {
            $vfolder_base = ($id == _("Virtual Folders"));
            $search_id = $GLOBALS['imp_search']->createSearchID($id);

            if (($vfolder_base ||
                 (isset($this->_tree[$search_id]) &&
                  $this->isVFolder($this->_tree[$search_id])))) {
                if (!$vfolder_base) {
                    $id = $search_id;
                }
                $parent = $this->_tree[$id]['p'];
                unset($this->_vfolders[$id]);
                unset($this->_tree[$id]);

                /* Delete the entry from the parent tree. */
                $key = array_search($id, $this->_parent[$parent]);
                unset($this->_parent[$parent][$key]);

                /* Rebuild the parent tree. */
                if (!$vfolder_base && empty($this->_parent[$parent])) {
                    $this->delete($parent);
                } else {
                    $this->_parent[$parent] = array_values($this->_parent[$parent]);
                }
                $this->_changed = true;

                return true;
            }
        }

        return parent::delete($id);
    }

    /**
     * Rename a current folder.
     *
     * @since IMP 4.1
     *
     * @param array $old  The old folder names.
     * @param array $new  The new folder names.
     */
    function rename($old, $new)
    {
        foreach ($old as $key => $val) {
            $polled = (isset($this->_tree[$val])) ? $this->isPolled($this->_tree[$val]) : false;
            if ($this->delete($val)) {
                $this->insert($new[$key]);
                if ($polled) {
                    $this->addPollList($new[$key]);
                }
            }
        }
    }

    /**
     * Does the element have any children?
     *
     * @see IMAP_Tree::hasChildren()
     */
    function hasChildren($elt, $viewable = false)
    {
        if ($this->isVFolder($elt) && $this->isContainer($elt)) {
            return true;
        }
        return parent::hasChildren($elt, $viewable);
    }

    /**
     * Initializes the list of subscribed mailboxes.
     *
     * @deprecated since Horde 3.1
     *
     * @see IMAP_Tree::_initSubscribed()
     */
    function _initSubscribed()
    {
        $hsub = (is_null($this->_subscribed));
        parent::_initSubscribed();

        /* Add in other hierarchy subscription information. */
        if (!$this->_newimaptree && $hsub && $this->_namespaces) {
            foreach ($this->_namespaces as $val) {
                $sublist = @imap_lsub($this->_getStream(), $this->_server, $val['name'] . '*');
                if (!empty($sublist)) {
                    foreach ($sublist as $val2) {
                        $this->_subscribed[substr($val2, strpos($val2, '}') + 1)] = 1;
                    }
                }
            }
        }
    }

    /**
     * Returns a list of all IMAP folders in the tree (i.e. not containers or
     * non-imap elements).
     *
     * @todo Move core code to framework.
     * @since IMP 4.1
     *
     * @return array  An array of IMAP mailbox names.
     */
    function folderList()
    {
        $ret_array = array();

        $mailbox = $this->reset();
        do {
            if (!$this->isContainer($mailbox) &&
                !$this->isVFolder($mailbox)) {
                $ret_array[] = $mailbox['v'];
            }
        } while (($mailbox = $this->next(IMAPTREE_NEXT_SHOWCLOSED)));

        return $ret_array;
    }

    /**
     * Is the mailbox open in the sidebar?
     *
     * @since IMP 4.1.1
     *
     * @param array $mbox  A mailbox name.
     *
     * @return integer  True if the mailbox is open in the sidebar.
     */
    function isOpenSidebar($mbox)
    {
        switch ($GLOBALS['prefs']->getValue('nav_expanded_sidebar')) {
        case IMAPTREE_OPEN_USER:
            $this->_initExpandedList();
            return !empty($this->_expanded[$mbox]);
            break;

        case IMAPTREE_OPEN_ALL:
            return true;
            break;

        case IMAPTREE_OPEN_NONE:
        default:
            return false;
            break;
        }
    }

}
