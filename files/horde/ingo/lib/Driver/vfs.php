<?php
/**
 * Ingo_Driver_vfs:: Implements an Ingo storage driver using Horde VFS.
 *
 * $Horde: ingo/lib/Driver/vfs.php,v 1.12.10.9 2007/01/02 13:55:03 jan Exp $
 *
 * Copyright 2003-2007 Brent J. Nordquist <bjn@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Brent J. Nordquist <bjn@horde.org>
 * @since   Ingo 0.1
 * @package Ingo
 */
class Ingo_Driver_vfs extends Ingo_Driver {

    /**
     * Constructs a new VFS-based storage driver.
     *
     * @param array $params  A hash containing driver parameters.
     */
    function Ingo_Driver_vfs($params = array())
    {
        $default_params = array(
            'hostspec'    =>  'localhost',
            'port'        =>  21,
            'filename'    =>  '.ingo_filter',
            'vfstype'     =>  'ftp',
            'username'    =>  false,
            'password'    =>  false,
            'vfs_path'    =>  '',
        );
        $this->_params = array_merge($default_params, $params);
    }

    /**
     * Substitutes _param variables.
     *
     * @param string $username  The user's login.
     * @param string $password  The user's password.
     *
     */
    function _substVars($username, $password)
    {
        if (empty($this->_params['username'])) {
            $this->_params['username'] = $username;
        }
        if (empty($this->_params['password'])) {
            $this->_params['password'] = $password;
        }
        if (!empty($this->_params['vfs_path'])) {
            $this->_params['vfs_path'] = str_replace(array('%u', '%U'),
                                                     array($username, $this->_params['username']),
                                                     $this->_params['vfs_path']);
            if (substr($this->_params['vfs_path'], -1) != '/') {
                $this->_params['vfs_path'] .= '/';
            }
        }
        if (empty($this->_params['filename']) &&
            !empty($this->_params['procmailrc'])) {
            $this->_params['filename'] = $this->_params['procmailrc'];
        }
    }

    /**
     * Sets a script running on the backend.
     *
     * @param string $script  The filter script
     *
     * @return mixed  True on success, or PEAR_Error on failure.
     */
    function setScriptActive($script, $username, $password)
    {
        if ($this->_params['vfstype'] != 'ftp') {
            return PEAR::raiseError(_(sprintf("VFS type \"%s\" not yet implemented.", $this->_params['vfstype'])));
        }

        $this->_substVars($username, $password);

        require_once 'VFS.php';
        $vfs = &VFS::singleton($this->_params['vfstype'], $this->_params);
        $res = $vfs->writeData('', $this->_params['vfs_path'] . $this->_params['filename'], $script);
        $vfs->_disconnect();
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }
        return true;
    }

    /**
     * Returns the content of the currently active script.
     *
     * @param string $username  The backend username.
     * @param string $password  The backend password.
     *
     * @return string  The complete ruleset of the specified user.
     */
    function getScript($username, $password)
    {
        if ($this->_params['vfstype'] != 'ftp') {
            return PEAR::raiseError(_(sprintf("VFS type \"%s\" not yet implemented.", $this->_params['vfstype'])));
        }

        $this->_substVars($username, $password);

        require_once 'VFS.php';
        $vfs = &VFS::singleton($this->_params['vfstype'], $this->_params);
        $res = $vfs->read('', $this->_params['vfs_path'] . $this->_params['filename']);
        $vfs->_disconnect();
        return $res;
    }

}
