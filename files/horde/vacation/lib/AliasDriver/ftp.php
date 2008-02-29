<?php
/**
 * Vacation_Driver:: defines an API for implementing vacation backends for the
 * vacation module.
 *
 * $Horde: vacation/lib/AliasDriver/ftp.php,v 1.5.2.1 2007/01/02 13:55:21 jan Exp $
 *
 * Copyright 2004-2007 Cronosys, LLC <http://www.cronosys.com/>
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jason M. Felice <jfelice@cronosys.com>
 * @package Vacation
 */
class Vacation_AliasDriver_ftp extends Vacation_AliasDriver {

    function getAliases()
    {
        require_once 'VFS.php';

        $params = array('hostspec' => $this->_params['host'],
                        'port' => $this->_params['port'],
                        'pasv' => $this->_params['pasv'],
                        'username' => $this->_params['username'],
                        'password' => $this->_params['password']);
        $vfs = &VFS::singleton('ftp', $params);
        if (is_a($vfs, 'PEAR_Error')) {
            return $vfs;
        }

        $res = $vfs->checkCredentials();
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        $res = $vfs->read(dirname($this->_params['path']),
                          basename($this->_params['path']));
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        if (!preg_match_all('|^\s*([^:\s#]+)[\s:]+(.*?)\s*$|m', $res, $matches,
                            PREG_SET_ORDER)) {
            return array();
        }

        /* This freaky thing can parse an /etc/aliases style file or a
         * /etc/mail/virtual style file. */
        $aliases = array();
        for ($i = 0; $i < count($matches); $i++) {
            $key = $matches[$i][1];
            if (preg_match('/^(.*)@(.*)$/', $key, $kmatches)) {
                if (!in_array(strtolower($kmatches[2]),
                              $this->_params['mydomains'])) {
                    continue;
                }
                $key = $kmatches[1];
            }
            $values = preg_split('|\s*,\s*|', $matches[$i][2]);
            foreach ($values as $value) {
                if (preg_match('/^(.*)@(.*)$/', $value, $vmatches)) {
                    if (!in_array(strtolower($vmatches[2]),
                                  $this->_params['mydomains'])) {
                        continue;
                    }
                    $value = $vmatches[1];
                }
                $aliases[$key][] = $value;
            }
        }

        return $aliases;
    }

}
