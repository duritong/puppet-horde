<?php

require_once 'SyncML/DeviceInfo.php';
require_once 'SyncML/Device.php';
require_once 'SyncML/Constants.php';

/**
 * The SyncML_State class provides a SyncML state object.
 *
 * $Horde: framework/SyncML/SyncML/State.php,v 1.17.2.9 2007/01/02 13:54:41 jan Exp $
 *
 * Copyright 2003-2007 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @since   Horde 3.0
 * @package SyncML
 */
class SyncML_State {

    /**
     * Session id of this session.
     */
    var $_sessionID;

    /**
     * Id of current message.
     */
    var $_msgID;

    /**
     * The target URI as sent by the client. This is normally the URL
     * of the Horde rpc server. However the client is free to send
     * anything: sync4j for example does not send the path part.
     */
    var $_targetURI;

    /**
     * The source URI as sent by the client. Can be used to identify
     * the client; the session id is constructed mainly from this
     * data.
     */
    var $_sourceURI;

    /**
     * 0 for syncml 1.0, 1 for syncml 1.1.
     */
    var $_version;

    /**
     * Username used to auth with the backend.
     */
    var $_locName;

    /**
     * Password used to auth with the backend.
     */
    var $_password;

    /**
     * True means that this session has authenticated successfully.
     */
    var $_isAuthorized;

    /**
     * Namespace information.
     */
    var $_uri;

    /**
     * Namespace information.
     */
    var $_uriMeta;

    /**
     * Namespace information.
     */
    var $_uriDevInf;

    var $_wbxml; // boolean

    var $_maxMsgSize;

    var $_syncs = array();

    /**
     * Written to db after successful sync.
     */
    var $_clientAnchorNext = array();

    var $_serverAnchorLast = array();

    /**
     * Written to db after successful sync.
     */
    var $_serverAnchorNext = array();

    /**
     * Small hash to store the number of adds, deletes, etc. during a
     * session for creation of summary for logfile.
     */
    var $_log = array();

    /**
     * The SyncML_Device class defined in Device.php. This class
     * handles decice specific stuff.
     */
    var $_device;

    /**
     * Device info provided by the SyncML DevInf data. Mainly used by
     * the SyncML_Device class.
     */
    var $_deviceInfo;

    /**
     * If we are expecting a Result packet, this stores where to
     * route it.
     */
    var $_expectedResult = array();

    /**
     * Creates a new instance of SyncML_State.
     */
    function SyncML_State($sourceURI, $locName, $sessionID, $password = false)
    {
        $this->setSourceURI($sourceURI);
        $this->setLocName($locName);
        $this->setSessionID($sessionID);
        if ($password) {
            $this->setPassword($password);
        }

        $this->isAuthorized = false;

        // Create empty dummy device info. Will be replaced with real
        // DevInf information if they are transferred.
        $this->_deviceInfo = &new SyncML_DeviceInfo();

        /* allow very big messages unless told otherwise: */
        $this->setMaxMsgSize(1000000000);
    }

    function setDeviceInfo($di)
    {
        $this->_deviceInfo = $di;
    }

    function getDeviceInfo()
    {
        return $this->_deviceInfo;
    }

    function getLocName()
    {
        return $this->_locName;
    }

    function getSourceURI()
    {
        return $this->_sourceURI;
    }

    function getTargetURI()
    {
        return $this->_targetURI;
    }

    function getVersion()
    {
        return $this->_version;
    }

    function getMsgID()
    {
        return $this->_msgID;
    }

    function setWBXML($wbxml)
    {
        $this->_wbxml = $wbxml;
    }

    function isWBXML()
    {
        return !empty($this->_wbxml);
    }

    function setMaxMsgSize($s)
    {
        $this->_maxMsgSize = $s;
    }

    function getMaxMsgSize()
    {
        return $this->_maxMsgSize;
    }

    /**
     * Setter for property msgID.
     *
     * @param string $msgID  New value of property msgID.
     */
    function setMsgID($msgID)
    {
        $this->_msgID = $msgID;
    }

    /**
     * Setter for property locName.
     *
     * @param string $locName  New value of property locName.
     */
    function setLocName($locName)
    {
        $this->_locName = $locName;
    }

    function setPassword($password)
    {
        $this->_password = $password;
    }

    function setSourceURI($sourceURI)
    {
        $this->_sourceURI = $sourceURI;
    }

    function setTargetURI($targetURI)
    {
        $this->_targetURI = $targetURI;
    }

    function setVersion($version)
    {
        $this->_version = $version;

        if ($version == 0) {
            $this->_uri = NAME_SPACE_URI_SYNCML;
            $this->_uriMeta = NAME_SPACE_URI_METINF;
            $this->_uriDevInf = NAME_SPACE_URI_DEVINF;
        } else {
            $this->_uri = NAME_SPACE_URI_SYNCML_1_1;
            $this->_uriMeta = NAME_SPACE_URI_METINF_1_1;
            $this->_uriDevInf = NAME_SPACE_URI_DEVINF_1_1;
        }
    }

