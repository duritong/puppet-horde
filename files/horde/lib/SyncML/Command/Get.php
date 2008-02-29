<?php

require_once 'SyncML/Command.php';

/**
 * The SyncML_Command_Get class.
 *
 * This class responds to a client get request and returns the DevInf
 * information for the SyncML server.
 *
 * $Horde: framework/SyncML/SyncML/Command/Get.php,v 1.14.10.11 2007/01/02 13:54:42 jan Exp $
 *
 * Copyright 2003-2007 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <fourmont@gmx.de>
 * @author  Anthony Mills <amills@pyramid6.com>
 * @since   Horde 3.0
 * @package SyncML
 */
class SyncML_Command_Get extends SyncML_Command {

    function output($currentCmdID, &$output)
    {
        $state = &$_SESSION['SyncML.state'];

        $ref = ($state->getVersion() == 0) ? './devinf10' : './devinf11';

        $status = &new SyncML_Command_Status(($state->isAuthorized() ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS), 'Get');
        $status->setCmdRef($this->_cmdID);
        $status->setTargetRef($ref);
        $currentCmdID = $status->output($currentCmdID, $output);

        if ($state->isAuthorized()) {
            $attrs = array();
            $output->startElement($state->getURI(), 'Results', $attrs);

            $output->startElement($state->getURI(), 'CmdID', $attrs);
            $chars = $currentCmdID;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdID');

            $output->startElement($state->getURI(), 'MsgRef', $attrs);
            $chars = $state->getMsgID();
            $output->characters($chars);
            $output->endElement($state->getURI(), 'MsgRef');

            $output->startElement($state->getURI(), 'CmdRef', $attrs);
            $chars = $this->_cmdID;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdRef');

            $output->startElement($state->getURI(), 'Meta', $attrs);
            $output->startElement($state->getURIMeta(), 'Type', $attrs);
            if (is_a($output, 'XML_WBXML_Encoder')) {
                $output->characters(MIME_SYNCML_DEVICE_INFO_WBXML);
            } else {
                $output->characters(MIME_SYNCML_DEVICE_INFO_XML);
            }

            $output->endElement($state->getURIMeta(), 'Type');
            $output->endElement($state->getURI(), 'Meta');

            $output->startElement($state->getURI(), 'Item', $attrs);
            $output->startElement($state->getURI(), 'Source', $attrs);
            $output->startElement($state->getURI(), 'LocURI', $attrs);
            $output->characters($ref);
            $output->endElement($state->getURI(), 'LocURI');
            $output->endElement($state->getURI(), 'Source');

            $output->startElement($state->getURI(), 'Data', $attrs);

            /* DevInf data is stored in wbxml not as a seperate codepage but
             * rather as a complete wbxml stream as opaque data.  So we need a
             * new Handler. */
            $devinfoutput = $output->createSubHandler();
            
            $devinfoutput->startElement($state->getURIDevInf() , 'DevInf', $attrs);
            $devinfoutput->startElement($state->getURIDevInf() , 'VerDTD', $attrs);
            $devinfoutput->characters(($state->getVersion() == 0) ? '1.0' : '1.1');
            $devinfoutput->endElement($state->getURIDevInf() , 'VerDTD', $attrs);
            $devinfoutput->startElement($state->getURIDevInf() , 'Man', $attrs);
            $devinfoutput->characters('The Horde Project (http://www.horde.org)');
            $devinfoutput->endElement($state->getURIDevInf() , 'Man', $attrs);
            $devinfoutput->startElement($state->getURIDevInf() , 'DevID', $attrs);
            $devinfoutput->characters($_SERVER['HTTP_HOST']);
            $devinfoutput->endElement($state->getURIDevInf() , 'DevID', $attrs);
            $devinfoutput->startElement($state->getURIDevInf() , 'DevTyp', $attrs);
            $devinfoutput->characters('server');
            $devinfoutput->endElement($state->getURIDevInf() , 'DevTyp', $attrs);
            $this->_writeDataStore('notes', 'text/x-vnote', '1.1', $devinfoutput,
                                   array('text/plain' => '1.0'));
            $this->_writeDataStore('contacts', 'text/x-vcard', '3.0', $devinfoutput,
                                   array('text/x-vcard' => '2.1'));
            $this->_writeDataStore('tasks', 'text/calendar', '2.0', $devinfoutput,
                                   array('text/x-vcalendar' => '1.0'));
            $this->_writeDataStore('calendar', 'text/calendar', '2.0', $devinfoutput,
                                   array('text/x-vcalendar' => '1.0'));
            $devinfoutput->endElement($state->getURIDevInf() , 'DevInf', $attrs);

            $output->opaque($devinfoutput->getOutput());
            $output->endElement($state->getURI(), 'Data');
            $output->endElement($state->getURI(), 'Item');
            $output->endElement($state->getURI(), 'Results');

            $currentCmdID++;
        }

        return $currentCmdID;
    }

