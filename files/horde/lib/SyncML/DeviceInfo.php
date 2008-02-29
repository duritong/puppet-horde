<?php
/**
 * SyncML_DeviceInfo represents a device information set according to the
 * SyncML spec.
 *
 * See http://www.syncml.org/docs/syncml_devinf_v11_20020215.pdf
 *
 * A DeviceInfo object is created by Command/Put::SyncML_Command_Put from an
 * appropriate XML message. SyncML_Command_Put directly populates the members
 * variables.
 *
 * The current implementation should handle all DevInf 1.1 DTD elements
 * expcept DSMem entries.
 *
 * Copyright 2005-2007 Karsten Fourmont <karsten@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * $Horde: framework/SyncML/SyncML/DeviceInfo.php,v 1.2.2.7 2007/01/02 13:54:41 jan Exp $
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @package SyncML
 */
class SyncML_DeviceInfo {

    var $_VerDTD;
    var $_Man;
    var $_Mod;
    var $_OEM;
    var $_FwV;
    var $_SwV;
    var $_HwV;
    var $_DevID;
    var $_DevTyp;

    /**
     * Array of SyncML_DataStore objects.
     */
    var $_DataStore;

    /**
     * Array: CTType => array: PropertyName => SyncML_Property object.
     */
    var $_CTCap;

    /**
     * Array XNam => array of Xval entries.
     */
    var $_Ext;

    /**
     * @var boolean
     */
    var $_UTC;

    /**
     * @var boolean
     */
    var $_SupportLargeObjs;

    /**
     * @var boolean
     */
    var $_SupportNumberOfChanges;


    /**
     * Returns the DevInf data for a datastore identified by $sourceURI
     * (_SourceRef parameter).  Returns a SyncML_DataStore object or null if
     * no such datastore exists.
     */ 
    function getDataStore($sourceURI)
    {

        if (is_array($this->_DataStore)) {
            foreach ($this->_DataStore as $v) {
                if ($v->_SourceRef == $sourceURI) {
                    return $v;
                }
            }
        }
        return null;
    }
}

/**
 * The SyncML_DataStore class describes one of the possible datastores
 * (i.e. databases) of the device.  Most important attributes are the
 * preferred Mime Types for sending and receiving data for this datastore:
 * _Tx-Pref and _Rx-Pref
 *
 * @package SyncML
 */
class SyncML_DataStore {

    /**
     * The local URI of the datastore.
     */
    var $_SourceRef;

    /**
     * Optional.
     */
    var $_DisplayName;

    /**
     * Optional.
     */
    var $_MaxGUIDSize;

    /**
     * One element array of CTType => VerCT
     */
    var $_Rx_Pref;

    /**
     * Array of CTType => VerCT
     */
    var $_Rx;

    /**
     * One element array of CTType => VerCT
     */
    var $_Tx_Pref;

    /**
     * Array of CTType => VerCT
     */
    var $_Tx;

    /**
     * Optional.
     */
    var $_DSMem;

    /**
     * Array SyncType => true
     */
    var $_SyncCap;

    /**
     * Returns the preferred contentype the client wants to receive (RX). Or
     * NULL if not specified (which is not allowed by protocol).
     */
    function getPreferredRXContentType()
    {
        if (is_array($this->_Rx_Pref)) {
            $r = array_keys($this->_Rx_Pref);
            return $r[0];
        }
        return null;
    }

    /**
     * Returns the version of the preferred contentype the client wants to
     * receive (RX). Or NULL if not specified (which is not allowed by
     * protocol).
     */
    function getPreferredRXContentTypeVersion()
    {
        if (is_array($this->_Rx_Pref)) {
            $r = array_values($this->_Rx_Pref);
            return $r[0];
        }
        return NULL;
    }
}

/**
 * The class SyncML_Property is used to define a single property of a vcard
 * element.  The contents of a Property can be defined by an enumeration of
 * valid values (_ValEnum) or by a DataType/Size combination, or not at all.
 *
 * @package SyncML
 */
class SyncML_Property {

    /**
     * Array of valid values => true
     */
    var $_ValEnum;

    /**
     * If not ValEnum.
     */
    var $_DataType;

    /**
     * If not ValEnum, optional.
     */
    var $_Size;

    /**
     * Optional.
     */
    var $_DisplayName;

    /**
     * Array of ParamName => SyncML_PropertyParameter
     */
    var $_params;

}

/**
 * The class SyncML_PropertyParameter is used to define a single parameter of
 * a property of a vcard element.  The contents of a PropertyParameter can be
 * defined by an enumeration of valid values (_ValEnum) or by a DataType/Size
 * combination, or not at all.
 *
 * @package SyncML
 */
class SyncML_PropertyParameter {

    /**
     * If provided: array of ValEnum => true
     */
    var $_ValEnum;

    /**
     * If not ValEnum.
     */
    var $_DataType;

    /**
     * If not ValEnum, optional.
     */
    var $_Size;

    /**
     * Optional.
     */
    var $_DisplayName;

}
