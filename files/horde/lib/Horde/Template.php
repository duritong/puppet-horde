<?php
/**
 * Horde Template system. Adapted from bTemplate by Brian Lozier
 * <brian@massassi.net>.
 *
 * $Horde: framework/Template/Template.php,v 1.38.10.11 2007/01/02 13:54:43 jan Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 3.0
 * @package Horde_Template
 */
class Horde_Template {

    /**
     * Option values.
     *
     * @var array
     */
    var $_options = array();

    /**
     * Directory that templates should be read from.
     *
     * @var string
     */
    var $_basepath = '';

    /**
     * Reset template variables after parsing?
     *
     * @var boolean
     */
    var $_resetVars = true;

    /**
     * Tag (scalar) values.
     *
     * @var array
     */
    var $_scalars = array();

    /**
     * Loop tag values.
     *
     * @var array
     */
    var $_arrays = array();

    /**
     * Cloop tag values.
     *
     * @var array
     */
    var $_carrays = array();

    /**
     * If tag values.
     *
     * @var array
     */
    var $_ifs = array();

    /**
     * Name of cached template file.
     *
     * @var string
     */
    var $_templateFile = null;

    /**
     * Cached source of template file.
     *
     * @var string
     */
    var $_template = null;

    /**
     * Constructor. Can set the template base path and whether or not
     * to drop template variables after a parsing a template.
     *
     * @param string  $basepath   The directory where templates are read from.
     * @param boolean $resetVars  Drop template vars after parsing a template?
     */
    function Horde_Template($basepath = null, $resetVars = true)
    {
        if (!is_null($basepath)) {
            $this->_basepath = $basepath;
        }
        $this->_resetVars = (bool)$resetVars;
    }

    /**
     * Sets an option.
     *
     * @param string $option  The option name.
     * @param mixed  $val     The option's value.
     */
    function setOption($option, $val)
    {
        $this->_options[$option] = $val;
    }

    /**
     * Set the template contents to a string.
     *
     * @param string $template  The template text.
     */
    function setTemplate($template)
    {
        $this->_template = $template;
        $this->_templateFile = 'string';
    }

    /**
     * Returns an option's value.
     *
     * @param string $option  The option name.
     *
     * @return mixed  The option's value.
     */
    function getOption($option)
    {
        return isset($this->_options[$option]) ? $this->_options[$option] : null;
    }

    /**
     * Sets a tag, loop, or if variable.
     *
     * @param string|array $tag   Either the tag name or a hash with tag names
     *                            as keys and tag values as values.
     * @param mixed        $var   The value to replace the tag with.
     * @param boolean      $isIf  Is this for an <if:> tag? (Default: no).
     */
    function set($tag, $var, $isIf = false)
    {
        if (is_array($tag)) {
            foreach ($tag as $tTag => $tVar) {
                $this->set($tTag, $tVar, $isIf);
            }
        } elseif (is_array($var) || is_object($var)) {
            $this->_arrays[$tag] = $var;
            if ($isIf) {
                // Just store the same variable that we stored in
                // $this->_arrays - if we don't modify it, PHP's
                // reference counting ensures we're not using any
                // additional memory here.
                $this->_ifs[$tag] = $var;
            }
        } else {
            $this->_scalars[$tag] = $var;
            if ($isIf) {
                // Just store the same variable that we stored in
                // $this->_scalars - if we don't modify it, PHP's
                // reference counting ensures we're not using any
                // additional memory here.
                $this->_ifs[$tag] = $var;
            }
        }
    }

    /**
     * Returns the value of a tag or loop.
     *
     * @param string $tag  The tag name.
     *
     * @return mixed  The tag value or null if the tag hasn't been set yet.
     */
    function get($tag)
    {
        if (isset($this->_arrays[$tag])) {
            return $this->_arrays[$tag];
        }
        if (isset($this->_scalars[$tag])) {
            return $this->_scalars[$tag];
        }
        return null;
    }

    /**
     * Sets values for a cloop.
     *
     * @param string $tag   The name of the cloop.
     * @param array $array  The values for the cloop.
     * @param array $cases  The cases (test values) for the cloops.
     */
    function setCloop($tag, $array, $cases)
    {
        $this->_carrays[$tag] = array(
            'array' => $array,
            'cases' => $cases
        );
    }

    /**
     * Resets the template variables.
     *
     * @param boolean $scalars  Reset scalar (basic tag) variables?
     * @param boolean $arrays   Reset loop variables?
     * @param boolean $carrays  Reset cloop variables?
     * @param boolean $ifs      Reset if variables?
     */
    function resetVars($scalars, $arrays, $carrays, $ifs)
    {
        if ($scalars === true) {
            $this->_scalars = array();
        }
        if ($arrays === true) {
            $this->_arrays = array();
        }
        if ($carrays === true) {
            $this->_carrays = array();
        }
        if ($ifs === true) {
            $this->_ifs = array();
        }
    }

