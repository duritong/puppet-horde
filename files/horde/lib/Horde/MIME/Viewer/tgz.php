<?php
/**
 * The MIME_Viewer_tgz class renders out plain or gzipped tarballs in HTML.
 *
 * $Horde: framework/MIME/MIME/Viewer/tgz.php,v 1.37.10.14 2007/01/02 13:54:26 jan Exp $
 *
 * Copyright 1999-2007 Anil Madhavapeddy <anil@recoil.org>
 * Copyright 2002-2007 Michael Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anil Madhavapeddy <anil@recoil.org>
 * @author  Michael Cochrane <mike@graftonhall.co.nz>
 * @since   Horde 1.3
 * @package Horde_MIME_Viewer
 */
class MIME_Viewer_tgz extends MIME_Viewer {

    /**
     * Render out the currently set tar file contents.
     *
     * @param array $params  Any parameters the Viewer may need.
     *
     * @return string  The rendered contents.
     */
    function render($params = array())
    {
        require_once 'Horde/Compress.php';

        $contents = $this->mime_part->getContents();

        /* Only decompress gzipped files. */
        $subtype = $this->mime_part->getSubType();
        if (($subtype == 'x-compressed-tar') ||
            ($subtype == 'tgz') ||
            ($subtype == 'x-tgz') ||
            ($subtype == 'gzip') ||
            ($subtype == 'x-gzip') ||
            ($subtype == 'x-gzip-compressed') ||
            ($subtype == 'x-gtar')) {
            $gzip = &Horde_Compress::singleton('gzip');
            $contents = $gzip->decompress($contents);
            if (empty($contents)) {
                return '<pre>' . _("Unable to open compressed archive.") . '</pre>';
            } elseif (is_a($contents, 'PEAR_Error')) {
                return '<pre>' . $contents->getMessage() . '</pre>';
            }
        }

        if ($subtype == 'gzip' ||
            $subtype == 'x-gzip' ||
            $subtype == 'x-gzip-compressed') {
            global $conf;
            require_once 'Horde/MIME/Magic.php';
            $mime_type = MIME_Magic::analyzeData($contents, isset($conf['mime']['magic_db']) ? $conf['mime']['magic_db'] : null);
            if (!$mime_type) {
                $mime_type = _("Unknown");
            }
            return '<pre>' . _("Content type of compressed file: ") . $mime_type . '</pre>';
        }

        /* Obtain the list of files/data in the tar file. */
        $tar = &Horde_Compress::singleton('tar');

        $tarData = $tar->decompress($contents);
        if (is_a($tarData, 'PEAR_Error')) {
            return '<pre>' . $tarData->getMessage() . '</pre>';
        }

        $fileCount = count($tarData);

        include_once 'Horde/Text.php';

        $text  = '<strong>' . htmlspecialchars(sprintf(_("Contents of \"%s\""), $this->mime_part->getName())) . ':</strong>' . "\n";
        $text .= '<table><tr><td align="left"><tt><span class="fixed">';
        $text .= Text::htmlAllSpaces(_("Archive Name") . ':  ' . $this->mime_part->getName()) . "\n";
        $text .= Text::htmlAllSpaces(_("Archive File Size") . ': ' . strlen($contents) . ' bytes') . "\n";
        $text .= Text::htmlAllSpaces(($fileCount != 1) ? sprintf(_("File Count: %s files"), $fileCount) : sprintf(_("File Count: %s file"), $fileCount));
        $text .= "\n\n";
        $text .= Text::htmlAllSpaces(
                     str_pad(_("File Name"),     62, ' ', STR_PAD_RIGHT) .
                     str_pad(_("Attributes"),    15, ' ', STR_PAD_LEFT) .
                     str_pad(_("Size"),          10, ' ', STR_PAD_LEFT) .
                     str_pad(_("Modified Date"), 19, ' ', STR_PAD_LEFT)
                 ) . "\n";

        $text .= str_repeat('-', 106) . "\n";

        foreach ($tarData as $val) {
           $text .= Text::htmlAllSpaces(
                        str_pad($val['name'], 62, ' ', STR_PAD_RIGHT) .
                        str_pad($val['attr'], 15, ' ', STR_PAD_LEFT) .
                        str_pad($val['size'], 10, ' ', STR_PAD_LEFT) .
                        str_pad(strftime("%d-%b-%Y %H:%M", $val['date']), 19, ' ', STR_PAD_LEFT)
                    ) . "\n";
        }

        $text .= str_repeat('-', 106) . "\n";
        $text .= '</span></tt></td></tr></table>';

        return nl2br($text);
    }

    /**
     * Return the content-type
     *
     * @return string  The content-type of the output.
     */
    function getType()
    {
        return 'text/html; charset=' . NLS::getCharset();
    }

}
