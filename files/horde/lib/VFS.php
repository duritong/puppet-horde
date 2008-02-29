<?php

require_once 'PEAR.php';
require_once 'Log.php';

define('VFS_QUOTA_METRIC_BYTE', 1);
define('VFS_QUOTA_METRIC_KB', 2);
define('VFS_QUOTA_METRIC_MB', 3);
define('VFS_QUOTA_METRIC_GB', 4);

/**
 * VFS API for abstracted file storage and access.
 *
 * $Horde: framework/VFS/VFS.php,v 1.69.4.17 2007/01/02 13:54:48 jan Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package VFS
 * @since   Horde 2.2
 */
class VFS {

    /**
     * Hash containing connection parameters.
     *
     * @var array
     */
    var $_params = array();

    /**
     * List of additional credentials required for this VFS backend (example:
     * For FTP, we need a username and password to log in to the server with).
     *
     * @var array
     */
    var $_credentials = array();

    /**
     * List of permissions and if they can be changed in this VFS backend.
     *
     * @var array
     */
    var $_permissions = array(
        'owner' => array('read' => false, 'write' => false, 'execute' => false),
        'group' => array('read' => false, 'write' => false, 'execute' => false),
        'all'   => array('read' => false, 'write' => false, 'execute' => false));

    /**
     * A PEAR Log object. If present, will be used to log errors and
     * informational messages about VFS activity.
     *
     * @var Log
     */
    var $_logger = null;

    /**
     * The log level to use - messages with a higher log level than configured
     * here will not be logged. Defaults to only logging errors or higher.
     *
     * @var integer
     */
    var $_logLevel = PEAR_LOG_ERR;

    /**
     * The current size, in bytes, of the VFS item.
     *
     * @var integer
     */
    var $_vfsSize = null;

    /**
     * Constructor.
     *
     * @param array $params  A hash containing connection parameters.
     */
    function VFS($params = array())
    {
        if (empty($params['user'])) {
            $params['user'] = '';
        }
        if (empty($params['vfs_quotalimit'])) {
            $params['vfs_quotalimit'] = -1;
        }
        if (empty($params['vfs_quotaroot'])) {
            $params['vfs_quotaroot'] = '/';
        }
        $this->_params = $params;
    }

    /**
     * Checks the credentials that we have by calling _connect(), to see if
     * there is a valid login.
     *
     * @return mixed  True on success, PEAR_Error describing the problem if the
     *                credentials are invalid.
     */
    function checkCredentials()
    {
        return $this->_connect();
    }

    /**
     * Sets configuration parameters.
     *
     * @param array $params  An associative array with parameter names as keys.
     */
    function setParams($params = array())
    {
        foreach ($params as $name => $value) {
            $this->_params[$name] = $value;
        }
    }

    /**
     * Returns configuration parameters.
     *
     * @param string $name  The parameter to return.
     *
     * @return mixed  The parameter value or null if it doesn't exist.
     */
    function getParam($name)
    {
        return isset($this->_params[$name]) ? $this->_params[$name] : null;
    }

    /**
     * Logs a message if a PEAR Log object is available, and the message's
     * priority is lower than or equal to the configured log level.
     *
     * @param mixed   $message   The message to be logged.
     * @param integer $priority  The message's priority.
     */
    function log($message, $priority = PEAR_LOG_ERR)
    {
        if (!isset($this->_logger) || $priority > $this->_logLevel) {
            return;
        }

        if (is_a($message, 'PEAR_Error')) {
            $userinfo = $message->getUserInfo();
            $message = $message->getMessage();
            if ($userinfo) {
                if (is_array($userinfo)) {
                    $userinfo = implode(', ', $userinfo);
                }
                $message .= ': ' . $userinfo;
            }
        }

        /* Make sure to log in the system's locale. */
        $locale = setlocale(LC_TIME, 0);
        setlocale(LC_TIME, 'C');

        $this->_logger->log($message, $priority);

        /* Restore original locale. */
        setlocale(LC_TIME, $locale);
    }

    /**
     * Sets the PEAR Log object used to log informational or error messages.
     *
     * @param Log &$logger  The Log object to use.
     */
    function setLogger(&$logger, $logLevel = null)
    {
        if (!is_a($logger, 'Log')) {
            return false;
        }

        $this->_logger = &$logger;
        if (!is_null($logLevel)) {
            $this->_logLevel = $logLevel;
        }
    }

