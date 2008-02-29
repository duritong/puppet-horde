<?php

require_once dirname(__FILE__) . '/week.php';

/**
 * This class represent a workweek fbview of mulitple free busy information.
 *
 * Copyright 2003-2007 Mike Cochrane <mike@graftonhall.co.nz>
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information.
 *
 * $Horde: kronolith/lib/FBView/workweek.php,v 1.7.10.5 2007/01/02 13:55:06 jan Exp $
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @author  Jan Schneider <jan@horde.org>
 * @package Kronolith
 */
class Kronolith_FreeBusy_View_workweek extends Kronolith_FreeBusy_View_week {

    var $view = 'workweek';
    var $_days = 5;

}