    /**
     * Retrieve list of blocks present in the template source.
     *
     * @param string $filename  The file to fetch the template from.
     * @return mixed  An array of applications, or PEAR_Error on failure.
     */
    function listBlocks()
    {
        $blocks = array();
        if (preg_match_all('<block:([a-zA-Z0-9_]+:[a-zA-Z0-9_]+)>',
                           $this->_template, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $blocks[] = $match[1];
            }
        }

        return $blocks;
    }

    /**
     * Fetches a template from the specified file and return the parsed
     * contents.
     *
     * @param string $filename  The file to fetch the template from.
     *
     * @return string  The parsed template.
     */
    function fetch($filename)
    {
        $contents = $this->_getTemplate($filename);
        if (is_a($contents, 'PEAR_Error')) {
            return $contents;
        }

        // Parse and return the contents.
        return $this->parse($contents);
    }

    /**
     * Parses all variables/tags in the template.
     *
     * @param string $contents  The unparsed template.
     *
     * @return string  The parsed template.
     */
    function parse($contents = null)
    {
        if (!$contents) {
            $contents = $this->_template;
        }

        // Process ifs.
        if (!empty($this->_ifs)) {
            foreach ($this->_ifs as $tag => $value) {
                $contents = $this->_parseIf($tag, $contents);
            }
        }

        // Process tags.
        $search = array();
        $replace = array();
        foreach ($this->_scalars as $key => $value) {
            $search[] = $this->_getTag($key);
            $replace[] = $value;
        }
        if (count($search)) {
            $contents = str_replace($search, $replace, $contents);
        }

        // Process loops and arrays.
        foreach ($this->_arrays as $key => $array) {
            $contents = $this->_parseLoop($key, $array, $contents);
        }

        // Process cloops.
        foreach ($this->_carrays as $key => $array) {
            $contents = $this->_parseCloop($key, $array, $contents);
        }

        // Parse gettext tags, if the option is enabled.
        if ($this->getOption('gettext')) {
            $contents = $this->_parseGettext($contents);
        }

        // Parse embedded blocks
        if ($this->getOption('blocks')) {
            $contents = $this->_parseBlocks($contents);
        }

        // Reset template data unless we're supposed to keep it
        // around.
        if ($this->_resetVars) {
            $this->resetVars(false, true, true, false);
        }

        // Return parsed template.
        return $contents;
    }

    /**
     * Returns full start and end tags for a named tag.
     *
     * @access private
     *
     * @param string $tag
     * @param string $directive  The kind of tag - tag, if, loop, cloop.
     *
     * @return array  'b' => Start tag.
     *                'e' => End tag.
     */
    function _getTags($tag, $directive)
    {
        return array('b' => '<' . $directive . ':' . $tag . '>',
                     'e' => '</' . $directive . ':' . $tag . '>');
    }

    /**
     * Formats a scalar tag (default format is <tag:name>).
     *
     * @access private
     *
     * @param string $tag  The name of the tag.
     *
     * @return string  The full tag with the current start/end delimiters.
     */
    function _getTag($tag)
    {
        return '<tag:' . $tag . ' />';
    }

    /**
     * Extracts a portion of a template.
     *
     * @access private
     *
     * @param array $t           The tag to extract. Hash format is:
     *                             $t['b'] - The start tag
     *                             $t['e'] - The end tag
     * @param string &$contents  The template to extract from.
     */
    function _getStatement($t, &$contents)
    {
        // Locate the statement.
        $pos = strpos($contents, $t['b']);
        if ($pos === false) {
            return false;
        }

        $tag_length = strlen($t['b']);
        $fpos = $pos + $tag_length;
        $lpos = strpos($contents, $t['e']);
        $length = $lpos - $fpos;

        // Extract & return the statement.
        return substr($contents, $fpos, $length);
    }

    /**
     * Parses gettext tags.
     *
     * @param string $contents  The unparsed content of the file.
     *
     * @return string  The parsed contents of the gettext blocks.
     */
    function _parseGettext($contents)
    {
        // Get the tags & loop.
        $t = array('b' => '<gettext>',
                   'e' => '</gettext>');
        while ($text = $this->_getStatement($t, $contents)) {
            $contents = str_replace($t['b'] . $text . $t['e'], _($text), $contents);
        }
        return $contents;
    }

