<?php
/**
 * SessionHandler implementation for PHP's built-in session handler.
 *
 * Required parameters:<pre>
 *   None.</pre>
 *
 * Optional parameters:<pre>
 *   None.</pre>
 *
 * $Horde: framework/SessionHandler/SessionHandler/none.php,v 1.3.2.5 2007/01/02 13:54:38 jan Exp $
 *
 * Copyright 2005-2007 Matt Selsky <selsky@columbia.edu>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Matt Selsky <selsky@columbia.edu>
 * @since   Horde 3.1
 * @package Horde_SessionHandler
 */
class SessionHandler_none extends SessionHandler {

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
        $file = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $id;

        $session_data = @file_get_contents($file);
        if ($session_data === false) {
            return PEAR::raiseError(_("Unable to read file: ") . $file);
        }

        return $session_data;
    }

    /**
     * Get a list of the valid session identifiers.
     *
     * @return array  A list of valid session identifiers.
     */
    function getSessionIDs()
    {
        $sessions = array();

        $path = session_save_path();
        $d = dir(empty($path) ? Util::getTempDir() : $path);

        while (false !== ($entry = $d->read())) {
            /* Make sure we're dealing with files that start with
             * sess_. */
            if (is_file($d->path . DIRECTORY_SEPARATOR . $entry) &&
                !strncmp($entry, 'sess_', strlen('sess_'))) {
                $sessions[] = substr($entry, strlen('sess_'));
            }
        }

        return $sessions;
    }

}
