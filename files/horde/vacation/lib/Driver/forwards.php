<?php
/**
 * Vacation_Driver_forwards:: implements the Vacation_Driver API for ftp
 * driven dot-forward compliant mail servers.
 *
 * $Horde: vacation/lib/Driver/forwards.php,v 1.43.2.3 2007/01/02 13:55:22 jan Exp $
 *
 * Copyright 2001-2007 Eric Rostetter <eric.rostetter@physics.utexas.edu>
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Eric Rostetter <eric.rostetter@physics.utexas.edu>
 * @package Vacation
 */
class Vacation_Driver_forwards extends Vacation_Driver {

    /**
     * The FTP stream we open via the VFS class.
     *
     * @var VFS_ftp
     */
    var $_vfs;

    /**
     * The current vacation details.
     *
     * @var array
     */
    var $_details = null;

    /**
     * Check if the realm has a specific configuration. If not, try to
     * fall back on the default configuration. If still not a valid
     * configuration then exit with an error.
     *
     * @param string $realm  The realm of the user, or "default" if none.
     *                       Note: passed by reference so we can change its
     *                       value.
     */
    function checkConfig(&$realm)
    {
        // If no realm passed in, or no host config for the realm
        // passed in, then we fall back to the default realm
        if (empty($realm) || empty($this->_params[$realm]['host'])) {
            $realm = 'default';
        }

        // If still no host/port, then we have a misconfigured module.
        if (empty($this->_params[$realm]['host']) ||
            empty($this->_params[$realm]['port']) ) {
            $this->err_str = _("The vacation application is not properly configured.");
            return false;
        }
        return true;
    }

    /**
     * Set the vacation notice up.
     *
     * @param string $user      The username to enable vacation for.
     * @param string $realm     The realm of the user.
     * @param string $password  The password for the user.
     * @param string $message   The message to install.
     * @param string $alias     The email alias to pass to vacation
     *
     * @return boolean  Returns true on success, false on error.
     */
    function setVacation($user, $realm, $password, $message, $alias)
    {
        // Make sure the configuration file is correct
        if (!$this->checkConfig($realm)) {
            return false;
        }

        // We need to find out what type of database file to use
        $conf = &$GLOBALS['conf'];
        $dbfile = VACATION_BASE . '/files/empty.' .
                  $this->_params[$realm]['dbtype'] . '.bin';

        // Build the params array to pass to VFS.
        $_args = array('hostspec' => $this->_params[$realm]['host'],
                       'port' => $this->_params[$realm]['port'],
                       'pasv' => $this->_params[$realm]['pasv'],
                       'username' => $user,
                       'password' => $password);

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

        // Set up the vacation specific files first.
        $status = $_vfs->writeData('', '.vacation.msg', $message);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }
        $status = $_vfs->write('', '.vacation.pag', $dbfile);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }
        $status = $_vfs->write('', '.vacation.dir', $dbfile);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }
        $status = $_vfs->write('', '.vacation.db', $dbfile);
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
            $alias_list = preg_split('/[,\s]+/', $alias);
            foreach ($alias_list as $i => $elt) {
                $addr = $this->_makeEmailAddress($elt, $realm);
                if ($addr === false) {
                    return false;
                }
                $alias_list[$i] = '-a ' . escapeshellarg($addr);
            }
            $alias = join(' ', $alias_list);
        } else {
            $alias = '';
        }

        // Now set up the .forward file
        if (!empty($alias) && ($alias != $my_email)) {
           $contents = "\\$my_email, \"|" . $conf['vacation']['path'] .
                       " $alias $my_email\"";
        } else {
           $contents = "\\$my_email, \"|" . $conf['vacation']['path'] .
                       " $my_email\"";
        }
        $status = $_vfs->writeData('', '.forward', $contents);
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }

        // Try to change the permissions, but ignore any errors.
        $_vfs->changePermissions('', '.forward', '0600');

        // Update the current details.
        $this->_details = array('vacation' => 'y',
                                'message' => $message);
        return true;
    }

    /**
     * Remove any existing vacation notices.
     *
     * @param string $user      The user to disable vacation notices for.
     * @param string $realm     The realm of the user.
     * @param string $password  The password of the user.
     *
     * @return boolean  Returns true on success, false on error.
     */
    function unsetVacation($user, $realm, $password)
    {
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

        $status = $_vfs->deleteFile('', '.forward');
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            $this->err_str .= '  ' . _("Maybe you didn't have a vacation notice installed?");
            return false;
        }
        $status = $_vfs->deleteFile('', '.vacation.msg');
        if (is_a($status, 'PEAR_Error')) {
            $this->err_str = $status->getMessage();
            return false;
        }

        // We could, but don't, check for errors on these. They are
        // more-or-less harmless without the above two files.
        $_vfs->deleteFile('', '.vacation.pag');
        $_vfs->deleteFile('', '.vacation.dir');
        $_vfs->deleteFile('', '.vacation.db');

        // Update the current details.
        $this->_details = null;
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

            $file['forward'] = $_vfs->read('', '.forward');
            $file['message'] = $_vfs->read('', '.vacation.msg');
            foreach ($file as $f) {
                if (is_a($f, 'PEAR_Error')) {
                    $this->err_str = $f->getMessage();
                    return false;
                }
            }

            $this->_details['message'] = $file['message'];
            $details = $this->_parseForward($user, $file['forward']);
            if ($details['vacation']['set']) {
                // Driver.php wants output in y/n format:
                $this->_details['vacation'] = 'y';
                $this->_details['alias'] = $details['vacation']['alias'];
            } else {
                $this->_details['vacation'] = 'n';
            }
        }

        return $this->_details;
    }

    /**
     * Parses a string from the .forward file.
     *
     * @param string $user  The username for which to retrieve details.
     * @param string $str   The string from the .forward file.
     *
     * @return mixed  The contents of the file in an array
     */
    function _parseForward($user, $str)
    {
        require_once 'Horde/MIME.php';
        $adrlist = MIME::rfc822Explode($str, ',');
        foreach ($adrlist as $adr) {
            $adr = trim($adr);
            if ($adr == "\\$user") {
                // This matches the way the forwards module writes
                // $user.
                $content['forward']['metoo'] = true;
            } elseif (preg_match('/\|.*vacation\s*(-a\s+(.*))?\s+(.+)/',
                                 $adr, $matches)) {
                // This matches the way the vacation module writes
                // vacation command.
                $content['vacation']['alias'] = $matches[2];
            } elseif ($adr != "") {
                // This matches everything else.
                $buffer[] = $adr;
            }
        }
        if ($content['forward']['set'] = (isset($buffer)&&is_array($buffer))) {
            $content['forward']['receivers'] = implode(', ', $buffer);
        }
        $content['vacation']['set'] = (isset($content['vacation']) && is_array($content['vacation']));

        return $content;
    }

}
