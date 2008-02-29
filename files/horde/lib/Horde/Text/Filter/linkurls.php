<?php
/**
 * The Text_Filter_linkurls:: class turns all URLs in the text into hyperlinks.
 *
 * Parameters:
 * <pre>
 * target   -- The link target.  Defaults to _blank.
 * class    -- The CSS class of the generated links.  Defaults to none.
 * nofollow -- Whether to set the 'rel="nofollow"' attribute on links.
 * capital  -- generate uppercase <A> tags so you can know which tags you just
 *             generated.  Defaults to false.
 * callback -- An optional callback function that the URL is passed through
 *             before being set as the href attribute.  Must be a string with
 *             the function name, the function must take the original as the
 *             first and only parameter.
 * </pre>
 *
 * $Horde: framework/Text_Filter/Filter/linkurls.php,v 1.11.10.7 2007/01/02 13:54:43 jan Exp $
 *
 * Copyright 2003-2007 Tyler Colbert <tyler-hordeml@colberts.us>
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Tyler Colbert <tyler-hordeml@colberts.us>
 * @author  Jan Schneider <jan@horde.org>
 * @package Horde_Text
 */
class Text_Filter_linkurls extends Text_Filter {

    /**
     * Filter parameters.
     *
     * @var array
     */
    var $_params = array('target' => '_blank',
                         'class' => '',
                         'nofollow' => false,
                         'capital' => false,
                         'callback' => null);

    /**
     * Executes any code necessary before applying the filter patterns.
     *
     * @param string $text  The text before the filtering.
     *
     * @return string  The modified text.
     */
    function preProcess($text)
    {
        if ($this->_params['capital']) {
            $text = str_replace(array('</A>', '<A'), array('</a>', '<a'), $text);
        }

        return $text;
    }

    /**
     * Returns a hash with replace patterns.
     *
     * @return array  Patterns hash.
     */
    function getPatterns()
    {
        $a = $this->_params['capital'] ? 'A' : 'a';

        $class = $this->_params['class'];
        if (!empty($class)) {
            $class = ' class="' . $class . '"';
        }
        $nofollow = $this->_params['nofollow'] ? ' rel="nofollow"' : '';

        $url = $this->_params['callback'] ? '\' . ' . $this->_params['callback'] . '(\'$0\') . \'' : '$0';

        $regexp = array('|([\w+]+)://([^\s"<]*[\w+#?/&=])|e' =>
                        '\'<' . $a . ' href="' . $url . '"' . $nofollow . ' target="_blank"' . $class . '>$0</' . $a . '>\'');

        return array('regexp' => $regexp);
    }

}
