<?php

include_once 'SyncML/Command.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Results.php,v 1.11.10.7 2007/01/02 13:54:42 jan Exp $
 *
 * Copyright 2003-2007 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Nathan P Sharp
 * @since   Horde 3.0
 * @package SyncML
 */
class SyncML_Command_Results extends SyncML_Command {

    var $_subCommand;
    var $_savedUri;
    var $_savedElement;
    var $_savedAttrs;
    var $_savedCmdID;
    var $_chars;

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch (count($this->_Stack)) {
        case 1:
            // Save the info, we may have to re-send this startElement
            // to the sub-command later.
            $this->_savedUri = $uri;
            $this->_savedElement = $element;
            $this->_savedAttrs = $attrs;
            break;
        }

        if (isset($this->_subCommand)) {
            $this->_subCommand->startElement($uri, $element, $attrs);
        }
    }

    function endElement($uri, $element)
    {
        switch (count($this->_Stack)) {
        case 2:
            if ($element == 'CmdID' && !isset($this->_subCommand)) {
                $this->_savedCmdID = intval(trim($this->_chars));
            }

            if ($element == 'CmdRef') {
                $cmdRef = intval(trim($this->_chars));
                $state = &$_SESSION['SyncML.state'];

                // If this result packet is related to something and
                // needs to be routed to a particular command object,
                // do it now.
                $cmdType = $state->getExpectedResultType($cmdRef);
                if (!empty($cmdType)) {
                    $this->_subCommand = &SyncML_Command::factory($cmdType);
                    // This isn't totally technically correct because
                    // the XML could have been out of order and we
                    // might have missed some stuff, but in reality,
                    // it probably won't happen.
                    $this->_subCommand->startElement(
                        $this->_savedUri,
                        $this->_savedElement,
                        $this->_savedAttrs);
                    if (isset($this->_savedCmdID)) {
                        // FIXME: Shouldn't be accessing this private
                        // member.
                        $this->_subCommand->_cmdID = $this->_savedCmdID;
                        break;
                    }
                }
            }
            // Fall through to the default case.

        default:
            if (isset($this->_subCommand)) {
                $this->_subCommand->endElement($uri, $element);
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function characters($str)
    {
        parent::characters($str);
        if (isset($this->_subCommand)) {
            $this->_subCommand->characters($str);
        }
    }

    function output($currentCmdID, &$output)
    {
        $state = &$_SESSION['SyncML.state'];

        $status = &new SyncML_Command_Status(RESPONSE_OK, 'Results');
        $status->setCmdRef($this->_cmdID);

        $ref = ($state->getVersion() == 0) ? './devinf10' : './devinf11';
        $status->setSourceRef($ref);

        return $status->output($currentCmdID, $output);
    }

}