    /**
     * Retrieves the size of a file from the VFS.
     *
     * @abstract
     *
     * @param string $path  The pathname to the file.
     * @param string $name  The filename to retrieve.
     *
     * @return integer The file size.
     */
    function size($path, $name)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Returns the size of a folder
     *
     * @since Horde 3.1
     *
     * @param string $path  The path to the folder.
     * @param string $name  The name of the folder.
     *
     * @return integer  The size of the folder, in bytes, or PEAR_Error on
     *                  failure.
     */
    function getFolderSize($path = null, $name = null)
    {
        $size = 0;
        $root = ((!is_null($path)) ? $path . '/' : '') . $name;
        $object_list = $this->listFolder($root, null, true, false, true);
        foreach ($object_list as $key => $val) {
            if (isset($val['subdirs'])) {
                $size += $this->getFolderSize($root, $key);
            } else {
                $filesize = $this->size($root, $key);
                if (is_a($filesize, 'PEAR_Error')) {
                    return $filesize;
                }
                $size += $filesize;
            }
        }

        return $size;
    }

    /**
     * Retrieves a file from the VFS.
     *
     * @abstract
     *
     * @param string $path  The pathname to the file.
     * @param string $name  The filename to retrieve.
     *
     * @return string The file data.
     */
    function read($path, $name)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Retrieves a part of a file from the VFS. Particularly useful when
     * reading large files which would exceed the PHP memory limits if they
     * were stored in a string.
     *
     * @abstract
     *
     * @param string  $path       The pathname to the file.
     * @param string  $name       The filename to retrieve.
     * @param integer $offset     The offset of the part. (The new offset will
     *                            be stored in here).
     * @param integer $length     The length of the part. If the length = -1,
     *                            the whole part after the offset is retrieved.
     *                            If more bytes are given as exists after the
     *                            given offset. Only the available bytes are
     *                            read.
     * @param integer $remaining  The bytes that are left, after the part that
     *                            is retrieved.
     *
     * @return string The file data.
     */
    function readByteRange($path, $name, &$offset, $length = -1, &$remaining)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Stores a file in the VFS.
     *
     * @abstract
     *
     * @param string $path         The path to store the file in.
     * @param string $name         The filename to use.
     * @param string $tmpFile      The temporary file containing the data to
     *                             be stored.
     * @param boolean $autocreate  Automatically create directories?
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function write($path, $name, $tmpFile, $autocreate = false)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Stores a file in the VFS from raw data.
     *
     * @abstract
     *
     * @param string $path         The path to store the file in.
     * @param string $name         The filename to use.
     * @param string $data         The file data.
     * @param boolean $autocreate  Automatically create directories?
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function writeData($path, $name, $data, $autocreate = false)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Moves a file through the backend.
     *
     * @abstract
     *
     * @param string $path  The path of the original file.
     * @param string $name  The name of the original file.
     * @param string $dest  The destination file name.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function move($path, $name, $dest)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Copies a file through the backend.
     *
     * @abstract
     *
     * @param string $path  The path of the original file.
     * @param string $name  The name of the original file.
     * @param string $dest  The name of the destination directory.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function copy($path, $name, $dest)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Deletes a file from the VFS.
     *
     * @abstract
     *
     * @param string $path  The path to delete the file from.
     * @param string $name  The filename to delete.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function deleteFile($path, $name)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Renames a file in the VFS.
     *
     * @abstract
     *
     * @param string $oldpath  The old path to the file.
     * @param string $oldname  The old filename.
     * @param string $newpath  The new path of the file.
     * @param string $newname  The new filename.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function rename($oldpath, $oldname, $newpath, $newname)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Returns if a given file or folder exists in a folder.
     *
     * @param string $path  The path to the folder.
     * @param string $name  The file or folder name.
     *
     * @return boolean  True if it exists, false otherwise.
     */
    function exists($path, $name)
    {
        $list = $this->listFolder($path);
        if (is_a($list, 'PEAR_Error')) {
            return false;
        } else {
            return isset($list[$name]);
        }
    }

    /**
     * Creates a folder in the VFS.
     *
     * @abstract
     *
     * @param string $path  The parent folder.
     * @param string $name  The name of the new folder.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function createFolder($path, $name)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Automatically creates any necessary parent directories in the specified
     * $path.
     *
     * @param string $path  The VFS path to autocreate.
     */
    function autocreatePath($path)
    {
        $dirs = explode('/', $path);
        if (is_array($dirs)) {
            $cur = '/';
            foreach ($dirs as $dir) {
                if (!empty($dir)) {
                    if (!$this->isFolder($cur, $dir)) {
                        $result = $this->createFolder($cur, $dir);
                        if (is_a($result, 'PEAR_Error')) {
                            return $result;
                        }
                    }
                    if ($cur != '/') {
                        $cur .= '/';
                    }
                    $cur .= $dir;
                }
            }
        }

        return true;
    }

