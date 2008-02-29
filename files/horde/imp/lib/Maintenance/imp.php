<?php

require_once 'Horde/Maintenance.php';
require_once $GLOBALS['registry']->get('fileroot', 'imp') . '/lib/base.php';

/**
 * $Horde: imp/lib/Maintenance/imp.php,v 1.19.10.6 2007/01/02 13:55:01 jan Exp $
 *
 * The Maintenance_IMP class defines the maintenance operations run upon
 * login to IMP.
 *
 * Copyright 2001-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 3.0
 * @package Horde_Maintenance
 */
class Maintenance_IMP extends Maintenance {

    /**
     * Hash holding maintenance preference names.
     *
     * @var array
     */
    var $maint_tasks = array(
        'tos_agreement'              => MAINTENANCE_FIRST_LOGIN,
        'fetchmail_login'            => MAINTENANCE_EVERY,
        'rename_sentmail_monthly'    => MAINTENANCE_MONTHLY,
        'delete_sentmail_monthly'    => MAINTENANCE_MONTHLY,
        'delete_attachments_monthly' => MAINTENANCE_MONTHLY,
        'purge_trash'                => MAINTENANCE_MONTHLY
    );

}
