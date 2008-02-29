<?php
/**
 * Vacation_Driver_qmail:: implements the Vacation_Driver API for ftp driven
 * qmail mail servers.  This depends on Peter Samuel's qmail-vacation
 * program.
 *
 * $Horde: vacation/lib/Driver/qmail.php,v 1.27.2.2 2007/01/02 13:55:22 jan Exp $
 *
 * Copyright 2001-2007 Eric Rostetter <eric.rostetter@physics.utexas.edu>
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Eric Rostetter <eric.rostetter@physics.utexas.edu>
 * @since   Vacation 2.2
 * @package Vacation
 */
class Vacation_Driver_qmail extends Vacation_Driver {

    /**
     * The FTP stream we open via the VFS class.
     * @var VFS_ftp
     */
    var $_vfs;

    /**
     * The current vacation details.
     * @var array
     */
    var $_details = null;

    /**
     * Check if the realm has a specific configuration.  If not, try
     * to fall back on the default configuration.  If still not a
     * valid configuration then exit with an error.
     *
     * @param string $realm  The realm of the user, or 'default' if none.
     *                       Note: passed by reference so we can change its
     *                       value!
     */
    function checkConfig(&$realm)
    {
        // If no realm passed in, or no host config for the realm
        // passed in, then we fall back to the default realm.
        if (empty($realm) || empty($this->_params[$realm]['host'])) {
            $realm = 'default';
        }

        // If still no host/port, then we have a misconfigured module.
        if (empty($this->_params[$realm]['host']) ||
            empty($this->_params[$realm]['port']) ) {
            $this->err_str = _("The module is not properly configured!");
            return false;
        }
        return true;
    }

    /**
     * Set the vacation notice up.
     *
     * @param string $user     The username to enable vacation for.
     * @param string $realm    The realm of the user.
     * @param string $pass     The password for the user.
     * @param string $message  The message to install.
     * @param string $alias    The email alias to pass to vacation
     *
     * @return boolean  Returns true on success, false on error.
     */
    function setVacation($user, $realm, $pass, $message, $alias)
    {
        // Make sure the configuration file is correct.
        if (!$this->checkConfig($realm)) {
            return false;
        }

        // We need to find out what type of database file to use.
        $conf = &$GLOBALS['conf'];
        $dbfile = VACATION_BASE . '/files/empty.' .
            $this->_params[$realm]['dbtype'] . '.bin';

        // Build the ftp array to pass to VFS.
        $_args = array('hostspec' => $this->_params[$realm]['host'],
                       'port' => $this->_params[$realm]['port'],
                       'pasv' => $this->_params[$realm]['pasv'],
                       'username' => $user,
                       'password' => $pass);

        // Create the VFS ftp driver.
        require_once 'VFS.php';
        $_vfs = &VFS::singleton('ftp', $_args);

        // Try to login with the username/password, no realm.
        $status = $_vfs->checkCredentials();
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            $this->err_str .= '  ' .  _("Check your username and password.");
            return false;
        }

       // clean up old backup and move existing .qmail 
        if ($_vfs->exists('', '.qmail_horde')) {
            $this->err_str = _("Vacation notice already exists.");
            return false;
        }
        if ($_vfs->exists('', '.qmail')) {
            $status = $_vfs->rename('', '.qmail', '', '.qmail_horde');
            if (is_a($status, 'PEAR_Error')) {
                $this->err_str = $status->getMessage();
                return false;
            }
        }

