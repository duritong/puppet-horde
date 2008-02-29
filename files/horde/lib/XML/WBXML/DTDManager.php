<?php

include_once 'XML/WBXML/DTD/SyncML.php';
include_once 'XML/WBXML/DTD/SyncMLMetInf.php';
include_once 'XML/WBXML/DTD/SyncMLDevInf.php';

/**
 * $Horde: framework/XML_WBXML/WBXML/DTDManager.php,v 1.3.12.12 2007/01/02 13:54:50 jan Exp $
 *
 * Copyright 2003-2007 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * From Binary XML Content Format Specification Version 1.3, 25 July
 * 2001 found at http://www.wapforum.org
 *
 * @package XML_WBXML
 */
class XML_WBXML_DTDManager {

    /**
     * @var array
     */
    var $_strDTD = array();

    /**
     * @var array
     */
    var $_strDTDURI = array();

    /**
     */
    function XML_WBXML_DTDManager()
    {
        $this->registerDTD('-//SYNCML//DTD SyncML 1.0//EN', 'syncml:syncml1.0', new XML_WBXML_DTD_SyncML(0));
        $this->registerDTD('-//SYNCML//DTD SyncML 1.1//EN', 'syncml:syncml1.1', new XML_WBXML_DTD_SyncML(1));

        $this->registerDTD('-//SYNCML//DTD MetInf 1.0//EN', 'syncml:metinf1.0', new XML_WBXML_DTD_SyncMLMetInf(0));
        $this->registerDTD('-//SYNCML//DTD MetInf 1.1//EN', 'syncml:metinf1.1', new XML_WBXML_DTD_SyncMLMetInf(1));

        $this->registerDTD('-//SYNCML//DTD DevInf 1.0//EN', 'syncml:devinf1.0', new XML_WBXML_DTD_SyncMLDevInf(0));
        $this->registerDTD('-//SYNCML//DTD DevInf 1.1//EN', 'syncml:devinf1.1', new XML_WBXML_DTD_SyncMLDevInf(1));
    }

    /**
     */
    function &getInstance($publicIdentifier)
    {
        $publicIdentifier = strtolower($publicIdentifier);
        if (isset($this->_strDTD[$publicIdentifier])) {
            $dtd = &$this->_strDTD[$publicIdentifier];
        } else {
            $dtd = null;
        }
        return $dtd;
    }

    /**
     */
    function &getInstanceURI($uri)
    {
        $uri = strtolower($uri);

        // some manual hacks:
        if ($uri == 'syncml:syncml') {
            $uri = 'syncml:syncml1.0';
        }
        if ($uri == 'syncml:metinf') {
            $uri = 'syncml:metinf1.0';
        }
        if ($uri == 'syncml:devinf') {
            $uri = 'syncml:devinf1.0';
        }

        if (isset($this->_strDTDURI[$uri])) {
            $dtd = &$this->_strDTDURI[$uri];
        } else {
            $dtd = null;
        }
        return $dtd;
    }

    /**
     */
    function registerDTD($publicIdentifier, $uri, &$dtd)
    {
        $publicIdentifier = strtolower($publicIdentifier);
        $dtd->setDPI($publicIdentifier);

        $this->_strDTD[$publicIdentifier] = $dtd;
        $this->_strDTDURI[strtolower($uri)] = $dtd;
    }

}
