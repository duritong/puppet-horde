<?php
/**
 * Vacation_Driver:: defines an API for implementing vacation backends
 * for the vacation module.
 *
 * $Horde: vacation/lib/AliasDriver/none.php,v 1.4.2.1 2007/01/02 13:55:22 jan Exp $
 *
 * Copyright 2004-2007 Cronosys, LLC <http://www.cronosys.com/>
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jason M. Felice <jfelice@cronosys.com>
 * @package Vacation
 */
class Vacation_AliasDriver_none extends Vacation_AliasDriver {

    function getAliases()
    {
        return array();
    }

}
