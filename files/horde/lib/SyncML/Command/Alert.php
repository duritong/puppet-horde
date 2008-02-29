<?php

include_once 'SyncML/Command.php';

/**
 * The SyncML_Alert class provides a SyncML implementation of
 * the Alert command as defined in SyncML Representation Protocol,
 * version 1.1 5.5.2.
 *
 * $Horde: framework/SyncML/SyncML/Command/Alert.php,v 1.18.10.10 2007/01/02 13:54:42 jan Exp $
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
class SyncML_Command_Alert extends SyncML_Command {

    /**
     * @var integer
     */
    var $_alert;

    /**
     * @var string
     */
    var $_sourceLocURI;

    /**
     * @var string
     */
    var $_targetLocURI;

    /**
     * @var string
     */
    var $_metaAnchorNext;

    /**
     * @var integer
     */
    var $_metaAnchorLast;

    /**
     * Creates a new instance of Alert.
     */
    function SyncML_Command_Alert($alert = null)
    {
        if ($alert != null) {
            $this->_alert = $alert;
        }
    }

    function output($currentCmdID, &$output)
    {
        global $backend;

        $attrs = array();
        $state = &$_SESSION['SyncML.state'];

        // Handle unauthorized first.
        if (!$state->isAuthorized()) {
            $status = &new SyncML_Command_Status(RESPONSE_INVALID_CREDENTIALS, 'Alert');
            $status->setCmdRef($this->_cmdID);
            $currentCmdID = $status->output($currentCmdID, $output);
            return $currentCmdID;
        }

        $database = $this->_targetLocURI;

        // Store client's Next Anchor in State.  After successful sync
        // this is then written to persistence for negotiation of
        // further syncs.
        $state->setClientAnchorNext($database, $this->_metaAnchorNext);

        $info = $backend->getSyncSummary($state->getSyncIdentifier(),
                                         $this->_targetLocURI);

        if (is_a($info, 'DataTreeObject')) {
            $x = $info->get('ClientAnchor');
            $clientlast = $x[$database];
            $backend->logMessage(sprintf('previous sync found for database: %s; client-ts: %s',
                                         $database,
                                         $clientlast),
                                 __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $x = $info->get('ServerAnchor');
            $state->setServerAnchorLast($database, $x[$database]);
        } else {
            $backend->logMessage(sprintf('SyncML: No info about previous syncs found for id %s and database %s',
                                         $state->getSyncIdentifier(),
                                         $database),
                                 __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $clientlast = 0;
            $state->setServerAnchorLast($database, 0);
        }

        // Set Server Anchor for this sync to current time.
        $state->setServerAnchorNext($database, time());
        if (($clientlast || $clientlast === '0')
                 && $clientlast == $this->_metaAnchorLast) {
            // Last Sync Anchor matches, TwoWaySync will do.
            $code = RESPONSE_OK;
            $backend->logMessage("SyncML: Anchor match, TwoWaySync since "
                                . $clientlast,
                               __FILE__, __LINE__, PEAR_LOG_DEBUG);
        } else {
            if ($clientlast) {
                $backend->logMessage('client requested sync with anchor ts ' .
                                     $this->_metaAnchorLast . ' but server ' .
                                     'has timestamp' . $clientlast .
                                     ' on file',
                                     __FILE__, __LINE__, PEAR_LOG_INFO);
            }
            $backend->logMessage("SyncML: Anchor mismatch, enforcing SlowSync",
                                 __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $clientlast = 0;
            $state->setServerAnchorLast($database, 0);
            if ($this->_alert == 201) {
                /* Slowsync requested from client anyway: just acknowledge. */
                $code = RESPONSE_OK;
            } else {
                /* Mismatch, enforce slow sync. */
                $this->_alert = 201;
                $code = 508;
            }
        }

        $status = &new SyncML_Command_Status($code, 'Alert');
        $status->setCmdRef($this->_cmdID);
        if ($this->_sourceLocURI != null) {
            $status->setSourceRef($this->_sourceLocURI);
        }
        if ($this->_targetLocURI != null) {
            $status->setTargetRef($this->_targetLocURI);
        }

        // Mirror Next Anchor from client back to client.
        if (isset($this->_metaAnchorNext)) {
            $status->setItemDataAnchorNext($this->_metaAnchorNext);
        }

        // Mirror Last Anchor from client back to client.
        if (isset($this->_metaAnchorLast)) {
            $status->setItemDataAnchorLast($this->_metaAnchorLast);
        }

        $currentCmdID = $status->output($currentCmdID, $output);

        if ($state->isAuthorized()) {
            $output->startElement($state->getURI(), 'Alert', $attrs);

            $output->startElement($state->getURI(), 'CmdID', $attrs);
            $chars = $currentCmdID;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdID');

            $output->startElement($state->getURI(), 'Data', $attrs);
            $chars = $this->_alert;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'Data');

            $output->startElement($state->getURI(), 'Item', $attrs);

            if ($this->_sourceLocURI != null) {
                $output->startElement($state->getURI(), 'Target', $attrs);
                $output->startElement($state->getURI(), 'LocURI', $attrs);
                $chars = $this->_sourceLocURI;
                $output->characters($chars);
                $output->endElement($state->getURI(), 'LocURI');
                $output->endElement($state->getURI(), 'Target');
            }

            if ($this->_targetLocURI != null) {
                $output->startElement($state->getURI(), 'Source', $attrs);
                $output->startElement($state->getURI(), 'LocURI', $attrs);
                $chars = $this->_targetLocURI;
                $output->characters($chars);
                $output->endElement($state->getURI(), 'LocURI');
                $output->endElement($state->getURI(), 'Source');
            }

            $output->startElement($state->getURI(), 'Meta', $attrs);

            $output->startElement($state->getURIMeta(), 'Anchor', $attrs);

            $output->startElement($state->getURIMeta(), 'Last', $attrs);
            $chars = $state->getServerAnchorLast($database);
            $output->characters($chars);
            $output->endElement($state->getURIMeta(), 'Last');

            $output->startElement($state->getURIMeta(), 'Next', $attrs);
            $chars = $state->getServerAnchorNext($database);
            $output->characters($chars);
            $output->endElement($state->getURIMeta(), 'Next');

            $output->endElement($state->getURIMeta(), 'Anchor');
            $output->endElement($state->getURI(), 'Meta');
            $output->endElement($state->getURI(), 'Item');
            $output->endElement($state->getURI(), 'Alert');

            $currentCmdID++;
        }

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

    }

    function endElement($uri, $element)
    {
        global $backend;

        switch (count($this->_Stack)) {
        case 1:
            $state = &$_SESSION['SyncML.state'];
            if ($this->_targetLocURI != 'tasks'
                && $this->_targetLocURI != 'calendar'
                && $this->_targetLocURI != 'notes'
                && $this->_targetLocURI != 'contacts') {
                $backend->logMessage('Error! Invalid database: '
                                     . $this->_targetLocURI
                                     . '. Only tasks, calendar, notes,'
                                     . ' and contacts allowed!',
                                     __FILE__, __LINE__, PEAR_LOG_ERR);
                die("error: invalid database!");
            }

            $backend->logMessage('looking for sync for ' .
                                 $this->_targetLocURI,
                                 __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $sync = & $state->getSync($this->_targetLocURI);

            if (!$sync) {
                $backend->logMessage('Create new sync for ' .
                                     $this->_targetLocURI,
                                     __FILE__, __LINE__, PEAR_LOG_DEBUG);

                $sync = &new SyncML_Sync($this->_alert,
                                         $this->_targetLocURI,
                                         $this->_sourceLocURI);
                $state->setSync($this->_targetLocURI, $sync);
            }
            break;

        case 2:
            if ($element == 'Data') {
                $this->_alert = intval(trim($this->_chars));
            }
            break;

        case 4:
            if ($element == 'LocURI') {
                if ($this->_Stack[2] == 'Source') {
                    $this->_sourceLocURI = trim($this->_chars);
                } elseif ($this->_Stack[2] == 'Target') {
                    $this->_targetLocURI = basename(preg_replace('/\?.*$/', '', trim($this->_chars)));
                }
            }
            break;

        case 5:
            if ($element == 'Next') {
                $this->_metaAnchorNext = trim($this->_chars);
            } elseif ($element == 'Last') {
                $this->_metaAnchorLast = trim($this->_chars);
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function getAlert()
    {
        return $this->_alert;
    }

    function setAlet($alert)
    {
        $this->_alert = $alert;
    }

}
