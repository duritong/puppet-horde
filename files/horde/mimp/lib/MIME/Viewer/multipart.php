<?php
/**
 * The MIMP_MIME_Viewer_multipart class handles multipart messages not
 * rendered by any specific MIME_Viewer.
 *
 * $Horde: mimp/lib/MIME/Viewer/multipart.php,v 1.13.2.1 2007/01/02 13:55:10 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package Horde_MIME_Viewer
 */
class MIMP_MIME_Viewer_multipart extends MIME_Viewer {

    /**
     * Render out the currently set contents.
     *
     * The $mime_part class variable has the information to render
     * out, encapsulated in a MIME_Part object.
     */
    function render($params)
    {
        foreach ($this->mime_part->getParts() as $part) {
            $params[0]->buildMessagePart($part);
        }
    }

    /**
     * Return the content-type.
     *
     * @return string  The content-type of the message.
     */
    function getType()
    {
        return 'text/html';
    }

}
