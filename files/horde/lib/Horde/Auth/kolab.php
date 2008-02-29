<?php

require_once 'Horde/Auth/imap.php';
require_once 'Horde/History.php';

/**
 * Kolab implementation of the Horde authentication system. Derives from the
 * Auth_imap IMAP authentication object, and simply provides parameters to it
 * based on the global Kolab configuration.
 *
 * $Horde: framework/Auth/Auth/kolab.php,v 1.1.10.9 2007/01/02 13:54:07 jan Exp $
 *
 * Copyright 2004-2007 Stuart Binge <s.binge@codefusion.co.za>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Stuart Binge <s.binge@codefusion.co.za>
 * @since   Horde 1.3
 * @package Horde_Auth
 */
class Auth_kolab extends Auth_imap {

    /**
     * Constructs a new Kolab authentication object.
     *
     * @param array $params  A hash containing connection parameters.
     */
    function Auth_kolab($params = array())
    {
        $params['hostspec'] = $GLOBALS['conf']['kolab']['imap']['server'];
        $params['port'] = $GLOBALS['conf']['kolab']['imap']['port'];
        $params['protocol'] = 'imap/notls/novalidate-cert';

        parent::Auth_imap($params);
    }

    /**
     * Find out if a set of login credentials are valid.
     *
     * @access private
     *
     * @param string $userId      The userId to check.
     * @param array $credentials  An array of login credentials. For Kolab,
     *                            this must contain a password entry.
     *
     * @return boolean  Whether or not the credentials are valid.
     */
    function _authenticate($userId, $credentials)
    {
        global $conf;

        $login_ok = parent::_authenticate($userId, $credentials);

        if ($conf['auth']['params']['login_block'] != 1) {
            // Return if feature is disabled.
            return $login_ok;
        }

        $history = &Horde_History::singleton();

        $history_identifier = "$userId@logins.kolab";
        $history_log = $history->getHistory($history_identifier);
        $history_list = array();

        // Extract history list from log.
        if ($history_log && !is_a($history_log, 'PEAR_Error')) {
            $data = $history_log->getData();
            if (!empty($data)) {
                $entry = array_shift($data);
                $history_list = $entry['history_list'];
            }
        }

        // Calculate the time range.
        $start_time = (time() - $conf['auth']['params']['login_block_time'] * 60);

        $new_history_list = array();
        $count = 0;

        // Copy and count all relevant timestamps.
        foreach ($history_list as $entry) {
            $timestamp = $entry[ 'timestamp' ];

            if ($timestamp > $start_time) {
                $new_history_list[] = $entry;
                $count++;
            }
        }

        $max_count = $conf['auth']['params']['login_block_count'];

        if ($count > $max_count || !$login_ok) {
            // Add entry for current failed login.
            $entry = array();
            $entry[ 'timestamp' ] = time();
            $new_history_list[] = $entry;

            // Write back history.
            $history->log($history_identifier, array('action' => 'add', 'who' => $userId,
                                                     'history_list' => $new_history_list), true);

            if ($count > $max_count) {
                $this->_setAuthError(AUTH_REASON_MESSAGE, _("Too many invalid logins during the last minutes."));
            } else {
                $this->_setAuthError(AUTH_REASON_BADLOGIN);
            }

            return false;
        }

        return $login_ok;
    }

}
