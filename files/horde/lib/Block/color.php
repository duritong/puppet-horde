<?php

// This block is an example and probably not useful to users.
// $block_name = _("Color");

/**
 * $Horde: horde/lib/Block/color.php,v 1.12 2004/06/01 10:05:31 jan Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_Horde_color extends Horde_Block {

    var $_app = 'horde';

    function _params()
    {
        return array('type' => 'text',
                     'name' => _("Color"),
                     'default' => '#ff0000');
    }

    /**
     * The title to go in this block.
     *
     * @return string   The title text.
     */
    function _title()
    {
        return _("Color");
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content
     */
    function _content()
    {
        $html  = '<table width="100" height="100" bgcolor="%s">';
        $html .= '<tr><td>&nbsp;</td></tr>';
        $html .= '</table>';

        return sprintf($html, $this->_params['color']);
    }

}
