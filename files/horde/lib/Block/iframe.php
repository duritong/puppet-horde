<?php

$block_name = _("View an external web page");

/**
 * $Horde: horde/lib/Block/iframe.php,v 1.18.8.5 2006/12/08 21:51:36 jan Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_Horde_iframe extends Horde_Block {

    var $_app = 'horde';

    function _params()
    {
        return array('iframe' => array('type' => 'text',
                                       'name' => _("URL"),
                                       'default' => ''),
                     'title'  => array('type' => 'text',
                                       'name' => _("Title")),
                     'height' => array('type' => 'enum',
                                       'name' => _("Height"),
                                       'default' => '600',
                                       'values' => array('480' => _("Small"),
                                                         '600' => _("Medium"),
                                                         '768' => _("Large"),
                                                         '1024' => _("Extra Large"))));
    }

    /**
     * The title to go in this block.
     *
     * @return string   The title text.
     */
    function _title()
    {
        global $registry;

        $title = isset($this->_params['title'])
            ? $this->_params['title']
            : $this->_params['iframe'];

        $html = Horde::link($this->_params['iframe']) . $title .
            '</a> :: <small>' .
            Horde::link($this->_params['iframe'], '', '', '_blank') .
            Horde::img('website.png', _("Open in a new window")) . ' ' .
            _("Open in a new window") . '</a></small>';

        return $html;
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content
     */
    function _content()
    {
        global $browser;

        if (!$browser->hasFeature('iframes')) {
            return _("Your browser does not support this feature.");
        }

        if (empty($this->_params['height'])) {
            if ($browser->isBrowser('msie') || $browser->isBrowser('konqueror')) {
                $height = '';
            } else {
                $height = ' height="100%"';
            }
        } else {
            $height = ' height="' . htmlspecialchars($this->_params['height']) . '"';
        }
        return '<iframe src="' . htmlspecialchars($this->_params['iframe']) . '" width="100%"' . $height . ' marginheight="0" scrolling="auto" frameborder="0"></iframe>';
    }

}
