<?php
/**
 * The Horde_Cache_zps4:: class provides a Zend Performance Suite
 * (version 4.0+) implementation of the Horde caching system.
 *
 * $Horde: framework/Cache/Cache/zps4.php,v 1.1.10.6 2007/03/08 23:20:54 slusarz Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 3.0
 * @package Horde_Cache
 */
class Horde_Cache_zps4 extends Horde_Cache {

    /**
     * Attempts to retrieve a piece of cached data and return it to the caller.
     *
     * @param string  $key       Cache key to fetch.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return mixed  Cached data, or false if none was found.
     */
    function get($key, $lifetime = 1)
    {
        return output_cache_get($key, $lifetime);
    }

    /**
     * Attempts to store an object to the cache.
     *
     * @param string $key   Cache key (identifier).
     * @param mixed  $data  Data to store in the cache.
     *
     * @return boolean  True on success, false on failure.
     */
    function set($key, $data)
    {
        output_cache_put($key, $data);
        return true;
    }

    /**
     * Attempts to directly output cached data.
     *
     * @param string  $key       Cache key to output.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return boolean  True if output or false if no object was found.
     */
    function output($key, $lifetime = 1)
    {
        echo $this->get($key, $lifetime);
    }

    /**
     * Checks if a given key exists in the cache, valid for the given lifetime.
     *
     * @param string  $key       Cache key to check.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return boolean  Existance.
     */
    function exists($key, $lifetime = 1)
    {
        $exists = output_cache_exists($key, $lifetime);
        output_cache_stop();
        return $exists;
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
        return output_cache_remove_key($key);
    }

}
