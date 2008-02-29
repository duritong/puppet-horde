<?php

/* Defines used to determine what kind of field query we are dealing with. */
define('IMP_SEARCH_HEADER', 1);
define('IMP_SEARCH_BODY', 2);
define('IMP_SEARCH_DATE', 3);
define('IMP_SEARCH_TEXT', 4);

/* Defines used to identify the flag input. */
define('IMP_SEARCH_FLAG_SEEN', 1);
define('IMP_SEARCH_FLAG_ANSWERED', 2);
define('IMP_SEARCH_FLAG_FLAGGED', 3);
define('IMP_SEARCH_FLAG_DELETED', 4);

/* Defines used to identify whether to show unsubscribed folders. */
define('IMP_SEARCH_SHOW_UNSUBSCRIBED', 0);
define('IMP_SEARCH_SHOW_SUBSCRIBED_ONLY', 1);

/**
 * The IMP_Search:: class contains all code related to mailbox searching
 * in IMP.
 *
 * The class uses the $_SESSION['imp']['search'] variable to store information
 * across page accesses. The format of that entry is as follows:
 *
 * $_SESSION['imp']['search'] = array(
 *     'q' => array(
 *         'id_1' => array(
 *             'query' => IMAP_Search_Query object (serialized),
 *             'folders' => array (List of folders to search),
 *             'uiinfo' => array (Info used by search.php to render page),
 *             'label' => string (Description of search),
 *             'vfolder' => boolean (True if this is a Virtual Folder)
 *         ),
 *         'id_2' => array(
 *             ....
 *         ),
 *         ....
 *     ),
 *     'vtrash_id' => string (The Virtual Trash query ID),
 *     'vinbox_id' => string (The Virtual Inbox query ID)
 * );
 *
 * $Horde: imp/lib/Search.php,v 1.37.10.37 2007/01/02 13:54:56 jan Exp $
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
class IMP_Search {

    /**
     * The ID of the current search query in use.
     *
     * @var string
     */
    var $_id = null;

    /**
     * The IMP_Tree:: object to update.
     *
     * @var object
     */
    var $_imptree;

    /**
     * Save Virtual Folder information when adding entries?
     *
     * @var boolean
     */
    var $_saveVFolder = true;

    /**
     * Constructor.
     *
     * @param array $params  Available parameters:
     * <pre>
     * 'id'  --  The ID of the search query in use.
     * </pre>
     */
    function IMP_Search($params = array())
    {
        if (!empty($params['id'])) {
            $this->_id = $this->_strip($params['id']);
        }
    }

    /**
     * Set up IMP_Search variables for the current session.
     */
    function sessionSetup()
    {
        if (!isset($_SESSION['imp']['search'])) {
            $_SESSION['imp']['search'] = array('q' => array());
        }
        foreach ($this->_getVFolderList() as $key => $val) {
            if (!empty($val['vfolder']) &&
                !$this->isVTrashFolder($key) &&
                !$this->isVINBOXFolder($key)) {
                $_SESSION['imp']['search']['q'][$key] = $val;
                $this->_updateIMPTree('add', $key, $val['label']);
            }
        }
        $this->createVINBOXFolder();
        $this->createVTrashFolder();
    }

    /**
     * Run a search.
     *
     * @param IMAP_Search_Query &$ob  An optional search query to add (via
     *                                'AND') to the active search.
     * @param string $id              The search query id to use (by default,
     *                                will use the current ID set in the
     *                                object).
     *
     * @return array  The sorted list.
     */
    function runSearch(&$ob, $id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        $mbox = '';
        $sorted = array();
        $use_pop3 = ($_SESSION['imp']['base_protocol'] == 'pop3');

        if (empty($_SESSION['imp']['search']['q'][$id])) {
            return $sorted;
        }
        $search = &$_SESSION['imp']['search']['q'][$id];

        $charset = NLS::getCharset();
        $search_params = array('pop3' => $use_pop3, 'charset' => $charset);

        /* Check if the IMAP server supports searches in the current
         * charset. */
        if (empty($_SESSION['imp']['imap_server']['search_charset'][$charset])) {
            $search_params['no_imap_charset'] = true;
        }

        require_once IMP_BASE . '/lib/IMAP/Search.php';
        $imap_search = &IMP_IMAP_Search::singleton($search_params);

        /* Prepare the search query. */
        if (!empty($ob)) {
            $old_query = unserialize($search['query']);
            $query = &new IMP_IMAP_Search_Query();
            $query->imapAnd(array($ob, $old_query));
        } else {
            $query = unserialize($search['query']);
        }

        /* How do we want to sort results? */
        $sortby = $GLOBALS['prefs']->getValue('sortby');
        if ($sortby == SORTTHREAD) {
            $sortby = SORTDATE;
        }
        $sortdir = $GLOBALS['prefs']->getValue('sortdir');

        foreach ($search['folders'] as $val) {
            $results = $imap_search->searchSortMailbox($query, $_SESSION['imp']['stream'], $val, $sortby, $sortdir);

            if (is_array($results)) {
                foreach ($results as $val2) {
                    $sorted[] = $val2 . IMP_IDX_SEP . $val;
                }
            }
        }

        return $sorted;
    }

    /**
     * Creates the IMAP search query in the IMP session.
     *
     * @param IMAP_Search_Query $query  The search query object.
     * @param array $folders            The list of folders to search.
     * @param array $search             The search array used to build the
     *                                  search UI screen.
     * @param string $label             The label to use for the search
     *                                  results.
     * @param string $id                The query id to use (or else one is
     *                                  automatically generated).
     *
     * @return string  Returns the search query id.
     */
    function createSearchQuery($query, $folders, $search, $label, $id = null)
    {
        $id = (empty($id)) ? base_convert(microtime() . mt_rand(), 16, 36) : $this->_strip($id);
        $_SESSION['imp']['search']['q'][$id] = array(
            'query' => serialize($query),
            'folders' => $folders,
            'uiinfo' => $search,
            'label' => $label,
            'vfolder' => false
        );
        return $id;
    }

    /**
     * Deletes an IMAP search query.
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return string  Returns the search query id.
     */
    function deleteSearchQuery($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        $is_vfolder = !empty($_SESSION['imp']['search']['q'][$id]['vfolder']);
        unset($_SESSION['imp']['search']['q'][$id]);

        if ($is_vfolder) {
            $vfolders = $this->_getVFolderList();
            unset($vfolders[$id]);
            $this->_saveVFolderList($vfolders);
            $this->_updateIMPTree('delete', $id);
        }
    }

    /**
     * Retrieves the previously stored search UI information.
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return array  The array necessary to rebuild the search UI page.
     */
    function retrieveUIQuery($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        return (isset($_SESSION['imp']['search']['q'][$id]['uiinfo']))
            ? $GLOBALS['imp']['search']['q'][$id]['uiinfo']
            : array();
    }

    /**
     * Generates the label to use for search results.
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return string  The search results label.
     */
    function getLabel($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        return (isset($_SESSION['imp']['search']['q'][$id]['label']))
            ? $GLOBALS['imp']['search']['q'][$id]['label']
            : '';
    }

    /**
     * Obtains the list of virtual folders for the current user.
     *
     * @access private
     *
     * @return array  The list of virtual folders.
     */
    function _getVFolderList()
    {
        $vfolder = $GLOBALS['prefs']->getValue('vfolder');
        if (empty($vfolder)) {
            return array();
        }

        $vfolder = @unserialize($vfolder);
        if (!is_array($vfolder)) {
            return array();
        }

        $elt = reset($vfolder);
        if (!isset($elt['ob'])) {
            // Already in 4.1+ format.
            return $vfolder;
        }

        // Convert from 4.0 format to 4.1+ format, if necessary
        $convert = array();
        foreach ($vfolder as $key => $val) {
            $convert[$key] = array(
                'query' => $val['ob'],
                'folders' => $val['search']['folders'],
                'uiinfo' => $val['search'],
                'label' => $val['search']['vfolder_label'],
                'vfolder' => true
            );
            unset($convert[$key]['search']['folders'], $convert[$key]['search']['vfolder_label']);
        }
        return $convert;
    }

    /**
     * Saves the list of virtual folders for the current user.
     *
     * @access private
     *
     * @param array  The virtual folder list.
     */
    function _saveVFolderList($vfolder)
    {
        $GLOBALS['prefs']->setValue('vfolder', serialize($vfolder));
    }

    /**
     * Add a virtual folder for the current user.
     *
     * @param IMAP_Search_Query $query  The search query object.
     * @param array $folders            The list of folders to search.
     * @param array $search             The search array used to build the
     *                                  search UI screen.
     * @param string $label             The label to use for the search
     *                                  results.
     * @param string $id                The virtual folder id.
     *
     * @return string  The virtual folder ID.
     */
    function addVFolder($query, $folders, $search, $label, $id = null)
    {
        $id = $this->createSearchQuery($query, $folders, $search, $label, $id);
        $_SESSION['imp']['search']['q'][$id]['vfolder'] = true;
        if ($this->_saveVFolder) {
            $vfolders = $this->_getVFolderList();
            $vfolders[$id] = $_SESSION['imp']['search']['q'][$id];
            $this->_saveVFolderList($vfolders);
        }
        $this->_updateIMPTree('add', $id, $label);
        return $id;
    }

    /**
     * Add a virtual trash folder for the current user.
     */
    function createVTrashFolder()
    {
        /* Delete the current Virtual Trash folder, if it exists. */
        $vtrash_id = $GLOBALS['prefs']->getValue('vtrash_id');
        if (!empty($vtrash_id)) {
            $this->deleteSearchQuery($vtrash_id);
        }

        if (!$GLOBALS['prefs']->getValue('use_vtrash')) {
            return;
        }

        /* Create Virtual Trash with new folder list. */
        require_once IMP_BASE . '/lib/Folder.php';
        $imp_folder = &IMP_Folder::singleton();
        $fl = $imp_folder->flist_IMP();
        $flist = array();
        foreach ($fl as $mbox) {
            if (!empty($mbox['val'])) {
                $flist[] = $mbox['val'];
            }
        }
        array_unshift($flist, 'INBOX');

        require_once IMP_BASE . '/lib/IMAP/Search.php';
        $query = &new IMP_IMAP_Search_query();
        $query->deleted(true);
        $label = _("Virtual Trash");

        $this->_saveVFolder = false;
        if (empty($vtrash_id)) {
            $vtrash_id = $this->addVFolder($query, $flist, array(), $label);
            $GLOBALS['prefs']->setValue('vtrash_id', $vtrash_id);
        } else {
            $this->addVFolder($query, $flist, array(), $label, $vtrash_id);
        }
        $this->_saveVFolder = true;
        $_SESSION['imp']['search']['vtrash_id'] = $vtrash_id;
    }

    /**
     * Determines whether a virtual folder ID is the Virtual Trash Folder.
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return boolean  True if the virutal folder ID is the Virtual Trash
     *                  folder.
     */
    function isVTrashFolder($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        $vtrash_id = $GLOBALS['prefs']->getValue('vtrash_id');
        return (!empty($vtrash_id) && ($id == $vtrash_id));
    }

    /**
     * Add a virtual INBOX folder for the current user.
     */
    function createVINBOXFolder()
    {
        /* Initialize IMP_Tree. */
        require_once IMP_BASE . '/lib/IMAP/Tree.php';
        $imptree = &IMP_Tree::singleton();

        /* Delete the current Virtual Trash folder, if it exists. */
        $vinbox_id = $GLOBALS['prefs']->getValue('vinbox_id');
        if (!empty($vinbox_id)) {
            $this->deleteSearchQuery($vinbox_id);
        }

        if (!$GLOBALS['prefs']->getValue('use_vinbox')) {
            return;
        }

        /* Create Virtual INBOX with nav_poll list. Filter out any nav_poll
         * entries that don't exist. */
        $flist = array_values(array_intersect(array_keys($imptree->getPollList()), $imptree->folderList()));

        /* Sort Virtual INBOX list. */
        require_once IMP_BASE . '/lib/IMAP/Sort.php';
        $ns_new = IMP::getNamespace();
        $imap_sort = new IMP_IMAP_Sort($ns_new['delimiter']);
        $imap_sort->sortMailboxes($flist);

        require_once IMP_BASE . '/lib/IMAP/Search.php';
        $query = &new IMP_IMAP_Search_query();
        $query->seen(false);
        $query->deleted(false);
        $label = _("Virtual INBOX");

        $this->_saveVFolder = false;
        if (empty($vinbox_id)) {
            $vinbox_id = $this->addVFolder($query, $flist, array(), $label);
            $GLOBALS['prefs']->setValue('vinbox_id', $vinbox_id);
        } else {
            $this->addVFolder($query, $flist, array(), $label, $vinbox_id);
        }
        $this->_saveVFolder = true;
        $_SESSION['imp']['search']['vinbox_id'] = $vinbox_id;
    }

    /**
     * Determines whether a virtual folder ID is the Virtual INBOX Folder.
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return boolean  True if the virutal folder ID is the Virtual INBOX
     *                  folder.
     */
    function isVINBOXFolder($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        $vinbox_id = $GLOBALS['prefs']->getValue('vinbox_id');
        return (!empty($vinbox_id) && ($id == $vinbox_id));
    }

    /**
     * Is the current active folder an editable Virtual Folder?
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return boolean  True if the current folder is both a virtual folder
     *                  and can be edited.
     */
    function isEditableVFolder($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        return ($this->isVFolder($id) && !$this->isVTrashFolder($id) && !$this->isVINBOXFolder($id));
    }

    /**
     * Return a list of IDs and query labels, sorted by the label.
     *
     * @param boolean $vfolder  If true, only return Virtual Folders?
     *
     * @return array  An array with the folder IDs as the key and the labels
     *                as the value.
     */
    function listQueries($vfolder = false)
    {
        $vfolders = array();

        if (empty($_SESSION['imp']['search']['q'])) {
            return $vfolders;
        }

        foreach ($_SESSION['imp']['search']['q'] as $key => $val) {
            if (!$vfolder || !empty($val['vfolder'])) {
                $vfolders[$key] = $this->getLabel($key);
            }
        }
        natcasesort($vfolders);

        return $vfolders;
    }

    /**
     * Get the list of searchable folders for the given search query.
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return array  The list of searchable folders.
     */
    function getSearchFolders($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        return (isset($_SESSION['imp']['search']['q'][$id]['folders'])) ? $_SESSION['imp']['search']['q'][$id]['folders'] : array();
    }

    /**
     * Returns a link to edit a given search query.
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @rerturn string  The URL to the search page.
     */
    function editURL($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        return Util::addParameter(Horde::applicationUrl('search.php'), array('edit_query' => $id));
    }

    /**
     * Returns a link to delete a given search query.
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return string  The URL to allow deletion of the search query.
     */
    function deleteURL($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        return Util::addParameter(Horde::applicationUrl('folders.php'), array('actionID' => 'delete_search_query', 'queryid' => $id));
    }

    /**
     * Is the given mailbox a search mailbox?
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return boolean  Whether the given mailbox name is a search mailbox.
     */
    function isSearchMbox($id = null)
    {
        return (is_null($id)) ? !empty($this->_id) : isset($_SESSION['imp']['search']['q'][$this->_strip($id)]);
    }

    /**
     * Is the given mailbox a virtual folder?
     *
     * @param string $id  The search query id to use (by default, will use
     *                    the current ID set in the object).
     *
     * @return boolean  Whether the given mailbox name is a virtual folder.
     */
    function isVFolder($id = null)
    {
        $id = (is_null($id)) ? $this->_id : $this->_strip($id);
        return (!empty($_SESSION['imp']['search']['q'][$id]['vfolder']));
    }

    /**
     * Get the ID for the search mailbox, if we are currently in a search
     * mailbox.
     *
     * @return mixed  The search ID if in a mailbox, else false.
     */
    function searchMboxID()
    {
        return (!is_null($this->_id)) ? $this->_id : false;
    }

    /**
     * Strip the identifying label from a mailbox ID.
     *
     * @access private
     *
     * @param string $id  The mailbox query ID.
     *
     * @return string  The virtual folder ID, with any IMP specific identifying
     *                 information stripped off.
     */
    function _strip($id)
    {
        return (strpos($id, IMP_SEARCH_MBOX) === 0) ? substr($id, strlen(IMP_SEARCH_MBOX)) : $id;
    }

    /**
     * Create the canonical search ID for a given search query.
     *
     * @since IMP 4.1.2
     *
     * @access public
     *
     * @param string $id  The mailbox query ID.
     *
     * @return string  The canonical search query ID.
     */
    function createSearchID($id)
    {
        return IMP_SEARCH_MBOX . $this->_strip($id);
    }

    /**
     * Return the base search fields.
     *
     * @return array  The base search fields.
     */
    function searchFields()
    {
        return array(
            'from' => array(
                'label' => _("From"),
                'type' => IMP_SEARCH_HEADER
            ),
            'to' => array(
                'label' => _("To"),
                'type' => IMP_SEARCH_HEADER
            ),
            'cc' => array(
                'label' => _("Cc"),
                'type' => IMP_SEARCH_HEADER
            ),
            'bcc' => array(
                'label' => _("Bcc"),
                'type' => IMP_SEARCH_HEADER
            ),
            'subject' => array(
                'label' => _("Subject"),
                'type' => IMP_SEARCH_HEADER
            ),
            'body' => array(
               'label' => _("Body"),
               'type' => IMP_SEARCH_BODY
            ),
            'text' => array(
               'label' => _("Entire Message"),
               'type' => IMP_SEARCH_TEXT
            ),
            'received_on' => array(
                'label' => _("Received On"),
                'type' => IMP_SEARCH_DATE
            ),
            'received_until' => array(
                'label' => _("Received Until"),
                'type' => IMP_SEARCH_DATE
            ),
            'received_since' => array(
                'label' => _("Received Since"),
                'type' => IMP_SEARCH_DATE
            )
        );
    }

    /**
     * Update IMAP_Tree object.
     *
     * @access private
     *
     * @param string $action  Either 'delete' or 'add'.
     * @param string $id      The query ID to update.
     * @param string $label   If $action = 'add', the label to use for the
     *                        query ID.
     */
    function _updateIMPTree($action, $id, $label = null)
    {
        if (empty($this->_imptree)) {
            require_once IMP_BASE . '/lib/IMAP/Tree.php';
            $this->_imptree = &IMP_Tree::singleton();
        }

        switch ($action) {
        case 'delete':
            $this->_imptree->delete($id);
            break;

        case 'add':
            $this->_imptree->insertVFolders(array($id => $label));
            break;
        }
    }

}