    /**
     * Writes DevInf data for one DataStore.
     *
     * @param string $sourceref: data for SourceRef element.
     * @param string $mimetype: data for &lt;(R|T)x-Pref&gt;&lt;CTType&gt;
     * @param string $version: data for &lt;(R|T)x-Pref&gt;&lt;VerCT&gt;
     * @param string &$output contenthandler that will received the output.
     * @param array $additionaltypes: array of additional types for Tx and Rx;
     *              format array('text/vcard' => '2.0')
     */
    function _writeDataStore($sourceref, $mimetype, $version, &$output,
                             $additionaltypes = false)
    {
        $attrs = array();

        $state = &$_SESSION['SyncML.state'];

        $output->startElement($state->getURIDevInf() , 'DataStore', $attrs);
        $output->startElement($state->getURIDevInf() , 'SourceRef', $attrs);
        $output->characters($sourceref);
        $output->endElement($state->getURIDevInf() , 'SourceRef', $attrs);

        $output->startElement($state->getURIDevInf() , 'Rx-Pref', $attrs);
        $output->startElement($state->getURIDevInf() , 'CTType', $attrs);
        $output->characters($mimetype);
        $output->endElement($state->getURIDevInf() , 'CTType', $attrs);
        $output->startElement($state->getURIDevInf() , 'VerCT', $attrs);
        $output->characters($version);
        $output->endElement($state->getURIDevInf() , 'VerCT', $attrs);
        $output->endElement($state->getURIDevInf() , 'Rx-Pref', $attrs);

        if (is_array($additionaltypes)) {
            foreach ($additionaltypes as $ct => $ctver){
                $output->startElement($state->getURIDevInf() , 'Rx', $attrs);
                $output->startElement($state->getURIDevInf() , 'CTType', $attrs);
                $output->characters($ct);
                $output->endElement($state->getURIDevInf() , 'CTType', $attrs);
                $output->startElement($state->getURIDevInf() , 'VerCT', $attrs);
                $output->characters($ctver);
                $output->endElement($state->getURIDevInf() , 'VerCT', $attrs);
                $output->endElement($state->getURIDevInf() , 'Rx', $attrs);
            }
        }

        $output->startElement($state->getURIDevInf() , 'Tx-Pref', $attrs);
        $output->startElement($state->getURIDevInf() , 'CTType', $attrs);
        $output->characters($mimetype);
        $output->endElement($state->getURIDevInf() , 'CTType', $attrs);
        $output->startElement($state->getURIDevInf() , 'VerCT', $attrs);
        $output->characters($version);
        $output->endElement($state->getURIDevInf() , 'VerCT', $attrs);
        $output->endElement($state->getURIDevInf() , 'Tx-Pref', $attrs);

        if (is_array($additionaltypes)) {
            foreach ($additionaltypes as $ct => $ctver){
                $output->startElement($state->getURIDevInf() , 'Tx', $attrs);
                $output->startElement($state->getURIDevInf() , 'CTType', $attrs);
                $output->characters($ct);
                $output->endElement($state->getURIDevInf() , 'CTType', $attrs);
                $output->startElement($state->getURIDevInf() , 'VerCT', $attrs);
                $output->characters($ctver);
                $output->endElement($state->getURIDevInf() , 'VerCT', $attrs);
                $output->endElement($state->getURIDevInf() , 'Tx', $attrs);
            }
        }

        $output->startElement($state->getURIDevInf() , 'SyncCap', $attrs);
        $output->startElement($state->getURIDevInf() , 'SyncType', $attrs);
        $output->characters('1');
        $output->endElement($state->getURIDevInf() , 'SyncType', $attrs);
        $output->startElement($state->getURIDevInf() , 'SyncType', $attrs);
        $output->characters('2');
        $output->endElement($state->getURIDevInf() , 'SyncType', $attrs);
        $output->endElement($state->getURIDevInf() , 'SyncCap', $attrs);
        $output->endElement($state->getURIDevInf() , 'DataStore', $attrs);
    }

}
