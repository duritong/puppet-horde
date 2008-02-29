<?php

include_once 'SyncML/Command.php';

/**
 * The SyncML_Command_Final class.
 *
 * $Horde: framework/SyncML/SyncML/Command/Final.php,v 1.10.10.6 2007/01/02 13:54:42 jan Exp $
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
class SyncML_Command_Final extends SyncML_Command {

    /**
     * Create the response for a final tag
     */
    function output($currentCmdID, &$output)
    {
        $state = &$_SESSION['SyncML.state'];
        $attrs = array();

        // If the client hasn't sent us device info, request it now.
        $di = SyncML_Device::deviceInfo();
        if (empty($di->_Man)) {
            $output->startElement($state->getURI(), 'Get', $attrs);

            $output->startElement($state->getURI(), 'CmdID', $attrs);
            $chars = $currentCmdID;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdID');

            $output->startElement($state->getURI(), 'Meta', $attrs);
            $output->startElement($state->getURIMeta(), 'Type', $attrs);
            $attrs = array();
            if (is_a($output, 'XML_WBXML_Encoder')) {
                $chars = MIME_SYNCML_DEVICE_INFO_WBXML;
            } else {
                $chars = MIME_SYNCML_DEVICE_INFO_XML;
            }
            $output->characters($chars);
            $output->endElement($state->getURI(), 'Type');
            $output->endElement($state->getURI(), 'Meta');

            $output->startElement($state->getURI(), 'Item', $attrs);
            $output->startElement($state->getURI(), 'Target', $attrs);
            $output->startElement($state->getURI(), 'LocURI', $attrs);
            $chars = ($state->getVersion() == 0) ? './devinf10' : './devinf11';
            $output->characters($chars);
            $output->endElement($state->getURI(), 'LocURI');
            $output->endElement($state->getURI(), 'Target');
            $output->endElement($state->getURI(), 'Item');

            $output->endElement($state->getURI(), 'Get');

            // Mark this id down as needing a results packet routed to
            // the Put command.
            $state->setExpectedResultType($currentCmdID, 'Put');

            $currentCmdID++;
        }
        return $currentCmdID;
    }
    /**
     * Create a &lt;Final&gt; output.
     * Before that continue a sync if there are pending entries.
     * Static method
     */
    function outputFinal($currentCmdID, &$output)
    {
        $state = &$_SESSION['SyncML.state'];
        $attrs = array();

        global $messageFull;
        /* If there's pending sync data and space left in the message:
         * send data now. */
        if (!$messageFull &&
                count($p = $state->getPendingSyncs()) > 0) {
            foreach ($p as $pendingSync) {
                if (!$messageFull) {
                   $GLOBALS['backend']->logMessage('continue sync for syncType=' . $pendingSync,
                                     __FILE__, __LINE__, PEAR_LOG_DEBUG);
                    $sync = & $state->getSync($pendingSync);
                    $currentCmdID = $sync->createSyncOutput($currentCmdID, $output);
                }
            }
        }       /*
         * Don't send the final tag if we haven't sent all sync data yet.
         */
        if (!$state->hasPendingElements()) {
            $output->startElement($state->getURI(), 'Final', $attrs);
            $output->endElement($state->getURI(), 'Final');
        } else {
            $GLOBALS['backend']->logMessage('pending elements, not sending final tag',
                                     __FILE__, __LINE__, PEAR_LOG_DEBUG);
        }
        return $currentCmdID;
    }

}