    /**
     * Parses block tags.
     *
     * Here we parse block tags in the form:
     *
     *  <block:app:name>
     *   <param:foo>foo parameter's value</param:foo>
     *   <param:bar>bar parameter's value</param:bar>
     *   <serial:baz>s:21:"baz parameter's value";</serial:baz>
     *  </block:app:name>
     *
     * ... and replace them with rendered application blocks.
     *
     * Block parameters are provided with the nested "param:foo" and
     * "serial:foo" tags.  The serial-style tags take a serialized PHP
     * value, so you can use it to provide array, boolean, and complex
     * type parameters.  The param-style tags provide string parameters.
     *
     * @access private
     *
     * @param string $contents  The unparsed contents of the file.
     *
     * @return string  The parsed contents of the file.
     */
    function _parseBlocks($contents)
    {
        return preg_replace('!<block:([a-zA-Z_0-9]+):([a-zA-Z_0-9]+)>(.*?)' .
                            '</block:([a-zA-Z_0-9]+):([a-zA-Z_0-9]+)>!se',
                            "\$this->_parseBlock('\\1', '\\2', '\\3')",
                            $contents);
    }

    /**
     * Handle the contents of one block tag.
     *
     * @access private
     */
    function _parseBlock($app, $name, $contents)
    {
        require_once 'Horde/Block.php';
        require_once 'Horde/Block/Collection.php';

        // Undo preg_replace() encoding strangeness.
        $contents = str_replace('\\"', '"', $contents);

        // Find block's parameters.
        $params = array();
        if (preg_match_all('!<param:([a-zA-Z_0-9]+)>(.*?)' .
                           '</param:([a-zA-Z_0-9]+)>!s', $contents, $matches,
                           PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[1]] = html_entity_decode($match[2]);
            }
        }
        if (preg_match_all('!<serial:([a-zA-Z_0-9]+)>(.*?)' .
                           '</serial:([a-zA-Z_0-9]+)>!s', $contents, $matches,
                           PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[1]] = unserialize(html_entity_decode($match[2]));
            }
        }

        // Construct the block.
        $block = &Horde_Block_Collection::getBlock($app, $name, $params);
        if (is_a($block, 'PEAR_Error')) {
            $result = '<strong>' . sprintf(_("Error creating block: %s"),
                                      $block->getMessage()) . '</strong>';
        } else {
            $result = $block->getContent();
            if (is_a($result, 'PEAR_Error')) {
                $result = '<strong>' . sprintf(_("Error getting content: %s"),
                                          $result->getMessage()) . '</strong>';
            }
        }

        return $result;
    }

    /**
     * Parses a given if statement.
     *
     * @access private
     *
     * @param string $tag       The name of the if block to parse.
     * @param string $contents  The unparsed contents of the if block.
     *
     * @return string  The parsed contents of the if block.
     */
    function _parseIf($tag, $contents, $key = null)
    {
        // Get the tags & if statement.
        $t = $this->_getTags($tag, 'if');
        $et = $this->_getTags($tag, 'else');

        // explode the tag, so we have the correct keys for the array
        if (isset($key)) {
            list($tg, $k) = explode('.', $tag);
        }
        while (($if = $this->_getStatement($t, $contents)) !== false) {
            // Check for else statement.
            if ($else = $this->_getStatement($et, $if)) {
                // Process the if statement.
                if ((isset($key) && $this->_ifs[$tg][$key][$k]) ||
                    (isset($this->_ifs[$tag]) && $this->_ifs[$tag])) {
                    $replace = str_replace($et['b'] . $else . $et['e'], '', $if);
                } else {
                    $replace = $else;
                }
            } else {
                // Process the if statement.
                if (isset($key)) {
                    $replace = $this->_ifs[$tg][$key][$k] ? $if : null;
                } else {
                    $replace = $this->_ifs[$tag] ? $if : null;
                }
            }

            // Parse the template.
            $contents = str_replace($t['b'] . $if . $t['e'], $replace,
                                    $contents);
        }

        // Return parsed template.
        return $contents;
    }

    /**
     * Parses the given array for any loops or other uses of the array.
     *
     * @access private
     *
     * @param string $tag       The name of the loop to parse.
     * @param array  $array     The values for the loop.
     * @param string $contents  The unparsed contents of the loop.
     *
     * @return string  The parsed contents of the loop.
     */
    function _parseLoop($tag, $array, $contents)
    {
        // Get the tags & loop.
        $t = $this->_getTags($tag, 'loop');
        $loop = $this->_getStatement($t, $contents);

        // See if we have a divider.
        $l = $this->_getTags($tag, 'divider');
        $divider = $this->_getStatement($l, $loop);
        $contents = str_replace($l['b'] . $divider . $l['e'], '', $contents);

        // Process the array.
        do {
            $parsed = '';
            $first = true;
            foreach ($array as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $i = $loop;
                    if (is_numeric($key)) {
                        foreach ($value as $key2 => $value2) {
                            if (!is_array($value2) && !is_object($value2)) {
                                // Replace associative array tags.
                                $aa_tag = $tag . '.' . $key2;
                                $i = str_replace($this->_getTag($aa_tag), $value2, $i);
                                $pos = strpos($tag, '.');
                                if (($pos !== false) &&
                                    !empty($this->_ifs[substr($tag, 0, $pos)])) {
                                    $this->_ifs[$aa_tag] = $value2;
                                    $i = $this->_parseIf($aa_tag, $i);
                                    unset($this->_ifs[$aa_tag]);
                                }
                            } else {
                                // Check to see if it's a nested loop.
                                $i = $this->_parseLoop($tag . '.' . $key2, $value2, $i);
                            }
                        }
                    }
                    $i = str_replace($this->_getTag($tag), $key, $i);
                } elseif (is_string($key) && !is_array($value) && !is_object($value)) {
                    $contents = str_replace($this->_getTag($tag . '.' . $key), $value, $contents);
                } elseif (!is_array($value) && !is_object($value)) {
                    $i = str_replace($this->_getTag($tag . ''), $value, $loop);
                } else {
                    $i = null;
                }

                // Parse conditions in the array.
                if (!empty($this->_ifs[$tag][$key]) && is_array($this->_ifs[$tag][$key]) && $this->_ifs[$tag][$key]) {
                    foreach ($this->_ifs[$tag][$key] as $cTag => $cValue) {
                        $i = $this->_parseIf($tag . '.' . $cTag, $i, $key);
                    }
                }

                // Add the parsed iteration.
                if (isset($i)) {
                    // If it's not the first time through, prefix the
                    // loop divider, if there is one.
                    if (!$first) {
                        $i = $divider . $i;
                    }
                    $parsed .= rtrim($i);
                }

                // No longer the first time through.
                $first = false;
            }

            // Replace the parsed pieces of the template.
            $contents = str_replace($t['b'] . $loop . $t['e'], $parsed, $contents);
        } while ($loop = $this->_getStatement($t, $contents));

        return $contents;
    }

    /**
     * Parses the given case loop (cloop).
     *
     * @access private
     *
     * @param string $tag       The name of the cloop to parse.
     * @param array  $array     The values for the cloop.
     * @param string $contents  The unparsed contents of the cloop.
     *
     * @return string  The parsed contents of the cloop.
     */
    function _parseCloop($tag, $array, $contents)
    {
        // Get the tags & cloop.
        $t = $this->_getTags($tag, 'cloop');

        while ($loop = $this->_getStatement($t, $contents)) {
            // Set up the cases.
            $array['cases'][] = 'default';
            $case_content = array();

            // Get the case strings.
            foreach ($array['cases'] as $case) {
                $ctags[$case] = $this->_getTags($case, 'case');
                $case_content[$case] = $this->_getStatement($ctags[$case], $loop);
            }

            // Process the cloop.
            $parsed = '';
            foreach ($array['array'] as $key => $value) {
                if (is_numeric($key) && (is_array($value) || is_object($value))) {
                    // Set up the cases.
                    if (isset($value['case'])) {
                        $current_case = $value['case'];
                    } else {
                        $current_case = 'default';
                    }
                    unset($value['case']);
                    $i = $case_content[$current_case];

                    // Loop through each value.
                    foreach ($value as $key2 => $value2) {
                        if (is_array($value2) || is_object($value2)) {
                            $i = $this->_parseLoop($tag . '.' . $key2, $value2, $i);
                        } else {
                            $i = str_replace($this->_getTag($tag . '.' . $key2), $value2, $i);
                        }
                    }
                }

                // Add the parsed iteration.
                $parsed .= rtrim($i);
            }

            // Parse the cloop.
            $contents = str_replace($t['b'] . $loop . $t['e'], $parsed, $contents);
        }

        return $contents;
    }

    /**
     * Fetch the contents of a template into $this->_template; cache
     * the filename in $this->_templateFile.
     *
     * @access private
     *
     * @param string $filename  Location of template file on disk.
     *
     * @return string  The loaded template content.
     */
    function _getTemplate($filename = null)
    {
        if (!is_null($filename) && ($filename != $this->_templateFile)) {
            $this->_template = null;
        }

        if (!is_null($this->_template)) {
            return $this->_template;
        }

        // Get the contents of the file.
        $file = $this->_basepath . $filename;
        $contents = @file_get_contents($file);
        if ($contents === false) {
            require_once 'PEAR.php';
            return PEAR::raiseError(sprintf(_("Template \"%s\" not found."), $file));
        }

        $this->_template = $contents;
        $this->_templateFile = $filename;

        return $this->_template;
    }

}
