<?php

/**
 * File value for vfs_type column.
 */
define('VFS_FILE', 1);

/**
 * Folder value for vfs_type column.
 */
define('VFS_FOLDER', 2);

/**
 * VFS implementation for PHP's PEAR database abstraction layer.
 *
 * Required values for $params:<pre>
 *      'phptype'       The database type (ie. 'pgsql', 'mysql', etc.).</pre>
 *
 * Optional values:<pre>
 *      'table'         The name of the vfs table in 'database'. Defaults to
 *                      'horde_vfs'.</pre>
 *
 * Required by some database implementations:<pre>
 *      'hostspec'      The hostname of the database server.
 *      'protocol'      The communication protocol ('tcp', 'unix', etc.).
 *      'database'      The name of the database.
 *      'username'      The username with which to connect to the database.
 *      'password'      The password associated with 'username'.
 *      'options'       Additional options to pass to the database.
 *      'tty'           The TTY on which to connect to the database.
 *      'port'          The port on which to connect to the database.</pre>
 *
 * The table structure for the VFS can be found in
 * data/vfs.sql.
 *
 * Database specific notes:
 *
 * MSSQL:
 * <pre>
 * - The vfs_data field must be of type IMAGE.
 * - You need the following php.ini settings:
 *    ; Valid range 0 - 2147483647. Default = 4096.
 *    mssql.textlimit = 0 ; zero to pass through
 *
 *    ; Valid range 0 - 2147483647. Default = 4096.
 *    mssql.textsize = 0 ; zero to pass through
 * </pre>
 *
 * $Horde: framework/VFS/VFS/sql.php,v 1.85.4.30 2007/06/05 23:37:48 slusarz Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package VFS
 */
class VFS_sql extends VFS {

    /**
     * Handle for the current database connection.
     *
     * @var DB
     */
    var $_db = false;

    /**
     * Retrieves the filesize from the VFS.
     *
     * @param string $path  The pathname to the file.
     * @param string $name  The filename to retrieve.
     *
     * @return int The file size.
     */
    function size($path, $name)
    {
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $length_op = $this->_getFileSizeOp();
        $sql = sprintf(
            'SELECT %s(vfs_data) FROM %s WHERE vfs_path = ? AND vfs_name = ?',
            $length_op,
            $this->_params['table']
        );
        $values = array($this->_convertPath($path), $name);
        $this->log($sql, PEAR_LOG_DEBUG);
        $size = $this->_db->getOne($sql, $values);

        if (is_null($size)) {
            return PEAR::raiseError(sprintf(_("Unable to check file size of \"%s/%s\"."), $path, $name));
        }

        return $size;
    }

    /**
     * Returns the size of a file.
     *
     * @access public
     *
     * @param string $path  The path of the file.
     * @param string $name  The filename.
     *
     * @return integer  The size of the folder in bytes or PEAR_Error on
     *                  failure.
     */
    function getFolderSize($path = null, $name = null)
    {
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $where = (is_null($path)) ? null : sprintf('WHERE vfs_path LIKE %s', ((!strlen($path)) ? '""' : $this->_db->quote($this->_convertPath($path) . '%')));
        $length_op = $this->_getFileSizeOp();
        $sql = sprintf(
            'SELECT SUM(%s(vfs_data)) FROM %s %s',
            $length_op,
            $this->_params['table'],
            $where
        );
        $this->log($sql, PEAR_LOG_DEBUG);
        $size = $this->_db->getOne($sql);

        return $size !== null ? $size : 0;
    }

    /**
     * Retrieve a file from the VFS.
     *
     * @param string $path  The pathname to the file.
     * @param string $name  The filename to retrieve.
     *
     * @return string  The file data.
     */
    function read($path, $name)
    {
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        return $this->_readBlob($this->_params['table'], 'vfs_data',
                                array('vfs_path' => $this->_convertPath($path),
                                      'vfs_name' => $name));
    }

