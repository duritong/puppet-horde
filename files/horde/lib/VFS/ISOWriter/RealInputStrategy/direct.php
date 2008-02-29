<?php

/**
 * Strategy for directly accessing input tree in a 'file' VFS
 *
 * $Horde: framework/VFS_ISOWriter/ISOWriter/RealInputStrategy/direct.php,v 1.1.8.5 2007/01/02 13:54:49 jan Exp $
 *
 * Copyright 2004-2007 Cronosys, LLC <http://www.cronosys.com/>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jason M. Felice <jfelice@cronosys.com>
 * @package VFS_ISO
 * @since   Horde 3.0
 */
class VFS_ISOWriter_RealInputStrategy_direct extends VFS_ISOWriter_RealInputStrategy {

    function getRealPath()
    {
        return $this->_sourceVfs->_getNativePath($this->_sourceRoot);
    }

    function finished()
    {
    }

}
