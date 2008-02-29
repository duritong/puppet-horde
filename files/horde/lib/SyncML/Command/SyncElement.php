<?php

include_once 'SyncML/Command.php';

/**
 * The class SyncML_Command_SyncElement stores information from the Add,
 * Delete and Replace elements found inside a sync command.
 *
 * $Horde: framework/SyncML/SyncML/Command/SyncElement.php,v 1.3.2.4 2007/01/02 13:54:42 jan Exp $
 *
 * Copyright 2005-2007 The horde project (www.horde.org)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @since   Horde 3.0
 * @package SyncML
 */
class SyncML_Command_SyncElement extends SyncML_Command {

    /**
     * Add, Delete or Replace.
     */
    var $_elementType;

    /**
     * Array of SyncML_SyncItem entries.
     */
    var $_items = array();

    /**
     * Mimetype for all items.
     */
    var $_contentType;

    /**
     * Temp data for creation of individual items.
     */
    var $_content;

    var $_cuid;

    /**
     * Mimetype for individual item.
     */
    var $_itemConentType;

    /**
     * Creates a Sync Element.
     *
     * @param string elementType either Add, Delete or Replace
     */
    function SyncML_Command_SyncElement($elementType)
    {
        $this->_elementType = $elementType;
    }

    function output($currentCmdID, &$output)
    {
        switch ($this->_elementType) {
        case 'Add':
            $response = RESPONSE_ITEM_ADDED;
            break;

        case 'Delete':
            $response = RESPONSE_OK;
            break;

        case 'Replace':
            $response = RESPONSE_OK;
            break;
        }

        $status = &new SyncML_Command_Status($response,
                                             $this->_elementType);
        $status->setCmdRef($this->_cmdID);

        return $status->output($currentCmdID, $output);
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);
        switch (count($this->_Stack)) {
        case 2:
            $this->_cuid = null;
            $this->_content = null;
            $this->_itemContentType = null;
            break;
        }
    }

    function endElement($uri, $element)
    {
        switch (count($this->_Stack)) {
        case 1:
            break;

        case 2:
            if ($element == 'Item') {
                if (empty($this->_itemContentType)
                    && !empty($this->_contentType)) {
                    $this->_itemContentType = $this->_contentType;
                }

                $this->_items[] = &new SyncML_SyncItem($this->_elementType,
                                                       $this->_content,
                                                       $this->_itemContentType,
                                                       $this->_cuid);
            }

        case 3:
            if ($element == 'Type') {
                $this->_contentType = trim($this->_chars);
            } elseif ($element == 'Data') {
                $this->_content = trim($this->_chars);
            }
            break;

        case 4:
            if ($element == 'LocURI') {
                if ($this->_Stack[2] == 'Source') {
                    $this->_cuid = trim($this->_chars);
                } elseif ($this->_Stack[2] == 'Target') {
                    // Not used: we ignore "suid proposals" from
                    // client.
                }
            }
            break;

        case 5:
            if ($element == 'Type') {
                $this->_itemContentType = trim($this->_chars);
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function getItems()
    {
        return $this->_items;
    }

    function getContentType()
    {
        return $this->_contentType;
    }

}

/**
 * The class SyncML_Command_SyncElement stores information about
 * the items inside a sync element (Add|Delete|Replace).
 *
 * A single SyncElement can contain multiple items.
 *
 * Instances of this class are created during the XML parsing by
 * SyncML_Command_SyncElement.
 *
 * @package SyncML
 */
class SyncML_SyncItem {

    /**
     * Add, Delete or Replace, inherited from
     * SyncML_Command_SyncElement.
     */
    var $_elementType;

    var $_cuid;

    /**
     * Optional, may be provided by parent element or even not at all.
     */
    var $_contentType;

    var $_content;

    function SyncML_SyncItem($elementType, $content = null,
                             $contentType = null, $cuid = null)
    {
        $this->_elementType = $elementType;
        $this->_cuid = $cuid;
        $this->_contentType = $contentType;
        $this->_content = $content;
    }

    function getCuid()
    {
        return $this->_cuid;
    }

    function getContent()
    {
        return $this->_content;
    }

    function getContentType()
    {
        return $this->_contentType;
    }

    function getElementType()
    {
        return $this->_elementType;
    }

}
