<?php
/**
 * Vacation_Driver_sql:: implements the Vacation_Driver API for SQL servers.
 *
 * $Horde: vacation/lib/Driver/sql.php,v 1.34.2.2 2007/01/02 13:55:22 jan Exp $
 *
 * Copyright 2001-2007 Ilya Krel and Mike Cochrane
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Ilya Krel <mail@krel.org>
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   Vacation 2.1
 * @package Vacation
 */
class Vacation_Driver_sql extends Vacation_Driver {

    /**
     * SQL connection object.
     */
    var $_db;

    /**
     * Boolean which contains the state of the SQL connection.
     */
    var $_connected = false;

    /**
     * Checks if the realm has a specific configuration. If not, tries to fall
     * back on the default configuration. If still not a valid configuration
     * then exits with an error.
     *
     * @param string $realm  The realm of the user, or "default" if none.
     *                       Note: passed by reference so we can change its
     *                       value.
     */
    function checkConfig(&$realm)
    {
        // If no realm passed in, or no table config for the realm passed in,
        // then we fall back to the default realm
        if (empty($realm) || empty($this->_params[$realm]['table'])) {
            $realm = 'default';
        }

        // If still no table,user_col,pass_col,message,subject,vacation then
        // we have a misconfigured module.
        if (empty($this->_params[$realm]['table']) ||
            empty($this->_params[$realm]['user_col']) ||
            empty($this->_params[$realm]['pass_col']) ||
            empty($this->_params[$realm]['message']) ||
            empty($this->_params[$realm]['subject']) ||
            empty($this->_params[$realm]['vacation']) ) {
            $this->err_str = _("The vacation application is not properly configured.");
            return false;
        }

        return true;
    }

    /**
     * Enables a vacation message for a user.
     *
     * @param string $user      The username to enable vacation for.
     * @param string $realm     The realm of the user.
     * @param string $password  The password of the user.
     * @param string $message   The message to install.
     * @param string $alias     The email alias for vacation to use --
     *                          Not yet implemented.
     *
     * @return boolean  True on success, false on error.
     */
    function setVacation($user, $realm, $password, $message, $alias)
    {
        // Make sure the configuration file is correct
        if (!$this->checkConfig($realm)) {
            return false;
        }

        /* _connect() will die with Horde::fatal() upon failure. */
        $this->_connect($realm);

        /* Determine if $message contains Subject: and if it does split it. */
        if (preg_match("/^Subject: ([^\n]+)\n(.+)$/s", $message, $matches)) {
            $mysubject = $matches[1];
            $mymessage = $matches[2];
        } else {
            $mysubject = '';
            $mymessage = $message;
        }

        // Build username.
        $myuser = $this->_buildUsername($user, $realm);

        // Check if an entry already exists and create one otherwise
        $query = 'SELECT ' . $this->_params[$realm]['vacation'] . ' AS vacation' .
                 ' FROM ' . $this->_params[$realm]['table'] .
                 ' WHERE ' . $this->_params[$realm]['user_col'] . ' = ?' .
                 ' AND ' . $this->_params[$realm]['pass_col'] . ' = ?';
        $values = array($myuser, "{MD5}".$this->encryptPassword($password));
        $result = $this->_db->query($query, $values);
        if (!is_a($result, 'PEAR_Error')) {
            $query = 'INSERT INTO ' . $this->_params[$realm]['table'] .
                     ' (' . $this->_params[$realm]['vacation'] . ',' .
                     ' ' . $this->_params[$realm]['user_col'] . ',' .
                     ' ' . $this->_params[$realm]['pass_col'] . ')' .
                     ' VALUES ( ?, ?, ?)';
            $values = array('0', $user, "{MD5}".$this->encryptPassword($password));
            $result = $this->_db->query($query, $values);
        }

        /* Build the SQL query. */
        $query = 'UPDATE ' . $this->_params[$realm]['table'] .
                 ' SET ' . $this->_params[$realm]['vacation'] . ' = ?,' .
                 ' ' . $this->_params[$realm]['message'] . ' = ?,' .
                 ' ' . $this->_params[$realm]['subject'] . ' = ?' .
                 ' WHERE ' . $this->_params[$realm]['user_col'] . ' = ?' .
                 ' AND ' . $this->_params[$realm]['pass_col'] . ' = ?';
        $values = array('1', $mymessage, $mysubject, $myuser, "{MD5}".$this->encryptPassword($password));

        /* Execute the query. */
        $result = $this->_db->query($query, $values);

        if (!is_a($result, 'PEAR_Error')) {
            if ($result === DB_OK) {
                $this->_disconnect();
                return true;
            } else {
                $this->_disconnect();
                return false;
            }
        } else {
            $this->_disconnect();
            return false;
        }
    }

