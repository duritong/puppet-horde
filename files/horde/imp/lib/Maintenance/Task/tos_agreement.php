<?php
/**
 * Maintenance module that presents a TOS Agreement page to user.
 * If user does not accept terms, user is not allowed to login.
 *
 * $Horde: imp/lib/Maintenance/Task/tos_agreement.php,v 1.11.10.8 2007/01/02 13:55:01 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 4.0
 * @package Horde_Maintenance
 */
class Maintenance_Task_tos_agreement extends Maintenance_Task {

    /**
     * The style of the maintenance page output.
     *
     * @var integer
     */
    var $_display_type = MAINTENANCE_OUTPUT_AGREE;

    /**
     * Determine if user agreed with the terms or not.  If the user does not
     * agree, log him/her out immediately.
     */
    function doMaintenance()
    {
        $result = Util::getFormData('not_agree');
        if (isset($result)) {
           header('Location: ' . Auth::addLogoutParameters(IMP::logoutUrl(), AUTH_REASON_MESSAGE, _("You did not agree to the Terms of Service agreement, so you were not allowed to login.")));
           exit;
        }
    }

    /**
     * Returns the TOS agreement for display on the maintenance page.
     *
     * @return string  The terms of service agreement.
     */
    function describeMaintenance()
    {
        global $conf;

        if (empty($conf['tos']['file'])) {
            Horde::fatal(PEAR::raiseError(sprintf(_("Terms of Service file not specified in conf.php"))), __FILE__, __LINE__);
        }

        return file_get_contents($conf['tos']['file']);
    }

}
