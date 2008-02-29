<?php

$block_name = _("Current Time");

/**
 * $Horde: horde/lib/Block/time.php,v 1.16 2004/11/23 15:17:53 chuck Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_Horde_time extends Horde_Block {

    var $_app = 'horde';

    function _params()
    {
        return $params = array('time' => array('type' => 'enum',
                                               'name' => _("Time format"),
                                               'default' => '24-hour',
                                               'values' => array('24-hour' => _("24 Hour Format"),
                                                                 '12-hour' => _("12 Hour Format"))));
    }

    /**
     * The title to go in this block.
     *
     * @return string   The title text.
     */
    function _title()
    {
        return _("Current Time");
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content
     */
    function _content()
    {
        if (empty($this->_params['time'])) {
            $this->_params['time'] = '24-hour';
        }

        // Set the timezone variable, if available.
        NLS::setTimeZone();

        $html = '<table width="100%" height="100%"><tr><td style="font-family:verdana;font-size:18px;" align="center" valign="middle">';
        $html .= strftime('%A, %B %d, %Y ');
        if ($this->_params['time'] == '24-hour') {
            $html .= strftime('%H:%M');
        } else {
            $html .= strftime('%I:%M %p');
        }
        $html .= '</td></tr></table>';

        return $html;
    }

}
