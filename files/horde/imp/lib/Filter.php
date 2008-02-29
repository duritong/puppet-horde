<?php
/**
 * The IMP_Filter:: class contains all functions related to handling
 * filtering messages in IMP.
 *
 * For full use, the following Horde API calls should be defined
 * (These API methods are not defined in IMP):
 *   mail/applyFilters
 *   mail/canApplyFilters
 *   mail/showFilters
 *   mail/blacklistFrom
 *   mail/showBlacklist
 *   mail/whitelistFrom
 *   mail/showWhitelist
 *
 * $Horde: imp/lib/Filter.php,v 1.56.10.11 2007/03/02 20:50:47 slusarz Exp $
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
class IMP_Filter {

    /**
     * Returns a reference to the global IMP_Filter object, only creating it
     * if it doesn't already exist. This ensures that only one IMP_Filter
     * instance is instantiated for any given session.
     *
     * This method must be invoked as:<code>
     *   $imp_filter = &IMP_Filter::singleton();
     * </code>
     *
     * @return IMP_Filter  The IMP_Filter instance.
     */
    function &singleton()
    {
        static $filter;

        if (!isset($filter)) {
            $filter = new IMP_Filter();
        }

        return $filter;
    }

    /**
     * Runs the filters if they are able to be applied manually.
     *
     * @param string $mbox  The mailbox to apply the filters to.
     */
    function filter($mbox)
    {
        if ($_SESSION['imp']['filteravail']) {
            if (isset($GLOBALS['imp_search']) &&
                $GLOBALS['imp_search']->isSearchMbox($mbox)) {
                $mbox_list = $GLOBALS['imp_search']->getSearchFolders($mbox);
            } else {
                $mbox_list = array($mbox);
            }
            $imp_imap = &IMP_IMAP::singleton();

            foreach ($mbox_list as $val) {
                $imp_imap->changeMbox($val);
                $params = array('imap' => $GLOBALS['imp']['stream'], 'mailbox' => IMP::serverString($val));
                $GLOBALS['registry']->call('mail/applyFilters', array($params));
            }
        }
    }

    /**
     * Adds the From address from the message(s) to the blacklist.
     *
     * @param array $indices  See IMP::parseIndicesList().
     */
    function blacklistMessage($indices)
    {
        $this->_processBWlist($indices, _("your blacklist"), 'blacklistFrom', 'showBlacklist');
    }

    /**
     * Adds the From address from the message(s) to the whitelist.
     *
     * @param array $indices  See IMP::parseIndicesList().
     */
    function whitelistMessage($indices)
    {
        $this->_processBWlist($indices, _("your whitelist"), 'whitelistFrom', 'showWhitelist');
    }

    /**
     * Internal function to handle adding addresses to [black|white]list.
     *
     * @access private
     *
     * @param array  $indices  See IMP::parseIndicesList().
     * @param string $descrip  The textual description to use.
     * @param string $reg1     The name of the mail/ registry call to use for
     *                         adding the addresses.
     * @param string $reg2     The name of the mail/ registry call to use for
     *                         linking to the filter management page.
     */
    function _processBWlist($indices, $descrip, $reg1, $reg2)
    {
        if (!($msgList = IMP::parseIndicesList($indices))) {
            return false;
        }

        require_once IMP_BASE . '/lib/IMAP.php';
        require_once IMP_BASE . '/lib/MIME/Headers.php';
        $imp_imap = &IMP_IMAP::singleton();

        /* Get the list of from addresses. */
        $addr = array();
        foreach ($msgList as $folder => $msgIndices) {
            /* Switch folders, if necessary (only valid for IMAP). */
            $imp_imap->changeMbox($folder);

            foreach ($msgIndices as $msg) {
                $imp_headers = &new IMP_Headers($msg);
                $from = $imp_headers->getFromAddress();
                $addr[] = $from;
            }
        }

        $GLOBALS['registry']->call('mail/' . $reg1, array($addr));

        /* Add link to filter management page. */
        if ($GLOBALS['registry']->hasMethod('mail/' . $reg2)) {
            $manage_link = Horde::link(Horde::url($GLOBALS['registry']->link('mail/' . $reg2)), sprintf(_("Filters: %s management page"), $descrip)) . _("HERE") . '</a>';
            $GLOBALS['notification']->push(sprintf(_("Click %s to go to %s management page."), $manage_link, $descrip), 'horde.message', array('content.raw'));
        }
    }

}
