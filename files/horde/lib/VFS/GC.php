<?php
/**
 * Class for providing garbage collection for any VFS instance.
 *
 * $Horde: framework/VFS/VFS/GC.php,v 1.4.12.10 2007/01/02 13:54:48 jan Exp $
 *
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Horde 3.0
 * @package VFS
 */
class VFS_GC {

    /**
     * Garbage collect files in the VFS storage system.
     *
     * @param VFS &$vfs      The VFS object to perform garbage collection on.
     * @param string $path   The VFS path to clean.
     * @param integer $secs  The minimum amount of time (in seconds) required
     *                       before a file is removed.
     */
    function gc(&$vfs, $path, $secs = 345600)
    {
        /* A 1% chance we will run garbage collection during a call. */
        if (rand(0, 99) != 0) {
            return;
        }

        /* Use a backend-specific method if one exists. */
        if (is_callable(array($vfs, 'gc'))) {
            return $vfs->gc($path, $secs);
        }

        /* Make sure cleaning is done recursively. */
        $files = $vfs->listFolder($path, null, true, false, true);
        if (!is_a($files, 'PEAR_Error') && is_array($files)) {
            $modtime = time() - $secs;
            foreach ($files as $val) {
                if ($val['date'] < $modtime) {
                    $vfs->deleteFile($path, $val['name']);
                }
            }
        }
    }

}
