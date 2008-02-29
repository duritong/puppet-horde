<?php
/**
 * The MIME_Viewer_zip class renders out the contents of ZIP files in HTML
 * format.
 *
 * $Horde: framework/MIME/MIME/Viewer/zip.php,v 1.30.10.11 2007/01/02 13:54:26 jan Exp $
 *
 * Copyright 2000-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2002-2007 Michael Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Cochrane <mike@graftonhall.co.nz>
 * @since   Horde 2.0
 * @package Horde_MIME_Viewer
 */
class MIME_Viewer_zip extends MIME_Viewer {

    /**
     * Render out the current zip contents.
     *
     * @param array $params  Any parameters the Viewer may need.
     *
     * @return string  The rendered contents.
     */
    function render($params = array())
    {
        return $this->_render($this->mime_part->getContents());
    }

    /**
     * Output the file list.
     *
     * @access private
     *
     * @param string $contents  The contents of the zip archive.
     * @param mixed $callback   The callback function to use on the zipfile
     *                          information.
     *
     * @return string  The file list.
     */
    function _render($contents, $callback = null)
    {
        require_once 'Horde/Compress.php';

        $zip = &Horde_Compress::singleton('zip');

        /* Make sure this is a valid zip file. */
        if ($zip->checkZipData($contents) === false) {
            return '<pre>' . _("This does not appear to be a valid zip file.") . '</pre>';
        }

        $zipInfo = $zip->decompress($contents, array('action' => HORDE_COMPRESS_ZIP_LIST));
        if (is_a($zipInfo, 'PEAR_Error')) {
            return $zipInfo->getMessage();
        }
        $fileCount = count($zipInfo);

        require_once 'Horde/Text.php';

        $text = '<strong>' . htmlspecialchars(sprintf(_("Contents of \"%s\""), $this->mime_part->getName())) . ':</strong>' . "\n";
        $text .= '<table><tr><td align="left"><tt><span class="fixed">';
        $text .= Text::htmlAllSpaces(_("Archive Name") . ': ' . $this->mime_part->getName()) . "\n";
        $text .= Text::htmlAllSpaces(_("Archive File Size") . ': ' . strlen($contents) . ' bytes') . "\n";
        $text .= Text::htmlAllSpaces(($fileCount != 1) ? sprintf(_("File Count: %s files"), $fileCount) : sprintf(_("File Count: %s file"), $fileCount));
        $text .= "\n\n";
        $text .= Text::htmlAllSpaces(
                     str_pad(_("File Name"),      50, ' ', STR_PAD_RIGHT) .
                     str_pad(_("Attributes"),     10, ' ', STR_PAD_LEFT) .
                     str_pad(_("Size"),           10, ' ', STR_PAD_LEFT) .
                     str_pad(_("Modified Date"),  19, ' ', STR_PAD_LEFT) .
                     str_pad(_("Method"),         10, ' ', STR_PAD_LEFT) .
                     str_pad(_("CRC"),            10, ' ', STR_PAD_LEFT) .
                     str_pad(_("Ratio"),           7, ' ', STR_PAD_LEFT)
                 ) . "\n";

        $text .= str_repeat('-', 116) . "\n";

        foreach ($zipInfo as $key => $val) {
            $ratio = (empty($val['size'])) ? 0 : 100 * ($val['csize'] / $val['size']);

            $val['name'] = str_pad($val['name'], 50, ' ', STR_PAD_RIGHT);
            $val['attr'] = str_pad($val['attr'], 10, ' ', STR_PAD_LEFT);
            $val['size'] = str_pad($val['size'], 10, ' ', STR_PAD_LEFT);
            $val['date'] = str_pad(strftime("%d-%b-%Y %H:%M", $val['date']), 19, ' ', STR_PAD_LEFT);
            $val['method'] = str_pad($val['method'], 10, ' ', STR_PAD_LEFT);
            $val['crc'] = str_pad($val['crc'], 10, ' ', STR_PAD_LEFT);
            $val['ratio'] = str_pad(sprintf("%1.1f%%", $ratio), 7, ' ', STR_PAD_LEFT);

            $val = array_map(array('Text', 'htmlAllSpaces'), $val);

            if (!is_null($callback)) {
                $val = call_user_func($callback, $key, $val);
            }

            $text .= $val['name'] . $val['attr'] . $val['size'] .
                     $val['date'] . $val['method'] . $val['crc'] .
                     $val['ratio'] . "\n";
        }

        $text .= str_repeat('-', 116) . "\n";
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
