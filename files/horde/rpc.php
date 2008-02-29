<?php
/**
 * RPC processing script.
 *
 * Possible GET values:
 *   'noauth'    -- Don't attempt manual authentication.
 *   'wsdl'      -- TODO
 *
 * $Horde: horde/rpc.php,v 1.30.10.7 2007/03/09 17:19:21 jan Exp $
 *
 * Copyright 2002-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('AUTH_HANDLER', true);
@define('HORDE_BASE', dirname(__FILE__));
require_once HORDE_BASE . '/lib/core.php';
require_once 'Horde/RPC.php';

/* Look at the Content-type of the request, if it is available, to try
 * and determine what kind of request this is. */
$input = null;
$params = array();

if (!empty($_SERVER['PATH_INFO']) ||
    in_array($_SERVER['REQUEST_METHOD'], array('PROPFIND'))) {
    $serverType = 'webdav';
} elseif (isset($_SERVER['REQUEST_METHOD']) &&
          $_SERVER['REQUEST_METHOD'] == 'GET' &&
          Util::getGet('restCall')) {
    $serverType = 'rest';
} elseif (!empty($_SERVER['CONTENT_TYPE'])) {
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/vnd.syncml+xml') !== false) {
        $serverType = 'syncml';
        /* Syncml does its own session handling. */
        $session_control = 'none';
    } elseif (strpos($_SERVER['CONTENT_TYPE'], 'application/vnd.syncml+wbxml') !== false) {
        $serverType = 'syncml_wbxml';
        /* Syncml does its own session handling. */
        $session_control = 'none';
    } elseif (strpos($_SERVER['CONTENT_TYPE'], 'text/xml') !== false) {
        $input = Horde_RPC::getInput();
        /* Check for SOAP namespace URI. */
        if (strpos($input, 'http://schemas.xmlsoap.org/soap/envelope/') !== false) {
            $serverType = 'soap';
        } else {
            $serverType = 'xmlrpc';
        }
    } else {
        header('HTTP/1.0 501 Not Implemented');
        exit;
    }
} else {
    $serverType = 'soap';
}

if ($serverType == 'soap' &&
    (!isset($_SERVER['REQUEST_METHOD']) ||
     $_SERVER['REQUEST_METHOD'] != 'POST')) {
    $session_control = 'none';
    if (isset($_GET['wsdl'])) {
        $params['wsdl'] = 1;
    } else {
        $params['disco'] = 1;
    }
}

/* Check to see if we want to skip authentication entirely if auth fails. */
if (Util::getGet('noauth')) {
    $params['noauth'] = 1;
}

/* Load base libraries. */
require_once HORDE_BASE . '/lib/base.php';

/* Load the RPC backend based on $serverType. */
$server = &Horde_RPC::singleton($serverType, $params);

/* Let the backend check authentication. By default, we look for HTTP
 * basic authentication against Horde, but backends can override this
 * as needed. */
$server->authorize();

/* Get the server's response. We call $server->getInput() to allow
 * backends to handle input processing differently. */
if ($input === null) {
    $input = $server->getInput();
}

$out = $server->getResponse($input, $params);

if (is_a($out, 'PEAR_Error')) {
    header('HTTP/1.0 500 Internal Server Error');
    echo $out->getMessage();
    exit;
}

/* Return the response to the client. */
header('Content-Type: ' . $server->getResponseContentType());
header('Content-length: ' . strlen($out));
header('Accept-Charset: UTF-8');
echo $out;