    function setSessionID($sessionID)
    {
        $this->_sessionID = $sessionID;
    }

    function isAuthorized()
    {
        if (!$this->_isAuthorized && !empty($this->_password)) {
            $GLOBALS['backend']->logMessage('checking auth for user='
            . $this->_locName,
             __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $auth = &Auth::singleton($GLOBALS['conf']['auth']['driver']);
            $this->_isAuthorized = $auth->authenticate($this->_locName, array('password' => $this->_password));
        }

        return $this->_isAuthorized;
    }

    function setSync($target, &$sync)
    {
        $this->_syncs[$target] = &$sync;
    }

    function &getSync($target)
    {
        if (isset($this->_syncs[$target])) {
            return $this->_syncs[$target];
        } else {
            $false = false;
            return $false;
        }
    }

    function getURI()
    {
        /*
         * The non WBXML devices (notably P900 and Sync4j seem to get confused
         * by a <SyncML xmlns="syncml:SYNCML1.1"> element. They require
         * just <SyncML>. So don't use an ns for non wbxml devices.
         */
        if ($this->isWBXML()) {
            return $this->_uri;
        } else {
            return '';
        }
    }
    function getURIMeta()
    {
        return $this->_uriMeta;
    }

    function getURIDevInf()
    {
        return $this->_uriDevInf;
    }

    function setClientAnchorNext($type, $a)
    {
        $this->_clientAnchorNext[$type] = $a;
    }

    function setServerAnchorLast($type, $a)
    {
        $this->_serverAnchorLast[$type] = $a;
    }

    function setServerAnchorNext($type, $a)
    {
        $this->_serverAnchorNext[$type] = $a;
    }

    function getClientAnchorNext($type)
    {
        return $this->_clientAnchorNext[$type];
    }

    function getServerAnchorNext($type)
    {
        return $this->_serverAnchorNext[$type];
    }

    function getServerAnchorLast($type)
    {
        return $this->_serverAnchorLast[$type];
    }

    /**
     * The log simply counts the entries for each topic.
     */
    function log($topic)
    {
        if (isset($this->_log[$topic])) {
            $this->_log[$topic] += 1;
        } else {
            $this->_log[$topic] = 1;
        }
    }

    /**
     * The Log is an array where the key is the event name and the
     * value says how often this event occured.
     */
    function getLog()
    {
        return $this->_log;
    }

    /**
     * Returns an identifier used to identify the sync device in the
     * map. Currently "locname:sourceURI".
     */
    function getSyncIdentifier()
    {
        return str_replace(':', '_', $this->_locName)
                . ':'
                . str_replace(':', '_', $this->_sourceURI);
    }

    function &getDevice()
    {
        return SyncML_Device::singleton();
    }

    /**
     * Returns the expected result type for a Results tag, used to redirect the
     * incoming command somewhere special.  For example, if we have requested
     * device info, the Results tag should be sent to the Put command so we can
     * read it in.
     *
     * Clears the hash of this Command Reference before returning a value.
     *
     * @param integer $cmdRef  The CmdRef of the incoming command.
     *
     * @return string  The command type to redirect to, or
     *                 undefined if no redirect registered.
     */
    function getExpectedResultType($cmdRef)
    {
        if ( isset($this->_expectedResult[$cmdRef]) ) {
            $cmdType = $this->_expectedResult[$cmdRef];
            unset($this->_expectedResult[$cmdRef]);
        }
        return $cmdType;
    }

    /**
     * Sets up a command redirection for a future Result command.
     *
     * @param integer $cmdID    The CmdID of the command we are sending to
     *                          the client.
     * @param string  $cmdType  The type of command to redirect to when
     *                          the Results command is received.
     */
    function setExpectedResultType($cmdID, $cmdType)
    {
        $this->_expectedResult[$cmdID] = $cmdType;
    }

    /**
     * Check if there are any pending elements that have not been sent to due
     * to message sitze restrictions. These will be sent int the next msg.
     *
     */
    function hasPendingElements()
    {

        if (is_array($this->_syncs)) {
            foreach ($this->_syncs as $sync) {
                if ($sync->hasPendingElements()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns all syncs which have pending elements left.
     * Returns an array of TargetLocURIs which can be used
     * as a key in getSync calls.
     */
    function getPendingSyncs()
    {
        $r = array();
        if (is_array($this->_syncs)) {
            foreach ($this->_syncs as $target => $sync) {
                if ($sync->hasPendingElements()) {
                    $r[] = $target;
                }
            }
        }
        return $r;
    }
}
