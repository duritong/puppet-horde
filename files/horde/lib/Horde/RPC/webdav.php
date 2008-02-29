<?php

include_once 'HTTP/WebDAV/Server.php';

/**
 * The Horde_RPC_webdav class provides a WebDAV implementation of the
 * Horde RPC system.
 *
 * $Horde: framework/RPC/RPC/webdav.php,v 1.1.12.7 2007/01/02 13:54:36 jan Exp $
 *
 * Copyright 2004-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 3.0
 * @package Horde_RPC
 */
class Horde_RPC_webdav extends Horde_RPC {

    /**
     * Resource handler for the WebDAV server.
     *
     * @var HTTP_WebDAV_Server_Horde
     */
    var $_server;

    /**
     * WebDav server constructor.
     *
     * @access private
     */
    function Horde_RPC_webdav()
    {
        parent::Horde_RPC();

        $this->_server = &new HTTP_WebDAV_Server_Horde();
    }

    /**
     * Sends an RPC request to the server and returns the result.
     *
     * @param string  The raw request string.
     *
     * @return string  The XML encoded response from the server.
     */
    function getResponse($request)
    {
        $this->_server->ServeRequest();
        exit;
    }

    /**
     * WebDAV handles authentication internally, so bypass the
     * system-level auth check by just returning true here.
     */
    function authorize()
    {
        return true;
    }

}

/**
 * Horde extension of the base HTTP_WebDAV_Server class.
 *
 * @package Horde_RPC
 */
class HTTP_WebDAV_Server_Horde extends HTTP_WebDAV_Server {

    /**
     * Realm string to be used in authentification popups
     *
     * @var string
     */
    var $http_auth_realm = 'Horde WebDAV';

    /**
     * String to be used in "X-Dav-Powered-By" header
     *
     * @var string
     */
    var $dav_powered_by = 'Horde WebDAV Server';

    /**
     * GET implementation.
     *
     * @param array $options  Array of input and output parameters.
     * <br><strong>input</strong><ul>
     * <li> path -
     * </ul>
     * <br><strong>output</strong><ul>
     * <li> size -
     * </ul>
     *
     * @return integer  HTTP-Statuscode.
     */
    function GET(&$options)
    {
        if ($options['path'] == '/') {
            $options['mimetype'] = 'httpd/unix-directory';
        } else {
            $options = $this->_list($options['path'], 0);
        }

        return true;
    }

    /**
     * PUT method handler
     *
     * @param array &$options  Parameter passing array.
     * @return boolean  True on success.
     */
    function PUT(&$options)
    {
        return true;
    }

    /**
     * PROPFIND method handler
     *
     * @param array $options  General parameter passing array.
     * @param array &$files  Return array for file properties.
     * @return boolean  True on success.
     */
    function PROPFIND($options, &$files)
    {
        $list = $this->_list($options['path'], $options['depth']);
        if ($list === false) {
            return false;
        }
        $files['files'] = $list;
        return true;
    }

    function _list($path, $depth)
    {
        global $registry;

        $list = array(
            array('path' => $this->path,
                'props' => array(
                    $this->mkprop('displayname', $this->path),
                    $this->mkprop('creationdate', time()),
                    $this->mkprop('getlastmodified', time()),
                    $this->mkprop('resourcetype', 'collection'),
                    $this->mkprop('getcontenttype', 'httpd/unix-directory'),
                    $this->mkprop('getcontentlength', 0))));
        if ($path == '/') {
            $apps = $registry->listApps(null, false, PERMS_READ);
            if (is_a($apps, 'PEAR_Error')) {
                return false;
            }
            foreach ($apps as $app) {
                if ($registry->hasMethod('browse', $app)) {
                    $props = array(
                        $this->mkprop('displayname', String::convertCharset($registry->get('name', $app), NLS::getCharset(), 'UTF-8')),
                        $this->mkprop('creationdate', time()),
                        $this->mkprop('getlastmodified', time()),
                        $this->mkprop('resourcetype', 'collection'),
                        $this->mkprop('getcontenttype', 'httpd/unix-directory'),
                        $this->mkprop('getcontentlength', 0));
                    $item = array('path' => $this->path . '/' . $app,
                                  'props' => $props);
                    $list[] = $item;
                }
            }
        } else {
            if (substr($path, 0, 1) == '/') {
                $path = substr($path, 1);
            }
            $pieces = explode('/', $path);
            $items = $registry->callByPackage($pieces[0], 'browse', array('path' => $path, 'properties' => array('name', 'browseable', 'contenttype', 'contentlength', 'created', 'modified')));
            if (is_a($items, 'PEAR_Error')) {
                return false;
            }
            if (!is_array(reset($items))) {
                /* We return an object's content. */
                return $items;
            }
            foreach ($items as $sub_path => $i) {
                $props = array(
                    $this->mkprop('displayname', String::convertCharset($i['name'], NLS::getCharset(), 'UTF-8')),
                    $this->mkprop('creationdate', empty($i['created']) ? 0 : $i['created']),
                    $this->mkprop('getlastmodified', empty($i['modified']) ? 0 : $i['modified']),
                    $this->mkprop('resourcetype', $i['browseable'] ? 'collection' : ''),
                    $this->mkprop('getcontenttype', $i['browseable'] ? 'httpd/unix-directory' : (empty($i['contenttype']) ? 'application/octet-stream' : $i['contenttype'])),
                    $this->mkprop('getcontentlength', empty($i['contentlength']) ? 0 : $i['contentlength']));
                $item = array('path' => '/' . $sub_path,
                              'props' => $props);
                $list[] = $item;
            }
        }

        if ($depth) {
            if ($depth == 1) {
                $depth = 0;
            }
            foreach ($list as $app => $item) {
                //_list($path . '/' . $item, $depth);
            }
        }

        return $list;
    }

    /**
     * Check authentication. We always return true here since we
     * handle permissions based on the resource that's requested, but
     * we do record the authenticated user for later use.
     *
     * @param string $type      Authentication type, e.g. "basic" or "digest"
     * @param string $username  Transmitted username.
     * @param string $password  Transmitted password.
     *
     * @return boolean  Authentication status. Always true.
     */
    function check_auth($type, $username, $password)
    {
        $auth = &Auth::singleton($GLOBALS['conf']['auth']['driver']);
        return $auth->authenticate($username, array('password' => $password));
    }

}
