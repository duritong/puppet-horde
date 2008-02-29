<?php

require_once 'Horde/IMAP/Tree.php';

/**
 * The MIMP_tree class provides a tree view of the folders in an
 * IMAP/POP3 repository.  It provides access functions to iterate
 * through this tree and query information about individual
 * mailboxes.
 *
 * $Horde: mimp/lib/IMAP/Tree.php,v 1.22.2.1 2007/01/02 13:55:09 jan Exp $
 *
 * Copyright 2000-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2000-2007 Jon Parise <jon@horde.org>
 * Copyright 2000-2007 Anil Madhavapeddy <avsm@horde.org>
 * Copyright 2003-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Anil Madhavapeddy <avsm@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package MIMP
 */
class MIMP_Tree extends IMAP_Tree {

    /**
     * Are we inheriting from the new (i.e. Horde 3.1+) version of IMAP_Tree?
     *
     * @var boolean
     */
    var $_newimaptree = false;

    /**
     * Singleton method.
     *
     * @see IMAP_Tree::singleton()
     */
    function &singleton()
    {
        return IMAP_Tree::singleton('mimp', 'MIMP_Tree', true);
    }

    /**
     * Constructor.
     *
     * @see IMAP_Tree::IMAP_Tree()
     */
    function MIMP_Tree($init = null, $cachename = null)
    {
        $this->_app = 'mimp';
        $this->_mode = IMAPTREE_MODE_MAIL;
        $this->_server = MIMP::serverString();

        if ($_SESSION['mimp']['base_protocol'] != 'pop3') {
            $ptr = reset($_SESSION['mimp']['namespace']);
            $this->_delimiter = $ptr['delimiter'];
            if (method_exists($this, 'extendedNamespaceSupport')) {
                $this->_newimaptree = true;
                $this->_namespaces = $_SESSION['mimp']['namespace'];
                $this->IMAPchildrenSupport($_SESSION['mimp']['imapchildren']);
            } else {
                // BC for Horde < 3.1.0
                $this->_delimiter = $ptr['delimiter'];
                $this->_prefix = $ptr['name'];
            }
        }

        parent::IMAP_Tree($init);
    }

    /**
     * Initalize the list at the top level of the hierarchy.
     *
     * @see IMAP_Tree::init()
     */
    function init($init = null)
    {
        static $already_init = null;

        $initmask = (($_SESSION['mimp']['base_protocol'] == 'pop3') ||
                     !$GLOBALS['prefs']->getValue('subscribe') ||
                     $_SESSION['mimp']['showunsub'])
            ? IMAPTREE_INIT_UNSUB : IMAPTREE_INIT_SUB;
        $initmask |= IMAPTREE_INIT_FETCHALL;

        if ($already_init == $initmask) {
            return;
        } else {
            $already_init = $initmask;
        }

        parent::init($initmask);
        $this->_tree['INBOX']['l'] = _("Inbox");
        $this->expandAll();
    }

    /**
     * Subclass specific initialization tasks.
     *
     * @see IMAP_Tree::_init()
     */
    function _init()
    {
        $boxes = array();

        foreach ($_SESSION['mimp']['namespace'] as $val) {
            $tmp = $this->_getList($val['name'] . '%');
            if (!empty($tmp)) {
                if ($val['type'] == 'personal') {
                    /* Some IMAP servers put the INBOX in the personal
                     * namespace - if we find it in here, simply rename to
                     * 'INBOX' since, per RFCs, the INBOX is always accessible
                     * via this string (although it may not be listed in the
                     * folder list under this string. */
                    $inbox_str = $val['name'] . 'INBOX';
                    if (isset($tmp[$inbox_str])) {
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
     * Returns a reference to a currently open IMAP stream.
     *
     * @see IMAP_Tree::_getStream()
     */
    function &_getStream()
    {
        return $_SESSION['mimp']['stream'];
    }

}
