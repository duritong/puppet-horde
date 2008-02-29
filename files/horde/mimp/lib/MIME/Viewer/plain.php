<?php
/**
 * The MIMP_MIME_Viewer_plain class renders out text/plain MIME parts.
 *
 * $Horde: mimp/lib/MIME/Viewer/plain.php,v 1.14.2.1 2007/01/02 13:55:10 jan Exp $
 *
 * Copyright 1999-2007 Anil Madhavapeddy <anil@recoil.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Anil Madhavapeddy <anil@recoil.org>
 * @package Horde_MIME_Viewer
 */
class MIMP_MIME_Viewer_plain extends MIME_Viewer {

    /**
     * Render out the currently set contents in HTML or plain text
     * format. The $mime_part class variable has the information to
     * render out, encapsulated in a MIME_Part object.
     */
    function render($params)
    {
        $contents = &$params[0];

        $text = $this->mime_part->getContents();

        if ($text === false) {
            return _("There was an error displaying this message part");
        }

        if (trim($text) == '') {
            return $text;
        }

        // Filter bad language.
        $text = MIMP::filterText($text);

        return $text;
    }

    /**
     * Return the content-type
     *
     * @return string  The content-type of the output.
     */
    function getType()
    {
        return $this->mime_part->getType(true);
    }

}
