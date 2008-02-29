<?php

require_once 'Horde.php';

/**
 * Turn text into HTML with varying levels of parsing.  For no html
 * whatsoever, use htmlspecialchars() instead.
 *
 * Parameters:
 * <pre>
 * parselevel -- The parselevel of the output. See the list of constants below.
 * charset    -- The charset to use for htmlspecialchars() calls.
 * class      -- The CSS class name for the links.
 * nofollow   -- Whether to set the 'rel="nofollow"' attribute on links.
 * callback   -- An optional callback function that the URL is passed through
 *               before being set as the href attribute.  Must be a string with
 *               the function name, the function must take the original as the
 *               first and only parameter.
 * </pre>
 *
 * <pre>
 * List of valid constants for the parse level:
 * --------------------------------------------
 * TEXT_HTML_PASSTHRU        =  No action. Pass-through. Included for
 *                              completeness.
 * TEXT_HTML_SYNTAX          =  Allow full html, also do line-breaks,
 *                              in-lining, syntax-parsing.
 * TEXT_HTML_MICRO           =  Micro html (only line-breaks, in-line linking).
 * TEXT_HTML_MICRO_LINKURL   =  Micro html (only line-breaks, in-line linking
 *                              of URLSs; no email addresses are linked).
 * TEXT_HTML_NOHTML          =  No html (all stripped, only line-breaks)
 * TEXT_HTML_NOHTML_NOBREAK  =  No html whatsoever, no line breaks added.
 *                              Included for completeness.
 * </pre>
 *
 * $Horde: framework/Text_Filter/Filter/text2html.php,v 1.4.2.7 2007/01/02 13:54:43 jan Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jan Schneider <jan@horde.org>
 * @package Horde_Text
 */
class Text_Filter_text2html extends Text_Filter {

    /**
     * Filter parameters.
     *
     * @var array
     */
    var $_params = array('charset' => null,
                         'class' => 'fixed',
                         'nofollow' => false,
                         'callback' => 'Horde::externalUrl');

    /**
     * Executes any code necessary before applying the filter patterns.
     *
     * @param string $text  The text before the filtering.
     *
     * @return string  The modified text.
     */
    function preProcess($text)
    {
        if (is_null($this->_params['charset'])) {
            $this->_params['charset'] = isset($GLOBALS['_HORDE_STRING_CHARSET']) ? $GLOBALS['_HORDE_STRING_CHARSET'] : 'ISO-8859-1';
        }

        /* Abort out on simple cases. */
        if ($this->_params['parselevel'] == TEXT_HTML_PASSTHRU) {
            return $text;
        }
        if ($this->_params['parselevel'] == TEXT_HTML_NOHTML_NOBREAK) {
            return @htmlspecialchars($text, ENT_COMPAT, $this->_params['charset']);
        }

        /* Do in-lining of http://xxx.xxx to link, xxx@xxx.xxx to email, part
         * one. */
        if ($this->_params['parselevel'] < TEXT_HTML_NOHTML) {
            /* Make sure that the original message doesn't contain any capital
             * </A> tags or open <A> tags , so we can assume we generated
             * them. */
            $text = str_replace(array('</A>', '<A'), array('</a>', '<a'), $text);
            $filter_array = array('linkurls');
            $filter_params = array(array('callback' => $this->_params['callback'],
                                         'nofollow' => $this->_params['nofollow'],
                                         'capital' => true));
            if ($this->_params['parselevel'] < TEXT_HTML_MICRO_LINKURL) {
                $filter_array[] = 'emails';
                $filter_params[] = array('capital_tags' => true);
            }
            $text = Text_Filter::filter($text, $filter_array, $filter_params);
        }

        /* For level TEXT_HTML_MICRO, TEXT_HTML_NOHTML, start with
         * htmlspecialchars(). */
        $text = @htmlspecialchars($text, ENT_COMPAT, $this->_params['charset']);

        /* Do in-lining of http://xxx.xxx to link, xxx@xxx.xxx to email, part
         * two. */
        if ($this->_params['parselevel'] < TEXT_HTML_NOHTML) {
            $syntax = array(
                '&lt;A href=&quot;' => '<a' . (empty($this->_params['class']) ? '' : ' class="' . $this->_params['class'] . '"') . ' href="',
                '&quot; rel=&quot;' => '" rel="',
                '&quot; title=&quot;' => '" title="',
                '&quot; target=&quot;_blank&quot;&gt;'  => '" target="_blank">',
                '&quot; onclick=&quot;' => '" onclick="',
                '\');&quot;&gt;' => '\');">',
                '&quot;&gt;' =>  '">',
                /* Only reconvert capital /A tags - the ones we generated. */
                '&lt;/A&gt;' => '</a>'
                );

            $text = str_replace(array_keys($syntax), $syntax, $text);
            $text = Text_Filter::filter($text, 'space2html');
        }

        /* Do the blank-line ---> <br /> substitution.  Everybody gets this;
         * if you don't want even that, just save the htmlspecialchars()
         * version of the input. */
        $text = nl2br($text);

        return $text;
    }

}
