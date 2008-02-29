<?php
/**
 * The MIME_Viewer_pdf class simply outputs the PDF file with the content-type
 * 'application/pdf' enabling web browsers with a PDF viewer plugin to view
 * the PDF file inside the browser.
 *
 * $Horde: framework/MIME/MIME/Viewer/pdf.php,v 1.3.12.6 2007/01/02 13:54:25 jan Exp $
 *
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Horde 3.0
 * @package Horde_MIME_Viewer
 */
class MIME_Viewer_pdf extends MIME_Viewer {

    /**
     * Return the content-type.
     *
     * @return string  The content-type of the output.
     */
    function getType()
    {
        return 'application/pdf';
    }

}