    /**
     * Retrieves a part of a file from the VFS. Particularly useful
     * when reading large files which would exceed the PHP memory
     * limits if they were stored in a string.
     *
     * @param string  $path       The pathname to the file.
     * @param string  $name       The filename to retrieve.
     * @param integer $offset     The offset of the part. (The new offset will be
     *                            stored in here).
     * @param integer $length     The length of the part. If the length = -1, the
     *                            whole part after the offset is retrieved. If
     *                            more bytes are given as exists after the given
     *                            offset. Only the available bytes are read.
     * @param integer $remaining  The bytes that are left, after the part that is
     *                            retrieved.
     *
     * @return string The file data.
     */
    function readPart($path, $name, &$offset, $length = -1, &$remaining)
    {
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $data = $this->_readBlob($this->_params['table'], 'vfs_data',
                                 array('vfs_path' => $this->_convertPath($path),
                                       'vfs_name' => $name));

        // Calculate how many bytes MUST be read, so the remainging
        // bytes and the new offset can be calculated correctly.
        $size = strlen ($data);
        if ($length == -1 || (($length + $offset) > $size)) {
            $length = $size - $offset;
        }
        if ($remaining < 0) {
            $remaining = 0;
        }

        $data = substr($data, $offset, $length);
        $offset = $offset + $length;
        $remaining = $size - $offset;

        return $data;
    }

    /**
     * Stores a file in the VFS.
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
        /* Don't need to check quota here since it will be checked when
         * writeData() is called. */
        if (function_exists('file_get_contents')) {
            $data = file_get_contents($tmpFile);
        } else {
            $size = filesize($tmpFile);
            if ($size === 0) {
                $data = '';
                } else {
                $dataFP = @fopen($tmpFile, 'rb');
                if (!$dataFP) {
                    return PEAR::raiseError(sprintf(_("The temporary file \"%s\" does not exist."), $tmpFile));
                }
                $data = @fread($dataFP, $size);
                @fclose($dataFP);
            }
        }

