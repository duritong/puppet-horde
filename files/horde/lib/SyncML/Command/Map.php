<?php

include_once 'SyncML/Command.php';

/**
 * The SyncML_Map class provides a SyncML implementation of
 * the Map command as defined in SyncML Representation Protocol,
 * version 1.0.1 5.5.8.
 *
 * $Horde: framework/SyncML/SyncML/Command/Map.php,v 1.1.10.7 2007/01/02 13:54:42 jan Exp $
 *
 * Copyright 2004-2007 Karsten Fourmont <karsten@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @since   Horde 3.0
 * @package SyncML
 */
class SyncML_Command_Map extends SyncML_Command {

    /**
     * @var string
     */
    var $_sourceLocURI;

    /**
     * @var string
     */
    var $_targetLocURI;

    var $_mapTarget;
    var $_mapSource;

    function output($currentCmdID, &$output)
    {
        $attrs = array();

        $state = &$_SESSION['SyncML.state'];

        $status = &new SyncML_Command_Status($state->isAuthorized() ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS, 'Map');
        $status->setCmdRef($this->_cmdID);
        if ($this->_sourceLocURI != null) {
            $status->setSourceRef($this->_sourceLocURI);
        }
        if ($this->_targetLocURI != null) {
            $status->setTargetRef($this->_targetLocURI);
        }

        $currentCmdID = $status->output($currentCmdID, $output);

        return $currentCmdID;
    }

    /**
     * Setter for property sourceURI.
     *
     * @param string $sourceURI  New value of property sourceURI.
     */
    function setSourceLocURI($sourceURI)
    {
        $this->_sourceURI = $sourceURI;
    }

    function getTargetLocURI()
    {
        return $this->_targetURI;
    }

    /**
     * Setter for property targetURI.
     *
     * @param string $targetURI  New value of property targetURI.
     */
    function setTargetURI($targetURI)
    {
        $this->_targetURI = $targetURI;
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch (count($this->_Stack)) {
        case 2:
            if ($element == 'MapItem') {
                unset($this->_mapTarget);
                unset($this->_mapSource);
            }
            break;
        }
    }

    function endElement($uri, $element)
    {
        global $backend;

        switch (count($this->_Stack)) {
        case 1:
            $state = &$_SESSION['SyncML.state'];
            $sync = & $state->getSync($this->_targetLocURI);
            break;

        case 2:
            if ($element == 'MapItem') {
                $state = &$_SESSION['SyncML.state'];
                $sync = & $state->getSync($this->_targetLocURI);
                if (!$state->isAuthorized()) {
                    $backend->logMessage('Not Authorized in MapItem!',
                                         __FILE__, __LINE__, PEAR_LOG_ERR);
                } else {
                    // Overwrite existing data by removing it first.
                    $backend->createUidMap($state->getSyncIdentifier(),
                                           $this->_targetLocURI,
                                           $this->_mapSource,
                                           $this->_mapTarget);

                    $backend->logMessage('created Map for source='
                                         . $this->_mapSource
                                         . ' and target=' . $this->_mapTarget
                                         . ' in db ' . $this->_targetLocURI,
                                         __FILE__, __LINE__, PEAR_LOG_DEBUG);

                }
            }
            break;

        case 3:
            if ($element == 'LocURI') {
                if ($this->_Stack[1] == 'Source') {
                    $this->_sourceLocURI = trim($this->_chars);
                } elseif ($this->_Stack[1] == 'Target') {
                    $this->_targetLocURI = trim($this->_chars);
                }
            }
            break;

        case 4:
            if ($element == 'LocURI') {
                if ($this->_Stack[2] == 'Source') {
                    $this->_mapSource = trim($this->_chars);
                } elseif ($this->_Stack[2] == 'Target') {
                    $this->_mapTarget = trim($this->_chars);
                }
            }
            break;
        }

        parent::endElement($uri, $element);
    }

}
