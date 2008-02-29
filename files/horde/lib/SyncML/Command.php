<?php

include_once 'SyncML/State.php';

/**
 * The SyncML_Command class provides a super class fo SyncBody
 * commands.
 *
 * $Horde: framework/SyncML/SyncML/Command.php,v 1.4.10.8 2007/01/02 13:54:41 jan Exp $
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
class SyncML_Command {

    /**
     * @var integer
     */
    var $_cmdID;

    /**
     * Internal structure used during XML parsing.
     *
     * @var array
     */
    var $_Stack = array();

    /**
     * @var string
     */
    var $_chars;

    function &factory($command, $params = null)
    {
        include_once 'SyncML/Command/' . $command . '.php';
        $class = 'SyncML_Command_' . $command;
        if (class_exists($class)) {
            $cmd = &new $class($params);
        } else {
            $GLOBALS['backend']->logMessage('Class definition of ' . $class . ' not found.',
                                            __FILE__, __LINE__, PEAR_LOG_ERR);
            include_once 'PEAR.php';
            $cmd = PEAR::raiseError('Class definition of ' . $class . ' not found.');
        }

        return $cmd;
    }

    function output($currentCmdID, $output)
    {
    }

    function startElement($uri, $localName, $attrs)
    {
        $this->_Stack[] = $localName;
    }

    function endElement($uri, $element)
    {
        switch (count($this->_Stack)) {
        case 2:
            if ($element == 'CmdID') {
                $this->_cmdID = intval(trim($this->_chars));
            }
            break;
        }

        $this->_chars = '';
        array_pop($this->_Stack);
    }

    function characters($str)
    {
        $this->_chars .= $str;
    }

}
