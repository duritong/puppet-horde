<?php

require_once dirname(__FILE__) . '/source.php';

/**
 * The MIME_Viewer_php class renders out syntax-highlighted PHP code
 * in HTML format.
 *
 * $Horde: framework/MIME/MIME/Viewer/php.php,v 1.22.10.9 2007/01/02 13:54:25 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 1.3
 * @package Horde_MIME_Viewer
 */
class MIME_Viewer_php extends MIME_Viewer_source {

    /**
     * Renders out the contents.
     *
     * @param array $params  Any parameters the Viewer may need.
     *
     * @return string  The rendered contents.
     */
    function render($params = array())
    {
        $results = trim(str_replace(array("\n", '<br />'), array('', "\n"),
                                    highlight_string($this->mime_part->getContents(), true)));

        // Educated Guess at whether we are inline or not.
        if (headers_sent() || ob_get_length()) {
            return $this->lineNumber($results);
        } else {
            return Util::bufferOutput('require', $GLOBALS['registry']->get('templates', 'horde') . '/common-header.inc') .
                $this->lineNumber($results) .
                Util::bufferOutput('require', $GLOBALS['registry']->get('templates', 'horde') . '/common-footer.inc');
        }
    }

}
