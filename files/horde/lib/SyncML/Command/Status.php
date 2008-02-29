<?php

require_once 'SyncML/Command.php';
require_once 'SyncML/Constants.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Status.php,v 1.15.10.7 2007/01/02 13:54:42 jan Exp $
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
class SyncML_Command_Status extends SyncML_Command {

    var $_response;

    var $_cmdRef;

    /**
     * Must be present.
     */
    var $_cmd;

    var $_sourceRef;

    var $_targetRef;

    var $_chalMetaFormat;

    var $_chalMetaType;

    var $_chalMetaNextNonce;

    var $_itemDataAnchorNext;

    var $_itemDataAnchorLast;

    function SyncML_Command_Status($response = null, $cmd = null)
    {
        if ($response != null) {
            $this->_response = $response;
        }

        if ($cmd != null) {
            $this->_cmd = $cmd;
        }
    }

    function output($currentCmdID, &$output)
    {
        $attrs = array();

        $state = &$_SESSION['SyncML.state'];

        if ($this->_cmd != null) {
            $attrs = array();
            $output->startElement($state->getURI(), 'Status', $attrs);

            $output->startElement($state->getURI(), 'CmdID', $attrs);
            $chars = $currentCmdID;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdID');

            $output->startElement($state->getURI(), 'MsgRef', $attrs);
            $chars = $state->getMsgID();
            $output->characters($chars);
            $output->endElement($state->getURI(), 'MsgRef');

            $output->startElement($state->getURI(), 'CmdRef', $attrs);
            $chars = $this->_cmdRef;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdRef');

            $output->startElement($state->getURI(), 'Cmd', $attrs);
            $chars = $this->_cmd;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'Cmd');

            if (isset($this->_targetRef)) {
                $output->startElement($state->getURI(), 'TargetRef', $attrs);
                $chars = $this->_targetRef;
                $output->characters($chars);
                $output->endElement($state->getURI(), 'TargetRef');
            }

            if (isset($this->_sourceRef)) {
                $output->startElement($state->getURI(), 'SourceRef', $attrs);
                $chars = $this->_sourceRef;
                $output->characters($chars);
                $output->endElement($state->getURI(), 'SourceRef');
            }

            // If we are responding to the SyncHdr and we are not
            // authorized then request basic authorization.
            //
            // FIXME: Right now we always send this, ignoring the
            // isAuthorized() test. Is that correct?
            if ($this->_cmd == 'SyncHdr' && !$state->isAuthorized()) {
                $this->_chalMetaFormat = 'b64';
                $this->_chalMetaType = 'syncml:auth-basic';
                $this->_response = RESPONSE_CREDENTIALS_MISSING;
            }

            if (isset($this->_chalMetaFormat) && isset($this->_chalMetaType)) {
                $output->startElement($state->getURI(), 'Chal', $attrs);
                $output->startElement($state->getURI(), 'Meta', $attrs);

                $metainfuri = $state->getURIMeta();

                $output->startElement($metainfuri, 'Type', $attrs);
                $chars = $this->_chalMetaType;
                $output->characters($chars);
                $output->endElement($metainfuri, 'Type');

                $output->startElement($metainfuri, 'Format', $attrs);
                $chars = $this->_chalMetaFormat;
                $output->characters($chars);
                $output->endElement($metainfuri, 'Format');

                $output->endElement($state->getURI(), 'Meta');
                $output->endElement($state->getURI(), 'Chal');
            }

            $output->startElement($state->getURI(), 'Data', $attrs);
            $chars = $this->_response;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'Data');

            if (isset($this->_itemDataAnchorNext) || isset($this->_itemDataAnchorLast)) {
                $output->startElement($state->getURI(), 'Item', $attrs);
                $output->startElement($state->getURI(), 'Data', $attrs);

                $metainfuri = $state->getURIMeta();
                $output->startElement($metainfuri, 'Anchor', $attrs);

                if (isset($this->_itemDataAnchorLast)) {

                  $output->startElement($metainfuri, 'Last', $attrs);
                  $chars = $this->_itemDataAnchorLast;
                  $output->characters($chars);
                  $output->endElement($metainfuri, 'Last');
                }

                if (isset($this->_itemDataAnchorNext)) {

                  $output->startElement($metainfuri, 'Next', $attrs);
                  $chars = $this->_itemDataAnchorNext;
                  $output->characters($chars);
                  $output->endElement($metainfuri, 'Next');
                }

                $output->endElement($metainfuri, 'Anchor');

                $output->endElement($state->getURI(), 'Data');
                $output->endElement($state->getURI(), 'Item');
            }

            $output->endElement($state->getURI(), 'Status');

            $currentCmdID++;
        }

        return $currentCmdID;
    }

    /**
     * Setter for property response.
     *
     * @param string $response  New value of property response.
     */
    function setResponse($response)
    {
        $this->_response = $response;
    }

    /**
     * Setter for property cmd.
     *
     * @param string $cmd  New value of property cmd.
     */
    function setCmd($cmd)
    {
        $this->_cmd = $cmd;
    }

    /**
     * Setter for property cmdRef.
     *
     * @param string $cmdRef  New value of property cmdRef.
     */
    function setCmdRef($cmdRef)
    {
        $this->_cmdRef = $cmdRef;
    }

    /**
     * Setter for property sourceRef.
     *
     * @param string $sourceRef  New value of property sourceRef.
     */
    function setSourceRef($sourceRef)
    {
        $this->_sourceRef = $sourceRef;
    }

    /**
     * Setter for property targetRef.
     *
     * @param string $targetRef  New value of property targetRef.
     */
    function setTargetRef($targetRef)
    {
        $this->_targetRef = $targetRef;
    }

    /**
     * Setter for property itemDataAnchorNext.
     *
     * @param string $itemDataAnchorNext  New value of property itemDataAnchorNext.
     */
    function setItemDataAnchorNext($itemDataAnchorNext)
    {
        $this->_itemDataAnchorNext = $itemDataAnchorNext;
    }

    /**
     * Setter for property itemDataAnchorLast.
     *
     * @param string $itemDataAnchorLast  New value of property itemDataAnchorLast.
     */
    function setItemDataAnchorLast($itemDataAnchorLast)
    {
        $this->_itemDataAnchorLast = $itemDataAnchorLast;
    }

}
