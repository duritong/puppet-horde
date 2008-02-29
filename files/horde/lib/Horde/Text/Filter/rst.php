<?php

require_once 'Text/reST.php';
require_once 'Text/reST/Formatter.php';

/**
 * The Text_Filter_rst:: class converts reStructuredText to HTML.
 *
 * $Horde: framework/Text_Filter/Filter/rst.php,v 1.9.10.5 2007/01/02 13:54:43 jan Exp $
 *
 * Copyright 2003-2007 Jason M. Felice <jfelice@cronosys.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jason M. Felice <jfelice@cronosys.com>
 * @package Horde_Text
 */
class Text_Filter_rst extends Text_Filter {

    /**
     * Executes any code necessaray after applying the filter
     * patterns.
     *
     * @param string $text  The text after the filtering.
     *
     * @return string  The modified text.
     */
    function postProcess($text)
    {
        $document = &Text_reST::parse($text);
        $formatter = &Text_reST_Formatter::factory('html');
        return $formatter->format($document, NLS::getCharset());
    }

}
