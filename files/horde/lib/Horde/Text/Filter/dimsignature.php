<?php
/**
 * Displays message signatures marked by a '-- ' in the style of the
 * CSS class "signature". Class names inside the signature are
 * prefixed with "signature-".
 *
 * $Horde: framework/Text_Filter/Filter/dimsignature.php,v 1.2.10.8 2007/01/02 13:54:43 jan Exp $
 *
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 3.0
 * @package Horde_Text
 */
class Text_Filter_dimsignature extends Text_Filter {

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
        $parts = preg_split('|(\n--\s*(?:<br />)?\r?\n)|', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $num_parts = count($parts);
        if ($num_parts > 2) {
            $text = implode('', array_slice($parts, 0, -2));
            $text .= '<span class="signature">' . $parts[$num_parts - 2];
            $text .= preg_replace('|class="([^"]+)"|', 'class="signature-\1"', $parts[$num_parts - 1]);
            $text .= '</span>';
        }

        return $text;
    }

}