    /**
     * Checks if a given item is a folder.
     *
     * @param string $path  The parent folder.
     * @param string $name  The item name.
     *
     * @return boolean  True if it is a folder, false otherwise.
     */
    function isFolder($path, $name)
    {
        $folderList = $this->listFolder($path, null, true, true);
        return isset($folderList[$name]);
    }

    /**
     * Deletes a folder from the VFS.
     *
     * @abstract
     *
     * @param string $path        The parent folder.
     * @param string $name        The name of the folder to delete.
     * @param boolean $recursive  Force a recursive delete?
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function deleteFolder($path, $name, $recursive = false)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Recursively remove all files and subfolders from the given
     * folder.
     *
     * @param string $path  The path of the folder to empty.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function emptyFolder($path)
    {
        // Get and delete the subfolders.
        $list = $this->listFolder($path, null, true, true);
        if (is_a($list, 'PEAR_Error')) {
            return $list;
        }
        foreach ($list as $folder) {
            $result = $this->deleteFolder($path, $folder['name'], true);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }
        // Only files are left, get and delete them.
        $list = $this->listFolder($path);
        if (is_a($list, 'PEAR_Error')) {
            return $list;
        }
        foreach ($list as $file) {
            $result = $this->deleteFile($path, $file['name']);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        return true;
    }

    /**
     * Returns a file list of the directory passed in.
     *
     * @param string $path        The path of the directory.
     * @param mixed $filter       String/hash to filter file/dirname on.
     * @param boolean $dotfiles   Show dotfiles?
     * @param boolean $dironly    Show only directories?
     * @param boolean $recursive  Return all directory levels recursively?
     *
     * @return array  File list on success or PEAR_Error on failure.
     */
    function listFolder($path, $filter = null, $dotfiles = true,
                        $dironly = false, $recursive = false)
    {
        $list = $this->_listFolder($path, $filter, $dotfiles, $dironly);
        if (!$recursive || is_a($list, 'PEAR_Error')) {
            return $list;
        }

        foreach ($list as $name => $values) {
            if ($values['type'] == '**dir') {
                $list[$name]['subdirs'] = $this->listFolder($path . '/' . $name, $filter, $dotfiles, $dironly, $recursive);
            }
        }

        return $list;
    }

    /**
     * Returns an an unsorted file list of the specified directory.
     *
     * @abstract
     *
     * @param string $path       The path of the directory.
     * @param mixed $filter      String/hash to filter file/dirname on.
     * @param boolean $dotfiles  Show dotfiles?
     * @param boolean $dironly   Show only directories?
     *
     * @return array  File list on success or PEAR_Error on failure.
     */
    function _listFolder($path, $filter = null, $dotfiles = true,
                         $dironly = false)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Returns the current working directory of the VFS backend.
     *
     * @return string  The current working directory.
     */
    function getCurrentDirectory()
    {
        return '';
    }

    /**
     * Returns whether or not a filename matches any filter element.
     *
     * @access private
     *
     * @param mixed $filter     String/hash to build the regular expression
     *                          from.
     * @param string $filename  String containing the filename to match.
     *
     * @return boolean  True on match, false on no match.
     */
    function _filterMatch($filter, $filename)
    {
        $namefilter = null;

        // Build a regexp based on $filter.
        if ($filter !== null) {
            $namefilter = '/';
            if (is_array($filter)) {
                $once = false;
                foreach ($filter as $item) {
                    if ($once !== true) {
                        $namefilter .= '(';
                        $once = true;
                    } else {
                        $namefilter .= '|(';
                    }
                    $namefilter .= $item . ')';
                }
            } else {
                $namefilter .= '(' . $filter . ')';
            }
            $namefilter .= '/';
        }

        $match = false;
        if ($namefilter !== null) {
            $match = preg_match($namefilter, $filename);
        }

        return $match;
    }

