<?php
/**
 * SessionHandler:: implementation for memcache.
 * memcache website: http://www.danga.com/memcached/
 *
 * Required parameters:<pre>
 *   'hostspec'  The hostname of the memcache server.
 *   'port'      The port on which to connect to the memcache server.</pre>
 *
 * Optional parameters:<pre>
 *   'persistent'  Use persistent DB connections? (boolean)
 *   'compression' Use compression when storing sessions.</pre>
 *
 * $Horde: framework/SessionHandler/SessionHandler/memcache.php,v 1.1.2.2 2007/01/02 13:54:38 jan Exp $
 *
 * Copyright 2005-2007 Rong-En Fan <rafan@infor.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Rong-En Fan <rafan@infor.org>
 * @since   Horde 3.1
 * @package Horde_SessionHandler
 */
class SessionHandler_memcache extends SessionHandler {

    /**
     * Current memcache connection.
     *
     * @var object
     */
    var $_db;

    /**
     * Boolean indicating whether or not we're connected to the
     * memcache server.
     *
     * @var boolean
     */
    var $_connected = false;

    /**
     * Lock handle.
     *
     * @var resource
     */
    var $_fp;

    /**
     * Close the SessionHandler backend.
     *
     * @return boolean  True on success, false otherwise.
     */
    function close()
    {
        if ($this->_connected) {
            $this->_connected = false;
            @memcache_close($this->_db);
        }

        $this->_unlockSession();
        return true;
    }

    /**
     * Read the data for a particular session identifier from the
     * SessionHandler backend.
     *
     * @param string $id  The session identifier.
     *
     * @return string  The session data.
     */
    function read($id)
    {
        /* Obtain session lock. */
        $this->_lockSession($id);

        /* Make sure we have a valid memcache connection. */
        $this->_connect();

        $result = @memcache_get($this->_db, $id);
        if (!$result) {
            Horde::logMessage('Error retrieving session data (id = ' . $id . ')', __FILE__, __LINE__, PEAR_LOG_ERR);
            $this->_unlockSession();
            @unlink($this->_params['lock_dir'] . '/lock_' . $id);
            return false;
        }

        Horde::logMessage('Read session data (id = ' . $id . ')', __FILE__, __LINE__, PEAR_LOG_DEBUG);
        return $result;
    }

    /**
     * Write session data to the SessionHandler backend.
     *
     * @param string $id            The session identifier.
     * @param string $session_data  The session data.
     *
     * @return boolean  True on success, false otherwise.
     */
    function write($id, $session_data)
    {
        /* Make sure we have a valid memcache connection. */
        $this->_connect();

        $lifetime = ini_get('session.gc_maxlifetime');
        $flags = $this->_params['compression'] ? MEMCACHE_COMPRESSED : 0;
        $result = @memcache_set($this->_db, $id, $session_data, $flags, $lifetime);

        if (!$result) {
            Horde::logMessage('Error writing session data (id = ' . $id . ')', __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }
        Horde::logMessage('Wrote session data (id = ' . $id . ')', __FILE__, __LINE__, PEAR_LOG_DEBUG);

        return true;
    }

    /**
     * Destroy the data for a particular session identifier in the
     * SessionHandler backend.
     *
     * @param string $id  The session identifier.
     *
     * @return boolean  True on success, false otherwise.
     */
    function destroy($id)
    {
        /* Make sure we have a valid memcache connection. */
        $this->_connect();

        $result = @memcache_delete($this->_db, $id);

        if (!$result) {
            Horde::logMessage('Failed to delete session (id = ' . $id . ')', __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }
        Horde::logMessage('Deleted session data (id = ' . $id . ')', __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $this->_unlockSession();
        @unlink($this->_params['lock_dir'] . '/lock_' . $id);
        return true;
    }

    /**
     * Garbage collect stale sessions from the SessionHandler backend.
     *
     * @param integer $maxlifetime  The maximum age of a session.
     *
     * @return boolean  True on success, false otherwise.
     */
    function gc($maxlifetime = 300)
    {
        return true;
    }

    /**
     * Attempts to open a connection to the memcache server(s).
     *
     * @access private
     */
    function _connect()
    {
        if ($this->_connected) {
            Horde::logMessage('Already connected to a memcache server for memcache SessionHandler' , __FILE__, __LINE__,PEAR_LOG_DEBUG);
            return;
        }

        Horde::assertDriverConfig($this->_params, 'sessionhandler',
                                  array('hostspec', 'port'),
                                  'session handler memcache');

        if (empty($this->_params['persistent'])) {
            $connect_persistent = false;
        } else {
            $connect_persistent = true;
        }
        $con = new Memcache;

        for ($i = 0, $n = count($this->_params['hostspec']); $i < $n; ++$i) {
            if (!@$con->addServer($this->_params['hostspec'][$i],
                                  $this->_params['port'][$i],
                                  $connect_persistent)) {
                Horde::logMessage('Could not add [' . $this->_params['hostspec'][$i] . ':' . $this->_params['port'][$i] . '] as memcache server for memcache SessionHandler' , __FILE__, __LINE__,PEAR_LOG_ERR);
            } else {
                Horde::logMessage('Added [' . $this->_params['hostspec'][$i] . ':' . $this->_params['port'][$i] . '] as memcache server for memcache SessionHandler', __FILE__, __LINE__, PEAR_LOG_DEBUG);
                $this->_connected = true;
            }
        }

        /* Check if any of the pooled connections works. */
        if ($con->getVersion()) {
            $this->_db = $con;
            $this->_connected = true;
            Horde::logMessage('Connected to a memcache server for memcache SessionHandler' , __FILE__, __LINE__,PEAR_LOG_DEBUG);
        } else {
            $this->_connected = false;
            Horde::logMessage('Could not connect to any memcache server for memcache SessionHandler' , __FILE__, __LINE__,PEAR_LOG_ERR);
        }
    }

    /**
     * @access private
     */
    function _lockSession($id)
    {
        $this->_fp = @fopen($this->_params['lock_dir'] . '/lock_' . $id, 'w+');
        if (!$this->_fp) {
            Horde::logMessage('Could not open session lock for ' . $id, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }
        if (!@flock($this->_fp, LOCK_EX)) {
            Horde::logMessage('Could not lock session ' . $id, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        return true;
    }

    /**
     * @access private
     */
    function _unlockSession()
    {
        if (is_resource($this->_fp)) {
            if (!@flock($this->_fp, LOCK_UN)) {
                Horde::logMessage('Could not unlock session', __FILE__, __LINE__, PEAR_LOG_ERR);
            }
            @fclose($this->_fp);
        }
    }

}
