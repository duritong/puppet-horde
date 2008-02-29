<?php

include_once 'SyncML/Command.php';
include_once 'SyncML/Command/Status.php';
include_once 'SyncML/Command/Alert.php';
include_once 'SyncML/Command/Sync.php';
include_once 'SyncML/Command/Final.php';
include_once 'SyncML/Sync.php';
include_once 'SyncML/Backend.php';

/**
 * The SyncML_SyncHdr and SyncML_SyncBody classes provides
 * a SyncHdr and SyncBody in SyncML Representation Protocol, version
 * 1.1 5.2.2 and 5.2.3.  Most of the work is passed on to
 * SyncML_Command_Alert and SyncML_Command_Sync.
 *
 * There are two global objects that are used by SyncML:
 * 1) $_SESSION['SyncML.state']:
 *    session object used to maintain the state between the individual
 *    SyncML messages.
 *
 * 2) $GLOBALS['backend']
 *    Backend to handle the communication with the datastore. Currently the
 *    horde backend is the only backend provided.
 *
 * $Horde: framework/SyncML/SyncML.php,v 1.21.10.11 2007/01/02 13:54:39 jan Exp $
 *
 * Copyright 2003-2007 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @author  Karsten Fourmont <fourmont@gmx.de>
 * @since   Horde 3.0
 * @package SyncML
 */
class SyncML_ContentHandler {

    /**
     * Output ContentHandler used to output XML events.
     *
     * @var object
     */
    var $_output;

    /**
     * Stack for holding the xml elements during creation of the object from
     * the xml event flow.
     *
     * @var array
     */
    var $_Stack = array();

    /**
     * @var string
     */
    var $_chars;

    function setOutput(&$output)
    {
        $this->_output = &$output;
    }

    function startElement($uri, $element, $attrs)
    {
        $this->_Stack[] = $element;
    }

    function endElement($uri, $element)
    {
        if (isset($this->_chars)) {
            unset($this->_chars);
        }

        array_pop($this->_Stack);
    }

    function characters($str)
    {
        if (isset($this->_chars)) {
            $this->_chars = $this->_chars . $str;
        } else {
            $this->_chars = $str;
        }
    }

}


/**
 * Defined in SyncML Representation Protocol, version 1.1 5.2.2
 *
 * @package SyncML
 */
class SyncML_SyncMLHdr extends SyncML_ContentHandler {

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.9. User name.
     *
     * @var string
     */
    var $_locName;

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.18
     *
     * @var string
     */
    var $_sessionID;

    /**
     * Defined in SyncML Representation Protocol, version 1.1.  Must be 1.0 (0)
     * or 1.1 (1).
     *
     * @var string
     */
    var $_version;

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.12
     *
     * @var string
     */
    var $_msgID;

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.10
     *
     * @var string
     */
    var $_targetURI;

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.10, 5.1.20
     *
     * @var string
     */
    var $_sourceURI;

    var $_credData;

    var $_credFormat;

    var $_credType;

    var $_maxMsgSize;

