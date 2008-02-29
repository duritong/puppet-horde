<?php
/**
 * The Horde_UI_Widget:: class provides base functionality for other Horde
 * UI elements.
 *
 * $Horde: framework/UI/UI/Widget.php,v 1.7.10.9 2007/01/02 13:54:46 jan Exp $
 *
 * Copyright 2003-2007 Jason M. Felice <jfelice@cronosys.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @since   Horde_UI 0.0.1
 * @package Horde_UI
 */
class Horde_UI_Widget {

    /**
     * Any variables that should be preserved in all of the widget's
     * links.
     *
     * @var array
     */
    var $_preserve = array();

    /**
     * The name of this widget.  This is used as the basename for variables
     * we access and manipulate.
     *
     * @var string
     */
    var $_name;

    /**
     * A reference to a Variables:: object this widget will use and
     * manipulate.
     *
     * @var Variables
     */
    var $_vars;

    /**
     * An array of name => value pairs which configure how this widget
     * behaves.
     *
     * @var array
     */
    var $_config;

    /**
     * Construct a new UI Widget interface.
     *
     * @param string $name      The name of the variable which will track this
     *                          UI widget's state.
     * @param Variables &$vars  A Variables:: object.
     * @param array $config     The widget's configuration.
     */
    function Horde_UI_Widget($name, &$vars, $config = array())
    {
        $this->_name = $name;
        $this->_vars = &$vars;
        $this->_config = $config;
    }

    /**
     * Instruct Horde_UI_Widget:: to preserve a variable.
     *
     * @param string $var   The name of the variable to preserve.
     * @param mixed $value  The value of the variable to preserve.
     */
    function preserve($var, $value)
    {
        $this->_preserve[$var] = $value;
    }

    /**
     * @access private
     */
    function _addPreserved($link)
    {
        foreach ($this->_preserve as $varName => $varValue) {
            $link = Util::addParameter($link, $varName, $varValue);
        }
        return $link;
    }

    /**
     * Render the widget.
     *
     * @abstract
     *
     * @param mixed $data  The widget's state data.
     */
    function render() {}

}
