<?php
/**
 * The Horde_Cache_file:: class provides a filesystem implementation of the
 * Horde caching system.
 *
 * Optional parameters:<pre>
 *   'dir'     The directory to store the cache files in.
 *   'prefix'  The filename prefix to use for the cache files.</pre>
 *
 * $Horde: framework/Cache/Cache/file.php,v 1.28.10.14 2007/03/09 04:54:37 slusarz Exp $
 *
 * Copyright 1999-2007 Anil Madhavapeddy <anil@recoil.org>
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anil Madhavapeddy <anil@recoil.org>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 1.3
 * @package Horde_Cache
 */
class Horde_Cache_file extends Horde_Cache {

    /**
     * The location of the temp directory.
     *
     * @var string
     */
    var $_dir;

    /**
     * The filename prefix for cache files.
     *
     * @var string
     */
    var $_prefix = 'cache_';

    /**
     * Construct a new Horde_Cache_file object.
     *
     * @param array $params  Parameter array.
     */
    function Horde_Cache_file($params = array())
    {
        if (!empty($params['dir']) && @is_dir($params['dir'])) {
            $this->_dir = $params['dir'];
        } else {
            require_once 'Horde/Util.php';
            $this->_dir = Util::getTempDir();
        }

        if (isset($params['prefix'])) {
            $this->_prefix = $params['prefix'];
        }

        parent::Horde_Cache($params);
    }

    /**
     * Attempts to retrieve cached data from the filesystem and return it to
     * the caller.
     *
     * @param string  $key       Cache key to fetch.
     * @param integer $lifetime  Lifetime of the data in seconds.
     *
     * @return mixed  Cached data, or false if none was found.
     */
    function get($key, $lifetime = 1)
    {
        if ($this->exists($key, $lifetime)) {
            $filename = $this->_keyToFile($key);
            $size = filesize($filename);
            if (!$size) {
                return '';
            }
            return file_get_contents($filename);
        }

        /* Nothing cached, return failure. */
        return false;
    }

    /**
     * Attempts to store data to the filesystem.
     *
     * @param string $key   Cache key.
     * @param mixed  $data  Data to store in the cache. (MUST BE A STRING)
     *
     * @return boolean  True on success, false on failure.
     */
    function set($key, $data)
    {
        require_once 'Horde/Util.php';
        $filename = $this->_keyToFile($key);
        $tmp_file = Util::getTempFile('HordeCache', true, $this->_dir);

        if (function_exists('file_put_contents')) {
            if (file_put_contents($tmp_file, $data) === false) {
                return false;
            }
        } elseif ($fd = fopen($tmp_file, 'w')) {
            $res = fwrite($fd, $data);
            fclose($fd);
            if ($res < strlen($data)) {
                return false;
            }
        } else {
            return false;
        }

        @rename($tmp_file, $filename);
        return true;
    }

    /**
     * Attempts to directly output cached data from the filesystem.
     *
     * @param string  $key       Cache key to output.
     * @param integer $lifetime  Lifetime of the data in seconds.
     *
     * @return boolean  True if output or false if no object was found.
     */
    function output($key, $lifetime = 1)
    {
        $data = $this->get($key, $lifetime);
        if ($data === false) {
            return false;
        } else {
            echo $data;
            return true;
        }
    }

    /**
     * Checks if a given key exists in the cache, valid for the given
     * lifetime. If it exists but is expired, delete the file.
     *
     * @param string  $key       Cache key to check.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return boolean  Existance.
     */
    function exists($key, $lifetime = 1)
    {
        $filename = $this->_keyToFile($key);

        /* Key exists in the cache */
        if (file_exists($filename)) {
            /* 0 means no expire. */
            if ($lifetime == 0) {
                return true;
            }

            /* If the file was been created after the supplied value,
             * the data is valid (fresh). */
            if (time() - $lifetime <= filemtime($filename)) {
                return true;
            } else {
                @unlink($filename);
            }
        }

        return false;
    }

    /**
     * Expire any existing data for the given key.
     *
     * @param string $key  Cache key to expire.
     *
     * @return boolean  Success or failure.
     */
    function expire($key)
    {
        $filename = $this->_keyToFile($key);
        return @unlink($filename);
    }

    /**
     * Map a cache key to a unique filename.
     *
     * @access private
     *
     * @param string $key  Cache key.
     *
     * @return string  Fully qualified filename.
     */
    function _keyToFile($key)
    {
        return $this->_dir . '/' . $this->_prefix . md5($key);
    }

    /**
     * Do any garbage collection needed for the driver.
     *
     * @access private
     *
     * @param integer $secs  The minimum amount of time (in seconds) required
     *                       before a cache item is removed.
     */
    function _doGC($secs)
    {
        $filename = $this->_dir . '/horde_cache_gc';
        $gc_time = time() - $secs;
        if (file_exists($filename)) {
            $old_time = file_get_contents($filename);
            if (($old_time !== false) &&
                ($gc_time > $old_time)) {
                return;
            }
        }

        $d = dir($this->_dir);
        while (($entry = $d->read()) !== false) {
            if (strpos($entry, $this->_prefix) === 0) {
                $mtime = filemtime($this->_dir . '/' . $entry);
                if ($gc_time > $mtime) {
                    @unlink($this->_dir . '/' . $entry);
                }
            }
        }
        $d->close();
 
        if (function_exists('file_put_contents')) {
            file_put_contents($filename, time());
        } else {
            $fp = fopen($filename, 'w');
            fwrite($fp, time());
            fclose($fp);
        }
    }

}
