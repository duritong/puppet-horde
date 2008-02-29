<?php
/**
 * Implementation of the Quota API for Courier IMAP servers.  This also
 * works for dovecot servers.
 *
 * $Horde: imp/lib/Quota/courier.php,v 1.21.10.12 2007/09/28 15:23:50 ericr Exp $
 *
 * Copyright 2002-2007 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   IMP 4.0
 * @package IMP_Quota
 */
class IMP_Quota_courier extends IMP_Quota {

    /**
     * Get quota information (used/allocated), in bytes.
     *
     * @return mixed  An associative array.
     *                'limit' = Maximum quota allowed
     *                'usage' = Currently used portion of quota (in bytes)
     *                Returns PEAR_Error on failure.
     */
    function getQuota()
    {
        $quota = @imap_get_quotaroot($_SESSION['imp']['stream'],
                                         $GLOBALS['imp_search']->isSearchMbox($_SESSION['imp']['mailbox']) ? 'INBOX' : $_SESSION['imp']['mailbox']);

        if (is_array($quota)) {
            if (count($quota)) {
                if (!empty($quota['limit'])) {
                    return array('usage' => $quota['usage'] * 1024, 'limit' => $quota['limit'] * 1024);
                } elseif (!empty($quota['STORAGE']['limit'])) {
                    return array('usage' => $quota['STORAGE']['usage'] * 1024, 'limit' => $quota['STORAGE']['limit'] * 1024);
                }
            }
            return array('usage' => 0, 'limit' => 0);
        }

        return PEAR::raiseError(_("Unable to retrieve quota"), 'horde.error');
    }

}