    /**
     * Disables the vacation message for a user.
     *
     * @param string $user   The username of the user.
     * @param string $realm  The realm of the user.
     * @param string $pass   The password of the user.
     *
     * @return boolean  True on success, false on error.
     */
    function unsetVacation($user, $realm, $password)
    {
        // Make sure the configuration file is correct
        if (!$this->checkConfig($realm)) {
            return false;
        }

        /* _connect() will die with Horde::fatal() upon failure. */
        $this->_connect($realm);

        // Build username.
        $myuser = $this->_buildUsername($user, $realm);

        /* Build the SQL query. */
        $query = 'UPDATE ' . $this->_params[$realm]['table'] .
                 ' SET ' . $this->_params[$realm]['vacation'] . ' = ?' .
                 ' WHERE ' . $this->_params[$realm]['user_col'] . ' = ?' .
                 ' AND ' . $this->_params[$realm]['pass_col'] . ' = ?';
        $values = array('0', $myuser, "{MD5}".$this->encryptPassword($password));

        /* Execute the query. */
        $result = $this->_db->query($query, $values);

        if (!is_a($result, 'PEAR_Error')) {
            if ($result === DB_OK) {
                $this->_disconnect();
                return true;
            } else {
                $this->_disconnect();
                return false;
            }
        } else {
            $this->_disconnect();
            return false;
        }
    }

    /**
     * Retrieves the current vacation details for the user.
     *
     * @param string $user      The username for which to retrieve details.
     * @param string $realm     The realm (domain) for the user.
     * @param string $password  The password for user.
     *
     * @return array|boolean  Vacation details or false.
     */
    function _getUserDetails($user, $realm, $password)
    {
        // Make sure the configuration file is correct
        if (!$this->checkConfig($realm)) {
            return false;
        }

        /* _connect() will die with Horde::fatal() upon failure. */
        $this->_connect($realm);

        // Build username.
        $myuser = $this->_buildUsername($user, $realm);

        /* Build the SQL query. */
        $query = 'SELECT ' . $this->_params[$realm]['vacation'] . ' AS vacation,' .
                 ' ' . $this->_params[$realm]['message'] . ' AS message,' .
                 ' ' . $this->_params[$realm]['subject'] . ' AS subject' .
                 ' FROM ' . $this->_params[$realm]['table'] .
                 ' WHERE ' . $this->_params[$realm]['user_col'] . ' = ?' .
                 ' AND ' . $this->_params[$realm]['pass_col'] . ' = ?';
        $values = array($myuser, "{MD5}".$this->encryptPassword($password));

        /* Execute the query. */
        $result = $this->_db->query($query, $values);

        if (!is_a($result, 'PEAR_Error')) {
            $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
            if (is_array($row)) {
                $this->_disconnect();
                $row['message'] = 'Subject: ' . $row['subject'] . "\n" . $row['message'];
                return $row;
            } else {
                $this->_disconnect();
                return false;
            }
        } else {
            $this->_disconnect();
            return false;
        }
    }

    /**
     * Does an SQL connect and logs in as user with privilege to change
     * vacation.
     *
     * @return boolean  True or False based on success of connect.
     */
    function _connect($realm)
    {
        if (!$this->_connected) {
            // Build the params array to pass to DB
            $_args = array_merge($this->_params, $this->_params[$realm]);

            Horde::assertDriverConfig($_args, 'server',
                array('phptype', 'table'),
                'vacation authentication SQL');

            if (!isset($this->_params['database'])) {
                $this->_params['database'] = '';
            }
            if (!isset($this->_params['username'])) {
                $this->_params['username'] = '';
            }
            if (!isset($this->_params['hostspec'])) {
                $this->_params['hostspec'] = '';
            }

            /* Connect to the SQL server using the supplied parameters. */
            require_once 'DB.php';
            $this->_db = &DB::connect($_args,
                                      array('persistent' => !empty($_args['persistent'])));
            if (is_a($this->_db, 'PEAR_Error')) {
                Horde::fatal(PEAR::raiseError(_("Unable to connect to SQL server.")), __FILE__, __LINE__);
            }

            // Set DB portability options.
            switch ($this->_db->phptype) {
            case 'mssql':
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
                break;
            default:
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
            }

            $this->_connected = true;
        }

        return true;
    }

    /**
     * Disconnects from the SQL server and clean up the connection.
     *
     * @return boolean  True on success, false on failure.
     */
    function _disconnect()
    {
        if ($this->_connected) {
            $this->_connected = false;
            return $this->_db->disconnect();
        }

        return true;
    }

    /**
     * Builds a username based on presense of realm.
     *
     * @return string  user@realm or user.
     */
    function _buildUsername($user, $realm)
    {
        if ($realm === 'default' ||
            $realm === '') {
            return $user;
        } else {
            return $user . '@' . $realm;
        }
    }

}