        return $this->writeData($path, $name, $data, $autocreate);
    }

    /**
     * Store a file in the VFS from raw data.
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
        $res = $this->_checkQuotaWrite('string', $data);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $path = $this->_convertPath($path);

        /* Check to see if the data already exists. */
        $sql = sprintf('SELECT vfs_id FROM %s WHERE vfs_path %s AND vfs_name = ?',
                       $this->_params['table'],
                       (empty($path) && $this->_db->dbsyntax == 'oci8') ? ' IS NULL' : ' = ' . $this->_db->quote($path));
        $values = array($name);
        $this->log($sql, PEAR_LOG_DEBUG);
        $id = $this->_db->getOne($sql, $values);

        if (is_a($id, 'PEAR_Error')) {
            $this->log($id, PEAR_LOG_ERR);
            return $id;
        }

        if (!is_null($id)) {
            return $this->_updateBlob($this->_params['table'], 'vfs_data',
                                      $data, array('vfs_id' => $id),
                                      array('vfs_modified' => time()));
        } else {
            /* Check to see if the folder already exists. */
            $dirs = explode('/', $path);
            $path_name = array_pop($dirs);
            $parent = implode('/', $dirs);
            if (!$this->isFolder($parent, $path_name)) {
                if (!$autocreate) {
                    return PEAR::raiseError(sprintf(_("Folder %s does not exist"), $path), 'horde.error');
                } else {
                    $result = $this->autocreatePath($path);
                    if (is_a($result, 'PEAR_Error')) {
                        return $result;
                    }
                }
            }

            $id = $this->_db->nextId($this->_params['table']);
            if (is_a($id, 'PEAR_Error')) {
                $this->log($id, PEAR_LOG_ERR);
                return $id;
            }
            return $this->_insertBlob($this->_params['table'], 'vfs_data',
                                      $data, array('vfs_id' => $id,
                                                   'vfs_type' => VFS_FILE,
                                                   'vfs_path' => $path,
                                                   'vfs_name' => $name,
                                                   'vfs_modified' => time(),
                                                   'vfs_owner' => $this->_params['user']));
        }
    }

    /**
     * Delete a file from the VFS.
     *
     * @param string $path  The path to store the file in.
     * @param string $name  The filename to use.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function deleteFile($path, $name)
    {
        $res = $this->_checkQuotaDelete($path, $name);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $path = $this->_convertPath($path);

        $sql = sprintf('DELETE FROM %s WHERE vfs_type = ? AND vfs_path %s AND vfs_name = ?',
                       $this->_params['table'],
                       (empty($path) && $this->_db->dbsyntax == 'oci8') ? ' IS NULL' : ' = ' . $this->_db->quote($path));
        $values = array(VFS_FILE, $name);
        $this->log($sql, PEAR_LOG_DEBUG);
        $result = $this->_db->query($sql, $values);

        if ($this->_db->affectedRows() == 0) {
            return PEAR::raiseError(_("Unable to delete VFS file."));
        }

        return $result;
    }

    /**
     * Rename a file or folder in the VFS.
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
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        if (strpos($newpath, '/') === false) {
            $parent = '';
            $path = $newpath;
        } else {
            list($parent, $path) = explode('/', $newpath, 2);
        }
        if (!$this->isFolder($parent, $path)) {
            if (is_a($result = $this->autocreatePath($newpath), 'PEAR_Error')) {
                return $result;
            }
        }

        $oldpath = $this->_convertPath($oldpath);
        $newpath = $this->_convertPath($newpath);

        $sql  = 'UPDATE ' . $this->_params['table'];
        $sql .= ' SET vfs_path = ?, vfs_name = ?, vfs_modified = ? WHERE vfs_path = ? AND vfs_name = ?';
        $this->log($sql, PEAR_LOG_DEBUG);

        $values = array($newpath, $newname, time(), $oldpath, $oldname);

        $result = $this->_db->query($sql, $values);

        if ($this->_db->affectedRows() == 0) {
            return PEAR::raiseError(_("Unable to rename VFS file."));
        }

        $rename = $this->_recursiveRename($oldpath, $oldname, $newpath, $newname);
        if (is_a($rename, 'PEAR_Error')) {
            $this->log($rename, PEAR_LOG_ERR);
            return PEAR::raiseError(sprintf(_("Unable to rename VFS directory: %s."), $rename->getMessage()));
        }

        return $result;
    }

    /**
     * Creates a folder on the VFS.
     *
     * @param string $path  Holds the path of directory to create folder.
     * @param string $name  Holds the name of the new folder.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function createFolder($path, $name)
    {
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $id = $this->_db->nextId($this->_params['table']);
        if (is_a($id, 'PEAR_Error')) {
            $this->log($id, PEAR_LOG_ERR);
            return $id;
        }

        $sql  = 'INSERT INTO ' . $this->_params['table'];
        $sql .= ' (vfs_id, vfs_type, vfs_path, vfs_name, vfs_modified, vfs_owner) VALUES (?, ?, ?, ?, ?, ?)';
        $this->log($sql, PEAR_LOG_DEBUG);

        $values = array($id, VFS_FOLDER, $this->_convertPath($path), $name, time(), $this->_params['user']);

        return $this->_db->query($sql, $values);
    }

    /**
     * Delete a folder from the VFS.
     *
     * @param string $path        The path of the folder.
     * @param string $name        The folder name to use.
     * @param boolean $recursive  Force a recursive delete?
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function deleteFolder($path, $name, $recursive = false)
    {
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $path = $this->_convertPath($path);

        $folderPath = $this->_getNativePath($path, $name);

        /* Check if not recursive and fail if directory not empty */
        if (!$recursive) {
            $folderList = $this->listFolder($folderPath, null, true);
            if (is_a($folderList, 'PEAR_Error')) {
                $this->log($folderList, PEAR_LOG_ERR);
                return $folderList;
            } elseif (!empty($folderList)) {
                return PEAR::raiseError(sprintf(_("Unable to delete %s, the directory is not empty"),
                                                $path . '/' . $name));
            }
        }

        /* First delete everything below the folder, so if error we
         * get no orphans */
        $sql = sprintf('DELETE FROM %s WHERE vfs_path %s',
                       $this->_params['table'],
                       (empty($folderPath) && $this->_db->dbsyntax == 'oci8') ? ' IS NULL' : ' LIKE ' . $this->_db->quote($this->_getNativePath($folderPath, '%')));
        $this->log($sql, PEAR_LOG_DEBUG);
        $deleteContents = $this->_db->query($sql);
        if (is_a($deleteContents, 'PEAR_Error')) {
            $this->log($deleteContents, PEAR_LOG_ERR);
            return PEAR::raiseError(sprintf(_("Unable to delete VFS recursively: %s."), $deleteContents->getMessage()));
        }

        /* Now delete everything inside the folder. */
        $sql = sprintf('DELETE FROM %s WHERE vfs_path %s',
                       $this->_params['table'],
                       (empty($path) && $this->_db->dbsyntax == 'oci8') ? ' IS NULL' : ' = ' . $this->_db->quote($folderPath));
        $this->log($sql, PEAR_LOG_DEBUG);
        $delete = $this->_db->query($sql);
        if (is_a($delete, 'PEAR_Error')) {
            $this->log($delete, PEAR_LOG_ERR);
            return PEAR::raiseError(sprintf(_("Unable to delete VFS directory: %s."), $delete->getMessage()));
        }

        /* All ok now delete the actual folder */
        $sql = sprintf('DELETE FROM %s WHERE vfs_path %s AND vfs_name = ?',
                       $this->_params['table'],
                       (empty($path) && $this->_db->dbsyntax == 'oci8') ? ' IS NULL' : ' = ' . $this->_db->quote($path));
        $values = array($name);
        $this->log($sql, PEAR_LOG_DEBUG);
        $delete = $this->_db->query($sql, $values);
        if (is_a($delete, 'PEAR_Error')) {
            $this->log($delete, PEAR_LOG_ERR);
            return PEAR::raiseError(sprintf(_("Unable to delete VFS directory: %s."), $delete->getMessage()));
        }

        return $delete;
    }

    /**
     * Return a list of the contents of a folder.
     *
     * @param string $path       The directory path.
     * @param mixed $filter      String/hash of items to filter based on
     *                           filename.
     * @param boolean $dotfiles  Show dotfiles?
     * @param boolean $dironly   Show directories only?
     *
     * @return mixed  File list on success or false on failure.
     */
    function _listFolder($path, $filter = null, $dotfiles = true,
                         $dironly = false)
    {
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $path = $this->_convertPath($path);

        // Fix for Oracle not differentiating between '' and NULL.
        if (empty($path) && $this->_db->dbsyntax == 'oci8') {
            $where = 'vfs_path IS NULL';
        } else {
            $where = 'vfs_path = ' . $this->_db->quote($path);
        }

        $length_op = $this->_getFileSizeOp();
        $sql = sprintf('SELECT vfs_name, vfs_type, %s(vfs_data), vfs_modified, vfs_owner FROM %s WHERE %s',
                       $length_op,
                       $this->_params['table'],
                       $where);
        $this->log($sql, PEAR_LOG_DEBUG);
        $fileList = $this->_db->getAll($sql);
        if (is_a($fileList, 'PEAR_Error')) {
            return $fileList;
        }

        $files = array();
        foreach ($fileList as $line) {
            // Filter out dotfiles if they aren't wanted.
            if (!$dotfiles && substr($line[0], 0, 1) == '.') {
                continue;
            }

            $file['name'] = $line[0];

            if ($line[1] == VFS_FILE) {
                $name = explode('.', $line[0]);

                if (count($name) == 1) {
                    $file['type'] = '**none';
                } else {
                    $file['type'] = VFS::strtolower($name[count($name) - 1]);
                }

                $file['size'] = $line[2];
            } elseif ($line[1] == VFS_FOLDER) {
                $file['type'] = '**dir';
                $file['size'] = -1;
            }

            $file['date'] = $line[3];
            $file['owner'] = $line[4];
            $file['perms'] = '';
            $file['group'] = '';

            // filtering
            if ($this->_filterMatch($filter, $file['name'])) {
                unset($file);
                continue;
            }
            if ($dironly && $file['type'] !== '**dir') {
                unset($file);
                continue;
            }

            $files[$file['name']] = $file;
            unset($file);
       }

        return $files;
    }

    /**
     * Returns a sorted list of folders in specified directory.
     *
     * @param string $path         The path of the directory to get the
     *                             directory list for.
     * @param mixed $filter        String/hash of items to filter based on
     *                             folderlist.
     * @param boolean $dotfolders  Include dotfolders?
     *
     * @return mixed  Folder list on success or PEAR_Error object on failure.
     */
    function listFolders($path = '', $filter = array(), $dotfolders = true)
    {
        $conn = $this->_connect();
        if (is_a($conn, 'PEAR_Error')) {
            return $conn;
        }

        $path = $this->_convertPath($path);

        $sql  = 'SELECT vfs_name, vfs_path FROM ' . $this->_params['table'];
        $sql .= ' WHERE vfs_path = ? AND vfs_type = ?';
        $this->log($sql, PEAR_LOG_DEBUG);

        $values = array($path, VFS_FOLDER);

        $folderList = $this->_db->getAll($sql, $values);
        if (is_a($folderList, 'PEAR_Error')) {
            return $folderList;
        }

        $folders = array();
        foreach ($folderList as $line) {
            $folder['val'] = $this->_getNativePath($line[1], $line[0]);
            $folder['abbrev'] = '';
            $folder['label'] = '';

            $count = substr_count($folder['val'], '/');

            $x = 0;
            while ($x < $count) {
                $folder['abbrev'] .= '    ';
                $folder['label'] .= '    ';
                $x++;
            }

            $folder['abbrev'] .= $line[0];
            $folder['label'] .= $line[0];

            $strlen = VFS::strlen($folder['label']);
            if ($strlen > 26) {
                $folder['abbrev'] = substr($folder['label'], 0, ($count * 4));
                $length = (29 - ($count * 4)) / 2;
                $folder['abbrev'] .= substr($folder['label'], ($count * 4), $length);
                $folder['abbrev'] .= '...';
                $folder['abbrev'] .= substr($folder['label'], -1 * $length, $length);
            }

            $found = false;
            foreach ($filter as $fltr) {
                if ($folder['val'] == $fltr) {
                    $found = true;
                }
            }

            if (!$found) {
                $folders[$folder['val']] = $folder;
            }
        }

        ksort($folders);
        return $folders;
    }

    /**
     * Garbage collect files in the VFS storage system.
     *
     * @param string $path   The VFS path to clean.
     * @param integer $secs  The minimum amount of time (in seconds) required
     *                       before a file is removed.
     */
    function gc($path, $secs = 345600)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $sql = 'DELETE FROM ' . $this->_params['table']
            . ' WHERE vfs_type = ? AND vfs_modified < ? AND (vfs_path = ? OR vfs_path LIKE ?)';
        $this->log($sql, PEAR_LOG_DEBUG);

        $values = array(VFS_FILE,
                        time() - $secs,
                        $this->_convertPath($path),
                        $this->_convertPath($path) . '/%');

        return $this->_db->query($sql, $values);
    }

    /**
     * Renames all child paths.
     *
     * @access private
     *
     * @param string $path  The path of the folder to rename.
     * @param string $name  The foldername to use.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function _recursiveRename($oldpath, $oldname, $newpath, $newname)
    {
        $oldpath = $this->_convertPath($oldpath);
        $newpath = $this->_convertPath($newpath);

        $sql  = 'SELECT vfs_name FROM ' . $this->_params['table'];
        $sql .= ' WHERE vfs_type = ? AND vfs_path = ?';
        $this->log($sql, PEAR_LOG_DEBUG);

        $values = array(VFS_FOLDER, $this->_getNativePath($oldpath, $oldname));

        $folderList = $this->_db->getCol($sql, 0, $values);

        foreach ($folderList as $folder) {
            $this->_recursiveRename($this->_getNativePath($oldpath, $oldname), $folder, $this->_getNativePath($newpath, $newname), $folder);
        }

        $sql = 'UPDATE ' . $this->_params['table'] . ' SET vfs_path = ? WHERE vfs_path = ?';
        $this->log($sql, PEAR_LOG_DEBUG);

        $values = array($this->_getNativePath($newpath, $newname), $this->_getNativePath($oldpath, $oldname));

        $result = $this->_db->query($sql, $values);

        return $result;
    }

    /**
     * Return a full filename on the native filesystem, from a VFS
     * path and name.
     *
     * @access private
     *
     * @param string $path  The VFS file path.
     * @param string $name  The VFS filename.
     *
     * @return string  The full native filename.
     */
    function _getNativePath($path, $name)
    {
        if (empty($path)) {
            return $name;
        }

        if (!empty($path)) {
            if (isset($this->_params['home']) &&
                preg_match('|^~/?(.*)$|', $path, $matches)) {
                $path = $this->_params['home'] . '/' . $matches[1];
            }
        }

        return $path . '/' . $name;
    }

    /**
     * Attempts to open a persistent connection to the SQL server.
     *
     * @access private
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function _connect()
    {
        if ($this->_db === false) {
            if (!is_array($this->_params)) {
                return PEAR::raiseError(_("No configuration information specified for SQL VFS."));
            }

            $required = array('phptype');
            foreach ($required as $val) {
                if (!isset($this->_params[$val])) {
                    return PEAR::raiseError(sprintf(_("Required \"%s\" not specified in VFS configuration."), $val));
                }
            }

            if (!isset($this->_params['database'])) {
                $this->_params['database'] = '';
            }
            if (!isset($this->_params['username'])) {
                $this->_params['username'] = '';
            }
            if (!isset($this->_params['hostspec'])) {
                $this->_params['hostspec'] = '';
            }
            if (!isset($this->_params['table'])) {
                $this->_params['table'] = 'horde_vfs';
            }

            /* Connect to the SQL server using the supplied parameters. */
            require_once 'DB.php';
            $this->_db = &DB::connect($this->_params,
                                      array('persistent' => !empty($this->_params['persistent'])));
            if (is_a($this->_db, 'PEAR_Error')) {
                $this->log($this->_db, PEAR_LOG_ERR);
                $error = $this->_db;
                $this->_db = false;
                return $error;
            }


            // Set DB portability options.
            switch ($this->_db->phptype) {
            case 'mssql':
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
                break;
            default:
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
            }

        }

        return true;
    }

    /**
     * Disconnect from the SQL server and clean up the connection.
     *
     * @access private
     */
    function _disconnect()
    {
        if ($this->_db) {
            $this->_db->disconnect();
            $this->_db = false;
        }
    }

    /**
     * TODO
     *
     * @access private
     *
     * @param string $table     TODO
     * @param string $field     TODO
     * @param string $criteria  TODO
     *
     * @return mixed  TODO
     */
    function _readBlob($table, $field, $criteria)
    {
        if (!count($criteria)) {
            return PEAR::raiseError('You must specify the fetch criteria');
        }

        $where = '';

        switch ($this->_db->dbsyntax) {
        case 'oci8':
            foreach ($criteria as $key => $value) {
                if (!empty($where)) {
                    $where .= ' AND ';
                }
                if (empty($value)) {
                    $where .= $key . ' IS NULL';
                } else {
                    $where .= $key . ' = ' . $this->_db->quote($value);
                }
            }

            $statement = OCIParse($this->_db->connection,
                                  sprintf('SELECT %s FROM %s WHERE %s',
                                          $field, $table, $where));
            OCIExecute($statement);
            if (OCIFetchInto($statement, $lob)) {
                $result = $lob[0]->load();
                if (is_null($result)) {
                    $result = PEAR::raiseError('Unable to load SQL data.');
                }
            } else {
                $result = PEAR::raiseError('Unable to load SQL data.');
            }
            OCIFreeStatement($statement);
            break;

        default:
            foreach ($criteria as $key => $value) {
                if (!empty($where)) {
                    $where .= ' AND ';
                }
                $where .= $key . ' = ' . $this->_db->quote($value);
            }

            $sql = sprintf('SELECT %s FROM %s WHERE %s',
                           $field, $table, $where);
            $this->log($sql, PEAR_LOG_DEBUG);
            $result = $this->_db->getOne($sql);

            if (is_null($result)) {
                $result = PEAR::raiseError('Unable to load SQL data.');
            } else {
                switch ($this->_db->dbsyntax) {
                case 'pgsql':
                    $result = pack('H' . strlen($result), $result);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * TODO
     *
     * @access private
     *
     * @param string $table       TODO
     * @param string $field       TODO
     * @param string $data        TODO
     * @param string $attributes  TODO
     *
     * @return mixed  TODO
     */
    function _insertBlob($table, $field, $data, $attributes)
    {
        $fields = array();
        $values = array();

        switch ($this->_db->dbsyntax) {
        case 'oci8':
            foreach ($attributes as $key => $value) {
                $fields[] = $key;
                $values[] = $this->_db->quoteSmart($value);
            }

            $statement = OCIParse($this->_db->connection,
                                  sprintf('INSERT INTO %s (%s, %s)' .
                                          ' VALUES (%s, EMPTY_BLOB()) RETURNING %s INTO :blob',
                                          $table,
                                          implode(', ', $fields),
                                          $field,
                                          implode(', ', $values),
                                          $field));

            $lob = OCINewDescriptor($this->_db->connection);
            OCIBindByName($statement, ':blob', $lob, -1, SQLT_BLOB);
            OCIExecute($statement, OCI_DEFAULT);
            $lob->save($data);
            $result = OCICommit($this->_db->connection);
            $lob->free();
            OCIFreeStatement($statement);
            return $result ? true : PEAR::raiseError('Unknown Error');

        default:
            foreach ($attributes as $key => $value) {
                $fields[] = $key;
                $values[] = $value;
            }

            $query = sprintf('INSERT INTO %s (%s, %s) VALUES (%s)',
                             $table,
                             implode(', ', $fields),
                             $field,
                             '?' . str_repeat(', ?', count($values)));
            break;
        }

        switch ($this->_db->dbsyntax) {
        case 'mssql':
        case 'pgsql':
            $values[] = bin2hex($data);
            break;

        default:
            $values[] = $data;
        }

        /* Execute the query. */
        $this->log($query, PEAR_LOG_DEBUG);
        return $this->_db->query($query, $values);
    }

    /**
     * TODO
     *
     * @access private
     *
     * @param string $table      TODO
     * @param string $field      TODO
     * @param string $data       TODO
     * @param string $where      TODO
     * @param array $alsoupdate  TODO
     *
     * @return mixed  TODO
     */
    function _updateBlob($table, $field, $data, $where, $alsoupdate)
    {
        $fields = array();
        $values = array();

        switch ($this->_db->dbsyntax) {
        case 'oci8':
            $wherestring = '';
            foreach ($where as $key => $value) {
                if (!empty($wherestring)) {
                    $wherestring .= ' AND ';
                }
                $wherestring .= $key . ' = ' . $this->_db->quote($value);
            }

            $statement = OCIParse($this->_db->connection,
                                  sprintf('SELECT %s FROM %s WHERE %s FOR UPDATE',
                                          $field,
                                          $table,
                                          $wherestring));

            OCIExecute($statement, OCI_DEFAULT);
            OCIFetchInto($statement, $lob);
            $lob[0]->save($data);
            $result = OCICommit($this->_db->connection);
            $lob[0]->free();
            OCIFreeStatement($statement);
            return $result ? true : PEAR::raiseError('Unknown Error');

        default:
            $updatestring = '';
            $values = array();
            foreach ($alsoupdate as $key => $value) {
                $updatestring .= $key . ' = ?, ';
                $values[] = $value;
            }
            $updatestring .= $field . ' = ?';
            switch ($this->_db->dbsyntax) {
            case 'mssql':
            case 'pgsql':
                $values[] = bin2hex($data);
                break;

            default:
                $values[] = $data;
            }

            $wherestring = '';
            foreach ($where as $key => $value) {
                if (!empty($wherestring)) {
                    $wherestring .= ' AND ';
                }
                $wherestring .= $key . ' = ?';
                $values[] = $value;
            }

            $query = sprintf('UPDATE %s SET %s WHERE %s',
                             $table,
                             $updatestring,
                             $wherestring);
            break;
        }

        /* Execute the query. */
        $this->log($query, PEAR_LOG_DEBUG);
        return $this->_db->query($query, $values);
    }

    /**
     * Convert the pathname from regular filesystem form to the internal
     * format needed to access the file in the database.
     * Namely, we will treat '/' as a base directory as this is pretty much
     * the standard way to access base directories over most filesystems.
}    *
     * @access private
     *
     * @param string $path  A VFS path.
     *
     * @return string  The path with any beginning slash stripped off.
     */
    function _convertPath($path)
    {
        if (!empty($path) && (strpos($path, '/') === 0)) {
            $path = strval(substr($path, 1));
        }

        return $path;
    }

    function _getFileSizeOp()
    {
        switch ($this->_db->dbsyntax) {
        case 'mysql':
            return 'LENGTH';

        case 'oci8':
            return 'LENGTHB';

        case 'mssql':
        case 'sybase':
            return 'DATALENGTH';

        case 'pgsql':
        default:
            return 'OCTET_LENGTH';
        }
    }

    /**
     * VFS_sql override of isFolder() to check for root folder.
     *
     * @param string $path  Path to possible folder
     * @param string $name  Name of possible folder
     *
     * @return boolean        True if $path/$name is a folder
     */
    function isFolder($path, $name)
    {
        if ($path == '' && $name == '') {
            // The root of VFS is always a folder.
            return true;
        }
        return parent::isFolder($path, $name);
    }

}
