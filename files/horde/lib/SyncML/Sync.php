<?php
/**
 * $Horde: framework/SyncML/SyncML/Sync.php,v 1.8.4.10 2007/01/02 13:54:41 jan Exp $
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
class SyncML_Sync {

    /**
     * Target: contacts, notes, calendar, tasks,
     */
    var $_targetLocURI; // target means server here

    var $_sourceLocURI; // source means client here

    var $_alert;

    /**
     * True indicates that there are still sync elements that have not been
     * sent yet due to message size limitations and have to be sent in the next
     * message.
     *
     * @var boolean
     */
    var $_pendingElements;

    /**
     * Remember entries we have handled already: once we send a delete for an
     * entry, we don't want to send an add afterwards. This array is also used
     * if a sync is sent in multiple messages due to message size restrictions.
     *
     * @var array
     */
    var $_done;

    function SyncML_Sync($alert, $serverURI, $clientURI)
    {
        $this->_alert = $alert;
        $this->_pendingElements = false;
        $this->_done = array();
        $this->_targetLocURI = basename($serverURI);
        $this->_sourceLocURI = $clientURI;
        global $backend;
        $backend->logMessage("create for syncType=$serverURI", __FILE__, __LINE__, PEAR_LOG_DEBUG);
    }

    /**
     * Here's where the actual processing of a client-sent Sync
     * Item takes place. Entries are added, deleted or replaced
     * from the server database by using Horde API (Registry) calls.
     */
    function handleSyncItem($item)
    {
        global $backend;

        $state = &$_SESSION['SyncML.state'];
        $device = &$state->getDevice();
        $syncIdentifier = $state->getSyncIdentifier();

        $hordeType = $type = $this->_targetLocURI;

        // Use contentType explicitly specified in this sync command.
        $contentType = $item->getContentType();

        // If not provided, use default from device info.
        if (!$contentType) {
            $contentType = $device->getPreferredContentType($type);
        }

        list($content, $contentType) =
            $device->convertClient2Server($item->getContent(),
                                          $contentType);
        $cuid = $item->getCuid();
        $suid = false;

        // Handle client add requests.
        if ($item->getElementType() =='Add') {
            $suid = $backend->importEntry($syncIdentifier, $hordeType,
                                          $content, $contentType, $cuid);
            if (!is_a($suid, 'PEAR_Error')) {
                $state->log("Client-Add");
                $backend->logMessage('added client entry as ' . $suid,
                                     __FILE__, __LINE__, PEAR_LOG_DEBUG);
            } else {
                $state->log("Client-AddFailure");
                $backend->logMessage('Error in adding client entry:' . $suid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
            }

        // Handle client delete requests.
        } elseif ($item->getElementType() =='Delete') {
            $suid = $backend->deleteEntry($syncIdentifier, $type, $cuid);
            if ($type == 'calendar'
                && $device->handleTasksInCalendar()) {
                // deleteEntry does not need to return an error on
                // deletion of nonexistent entries. So just to be sure
                // we have to delete from the tasks database as well:
                $backend->logMessage('special tasks delete ' . $suid . ' due to client request', __FILE__, __LINE__, PEAR_LOG_DEBUG);
                $suid = $backend->deleteEntry($syncIdentifier, 'tasks', $cuid);
            }
            if (!is_a($suid, 'PEAR_Error')) {
                $state->log("Client-Delete");
                $backend->logMessage('deleted entry ' . $suid . ' due to client request', __FILE__, __LINE__, PEAR_LOG_DEBUG);
            } else {
                $state->log("Client-DeleteFailure");
                $this->logMessage('Failure deleting client entry, maybe gone already on server. msg:'. $suid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
            }

        // Handle client replace requests.
        } elseif ($item->getElementType() == 'Replace') {
            $suid = $backend->replaceEntry($syncIdentifier, $hordeType,
                                           $content, $contentType, $cuid);

            if (!is_a($suid, 'PEAR_Error')) {
                $state->log("Client-Replace");
                $backend->logMessage('replaced entry ' . $suid . ' due to client request', __FILE__, __LINE__, PEAR_LOG_DEBUG);
            } else {
                $backend->logMessage($suid->message, __FILE__, __LINE__, PEAR_LOG_DEBUG);
                $backend->logMessage($suid, __FILE__, __LINE__, PEAR_LOG_DEBUG);

                // Entry may have been deleted; try adding it.
                $suid = $backend->importEntry($syncIdentifier, $hordeType,
                                              $content, $contentType, $cuid);
                if (!is_a($suid, 'PEAR_Error')) {
                    $state->log("Client-AddReplace");
                    $backend->logMessage('added client entry due to replace request as ' . $suid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
                } else {
                    $state->log("Client-AddFailure");
                    $backend->logMessage('Error in adding client entry due to replace request:' . $suid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
                }
            }
        } else {
            $backend->logMessage('Unexpected elementType: ' . $item->getElementType(),
                                 __FILE__, __LINE__, PEAR_LOG_ERR);
        }

        return $suid;
    }

    /**
     * Creates a &lt;Sync&gt; output.
     */
    function createSyncOutput($currentCmdID, &$output)
    {
        $state = &$_SESSION['SyncML.state'];
        $attrs = array();

        $output->startElement($state->getURI(), 'Sync', $attrs);
        $output->startElement($state->getURI(), 'CmdID', $attrs);
        $output->characters($currentCmdID);
        $currentCmdID++;
        $output->endElement($state->getURI(), 'CmdID');

        $output->startElement($state->getURI(), 'Target', $attrs);
        $output->startElement($state->getURI(), 'LocURI', $attrs);
        $output->characters($this->_sourceLocURI);
        $output->endElement($state->getURI(), 'LocURI');
        $output->endElement($state->getURI(), 'Target');

        $output->startElement($state->getURI(), 'Source', $attrs);
        $output->startElement($state->getURI(), 'LocURI', $attrs);
        $output->characters($this->_targetLocURI);
        $output->endElement($state->getURI(), 'LocURI');
        $output->endElement($state->getURI(), 'Source');

        $syncType = $this->_targetLocURI;

        global $backend;
        $backend->logMessage("handleSync for syncType=$syncType", __FILE__, __LINE__, PEAR_LOG_DEBUG);

        // Here's where server modifications are sent to the client:
        $refts = $state->getServerAnchorLast($syncType);
        $currentCmdID = $this->handleSync($currentCmdID, $syncType,
                                          $output, $refts);

        $output->endElement($state->getURI(), 'Sync');

        return $currentCmdID;
    }

    /**
     * Sends server changes to the client.
     */
    function handleSync($currentCmdID, $syncType, &$output, $refts, $end_ts = 0)
    {
        global $backend;
        global $messageFull;

        /* $messageFull will be set to true to indicate that there's no room
         * for other data in this message. If it's false (empty) and there
         * are pending Sync data, the final command will sent the pending data.
         * This global data should be moved to a global object, together
         * with currentCmdID and $output. */
        $messageFull = false;

        $state = &$_SESSION['SyncML.state'];
        $device = &$state->getDevice();
        $syncIdentifier = $state->getSyncIdentifier();
        $contentType = $device->getPreferredContentTypeClient($syncType, $this->_sourceLocURI);

        /* We faithfully expect to deal with all remaining elements. Will be
         * set to true if we run out of space for message creation. */
        $this->setPendingElements(false);

        // Handle deletions.
        $deletions = $backend->getServerDeletions($syncIdentifier, $syncType, $refts, $end_ts);

        if ($refts > 0) {
            // Don't send deletes on SlowSync.
            foreach ($deletions as $suid => $cuid) {
                $this->_done[$suid] = true;
                $backend->logMessage("delete: cuid=$cuid suid=$suid refts: $refts", __FILE__, __LINE__, PEAR_LOG_DEBUG);
                // Create a Delete request for client.
                $currentCmdID = $this->outputCommand($currentCmdID, $output, 'Delete',
                                                     null, null, $cuid, null);
                $state->log('Server-Delete');
            }
        }
        // Handle additions.
        $adds = $backend->getServerAdditions($syncIdentifier, $syncType, $refts, $end_ts);
        foreach ($adds as $suid => $cuid) {
            if (!empty($this->_done[$suid])) {
                // Already sent delete, no need to add.
                continue;
            }
            $backend->logMessage("add: $suid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $c = $backend->retrieveEntry($syncType, $suid, $contentType);
            if (!is_a($c, 'PEAR_Error')) {
                // Item in history but not in database. Strange, but can happen.
                list($clientContent, $clientContentType) =
                    $device->convertServer2Client($c, $contentType);
                // Check if we have space left in the message.
                if (($state->getMaxMsgSize()
                    - $output->getOutputSize()
                    - strlen($clientContent)) < 50) {
                    $backend->logMessage('max message size reached cursize='
                                         . $output->getOutputSize(),
                                         __FILE__, __LINE__, PEAR_LOG_DEBUG);
                    $messageFull = true;
                    $this->setPendingElements(true);
                    return $currentCmdID;
                }
                $this->_done[$suid] = true;
                $currentCmdID = $this->outputCommand($currentCmdID, $output,
                                                     'Add',
                                                     $clientContent,
                                                     $clientContentType,
                                                     null,
                                                     $suid);
                $state->log('Server-Add');
            } else {
                $backend->logMessage('api export call for ' . $suid . ' failed:  ' . $c->getMessage(),
                                     __FILE__, __LINE__, PEAR_LOG_DEBUG);
            }
        }

        // Handle changes.
        if ($refts != 0) {
            // Don't send changes for SlowSync, confuses some clients.
            $end_ts = time();
            $changes = $backend->getServerModifications($syncIdentifier,
                                                        $syncType,
                                                        $refts, $end_ts);
            if (is_a($changes, 'PEAR_Error')) {
                $backend->logMessage($changes, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $changes;
            }

            foreach ($changes as $suid => $cuid) {
                if (!empty($this->_done[$suid])) {
                    // Already sent delete or add, no need to modify.
                    continue;
                }

                if (!$cuid) {
                    // TODO: create an "add" here instead?
                    continue;
                }

                $c = $backend->retrieveEntry($syncType, $suid, $contentType);
                if (!is_a($c, 'PEAR_Error')) {
                    $backend->logMessage("change: $suid",
                                         __FILE__, __LINE__, PEAR_LOG_DEBUG);
                    list($clientContent, $clientContentType) =
                        $device->convertServer2Client($c, $contentType);
                    // Check if we have space left in the message.
                    if (($state->getMaxMsgSize()
                         - $output->getOutputSize()
                         - strlen($clientContent)) < 50) {
                        $backend->logMessage('max message size reached cursize='
                                             . $output->getOutputSize(),
                                             __FILE__, __LINE__, PEAR_LOG_DEBUG);
                        $messageFull = true;
                        $this->setPendingElements(true);
                        return $currentCmdID;
                    }
                    $this->_done[$suid] = true;
                    $currentCmdID = $this->outputCommand($currentCmdID, $output,
                                                         'Replace',
                                                         $clientContent,
                                                         $clientContentType,
                                                         $cuid,
                                                         null);
                    $state->log('Server-Replace');
                } else {
                    // Item in history but not in database. Strange, but
                    // can happen.
                }
            }
        }

        // If tasks are handled inside calendar, do the same again for
        // tasks.
        if ($syncType == 'calendar' && $device->handleTasksInCalendar()) {
            $backend->logMessage("handling tasks in calendar sync",
                                 __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $currentCmdID = $this->handleSync($currentCmdID, 'tasks',
                                              $output, $refts, $end_ts);
        }

        return $currentCmdID;
    }

    /**
     * Output a single Sync command (Add, Delete, Replace).
     */
    function outputCommand($currentCmdID, &$output, $command,
                           $content, $contentType = null,
                           $cuid = null, $suid = null)
    {
        $state = &$_SESSION['SyncML.state'];

        $attrs = array();
        $output->startElement($state->getURI(), $command, $attrs);

        $output->startElement($state->getURI(), 'CmdID', $attrs);
        $chars = $currentCmdID;
        $output->characters($chars);
        $output->endElement($state->getURI(), 'CmdID');

        if (isset($contentType)) {
            $output->startElement($state->getURI(), 'Meta', $attrs);
            $output->startElement($state->getURIMeta(), 'Type', $attrs);
            $output->characters($contentType);
            $output->endElement($state->getURIMeta(), 'Type');
            $output->endElement($state->getURI(), 'Meta');
        }

        if (isset($content)
            || isset($cuid) || isset($suid)) {
            $output->startElement($state->getURI(), 'Item', $attrs);
            if ($suid != null) {
                $output->startElement($state->getURI(), 'Source', $attrs);
                $output->startElement($state->getURI(), 'LocURI', $attrs);
                $output->characters($suid);
                $output->endElement($state->getURI(), 'LocURI');
                $output->endElement($state->getURI(), 'Source');
            }

            if ($cuid != null) {
                $output->startElement($state->getURI(), 'Target', $attrs);
                $output->startElement($state->getURI(), 'LocURI', $attrs);
                $output->characters($cuid);
                $output->endElement($state->getURI(), 'LocURI');
                $output->endElement($state->getURI(), 'Target');
            }
            if (isset($content)) {
                $output->startElement($state->getURI(), 'Data', $attrs);
                $output->characters($content);
                $output->endElement($state->getURI(), 'Data');
            }
            $output->endElement($state->getURI(), 'Item');
        }

        $output->endElement($state->getURI(), $command);

        $currentCmdID++;

        return $currentCmdID;
    }

    function setPendingElements($e)
    {
        $this->_pendingElements = $e;
    }

    function hasPendingElements()
    {
        return $this->_pendingElements;
    }

}
