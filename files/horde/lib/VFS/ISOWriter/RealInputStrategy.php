<?php

/**
 * Encapsulate strategies for getting a real, local filesystem structure from
 * a VFS.
 *
 * $Horde: framework/VFS_ISOWriter/ISOWriter/RealInputStrategy.php,v 1.1.8.8 2007/01/02 13:54:49 jan Exp $
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
class VFS_ISOWriter_RealInputStrategy {

    /**
     * A reference to the source VFS we want to read.
     *
     * @var VFS
     */
    var $_sourceVfs = null;

    /**
     * The root directory within the source VFS
     *
     * @var string
     */
    var $_sourceRoot;

    function VFS_ISOWriter_RealInputStrategy(&$sourceVfs, $sourceRoot)
    {
        $this->_sourceVfs = &$sourceVfs;
        $this->_sourceRoot = &$sourceRoot;
    }

    /**
     * Get a real path to the input tree.
     *
     * @abstract
     * @return mixed    A string with the real path, or PEAR_Error on failure.
     */
    function getRealPath()
    {
        return PEAR::raiseError(_("Not implemented."));
    }

    /**
     * Indicate we are finished with this input strategy.
     *
     * @abstract
     * @return mixed        Null or PEAR_Error on failure.
     */
    function finished()
    {
        return PEAR::raiseError(_("Not implemented."));
    }

    /**
     * Decide which strategy to use to get a real FS and create it.
     *
     * @static
     *
     * @param object &$sourceVfs        The VFS we want to read from.
     * @param string $sourceRoot        The root directory in that VFS.
     * @return object   A concrete strategy or PEAR_Error if no strategy is
     *                  available.
     */
    function factory(&$sourceVfs, $sourceRoot)
    {
        if (strtolower(get_class($sourceVfs)) == 'vfs_file') {
            $method = 'direct';
        } else {
            $method = 'copy';
        }

        @include_once dirname(__FILE__) . '/RealInputStrategy/' . $method .
                      '.php';
        $class = 'VFS_ISOWriter_RealInputStrategy_' . $method;
        if (!class_exists($class)) {
            return PEAR::raiseError(sprintf(_("Could not load strategy \"%s\"."),
                                            $method));
        }

        $strategy = &new $class($sourceVfs, $sourceRoot);
        return $strategy;
    }

}