    function getStateFromSession($sourceURI, $locName, $sessionID)
    {
        $GLOBALS['backend'] = &new SyncML_Backend_Horde();

        // Reload the Horde SessionHandler if necessary.
        Horde::setupSessionHandler();

        // SyncML protocol does not require the client to send the
        // username on any messages but the first.  So unfortunately
        // we can't use it to make a unique session id.
        session_id('syncml' . preg_replace('/[^a-zA-Z0-9]/',
                                           '', $sourceURI . $sessionID));
        session_start();

        // It would seem multisync does not send the user name once it
        // has been authorized. Make sure we have a valid session id.
        session_id('syncml' . preg_replace('/[^a-zA-Z0-9]/', '', $sourceURI . $sessionID));
        @session_start();

        if (!isset($_SESSION['SyncML.state'])) {
            // Create a new state if one does not already exist.
            $GLOBALS['backend']->logMessage('New session created: '
                              . session_id(),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $_SESSION['SyncML.state'] = &new SyncML_State($sourceURI,
                                                          $locName,
                                                          $sessionID);
        } else {
            $GLOBALS['backend']->logMessage('Existing session continued: '
                              . session_id(),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
        }

        return $_SESSION['SyncML.state'];
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);
    }

    function endElement($uri, $element)
    {
        switch (count($this->_Stack)) {
        case 1:
            // </SyncHdr></SyncML>
            // Find the state.
            $state = $this->getStateFromSession($this->_sourceURI, $this->_locName, $this->_sessionID);

            $state->setVersion($this->_version);
            $state->setMsgID($this->_msgID);
            $state->setTargetURI($this->_targetURI);
            $state->setPassword($this->_credData);
            $state->setWBXML(is_a($this->_output, 'XML_WBXML_Encoder'));

            // Store login name in state if not already set
            if (empty($state->_locName) && !empty($this->_locName)) {
                $state->setLocName($this->_locName);
            }
            if (!empty($this->_maxMsgSize)) {
                $state->setMaxMsgSize($this->_maxMsgSize);
            }

            $str = 'authorized=' . $state->isAuthorized() .
                ' version=' . $state->getVersion() .
                ' msgid=' . $state->getMsgID() .
                ' source=' . $state->getSourceURI() .
                ' target=' . $state->getTargetURI() .
                ' user=' . $state->getLocName() .
                ' charset=' . NLS::getCharset() .
                ' wbxml=' . $state->isWBXML()

                ;

            $_SESSION['SyncML.state'] = $state;

            $GLOBALS['backend']->logMessage($str, __FILE__, __LINE__, PEAR_LOG_DEBUG);

            // Got the state; now write our SyncHdr header.
            $this->outputSyncHdr($this->_output);
            break;

        case 2:
            if ($element == 'VerProto') {
                // </VerProto></SyncHdr></SyncML>
                if (trim($this->_chars) == 'SyncML/1.1') {
                    $this->_version = 1;
                } else {
                    $this->_version = 0;
                }
            } elseif ($element == 'SessionID') {
                // </SessionID></SyncHdr></SyncML>
                $this->_sessionID = trim($this->_chars);
            } elseif ($element == 'MsgID') {
                // </MsgID></SyncHdr></SyncML>
                $this->_msgID = intval(trim($this->_chars));
            } elseif ($element == 'Cred') {
                // </Cred></SyncHdr></SyncML>
                $this->_credData = base64_decode($this->_credData);

                $tmp = explode(':', $this->_credData);
                $this->_locName = $tmp[0];
                $this->_credData = $tmp[1];
                Horde::logMessage('SyncML: received credentials for user='
                                                . $this->_locName , 'cred='
                                                . $this->_credData,
                                                __FILE__, __LINE__, PEAR_LOG_DEBUG);

            }
            break;

        case 3:
            if ($element == 'LocURI') {
                if ($this->_Stack[1] == 'Source') {
                    // </LocURI></Source></SyncHdr></SyncML>
                    $this->_sourceURI = trim($this->_chars);
                } elseif ($this->_Stack[1] == 'Target') {
                    // </LocURI></Target></SyncHdr></SyncML>
                    $this->_targetURI = trim($this->_chars);
                }
            } elseif ($element == 'LocName') {
                if ($this->_Stack[1] == 'Source') {
                    // </LocName></Source></SyncHdr></SyncML>
                    $this->_locName = trim($this->_chars);
                }
            } elseif ($element == 'Data') {
                    // </Data></Cred></SyncHdr></SyncML>
                if ($this->_Stack[1] == 'Cred') {
                    $this->_credData = trim($this->_chars);
                }
            } elseif ($element == 'MaxMsgSize') {
                // </MaxMsgSize></Meta></SyncHdr></SyncML>
                $this->_maxMsgSize = intval($this->_chars);
            }
            break;

        case 4:
            if ($this->_Stack[1] == 'Cred') {
                if ($element == 'Format') {
                    // </Format></Meta></Cred></SyncHdr></SyncML>
                    $this->_credFormat = trim($this->_chars);
                } elseif ($element == 'Type') {
                    // </Type></Meta></Cred></SyncHdr></SyncML>
                    $this->_credType = trim($this->_chars);
                }
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function outputSyncHdr(&$output)
    {
        $attrs = array();

        $state = &$_SESSION['SyncML.state'];

        $uri = $state->getURI();
        $uriMeta = $state->getURIMeta();

        $output->startElement($uri, 'SyncHdr', $attrs);

        $output->startElement($uri, 'VerDTD', $attrs);
        $chars = ($this->_version == 1) ? '1.1' : '1.0';
        $output->characters($chars);
        $output->endElement($uri, 'VerDTD');

        $output->startElement($uri, 'VerProto', $attrs);
        $chars = ($this->_version == 1) ? 'SyncML/1.1' : 'SyncML/1.0';
        $output->characters($chars);
        $output->endElement($uri, 'VerProto');

        $output->startElement($uri, 'SessionID', $attrs);
        $output->characters($this->_sessionID);
        $output->endElement($uri, 'SessionID');

        $output->startElement($uri, 'MsgID', $attrs);
        $output->characters($this->_msgID);
        $output->endElement($uri, 'MsgID');

        $output->startElement($uri, 'Target', $attrs);
        $output->startElement($uri, 'LocURI', $attrs);
        $output->characters($this->_sourceURI);
        $output->endElement($uri, 'LocURI');
        $output->startElement($uri, 'LocName', $attrs);
        $output->characters($state->getLocName());
        $output->endElement($uri, 'LocName');
        $output->endElement($uri, 'Target');

        $output->startElement($uri, 'Source', $attrs);
        $output->startElement($uri, 'LocURI', $attrs);
        $output->characters($this->_targetURI);
        $output->endElement($uri, 'LocURI');
        $output->endElement($uri, 'Source');

        /*
        Do not send RespURI Element. It's optional and may be misleading:
        $this->_targetURI is data from the client and not guaranteed to be
        the correct URL for access. Let the client just stay with the same
        URL it has used for the previous request(s).
        */
        //$output->startElement($uri, 'RespURI', $attrs);
        //$output->characters($this->_targetURI);
        //$output->endElement($uri, 'RespURI');

        /*
        $output->startElement($uri, 'Meta', $attrs);

        // Dummy Max MsqSize, this is just put in to make the packet
        // work, it is not a real value.
        $output->startElement($uriMeta, 'MaxMsgSize', $attrs);
        $chars = '50000';
        $output->characters($chars);
        $output->endElement($uriMeta, 'MaxMsgSize');

        // Dummy MaxObjSize, this is just put in to make the packet
        // work, it is not a real value.
        $output->startElement($uriMeta, 'MaxObjSize', $attrs);
        $chars = '4000000';
        $output->characters($chars);
        $output->endElement($uriMeta, 'MaxObjSize');

        $output->endElement($uri, 'Meta');
        */

        $output->endElement($uri, 'SyncHdr');
    }

    function getSourceURI()
    {
        return $this->_sourceURI;
    }

    function getLocName()
    {
        return $this->_locName;
    }

    function getSessionID()
    {
        return $this->_sessionID;
    }

    function getVersion()
    {
        return $this->_version;
    }

    function getMsgID()
    {
        return $this->_msgID;
    }

    function getTargetURI()
    {
        return $this->_targetURI;
    }

    function opaque($o)
    {
    }

}

/**
 * Defined in SyncML Representation Protocol, version 1.1 5.2.3
 *
 * @package SyncML
 */
class SyncML_SyncMLBody extends SyncML_ContentHandler {

    var $_currentCmdID = 1;

    var $_currentCommand;

    var $_actionCommands = false;

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch (count($this->_Stack)) {
        case 1:
            $state = &$_SESSION['SyncML.state'];

            $this->_actionCommands = false; // so far, we have not seen commands that require action from our side

            // <SyncML><SyncBody>
            $uri = $state->getURI();
            $this->_output->startElement($uri, $element, $attrs);

            // Right our status about the header.
            $status = &new SyncML_Command_Status(($state->isAuthorized()) ?
                                                 RESPONSE_AUTHENTICATION_ACCEPTED : RESPONSE_INVALID_CREDENTIALS, 'SyncHdr');
            $status->setSourceRef($state->getSourceURI());
            $status->setTargetRef($state->getTargetURI());
            $status->setCmdRef(0);

            $this->_currentCmdID = $status->output($this->_currentCmdID, $this->_output);
            break;

        case 2:
            // <SyncML><SyncBody><[Command]>
            $this->_currentCommand = SyncML_Command::factory($element);
            $this->_currentCommand->startElement($uri, $element, $attrs);

            if ($element != 'Status' && $element != 'Map' && $element != 'Final') {
                // We've got to do something! This can't be the last
                // packet.
                $this->_actionCommands = true;
            }
            break;

        default:
            // <SyncML><SyncBody><Command><...>
            $this->_currentCommand->startElement($uri, $element, $attrs);
            break;
        }
    }

    function endElement($uri, $element)
    {
        switch (count($this->_Stack)) {
        case 1:
            // </SyncBody></SyncML>
            $state = &$_SESSION['SyncML.state'];

            if ($state->hasPendingElements()) {
                /* still something to do: don't close session */
                $this->_actionCommands = true;
            }
            $this->_currentCmdID = SyncML_Command_Final::outputFinal(
                                        $this->_currentCmdID,
                                        $this->_output);
            $this->_output->endElement($uri, $element);

            if (!$this->_actionCommands) {
                // This packet did not contain any real actions, just
                // status and map. This means we're done. The session
                // can be closed and the anchors saved for the next
                // sync.
                $GLOBALS['backend']->logMessage('sync' . session_id() .
                                                ' completed successfully!' .
                                                ' Storing Client-TS ' .
                                                $state->_clientAnchorNext,
                                                __FILE__, __LINE__, PEAR_LOG_INFO);
                $GLOBALS['backend']->writeSyncSummary(
                    $state->getSyncIdentifier(),
                    $state->_clientAnchorNext,
                    $state->_serverAnchorNext);
                $log = $state->getLog();
                $s = '';
                foreach ($log as $k => $v) {
                    $s .= " $k=$v";
                }
                $GLOBALS['backend']->logMessage('Summary:' . $s, __FILE__, __LINE__, PEAR_LOG_INFO);

                // Session can be closed here.
                session_unset();
                session_destroy();
            } else {
                $GLOBALS['backend']->logMessage('SyncML: return message completed',
                                                __FILE__, __LINE__, PEAR_LOG_DEBUG);
            }
            break;

        case 2:
            // </[Command]></SyncBody></SyncML>
            $this->_currentCommand->endElement($uri, $element);

            $this->_currentCmdID = $this->_currentCommand->output($this->_currentCmdID, $this->_output);

            unset($this->_currentCommand);
            break;

        default:
            // </...></[Command]></SyncBody></SyncML>
            $this->_currentCommand->endElement($uri, $element);
            break;
        }

        parent::endElement($uri, $element);
    }

    function characters($str)
    {
        if (isset($this->_currentCommand)) {
            $this->_currentCommand->characters($str);
        }
    }

}
