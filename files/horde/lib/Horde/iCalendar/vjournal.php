<?php
/**
 * Class representing vJournals.
 *
 * $Horde: framework/iCalendar/iCalendar/vjournal.php,v 1.8.10.5 2007/01/02 13:54:51 jan Exp $
 *
 * Copyright 2003-2007 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   Horde 3.0
 * @package Horde_iCalendar
 */
class Horde_iCalendar_vjournal extends Horde_iCalendar {

    function getType()
    {
        return 'vJournal';
    }

    function parsevCalendar($data)
    {
        parent::parsevCalendar($data, 'VJOURNAL');
    }

    function exportvCalendar()
    {
        return parent::_exportvData('VJOURNAL');
    }

}
