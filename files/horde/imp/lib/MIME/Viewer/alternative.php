<?php
/**
 * The IMP_MIME_Viewer_alternative class renders out messages from
 * multipart/alternative content types.
 *
 * $Horde: imp/lib/MIME/Viewer/alternative.php,v 1.45.10.8 2007/01/02 13:55:00 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 4.0
 * @package Horde_MIME_Viewer
 */
class IMP_MIME_Viewer_alternative extends MIME_Viewer {

    /**
     * The content-type of the preferred part.
     * Default: application/octet-stream
     *
     * @var string
     */
    var $_contentType = 'application/octet-stream';

    /**
     * The alternative ID for this part.
     *
     * @var string
     */
    var $_altID = '-';

    /**
     * Render out the currently set contents.
     *
     * @param array $params  An array with a reference to a MIME_Contents
     *                       object.
     *
     * @return string  The rendered text in HTML.
     */
    function render($params)
    {
        $contents = &$params[0];

        $display = array();
        $display_id = null;
        $partList = $this->mime_part->getParts();

        /* Default: Nothing displayed. */
        $text = null;

        /* We need to override the MIME key to ensure that only one
           alternative part is displayed. */
        $this->_getAltID($contents, $partList);

        /* Now we need to remove any multipart/mixed entries that may be
           present in the parts list, since they are simple containers
           for parts. */
        $partList = $this->_removeMultipartMixed($partList);

        /* RFC 2046: We show the LAST choice that can be displayed inline. */
        foreach ($partList as $part) {
            if ($contents->canDisplayInline($part)) {
                $display_list[] = $part;
             }
        }

        /* We need to set the summary here if we have a display part. */
        if (!empty($display_list)) {
            while (!empty($display_list)) {
                $display = array_pop($display_list);
                $text = $contents->renderMIMEPart($display);
                if (!empty($text)) {
                    $this->_contentType = $display->getType();
                    $contents->setSummary($display, 'part');
                    $display_id = $display->getMIMEId();
                    break;
                }
            }
        }

        /* Show links to alternative parts. */
        if (is_null($text) || (count($partList) > 1)) {
            if (is_null($text)) {
                $text = '<em>' . _("There are no alternative parts that can be displayed inline.") . '</em>';
            }

            /* Generate the list of summaries to use. */
            $summaryList = array();
            foreach ($partList as $part) {
                $id = $part->getMIMEId();
                if ($id && (is_null($display) || ($id != $display_id))) {
                    $summary = $contents->partSummary($part);

                    /* We don't want to show the MIME ID for alt parts. */
                    if (!empty($summary)) {
                        array_splice($summary, 1, 1);
                        $summaryList[] = $summary;
                    }
                }
            }

            /* Make sure there is at least one summary before showing the
               alternative parts. */
            if (!empty($summaryList) &&
                !$contents->viewAsAttachment() &&
                $this->getConfigParam('show')) {
                $status_array = array();
                $status = _("Alternative parts for this section:");
                if ($contents->showSummaryLinks()) {
                    $status .= '&nbsp;&nbsp;' . Help::link('imp', 'alternative-msg');
                }
                $status_array[] = $status;
                $status = '<table border="0" cellspacing="1" cellpadding="1">';
                foreach ($summaryList as $summary) {
                    $status .= '<tr valign="middle">';
                    foreach ($summary as $val) {
                        if (!empty($val)) {
                            $status .= "<td>$val&nbsp;</td>\n";
                        }
                    }
                    $status .= "</tr>\n";
                }
                $status .= '</table>';
                $status_array[] = $status;
                $text = $contents->formatStatusMsg($status_array, Horde::img('mime/binary.png', _("Multipart/alternative"), null, $GLOBALS['registry']->getImageDir('horde')), false) . $text;
            }
        }

        /* No longer force the alternative MIME ID for IMP_Contents methods. */
        if (!empty($this->_altID)) {
            $contents->setMIMEKeyOverride();
        }

        return $text;
     }

    /**
     * Determine the alternative ID
     *
     * @access private
     *
     * @param MIME_Contents &$contents  A MIME_Contents object.
     * @param array &$partList          The list of parts in this alternative
     *                                  section.
     */
    function _getAltID(&$contents, &$partList)
    {
        $altID = null;
        $override = $contents->getMIMEKeyOverride();

        if (is_null($override)) {
            $altID = $this->mime_part->getInformation('alternative');
            if ($altID === false) {
                foreach ($partList as $part) {
                    $altID = $part->getInformation('alternative');
                    if ($altID !== false) {
                        break;
                    }
                }
            }
        }

        if ($altID !== false) {
            $contents->setMIMEKeyOverride($altID);
            $this->_altID = $altID;
        }
    }

    /**
     * Remove multipart/mixed entries from an array of MIME_Parts and replace
     * with the contents of that part.
     *
     * @access private
     *
     * @param array $list  A list of MIME_Part objects.
     *
     * @return array  The list of objects with multipart/mixed parts removed.
     */
    function _removeMultipartMixed($list)
    {
        $output = array();

        foreach ($list as $part) {
            $output = array_merge($output, ($part->getType() == 'multipart/mixed') ? $this->_removeMultipartMixed($part->getParts()) : array($part));
        }

        return $output;
    }

    /**
     * Return the content-type.
     *
     * @return string  The content-type of the message.
     *                 Returns 'application/octet-stream' until actual
     *                 content type of the message can be determined.
     */
    function getType()
    {
        return $this->_contentType;
    }

}
