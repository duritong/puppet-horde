<?php
/**
 * SessionHandler:: implementation for DBM files.
 * NOTE: The PHP DBM functions are deprecated.
 *
 * No additional configuration parameters needed.
 *
 * $Horde: framework/SessionHandler/SessionHandler/dbm.php,v 1.9.12.8 2007/01/02 13:54:38 jan Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 3.0
 * @package Horde_SessionHandler
 */
class SessionHandler_dbm extends SessionHandler {

    /**
     * Our pointer to the DBM file, if open.
     *
     * @var resource
     */
    var $_dbm;

    /**
     * Open the SessionHandler backend.
     *
     * @param string $save_path     The path to the session object.
     * @param string $session_name  The name of the session.
     *
     * @return boolean  True on success, false otherwise.
     */
    function open($save_path, $session_name)
    {
        $this->_dbm = @dbmopen("$save_path/$session_name", 'c');
        return $this->_dbm;
    }

    /**
     * Close the SessionHandler backend.
     *
     * @return boolean  True on success, false otherwise.
     */
    function close()
    {
        return @dbmclose($this->_dbm);
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
        if ($data = dbmfetch($this->_dbm, $id)) {
            return base64_decode(substr($data, strpos($data, '|') + 1));
        } else {
            return '';
        }
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
        return @dbmreplace($this->_dbm, $id, time() . '|' . base64_encode($session_data));
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
        if (!(@dbmdelete($this->_dbm, $id))) {
            Horde::logMessage('Failed to delete session (id = ' . $id . ')', __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

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
        $expired = time() - $maxlifetime;
        $id = dbmfirstkey($this->_dbm);

        while ($id) {
            if ($data = dbmfetch($this->_dbm, $id)) {
                $age = substr($tmp, 0, strpos($data, '|'));
                if ($expired > $age) {
                    $this->destroy($id);
                }
            }

            $id = dbmnextkey($this->_dbm, $id);
        }

        return true;
    }

}
