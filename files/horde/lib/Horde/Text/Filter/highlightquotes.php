<?php

require_once 'Horde.php';
require_once 'Horde/String.php';

/**
 * Highlights quoted messages with different colors for the different quoting
 * levels.
 *
 * CSS class names called "quoted1" ... "quoted{$cssLevels}" must be present.
 * The text to be passed in must have already been passed through
 * htmlspecialchars().
 *
 * Parameters:
 * <pre>
 * 'citeblock'  -- Display cite blocks? Defaults to true.
 * 'cssLevels'  -- Number of defined CSS class names. Defaults to 5.
 * 'hideBlocks' -- Hide quoted text blocks by default? Defaults to false.
 * </pre>
 *
 * $Horde: framework/Text_Filter/Filter/highlightquotes.php,v 1.6.8.17 2007/01/02 13:54:43 jan Exp $
 *
 * Copyright 2004-2007 Michael Slusarz <slusarz@curecanti.org>
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Michael Slusarz <slusarz@curecanti.org>
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 3.0
 * @package Horde_Text
 */
class Text_Filter_highlightquotes extends Text_Filter {

    /**
     * Filter parameters.
     *
     * @var array
     */
    var $_params = array(
        'citeblock' => true,
        'cssLevels' => 5,
        'hideBlocks' => false
    );

    /**
     * Executes any code necessaray before applying the filter patterns.
     *
     * @param string $text  The text before the filtering.
     *
     * @return string  The modified text.
     */
    function preProcess($text)
    {
        /* Tack a newline onto the beginning of the string so that we
         * correctly highlight when the first character in the string is a
         * quote character. */
        return "\n$text";
    }

    /**
     * Returns a hash with replace patterns.
     *
     * @return array  Patterns hash.
     */
    function getPatterns()
    {
        /* Remove extra spaces before quoted text as the CSS formatting will
         * automatically add a bit of space for us. */
        if ($this->_params['citeblock']) {
            return array('regexp' => array("/<br \/>\s*\n\s*<br \/>\s*\n\s*((&gt;\s?)+)/m" => "<br />\n\\1"));
        }

        return array();
    }

    /**
     * Executes any code necessaray after applying the filter patterns.
     *
     * @param string $text  The text after the filtering.
     *
     * @return string  The modified text.
     */
    function postProcess($text)
    {
        /* Is there a blank line(s) before the current line? */
        $blankline = false;

        /* Use cite blocks to display the different quoting levels? */
        $cb = $this->_params['citeblock'];

        /* Cite level before parsing the current line. */
        $qlevel = 0;

        /* Generated output. */
        $text_out = '';

        /* Add show/hide toggles for whole quote blocks. */
        $text = preg_replace_callback('/(\n)(( *(&gt;\s?)+(?! ?&gt;).*?)(\n|$)(?! *(&gt; ?)+))/s',
                                      array($this, '_addQuoteToggles'), $text);

        /* Parse text line by line. */
        foreach (explode("\n", $text) as $line) {
            /* Cite level of current line. */
            $clevel = $closelevel = 0;
            $matches = array();
            /* Do we have a citation line? */
            if (preg_match('/^\s*((&gt;\s?)+)/m', $line, $matches)) {
                /* Count number of > characters => cite level */
                $clevel = $closelevel = count(preg_split('/&gt;\s?/', $matches[1])) - 1;
            }

            if ($cb && isset($matches[1])) {
                /* Strip all > characters. */
                $line = substr($line, String::length($matches[1]));
            }

            /* Is this cite level lower than the current level? */
            if ($clevel < $qlevel) {
                /* Strip more than one blank line in front of the cite
                 * blocks. */
                if ($blankline) {
                    while (($br_str = substr($text_out, -7)) == "<br />\n") {
                        $text_out = substr($text_out, 0, -7);
                    };
                }
                /* Add quote block end tags for each cite level. */
                for ($i = $clevel; $i < $qlevel; $i++) {
                    $text_out .= ($cb) ? '</div>' : '</font>';
                    --$closelevel;
                }
                /* Strip trailing line break if using cite blocks, we already
                 * have a div tag. */
                if ($cb && preg_match('/\<br \/>$/', $line)) {
                    $line = substr($line, 0, -6);
                }
            /* Is this cite level higher than the current level? */
            } elseif ($clevel > $qlevel) {
                /* Strip more than one blank line in front of the cite
                 * blocks. */
                if ($blankline) {
                    while (($br_str = substr($text_out, -7)) == "<br />\n") {
                        $text_out = substr($text_out, 0, -7);
                    };
                }
                for ($i = $qlevel; $i < $clevel; $i++) {
                    /* Add quote block start tags for each cite level. */
                    $text_out .= ($cb) ? '<div class="citation ' : '<font class="';
                    $text_out .= 'quoted' . (($i % $this->_params['cssLevels']) + 1) . '">';
                }
            }

            /* Count blank lines. */
            $blankline = ($line == '<br />');

            $text_out .= $line . "\n";
            $qlevel = $clevel;
        }

        /* Make sure all div/font tags are closed. */
        if ($closelevel > 0) {
            $text_out = preg_replace('/<\/div>$/', str_repeat(($cb) ? '</div>' : '</font>', $closelevel) . '</div>', $text_out);
        }

        /* Remove the leading newline we added above, if it's still there. */
        if ($text_out[0] == "\n") {
            $text_out = substr($text_out, 1);
        }

        return $text_out;
    }

    /**
     * Adds links to show and hide quoted blocks, hiding them by default if
     * the 'hideBlocks' parameter is true.
     *
     * @access private
     *
     * @param array $matches  The matches from the regexp.
     */
    function _addQuoteToggles($matches)
    {
        static $i = 0;

        /* Don't toggle small blocks; doesn't provide a UI benefit and looks
         * annoying. */
        $lines = substr_count($matches[0], "\n");
        if ($lines < 8) {
            return $matches[0];
        }

        Horde::addScriptFile('hideable.js', 'horde', true);
        Horde::addScriptFile('quoteBlocks.js', 'horde');

        $text = '';
        if ($this->_params['citeblock']) {
            $text = '<br />';
        };
        $text .= '<div id="qt_' . $i . '">' .
            Horde::link('#', '', 'widget togglequote', '', 'toggleQuoteBlock(\'' . $i . '\', \'' . $lines . '\'); return false;', '', '', array('style' => 'font-size:70%')) .
            ($this->_params['hideBlocks'] ? sprintf(_("[Show Quoted Text - %s lines]"), $lines) : _("[Hide Quoted Text]")) .
            '</a></div><div id="qb_' . $i . '"' . ($this->_params['hideBlocks'] ? ' style="display:none;"' : '') . '>' . $matches[0] . '</div>';

        $i++;

        return $text;
    }

}
