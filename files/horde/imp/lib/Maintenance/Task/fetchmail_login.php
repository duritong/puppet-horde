<?php
/**
 * $Horde: imp/lib/Maintenance/Task/fetchmail_login.php,v 1.5.12.8 2007/01/02 13:55:01 jan Exp $
 *
 * Copyright 2003-2007 Nuno Loureiro <nuno@co.sapo.pt>
 * Copyright 2004-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * Maintenance module that fetch mail upon login
 *
 * @author  Nuno Loureiro <nuno@co.sapo.pt>
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 4.0
 * @package Horde_Maintenance
 */
class Maintenance_Task_fetchmail_login extends Maintenance_Task {

    /**
     * The style of the maintenance page output.
     *
     * @var integer
     */
    var $_display_type = MAINTENANCE_OUTPUT_CONFIRM;

    /**
     * Fetch email from other accounts.
     */
    function doMaintenance()
    {
        /* If the user wants to fetch emails from other accounts on login,
         * go get those messages now. */
        if ($GLOBALS['prefs']->getValue('fm_accounts')) {
            require_once IMP_BASE . '/lib/Fetchmail.php';
            $fm_account = &new IMP_Fetchmail_Account();
            $fm_list = array();

            foreach ($fm_account->getAll('loginfetch') as $id => $val) {
                if ($val) {
                    $fm_list[] = $id;
                }
            }

            if (!empty($fm_list)) {
                IMP_Fetchmail::fetchMail($fm_list);
            }
        }
    }

    /**
     * Returns the summary of the accounts to fetch email from.
     *
     * @return string  The summary of the accounts to fetch email from.
     */
    function describeMaintenance()
    {
        $str  = _("You are about to fetch email from the following account(s):");
        $str .= "\n<blockquote>\n";

        if ($GLOBALS['prefs']->getValue('fm_accounts')) {
            require_once IMP_BASE . '/lib/Fetchmail.php';
            $fm_account = &new IMP_Fetchmail_Account();
            foreach ($fm_account->getAll('loginfetch') as $id => $val) {
                if ($val) {
                    $str .= " - " . $fm_account->getValue('id', $id) . "<br />\n";
                }
            }
        }

        $str .= "\n</blockquote>\n<strong>" . _("Note that this can take some time") . ".</strong>\n";

        return $str;
    }

}
