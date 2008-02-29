<?php

require_once 'Horde/IMAP/Search.php';

/**
 * The IMP_IMAP_Search:: class extends the IMAP_Search class in order to
 * provide necessary bug fixes to ensure backwards compatibility with Horde
 * 3.0.
 *
 * $Horde: imp/lib/IMAP/Search.php,v 1.5.2.2 2007/01/02 13:54:58 jan Exp $
 *
 * Copyright 2006-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @since   IMP 4.1
 * @package Horde_IMAP
 */
class IMP_IMAP_Search extends IMAP_Search {

    /**
     * Returns a reference to the global IMP_IMAP_Search object, only creating
     * it if it doesn't already exist.
     *
     * @see IMAP_Search::singleton()
     */
    function &singleton($params = array())
    {
        static $object;

        if (!isset($object)) {
            $object = new IMP_IMAP_Search($params);
        }

        return $object;
    }

    /**
     * Searches messages by ALL headers (rather than the limited set provided
     * by imap_search()).
     *
     * @see IMAP_Search::searchMailbox()
     */
    function searchMailbox($query, &$imap, $mbox)
    {
        /* Clear the search flag. */
        $this->_searchflag = 0;

        if ($_SESSION['imp']['base_protocol'] != 'pop3') {
            require_once IMP_BASE . '/lib/IMAP.php';
            $imp_imap = &IMP_IMAP::singleton();
            if (!$imp_imap->changeMbox($mbox, OP_READONLY)) {
                return array();
            }
        }

        return $this->_searchMailbox($query, $imap, $mbox);
    }

}

/**
 * The IMP_IMAP_Search_Query:: class extends the IMAP_Search_Query class in
 * order to provide necessary bug fixes to ensure backwards compatibility with
 * Horde 3.0.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @since   IMP 4.1
 * @package Horde_IMAP
 */
class IMP_IMAP_Search_Query extends IMAP_Search_Query {

    /**
     * Builds the IMAP search query.
     */
    function build()
    {
        $search = parent::build();
        if (empty($search)) {
            if (!empty($this->_or)) {
                return $search;
            }
            $search = new stdClass;
            $search->flags = null;
            $search->not = false;
            $search->fullquery = $search->query = 'ALL';
        }
        return $search;
    }
}