    /**
     * Changes permissions for an item on the VFS.
     *
     * @abstract
     *
     * @param string $path        The parent folder of the item.
     * @param string $name        The name of the item.
     * @param string $permission  The permission to set.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function changePermissions($path, $name, $permission)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Returns a sorted list of folders in the specified directory.
     *
     * @abstract
     *
     * @param string $path         The path of the directory to get the
     *                             directory list for.
     * @param mixed $filter        Hash of items to filter based on folderlist.
     * @param boolean $dotfolders  Include dotfolders?
     *
     * @return mixed  Folder list on success or a PEAR_Error object on failure.
     */
    function listFolders($path = '', $filter = null, $dotfolders = true)
    {
        return PEAR::raiseError(_("Not supported."));
    }

    /**
     * Returns the list of additional credentials required, if any.
     *
     * @return array  Credential list.
     */
    function getRequiredCredentials()
    {
        return array_diff($this->_credentials, array_keys($this->_params));
    }

    /**
     * Returns an array specifying what permissions are changeable for this
     * VFS implementation.
     *
     * @return array  Changeable permisions.
     */
    function getModifiablePermissions()
    {
        return $this->_permissions;
    }

    /**
     * Converts a string to all lowercase characters ignoring the current
     * locale.
     *
     * @param string $string  The string to be lowercased
     *
     * @return string  The string with lowercase characters
     */
    function strtolower($string)
    {
        $language = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'en');
        $string = strtolower($string);
        setlocale(LC_CTYPE, $language);
        return $string;
    }

    /**
     * Returns the character (not byte) length of a string.
     *
     * @param string $string   The string to return the length of.
     * @param string $charset  The charset to use when calculating the
     *                         string's length.
     *
     * @return string  The string's length.
     */
    function strlen($string, $charset = null)
    {
        if (extension_loaded('mbstring')) {
            if (is_null($charset)) {
                $charset = 'ISO-8859-1';
            }
            $ret = @mb_strlen($string, $charset);
            if (!empty($ret)) {
                return $ret;
            }
        }
        return strlen($string);
    }

    /**
     * Returns the size of the VFS item.
     *
     * @since Horde 3.1
     *
     * @return integer  The size, in bytes, of the VFS item.
     */
    function getVFSSize()
    {
        if (is_null($this->_vfsSize)) {
            $this->_vfsSize = $this->getFolderSize($this->_params['vfs_quotaroot']);
        }
        return $this->_vfsSize;
    }

    /**
     * Sets the VFS quota limit.
     *
     * @since Horde 3.1
     *
     * @param integer $quota   The limit to apply.
     * @param integer $metric  The metric to multiply the quota into.
     */
    function setQuota($quota, $metric = VFS_QUOTA_METRIC_BYTE)
    {
        switch ($metric) {
        case VFS_QUOTA_METRIC_KB:
            $quota *= pow(2, 10);
            break;

        case VFS_QUOTA_METRIC_MB:
            $quota *= pow(2, 20);
            break;

        case VFS_QUOTA_METRIC_GB:
            $quota *= pow(2, 30);
            break;
        }

        $this->_params['vfs_quotalimit'] = $quota;
    }

    /**
     * Sets the VFS quota root.
     *
     * @since Horde 3.1
     *
     * @param string $dir  The root directory for the quota determination.
     */
    function setQuotaRoot($dir)
    {
        $this->_params['vfs_quotaroot'] = $dir;
    }

    /**
     * Get quota information (used/allocated), in bytes.
     *
     * @since Horde 3.1
     *
     * @return mixed  An associative array.
     *                'limit' = Maximum quota allowed
     *                'usage' = Currently used portion of quota (in bytes)
     *                Returns PEAR_Error on failure.
     */
    function getQuota()
    {
        if (empty($this->_params['vfs_quotalimit'])) {
            return PEAR::raiseError(_("No quota set."));
        }

        $usage = $this->getVFSSize();
        if (is_a($usage, 'PEAR_Error')) {
            return $usage;
        } else {
            return array('usage' => $usage, 'limit' => $this->_params['vfs_quotalimit']);
        }
    }

    /**
     * Determines the location of the system temporary directory.
     *
     * @access protected
     *
     * @return string  A directory name which can be used for temp files.
     *                 Returns false if one could not be found.
     */
    function _getTempDir()
    {
        $tmp_locations = array('/tmp', '/var/tmp', 'c:\WUTemp', 'c:\temp',
                               'c:\windows\temp', 'c:\winnt\temp');

        /* Try PHP's upload_tmp_dir directive. */
        $tmp = ini_get('upload_tmp_dir');

        /* Otherwise, try to determine the TMPDIR environment variable. */
        if (empty($tmp)) {
            $tmp = getenv('TMPDIR');
        }

        /* If we still cannot determine a value, then cycle through a list of
         * preset possibilities. */
        while (empty($tmp) && sizeof($tmp_locations)) {
            $tmp_check = array_shift($tmp_locations);
            if (@is_dir($tmp_check)) {
                $tmp = $tmp_check;
            }
        }

        /* If it is still empty, we have failed, so return false; otherwise
         * return the directory determined. */
        return empty($tmp) ? false : $tmp;
    }

    /**
     * Creates a temporary file.
     *
     * @access protected
     *
     * @return string   Returns the full path-name to the temporary file or
     *                  false if a temporary file could not be created.
     */
    function _getTempFile()
    {
        $tmp_dir = $this->_getTempDir();
        if (empty($tmp_dir)) {
            return false;
        }

        $tmp_file = tempnam($tmp_dir, 'vfs');

        /* If the file was created, then register it for deletion and
         * return. */
        if (empty($tmp_file)) {
            return false;
        } else {
            return $tmp_file;
        }
    }

    /**
     * Checks the quota when preparing to write data.
     *
     * @access private
     *
     * @param string $mode   Either 'string' or 'file'.  If 'string', $data is
     *                       the data to be written.  If 'file', $data is the
     *                       filename containing the data to be written.
     * @param string &$data  Either the data or the filename to the data.
     *
     * @return mixed  PEAR_Error on error, true on success.
     */
    function _checkQuotaWrite($mode, &$data)
    {
        if ($this->_params['vfs_quotalimit'] != -1) {
            if ($mode == 'file') {
                $filesize = @filesize($data);
                if ($filesize === false) {
                    return PEAR::raiseError(_("Unable to read VFS file (filesize() failed)."));
               }
            } else {
                $filesize = strlen($data);
            }
            $vfssize = $this->getVFSSize();
            if (is_a($vfssize, 'PEAR_Error')) {
                return $vfssize;
            }
            if (($vfssize + $filesize) > $this->_params['vfs_quotalimit']) {
                return PEAR::raiseError(_("Unable to write VFS file, quota will be exceeded."));
            } elseif ($this->_vfsSize !== 0) {
                $this->_vfsSize += $filesize;
            }
        }

        return true;
    }

    /**
     * Checks the quota when preparing to delete data.
     *
     * @access private
     *
     * @param string $path  The path the file is located in.
     * @param string $name  The filename.
     *
     * @return mixed  PEAR_Error on error, true on success.
     */
    function _checkQuotaDelete($path, $name)
    {
        if (($this->_params['vfs_quotalimit'] != -1) &&
            !empty($this->_vfsSize)) {
            $filesize = $this->size($path, $name);
            if (is_a($filesize, 'PEAR_Error')) {
                return PEAR::raiseError(_("Unable to read VFS file (size() failed)."));
            }
            $this->_vfsSize -= $filesize;
        }

        return true;
    }

    /**
     * Attempts to return a concrete VFS instance based on $driver.
     *
     * @param mixed $driver  The type of concrete VFS subclass to return. This
     *                       is based on the storage driver ($driver). The
     *                       code is dynamically included.
     * @param array $params  A hash containing any additional configuration or
     *                       connection parameters a subclass might need.
     *
     * @return VFS  The newly created concrete VFS instance, or a PEAR_Error
     *              on failure.
     */
    function &factory($driver, $params = array())
    {
        include_once 'VFS/' . $driver . '.php';
        $class = 'VFS_' . $driver;
        if (class_exists($class)) {
            $vfs = &new $class($params);
        } else {
            $vfs = PEAR::raiseError(sprintf(_("Class definition of %s not found."), $class));
        }

        return $vfs;
    }

    /**
     * Attempts to return a reference to a concrete VFS instance based on
     * $driver. It will only create a new instance if no VFS instance with the
     * same parameters currently exists.
     *
     * This should be used if multiple types of file backends (and, thus,
     * multiple VFS instances) are required.
     *
     * This method must be invoked as: $var = &VFS::singleton()
     *
     * @param mixed $driver  The type of concrete VFS subclass to return. This
     *                       is based on the storage driver ($driver). The
     *                       code is dynamically included.
     * @param array $params  A hash containing any additional configuration or
     *                       connection parameters a subclass might need.
     *
     * @return VFS  The concrete VFS reference, or a PEAR_Error on failure.
     */
    function &singleton($driver, $params = array())
    {
        static $instances;
        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($driver, $params));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &VFS::factory($driver, $params);
        }

        return $instances[$signature];
    }

}