        // Set up the vacation specific files first.
        $status = $_vfs->writeData('', '.vacation.msg', $message);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }
        $status = $_vfs->writeData('', '.vacation.pag', $dbfile);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }
        $status = $_vfs->writeData('', '.vacation.dir', $dbfile);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }
        $status = $_vfs->writeData('', '.vacation.db', $dbfile);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }

        // Parse the email address and alias address passed in.
        $my_email = $this->_makeEmailAddress($user, $realm);
        if ($my_email === false) {
            return false;
        }
        if (!empty($alias)) {
            $alias = $this->_makeEmailAddress($alias, $realm);
            if ($alias === false) {
                return false;
            }
        }

        // Now set up the .forward file.
        if (!empty($alias) && ($alias != $my_email)) {
            $contents = '| ' . $conf['vacation']['path'] .
                " -a $alias $my_email\n./Maildir/";
        } else {
            $contents = '| ' . $conf['vacation']['path'] .
                " $my_email\n./Maildir/";
        }
        $status = $_vfs->writeData('', '.qmail', $contents);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }

        // Try to change the permissions, but ignore any errors.
        $_vfs->changePermissions('', '.qmail', '0600');

        // Update the current details.
        $this->_details = array('vacation' => 'y',
                                'message' => $message);
        return true;
    }

    /**
     * Remove any existing vacation notices.
     *
     * @param string $user   The user to disable vacation notices for.
     * @param string $realm  The realm of the user.
     * @param string $pass   The password of the user.
     *
     * @return boolean  Returns true on success, false on error.
     */
    function unsetVacation($user, $realm, $pass)
    {
        if (!$this->checkConfig($realm)) {
            return false;
        }

        $_args = array('hostspec' => $this->_params[$realm]['host'],
                       'port' => $this->_params[$realm]['port'],
                       'pasv' => $this->_params[$realm]['pasv'],
                       'username' => $user,
                       'password' => $pass);

        require_once 'VFS.php';
        $_vfs = &VFS::singleton('ftp', $_args);

        // Try to login with the username/password, no realm.
        $status = $_vfs->checkCredentials();
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            $this->err_str .= '  ' .  _("Check your username and password.");
            return false;
        }

        if ($_vfs->exists('', '.vacation.msg')) {
            $status = $_vfs->deleteFile('', '.vacation.msg');
            if (is_a($status, 'PEAR_Error')) {
                $this->err_str = $status->getMessage();
                return false;
            }
        } else {
            $this->err_str = _("Vacation notice not found.");
            return false;
        }

       // restore previous .qmail (if any)
        if ($_vfs->exists('', '.qmail_horde')) {
            $status = $_vfs->rename('', '.qmail_horde', '', '.qmail');
            if (is_a($status, 'PEAR_Error')) {
                $this->err_str = $status->getMessage();
                return false;
            }
        } else {
            $status = $_vfs->deleteFile('', '.qmail');
            if (is_a($status, 'PEAR_Error')) {
                $this->err_str = $status->getMessage();
                return false;
            }
        }

        // We could, but don't, check for errors on these. They are
        // more-or-less harmless without the above two files.
        $_vfs->deleteFile('', '.vacation.pag');
        $_vfs->deleteFile('', '.vacation.dir');
        $_vfs->deleteFile('', '.vacation.db');

        // Update the current details.
        $this->_details = false;
        return true;
    }

    /**
     * Retrieve the current vacation details for the user.
     *
     * @param string $user      The username for which to retrieve details.
     * @param string $realm     The realm (domain) for the user.
     * @param string $password  The password for user.
     *
     * @return mixed  Vacation details or false.
     */
    function _getUserDetails($user, $realm, $password)
    {
        if (is_null($this->_details)) {
            if (!$this->checkConfig($realm)) {
                return false;
            }

            $_args = array('hostspec' => $this->_params[$realm]['host'],
                           'port' => $this->_params[$realm]['port'],
                           'pasv' => $this->_params[$realm]['pasv'],
                           'username' => $user,
                           'password' => $password);

            require_once 'VFS.php';
            $_vfs = &VFS::singleton('ftp', $_args);

            // Try to login with the username/password, no realm.
            $status = $_vfs->checkCredentials();
            if (is_a($status, 'PEAR_Error')) {
                $this->err_str = $status->getMessage();
                $this->err_str .= '  ' .  _("Check your username and password.");
                return false;
            }

            $message = $_vfs->read('', '.vacation.msg');
            if (is_a($message, 'PEAR_Error')) {
                $this->err_str = $message->getMessage();
                return false;
            }

            $this->_details['message'] = $message;
            $this->_details['vacation'] = 'y';
        }

        return $this->_details;
    }

}
