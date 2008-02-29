<?php

require_once 'Horde/MIME/Viewer/html.php';

/**
 * The MIME_Viewer_html class renders out HTML text with an effort to
 * remove potentially malicious code.
 *
 * $Horde: imp/lib/MIME/Viewer/html.php,v 1.75.2.28 2007/11/22 15:45:22 jan Exp $
 *
 * Copyright 1999-2007 Anil Madhavapeddy <anil@recoil.org>
 * Copyright 1999-2007 Jon Parise <jon@recoil.org>
 * Copyright 2002-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Anil Madhavapeddy <anil@recoil.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @since   IMP 3.0
 * @package Horde_MIME_Viewer
 */
class IMP_MIME_Viewer_html extends MIME_Viewer_html {

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

        $attachment = $contents->viewAsAttachment();

        /* Sanitize the HTML. */
        $data = $this->_cleanHTML($this->mime_part->getContents());

        /* Search for inlined images that we can display. */
        $related = $this->mime_part->getInformation('related_part');
        if ($related !== false) {
            $relatedPart = $contents->getMIMEPart($related);
            foreach ($relatedPart->getCIDList() as $ref => $id) {
                $id = trim($id, '<>');
                $cid_part = $contents->getDecodedMIMEPart($ref);
                $data = str_replace("cid:$id", $contents->urlView($cid_part, 'view_attach'), $data);
            }
        }

        /* Convert links to open in new windows. But first we hide all links
         * that have an "#xyz" anchor and ignore all links that already have a
         * target. */
        $data = preg_replace(
             array('/<a\s([^>]*\s*href=["\']?(#|mailto:))/i',
                  '/<a\s([^>]*)\s*target=["\']?[^>"\'\s]*["\']?/i',
                  '/<a\s/i',
                  '/<area\s([^>]*\s*href=["\']?(#|mailto:))/i',
                  '/<area\s([^>]*)\s*target=["\']?[^>"\'\s]*["\']?/i',
                  '/<area\s/i',
                  "/\x01/",
                  "/\x02/"),
            array("<\x01\\1",
                  "<\x01 \\1 target=\"_blank\"",
                  '<a target="_blank" ',
                  "<\x02\\1",
                  "<\x02 \\1 target=\"_blank\"",
                  '<area target="_blank" ',
                  'a ',
                  'area '),
            $data);

        /* Turn mailto: links into our own compose links. */
        if (!$attachment) {
            $data = preg_replace_callback('/href\s*=\s*(["\'])?mailto:((?(1)[^\1]*?|[^\s>]+))(?(1)\1|)/i',
                                          create_function('$m', 'return \'href="\' . IMP::composeLink($m[2]) . \'"\';'),
                                          $data);
        }

        /* Filter bad language. */
        $data = IMP::filterText($data);

        if ($attachment) {
            $charset = $this->mime_part->getCharset();
        } else {
            $charset = NLS::getCharset();
            /* Put div around message. */
            $data = '<div id="html-message">' . $data . '</div>';
        }

        /* Only display images if specifically allowed by user. */
        if (!IMP::printMode() &&
            $GLOBALS['prefs']->getValue('html_image_replacement')) {

            /* Check to see if images exist. */
            $img_regex = '/(<img[^>]*src=|<input[^>]*src=|<body[^>]*background=|<td[^>]*background=|<table[^>]*background=|style=[^>]*background-image:.*url\()\s*(["\'])?((?(2)[^"\'>]*|[^\s>]*))(?(2)"|)/is';
            if (preg_match($img_regex, $data)) {
                /* Make sure the URL parameters are correct for the current
                   message. */
                $url = Util::removeParameter(Horde::selfUrl(true), array('index'));
                if (!$attachment) {
                    $url = Util::removeParameter($url, array('actionID'));
                }
                $base_ob = &$contents->getBaseObjectPtr();
                $url = Util::addParameter($url, 'index', $base_ob->getMessageIndex());

                $view_img = Util::getFormData('view_html_images');
                $addr_check = ($GLOBALS['prefs']->getValue('html_image_addrbook') && $this->_inAddressBook($contents));

                if (!$view_img && !$addr_check) {
                    $block_img = 'spacer_red.png';
                    $msg = array(String::convertCharset(_("This HTML message has images embedded in it. Blocked images appear like this: "), NLS::getCharset(), $charset) . Horde::img($block_img, null, 'height="10" width="10"'));
                    $newSrc = Horde::url($GLOBALS['registry']->getImageDir('imp') . '/' . $block_img);
                    $data = preg_replace($img_regex, '\\1"' . $newSrc . '"', $data);
                    $url = Util::addParameter($url, 'view_html_images', 1);
                    $attributes = $attachment ? array('style' => 'color:blue') : array();
                    $msg[] = Horde::link($url, String::convertCharset(_("Show the Images"), NLS::getCharset(), $charset), null, null, null, String::convertCharset(_("Show the Images"), NLS::getCharset(), $charset), null, $attributes) . String::convertCharset(_("Click here to SHOW the Images"), NLS::getCharset(), $charset) . '</a>.';
                } elseif ($addr_check) {
                    $msg = array(String::convertCharset(_("This HTML message has images embedded in it."), NLS::getCharset(), $charset), String::convertCharset(_("The images will be displayed because the sender is present in your addressbook."), NLS::getCharset(), $charset));
                }

                if (isset($msg)) {
                    $msg = $contents->formatStatusMsg($msg, Horde::img('mime/image.png', _("View the Images")), false);
                    if ($attachment) {
                        $msg = '<span style="background-color:white;color:black">' . nl2br($msg) . '</span><br />';
                    }
                    if (stristr($data, '<body') === false) {
                        $data = $msg . $data;
                    } else {
                        $data = preg_replace('/(.*<body.*?>)(.*)/is', '$1' . $msg . '$2', $data);
                    }
                }
            }
        }

        /* If we are viewing inline, give option to view in separate window. */
        if (!$attachment && $this->getConfigParam('external')) {
            $msg = sprintf(_("Click %s to view HTML content in a separate window."), $contents->linkViewJS($this->mime_part, 'view_attach', _("HERE"), _("View HTML content in a separate window")));
            $data = $contents->formatStatusMsg($msg, Horde::img('mime/html.png', _("HTML")), false) . $data;
        }

        return $data;
    }

    /**
     * Determine whether the sender appears in an available addressbook.
     *
     * @access private
     *
     * @param MIME_Contents &$contents  The MIME_Contents object.
     *
     * @return boolean  Does the sender appear in an addressbook?
     */
    function _inAddressBook(&$contents)
    {
        global $registry, $prefs;

        /* If we don't have access to the sender information, return false. */
        $base_ob = &$contents->getBaseObjectPtr();

        /* If we don't have a contacts provider available, give up. */
        if (!$registry->hasMethod('contacts/getField')) {
            return false;
        }

        $sources = explode("\t", $prefs->getValue('search_sources'));
        if ((count($sources) == 1) && empty($sources[0])) {
            $sources = array();
        }

        /* Try to get back a result from the search. */
        $result = $registry->call('contacts/getField', array($base_ob->getFromAddress(), '__key', $sources));
        if (is_a($result, 'PEAR_Error')) {
            return false;
        } else {
            return (count($result) ? true : false);
        }
    }

}
