<?php

require_once 'Horde/MIME/Viewer/images.php';

/**
 * The IMP_MIME_Viewer_images class allows images to be displayed
 * inline in a message.
 *
 * $Horde: imp/lib/MIME/Viewer/images.php,v 1.43.10.20 2007/01/02 13:55:00 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 3.2
 * @package Horde_MIME_Viewer
 */
class IMP_MIME_Viewer_images extends MIME_Viewer_images {

    /**
     * The content-type of the generated data.
     *
     * @var string
     */
    var $_contentType;

    /**
     * Render out the currently set contents.
     *
     * @param array $params  An array with a reference to a MIME_Contents
     *                       object.
     *
     * @return string  The rendered information.
     */
    function render($params)
    {
        $contents = $params[0];

        global $browser;

        /* If calling page is asking us to output data, do that without any
         * further delay and exit. */
        if (Util::getFormData('img_data')) {
            $this->_contentType = parent::getType();
            return parent::render();
        }

        /* Convert the image to browser-viewable format and display. */
        if (Util::getFormData('images_view_convert')) {
            return $this->_viewConvert();
        }

        /* Create the thumbnail and display. */
        if (Util::getFormData('images_view_thumbnail')) {
            return $this->_viewConvert(true);
        }

        /* This page has been called with the thumbnail parameter. See if we
         * can convert to an inline browser viewable form. */
        if (Util::getFormData('view_thumbnail')) {
            $img = $this->_getHordeImageOb();
            if ($img) {
                return $this->_popupImageWindow();
            } else {
                return $contents->formatStatusMsg(_("The server was not able to create a thumbnail of this image."));
            }
        }

        if (Util::getFormData('images_load_convert')) {
            return $this->_popupImageWindow();
        }

        if ($contents->viewAsAttachment()) {
            if (!$browser->hasFeature('javascript') ||
                !$contents->viewAsAttachment(true)) {
                /* If either:
                   + The browser doesn't support javascript
                   + We are not viewing in a popup window
                   Then simply render the image data. */
                $this->_contentType = parent::getType();
                return parent::render();
            } elseif ($browser->isViewable(parent::getType())) {
                /* The browser can display the image type directly - just
                   output the javascript code to render the auto resize popup
                   image window. */
                return $this->_popupImageWindow();
            }
        }

        if ($browser->isViewable($this->mime_part->getType())) {
            /* If we are viewing inline, and the browser can handle the image
               type directly, output an <img> tag to load the image. */
            $alt = $this->mime_part->getName(false, true);
            return Horde::img($contents->urlView($this->mime_part, 'view_attach'), $alt, null, '');
        } else {
            /* If we have made it this far, than the browser cannot view this
               image inline.  Inform the user of this and, possibly, ask user
               if we should convert to another image type. */
            $msg = _("Your browser does not support inline display of this image type.");

            if ($contents->viewAsAttachment()) {
                $msg .= '<br />' . sprintf(_("Click %s to download the image."), $contents->linkView($this->mime_part, 'download_attach', _("HERE"), array('viewparams' => array('img_data' => 1)), true));
            }

            /* See if we can convert to an inline browser viewable form. */
            $img = $this->_getHordeImageOb();
            if ($img && $browser->isViewable($img->getContentType())) {
                if ($contents->viewAsAttachment()) {
                    $convert_link = Horde::link($contents->urlView($this->mime_part, 'view_attach', array('images_load_convert' => 1, 'popup_view' => 1))) . _("HERE") . '</a>';
                } else {
                    $convert_link = $contents->linkViewJS($this->mime_part, 'view_attach', _("HERE"), null, null, array('images_load_convert' => 1));
                }
                $msg .= '<br />' . sprintf(_("Click %s to convert the image file into a format your browser can view."), $convert_link);
            }

            return $contents->formatStatusMsg($msg);
        }
    }

    /**
     * Return the content-type
     *
     * @return string  The content-type of the output.
     */
    function getType()
    {
        if ($this->_contentType) {
            return $this->_contentType;
        } else {
            return 'text/html; charset=' . NLS::getCharset();
        }
    }

    /**
     * Render out attachment information.
     *
     * @param array $params  An array with a reference to a MIME_Contents
     *                       object.
     *
     * @return string  The rendered text in HTML.
     */
    function renderAttachmentInfo($params)
    {
        $contents = &$params[0];

        /* Display the thumbnail link only if size is greater than 50 KB and
           there is an image conversion utility available. */
        if ($this->mime_part->getBytes() < 51200) {
            return '';
        }

        if (is_a($contents, 'IMP_Contents')) {
            $this->mime_part = &$contents->getDecodedMIMEPart($this->mime_part->getMIMEId(), true);
        }

        if (!$this->_getHordeImageOb()) {
            return '';
        }

        $status = array(
            sprintf(_("A large image named %s is attached to this message."), $this->mime_part->getName(true)),
            sprintf(_("Click %s to view a thumbnail of this image."), $contents->linkViewJS($this->mime_part, 'view_attach', _("HERE"), _("View Thumbnail"), null, array('view_thumbnail' => 1)))
        );
        return $contents->formatStatusMsg($status, Horde::img('mime/image.png', _("View Thumbnail"), null, $GLOBALS['registry']->getImageDir('horde')));
    }

    /**
     * Generate the HTML output for the JS auto-resize view window.
     *
     * @access private
     *
     * @return string  The HTML output.
     */
    function _popupImageWindow()
    {
        $params = $remove_params = array();
        if (Util::getFormData('view_thumbnail')) {
            $params['images_view_thumbnail'] = 1;
            $remove_params[] = 'view_thumbnail';
        } elseif (Util::getFormData('images_load_convert')) {
            $params['images_view_convert'] = 1;
            $remove_params[] = 'images_load_convert';
        } else {
            $params['img_data'] = 1;
        }
        $self_url = Util::addParameter(Util::removeParameter(Horde::selfUrl(true), $remove_params), $params);
        $title = MIME::decode($this->mime_part->getName(false, true));
        return parent::_popupImageWindow($self_url, $title);
    }

    /**
     * View thumbnail sized image.
     *
     * @access private
     *
     * @param boolean $thumb  View thumbnail size?
     *
     * @return string  The image data.
     */
    function _viewConvert($thumb = false)
    {
        $mime = $this->mime_part;
        $img = $this->_getHordeImageOb();

        if ($thumb) {
            $img->resize(96, 96, true);
        }
        $mime->setType($img->getContentType());
        $mime->setContents($img->raw(true));

        $this->_contentType = $img->getContentType();

        return $mime->getContents();
    }

    /**
     * Return a Horde_Image object.
     *
     * @access private
     *
     * @return Horde_Image  The requested object.
     */
    function _getHordeImageOb()
    {
        include_once 'Horde/Image.php';
        $params = array('temp' => Horde::getTempdir());
        if (!empty($GLOBALS['conf']['image']['convert'])) {
            $img = &Horde_Image::singleton('im', $params);
        } elseif (Util::extensionExists('gd')) {
            $img = &Horde_Image::singleton('gd', $params);
        } else {
            return false;
        }

        if (is_a($img, 'PEAR_Error')) {
            return false;
        }

        $ret = $img->loadString(1, $this->mime_part->getContents());
        if (is_a($ret, 'PEAR_Error')) {
            return false;
        }

        return $img;
    }

}
