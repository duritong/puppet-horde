<?php
/**
 * The Horde_Tree_javascript:: class extends the Horde_Tree class to provide
 * javascript specific rendering functions.
 *
 * Copyright 2003-2007 Marko Djukic <marko@oblo.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * $Horde: framework/Tree/Tree/javascript.php,v 1.34.2.10 2007/01/02 13:54:46 jan Exp $
 *
 * @author  Marko Djukic <marko@oblo.com>
 * @package Horde_Tree
 * @since   Horde 3.0
 */
class Horde_Tree_javascript extends Horde_Tree {

    /**
     * The name of the source for the tree data.
     *
     * @var string
     */
    var $_source_name = null;

    /**
     * The name of the target element to output the javascript tree.
     *
     * @var string
     */
    var $_options_name = null;

    /**
     * The name of the target element to output the javascript tree.
     *
     * @var string
     */
    var $_target_name = null;

    /**
     * Constructor
     */
    function Horde_Tree_javascript($tree_name, $params = array())
    {
        parent::Horde_Tree($tree_name, $params);

        if (!isset($GLOBALS['browser'])) {
            require_once 'Horde/Browser.php';
            $GLOBALS['browser'] = &Browser::singleton();
        }

        /* Check for a javascript session state. */
        if ($this->_usesession &&
            isset($_COOKIE[$this->_instance . '_expanded'])) {
            /* Remove "exp" prefix from cookie value. */
            $nodes = explode(',', substr($_COOKIE[$this->_instance . '_expanded'], 3));

            /* Make sure there are no previous nodes stored in the
             * session. */
            $_SESSION['horde_tree'][$this->_instance]['expanded'] = array();

            /* Save nodes to the session. */
            foreach ($nodes as $id) {
                $_SESSION['horde_tree'][$this->_instance]['expanded'][$id] = true;
            }
        }
    }

    /**
     * Returns the tree.
     *
     * @param boolean $static  If true the tree nodes can't be expanded and
     *                         collapsed and the tree gets rendered expanded.
     *
     * @return string  The HTML code of the rendered tree.
     */
    function getTree($static = false)
    {
        $this->_static = $static;
        $this->_source_name = 'n_' . $this->_instance;
        $this->_header_name = 'h_' . $this->_instance;
        $this->_options_name = 'o_' . $this->_instance;
        $this->_target_name = 't_' . $this->_instance;
        $this->_buildIndents($this->_root_nodes);

        $tree = $this->_getTreeSource() .
             '<div id="' . $this->_target_name . '"></div>' .
             $this->_getTreeInit();

        Horde::addScriptFile('tree.js', 'horde');
        return $tree;
    }

    /**
     * Check the current environment to see if we can render the HTML
     * tree. We check for DOM support in the browser.
     *
     * @static
     *
     * @return boolean  Whether or not this Tree:: backend will function.
     */
    function isSupported()
    {
        return $GLOBALS['browser']->hasFeature('dom');
    }

    /**
     * Returns just the JS node definitions as a string.
     *
     * @return string  The Javascript node array definitions.
     */
    function renderNodeDefinitions()
    {
        $this->_buildIndents($this->_root_nodes);

        $nodeJs = '';
        foreach ($this->_nodes as $node_id => $node) {
            $nodeJs .= $this->_getJsArrayElement(sprintf('%s[\'%s\']', 'n_' . $this->_instance, $node_id), $node);
        }

        return $nodeJs . $this->_instance . '.renderTree(root_nodes);';
    }

    /**
     * Outputs the data for the tree as a javascript array.
     *
     * @access private
     */
    function _getTreeSource()
    {
        global $browser;

        $js  = '<script type="text/javascript">' . "\n";
        $js .= 'var extraColsLeft = ' . $this->_extra_cols_left . ";\n";
        $js .= 'var extraColsRight = ' . $this->_extra_cols_right . ";\n";
        $js .= 'var ' . $this->_source_name . ' = new Array();' . "\n";

        foreach ($this->_nodes as $node_id => $node) {
            $js .= $this->_getJsArrayElement(sprintf('%s[\'%s\']', $this->_source_name,
                                                     $browser->escapeJSCode(addslashes($node_id))), $node);
        }
        $js .= $this->_getJsArrayElement($this->_header_name, $this->_header);
        $js .= $this->_getJsArrayElement($this->_options_name, $this->_options);
        $js .= '</script>' . "\n";

        return $js;
    }

    /**
     * Outputs the javascript to initialise the tree.
     *
     * @access private
     */
    function _getTreeInit()
    {
        $js  = '<script type="text/javascript">' . "\n";
        $js .= sprintf('%1$s = new Horde_Tree(\'%1$s\');' . "\n",
                       $this->_instance);

        $js .= $this->_getJsArrayElement('root_nodes', $this->_root_nodes);

        $js .= sprintf("%s.renderTree(root_nodes, %s);\n</script>\n",
                       $this->_instance,
                       $this->_static ? 'true' : 'false');

        return $js;
    }

    function _getJsArrayElement($var, $value)
    {
        if (is_array($value)) {
            $js = $var . ' = new Array();' . "\n";
            foreach ($value as $key => $val) {
                if (is_numeric($key)) {
                    $newVar = $var . '[' . $key . ']';
                } else {
                    $newVar = $var . '[\'' . $key . '\']';
                }
                $js .= $this->_getJsArrayElement($newVar, $val);
            }
            return $js;
        } else {
            return $var . " = '" . addslashes($value) . "';\n";
        }
    }

}
