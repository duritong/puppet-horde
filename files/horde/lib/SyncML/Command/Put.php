<?php

include_once 'SyncML/Command.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Put.php,v 1.12.10.8 2007/01/02 13:54:42 jan Exp $
 *
 * The SyncML_Command_Put:: class handles DevInf device information
 * sent by the client. The data is stored in a SyncML_DeviceInfo
 * object which is defined in Device.php and then stored in
 * SyncML_Device as an attribute.
 *
 * Copyright 2005-2007 Cuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @since   Horde 3.0
 * @package SyncML
 */
class SyncML_Command_Put extends SyncML_Command {

    var $_devinf;

    /**
     * Local vars to remember state.
     */
    var $_currentDS;
    var $_CTType;
    var $_VerCT;

    var $_currentPropName;
    var $_currentParamName;
    var $_currentCTType;

    var $_currentXNam;

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);
        switch (count($this->_Stack)) {
        case 1:
            $this->_devinf = &new SyncML_DeviceInfo();
            break;

        case 5:
            if ($element == 'DataStore') {
                $this->_currentDS = &new SyncML_DataStore();
            }
            break;
        }
    }

    function endElement($uri, $element)
    {
        global $backend;

        $di = &$this->_devinf;
        switch (count($this->_Stack)) {
        case 1:
            $_SESSION['SyncML.state']->setDeviceInfo($di);
            if (DEBUGLOG_DEVINF) {
                $fp = @fopen('/tmp/sync/devinf.txt', 'a');
                if ($fp) {
                    @fwrite($fp, var_export($di, true));
                    @fclose($fp);
                }
            }

        case 5:
            switch ($element) {
            case 'VerDTD':
                $di->_VerDTD = trim($this->_chars);
                break;

            case 'Man':
                $di->_Man = trim($this->_chars);
                break;

            case 'Mod':
                $di->_Mod = trim($this->_chars);
                break;

            case 'OEM':
                $di->_OEM = trim($this->_chars);
                break;

            case 'FwV':
                $di->_FwV = trim($this->_chars);
                break;

            case 'SwV':
                $di->_SwV = trim($this->_chars);
                break;

            case 'HwV':
                $di->_HwV = trim($this->_chars);
                break;

            case 'DevID':
                $di->_DevID = trim($this->_chars);
                break;

            case 'DevTyp':
                $di->_DevTyp = trim($this->_chars);
                break;

            case 'UTC':
                $di->_UTC = true;
                break;

            case 'SupportLargeObjs':
                $di->_SupportLargeObjs = true;
                break;

            case 'SupportNumberOfChanges':
                $di->_SupportNumberOfChanges = true;
                break;

            case 'DataStore':
                $di->_DataStore[] = $this->_currentDS;
                break;

            case 'CTCap':
            case 'Ext':
                // Automatically handled by subelements.
                break;
            }
            break;

        case 6:
            if ($this->_Stack[4] == 'DataStore') {
                switch ($element) {
                case 'SourceRef':
                    $this->_currentDS->_SourceRef = trim($this->_chars);
                    break;

                case 'DisplayName':
                    $this->_currentDS->_DisplayName = trim($this->_chars);
                    break;

                case 'MaxGUIDSize':
                    $this->_currentDS->_MaxGUIDSize = trim($this->_chars);
                    break;

                case 'DSMem':
                    // Currently ignored, to be done.
                    break;

                case 'SyncCap':
                    // Automatically handled by SyncType subelement.
                    break;

                case 'Rx-Pref':
                    $this->_currentDS->_Rx_Pref[$this->_CTType] = $this->_VerCT;
                    break;

                case 'Rx':
                    $this->_currentDS->_Rx[$this->_CTType] = $this->_VerCT;
                    break;

                case 'Tx-Pref':
                    $this->_currentDS->_Tx_Pref[$this->_CTType] = $this->_VerCT;
                    break;

                case 'Tx':
                    $this->_currentDS->_Tx[$this->_CTType] = $this->_VerCT;
                    break;
                }
            }

            if ($this->_Stack[4] == 'CTCap') {
                switch ($element) {
                case 'CTType':
                    $this->_currentCTType = trim($this->_chars);
                    break;

                case 'PropName':
                    $this->_currentPropName = trim($this->_chars);
                    // Reset param state.
                    unset($this->_currentParamName);
                    $di->_CTCap[$this->_currentCTType][$this->_currentPropName] = &new SyncML_Property();
                    break;

                case 'ParamName':
                    $this->_currentParamName = trim($this->_chars);
                    $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_params[$this->_currentParamName] = &new SyncML_PropertyParameter();
                    break;

                case 'ValEnum':
                    if (!empty($this->_currentParamName)) {
                        // We're in parameter mode.
                        $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_params[$this->_currentParamName]->_ValEnum[trim($this->_chars)] = true;
                    } else {
                        $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_ValEnum[trim($this->_chars)] = true;
                    }
                    break;

                case 'DataType':
                    if (!empty($this->_currentParamName)) {
                        // We're in parameter mode.
                        $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_params[$this->_currentParamName]->_DataType = trim($this->_chars);
                    } else {
                        $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_DataType = trim($this->_chars);
                    }
                    break;

                case 'Size':
                    if (!empty($this->_currentParamName)) {
                        // We're in parameter mode.
                        $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_params[$this->_currentParamName]->_Size = trim($this->_chars);
                    } else {
                        $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_Size = trim($this->_chars);
                    }
                    break;

                case 'DisplayName':
                    if (!empty($this->_currentParamName)) {
                        // We're in parameter mode.
                        $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_params[$this->_currentParamName]->_DisplayName = trim($this->_chars);
                    } else {
                        $di->_CTCap[$this->_currentCTType][$this->_currentPropName]->_DisplayName = trim($this->_chars);
                    }
                    break;
                }
            }

            if ($this->_Stack[4] == 'Ext' && $element == 'XNam') {
                $this->_currentXNam = trim($this->_chars);
            }
            if ($this->_Stack[4] == 'Ext' && $element == 'XVal') {
                $di->_Ext[$this->_currentXNam][] = trim($this->_chars);
            }
            break;

        case 7:
            if ($element == 'VerCT') {
                $this->_VerCT = trim($this->_chars);
            } elseif ($element == 'CTType') {
                $this->_CTType = trim($this->_chars);
            } elseif ($element == 'SyncType') {
                $this->_currentDS->_SyncCap[trim($this->_chars)] = true;
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function output($currentCmdID, &$output)
    {
        $state = &$_SESSION['SyncML.state'];

        $status = &new SyncML_Command_Status((($state->isAuthorized()) ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS), 'Put');
        $status->setCmdRef($this->_cmdID);

        $ref = ($state->getVersion() == 0) ? './devinf10' : './devinf11';

        $status->setSourceRef($ref);

        return $status->output($currentCmdID, $output);
    }

}
