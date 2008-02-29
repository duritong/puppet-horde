<?php

include_once 'SyncML/Command.php';
include_once 'SyncML/Command/SyncElement.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Sync.php,v 1.17.10.9 2007/01/02 13:54:42 jan Exp $
 *
 * Copyright 2005-2007 Karsten Fourmont <karsten@horde.org>
 *
 * The command handler for the &gt;Sync&lt; command is the central
 * class to dispatch sync messages.
 *
 * During parsing of the received XML, the actual sync commands (Add,
 * Replace, Delete) from the client are stored in the _syncElements
 * attribute.  When the output method of SyncML_Command_Sync is
 * called, these elements are processed and the resulting status
 * messages created.
 *
 * Then the server modifications are sent back to the client by the
 * handleSync method which is called from within the output method.
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @since   Horde 3.0
 * @package SyncML
 */
class SyncML_Command_Sync extends Syncml_Command
{
    /**
     * @var SyncML_Command_SyncElement
     */
    var $_currentSyncElement;

    /**
     * @var array
     */
    var $_syncElements = array();

    /**
     * contacts, calendar, tasks or notes
     *
     * @var string
     */
    var $_targetURI;

    /**
     * Creates a response to a sync command.
     * Currently that's the place where we also create
     * the &lt;sync&gt; with the server changes that
     * needs to be send to the client.
     */
    function output($currentCmdID, &$output)
    {
        $state =& $_SESSION['SyncML.state'];
        $sync =& $state->getSync($this->_targetURI);
        if (!is_object($sync)) {
            $GLOBALS['backend']->logMessage('No sync object found for URI = ' . $this->_targetURI,
                                            __FILE__, __LINE__, PEAR_LOG_ERR);
            // @TODO: create meaningful status code here.
        }

        // Here's where client modifications are processed:
        foreach ($this->_syncElements as $element) {
            foreach ($element->getItems() as $item) {
                $result = $sync->handleSyncItem($item);
            }
            $currentCmdID = $element->output($currentCmdID, $output);
        }

        /* Now send client changes to server: this will precede the
         * <sync> response: */
        $currentCmdID = $sync->createSyncOutput($currentCmdID, $output);

        $status = &new SyncML_Command_Status(RESPONSE_OK, 'Sync');
        $status->setCmdRef($this->_cmdID);

        if ($this->_targetURI != null) {
            $status->setTargetRef($this->_targetURI);
        }

        if ($this->_sourceURI != null) {
            $status->setSourceRef($this->_sourceURI);
        }

        return $status->output($currentCmdID, $output);
    }

    function getTargetURI()
    {
        return $this->_targetURI;
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch (count($this->_Stack)) {
        case 2:
            if ($element == 'Replace' || $element == 'Add' || $element == 'Delete') {
                $this->_currentSyncElement = &new SyncML_Command_SyncElement($element);
            }
            break;
        }

        if (isset($this->_currentSyncElement)) {
            $this->_currentSyncElement->startElement($uri, $element, $attrs);
        }
    }

    function endElement($uri, $element)
    {
        if (isset($this->_currentSyncElement)) {
            $this->_currentSyncElement->endElement($uri, $element);
        }

        switch (count($this->_Stack)) {
        case 2:
            if ($element == 'Replace' || $element == 'Add' || $element == 'Delete') {
                $this->_syncElements[] = $this->_currentSyncElement;
                unset($this->_currentSyncElement);
            }
            break;

        case 3:
            if ($element = 'LocURI' && !isset($this->_currentSyncElement)) {
                if ($this->_Stack[1] == 'Source') {
                    $this->_sourceURI = trim($this->_chars);
                } elseif ($this->_Stack[1] == 'Target') {
                    $this->_targetURI = basename(preg_replace('/\?.*$/', '', trim($this->_chars)));
                }
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function characters($str)
    {
        if (isset($this->_currentSyncElement)) {
            $this->_currentSyncElement->characters($str);
        } else {
            if (isset($this->_chars)) {
                $this->_chars = $this->_chars . $str;
            } else {
                $this->_chars = $str;
            }
        }
    }

}
