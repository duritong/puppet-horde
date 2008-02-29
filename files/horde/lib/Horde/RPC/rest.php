<?php
/**
 * The Horde_RPC_rest class provides a REST implementation of the Horde RPC
 * system.
 *
 * $Horde: framework/RPC/RPC/rest.php,v 1.6.2.2 2006/05/04 12:24:14 jan Exp $
 *
 * Copyright Rafael Varela Pet <rafael.varela.pet@usc.es>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Rafael Varela <rafael.varela.pet@usc.es>
 * @since   Horde 3.1
 * @package Horde_RPC
 */
class Horde_RPC_rest extends Horde_RPC {

    /**
     * @var string
     */
    var $_contentType = 'text/html';

    /**
     * Check authentication. Different backends may handle
     * authentication in different ways. The base class implementation
     * checks for HTTP Authentication against the Horde auth setup.
     *
     * @return boolean  Returns true if authentication is successful.
     *                  Should send appropriate "not authorized" headers
     *                  or other response codes/body if auth fails,
     *                  and take care of exiting.
     */
    function authorize()
    {
        if (!$this->_authorize) {
            return true;
        }

        if (Auth::getAuth()) {
            return true;
        }

        return parent::authorize();
    }

    /**
     * Get. all the GET input data. The 'call' param is extracted from the
     * array and treated separately.
     *
     * @return array  (method name, array of parameters)
     *
     * TODO: deserializing of complex data types.
     */
    function getInput()
    {
        $this->_contentType = Util::getGet('restContentType', 'text/html');

    	$getData = Util::dispelMagicQuotes($_GET);
    	unset($getData['restCall']);
    	unset($getData['restContentType']);

    	return array('method' => Util::getGet('restCall'),
    	             'params' => $getData);
    }

    /**
     * Sends an RPC request to the server and returns the result.
     *
     * @param string  The raw request string.
     *
     * @return mixed  The response from the server or an PEAR error object on
     *                failure.
     */
    function getResponse($request)
    {
        global $registry;

        $method = str_replace('.', '/', $request['method']);

        if (!$registry->hasMethod($method)) {
            return PEAR::raiseError(sprintf(_("Method not defined. Called method: %s"), $method));
        }

        /* Look at the method signature so that parameters are assigned by
         * name, instead of relying on the order of GET parameters to match
         * the order defined in the API method. */
        $signature = $registry->getSignature($method);
        $params = array();
        foreach (array_keys($signature[0]) as $param) {
            $params[$param] = isset($request['params'][$param]) ? $request['params'][$param] : null;
        }

        $result = $registry->call($method, $params);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        /* The result is returned depending on the requested content type. */
        if ($this->_contentType == 'text/html') {
            return '<html><body>' . print_r($result, true) . '</body></html>';
        } elseif ($this->_contentType == 'text/plain') {
            return print_r($result, true);
        } elseif ($this->_contentType == 'application/x-httpd-php') {
            return serialize($result);
        } else {
            return $result;
        }
    }

    /**
     * Get the Content-Type of the response.
     *
     * @return string  The MIME Content-Type of the RPC response.
     */
    function getResponseContentType()
    {
        switch ($this->_contentType) {
        case 'application/x-httpd-php':
            return 'text/plain';
        default:
            return $this->_contentType;
        }
    }

}
