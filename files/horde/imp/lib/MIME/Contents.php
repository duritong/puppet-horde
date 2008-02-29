<?php

require_once 'Horde/MIME/Contents.php';
// TODO: for BC - Remove for Horde 4.0
require IMP_BASE . '/config/mime_drivers.php';

/**
 * The IMP_Contents:: class extends the MIME_Contents:: class and contains
 * all functions related to handling the content and output of mail messages
 * in IMP.
 *
 * $Horde: imp/lib/MIME/Contents.php,v 1.153.4.45 2007/08/07 19:53:40 slusarz Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 4.0
 * @package IMP
 */
class IMP_Contents extends MIME_Contents {

    /**
     * The text of the body of the message.
     *
     * @var string
     */
    var $_body = '';

    /**
     * The MIME part id of the message body.
     *
     * @var integer
     */
    var $_body_id;

    /**
     * The text of various MIME body parts.
     *
     * @var array
     */
    var $_bodypart = array();

    /**
     * The IMAP index of the message.
     *
     * @var integer
     */
    var $_index;

    /**
     * The mailbox of the current message.
     *
     * @var string
     */
    var $_mailbox;

    /**
     * Should attachment stripping links be generated?
     *
     * @var boolean
     */
    var $_strip = false;

    /**
     * Attempts to return a reference to a concrete IMP_Contents instance.
     * If an IMP_Contents object is currently stored in the local cache,
     * recreate that object.  Else, create a new instance.
     * Ensures that only one IMP_Contents instance for any given message is
     * available at any one time.
     *
     * This method must be invoked as:
     *   $imp_contents = &IMP_Contents::singleton($index);
     *
     * @param string $index  The IMAP message mailbox/index. The index should
     *                       be in the following format:
     * <pre>
     * msg_id IMP_IDX_SEP msg_folder
     *   msg_id      = Message index of the message
     *   IMP_IDX_SEP = IMP constant used to separate index/folder
     *   msg_folder  = The full folder name containing the message index
     * </pre>
     *
     * @return IMP_Contents  The IMP_Contents object or null.
     */
    function &singleton($index)
    {
        static $instance = array();

        $signature = IMP_Contents::_createCacheID($index);
        if (isset($instance[$signature])) {
            return $instance[$signature];
        }

        $instance[$signature] = &IMP_Contents::getCache($signature);
        if (empty($instance[$signature])) {
            $instance[$signature] = &new IMP_Contents($index);
        }

        return $instance[$signature];
    }

    /**
     * Constructor
     *
     * @param mixed $in  Either an index string (see IMP_Contents::singleton()
     *                   for the format) or a MIME_Message object.
     */
    function IMP_Contents($in)
    {
        if (is_a($in, 'MIME_Message')) {
            $ob = $in;
        } else {
            list($this->_index, $this->_mailbox) = explode(IMP_IDX_SEP, $in);

            /* Get the MIME_Structure object for the given index. */
            require_once IMP_BASE . '/lib/IMAP.php';
            $imp_imap = &IMP_IMAP::singleton();
            $imp_imap->changeMbox($this->_mailbox);
            $structure = @imap_fetchstructure($_SESSION['imp']['stream'], $this->_index, FT_UID);
            if (!is_object($structure)) {
                return;
            }
            require_once 'Horde/MIME/Structure.php';
            $ob = &MIME_Structure::parse($structure);
        }

        switch ($GLOBALS['prefs']->getValue('attachment_display')) {
        case 'list':
            $this->_displayType = MIME_CONTENTS_DISPLAY_TYPE_LIST;
            break;

        case 'inline':
            $this->_displayType = MIME_CONTENTS_DISPLAY_TYPE_INLINE;
            break;

        case 'both':
            $this->_displayType = MIME_CONTENTS_DISPLAY_TYPE_BOTH;
            break;
        }

        parent::MIME_Contents($ob, array('download' => 'download_attach', 'view' => 'view_attach'));
    }

    /**
     * Returns the entire body of the message.
     *
     * @return string  The text of the body of the message.
     */
    function getBody()
    {
        if (empty($this->_body)) {
            $this->_body = @imap_body($_SESSION['imp']['stream'], $this->_index, FT_UID | FT_PEEK);
            $this->_body = str_replace("\r\n", "\n", $this->_body);
        }

        return $this->_body;
    }

    /**
     * Gets the raw text for one section of the message.
     *
     * @param integer $id  The ID of the MIME_Part.
     *
     * @return string  The text of the part.
     */
    function getBodyPart($id)
    {
        if (!isset($this->_bodypart[$id])) {
            $this->_bodypart[$id] = @imap_fetchbody($_SESSION['imp']['stream'], $this->_index, $id, FT_UID | FT_PEEK);
            $this->_bodypart[$id] = str_replace("\r\n", "\n", $this->_bodypart[$id]);
        }

        return isset($this->_bodypart[$id]) ? $this->_bodypart[$id] : '';
    }

    /**
     * Allow attachments to be stripped by providing a link in summary view?
     *
     * @param boolean $strip  Should the strip links be generated?
     */
    function setStripLink($strip = false)
    {
        $this->_strip = $strip;
    }

    /**
     * Returns an array summarizing a part of a MIME message.
     *
     * @param MIME_Part &$mime_part  See MIME_Contents::partSummary().
     * @param boolean $guess         See MIME_Contents::partSummary().
     *
     * @return array  See MIME_Contents::partSummary().
     *                Adds the following key to that return:
     *                [6] = Compressed Download Link
     *                [7] = Image Save link (if allowed)
     *                [8] = Strip Attachment Link (if allowed)
     */
    function partSummary(&$mime_part, $guess = false)
    {
        global $conf, $registry;

        $summary = parent::partSummary($mime_part, $guess);

        /* Don't add extra links if not requested or if this is a guessed
           part. */
        if ($guess || !$this->_links) {
            return $summary;
        }

        /* Display the compressed download link only if size is greater
           than 200 KB. */
        if (($mime_part->getBytes() > 204800) &&
            Util::extensionExists('zlib') &&
            ($mime_part->getType() != 'application/zip') &&
            ($mime_part->getType() != 'application/x-zip-compressed')) {
            $summary[] = $this->linkView($mime_part, 'download_attach', Horde::img('compressed.png', _("Download in .zip Format"), null, $registry->getImageDir('horde') . '/mime'), array('jstext' => sprintf(_("Download %s in .zip Format"), $mime_part->getDescription(true, true)), 'viewparams' => array('zip' => 1)), true);
        } else {
            $summary[] = null;
        }

        /* Display the image save link if the required registry calls are
         * present. */
        if (($mime_part->getPrimaryType() == 'image') &&
            $registry->hasMethod('images/selectGalleries') &&
            ($image_app = $registry->hasMethod('images/saveImage'))) {
            Horde::addScriptFile('popup.js');
            $url = Util::addParameter(Horde::applicationUrl('saveimage.php'), array('index' => ($this->_index . IMP_IDX_SEP . $this->_mailbox), 'id' => $mime_part->getMIMEId()));
            $summary[] = Horde::link('#', _("Save Image in Gallery"), null, null, "popup_imp('" . $url . "',450,200); return false;") . '<img src="' . $registry->get('icon', $image_app) . '" alt="' . _("Save Image in Gallery") . '" title="' . _("Save Image in Gallery") . '" /></a>';
        } else {
            $summary[] = null;
        }

        /* Strip the Attachment? */
        if ($this->_strip &&
            !$mime_part->getInformation('rfc822_part')) {
            $url = Horde::selfUrl(true);
            $url = Util::removeParameter($url, array('actionID', 'imapid', 'index'));
            $url = Util::addParameter($url, array('actionID' => 'strip_attachment', 'imapid' => $this->_getMIMEKey($mime_part, false), 'index' => $this->getMessageIndex()));
            $summary[] = Horde::link($url, _("Strip Attachment"), null, null, "return window.confirm('" . addslashes(_("Are you sure you wish to PERMANENTLY delete this attachment?")) . "');") . Horde::img('delete.png', _("Strip Attachment"), null, $registry->getImageDir('horde')) . '</a>';
        } else {
            $summary[] = null;
        }

        return $summary;
    }

    /**
     * Return the URL to the view.php page.
     *
     * @param MIME_Part &$mime_part  See MIME_Contents::urlView().
     * @param integer $actionID      See MIME_Contents::urlView().
     * @param array $params          See MIME_Contents::urlView().
     * @param boolean $dload         See MIME_Contents::urlView().
     * The following parameter names will be overwritten by this function:
     *   id, index, mailbox
     *
     * @return string  The URL to view.php.
     */
    function urlView(&$mime_part, $actionID, $params = array(), $dload = false)
    {
        require_once IMP_BASE . '/lib/Search.php';

        /* Add the necessary local parameters. */
        $params = array_merge($params, IMP::getSearchParameters($_SESSION['imp']['thismailbox']));
        $params['index'] = $this->_index;
        if (!isset($params['mailbox'])) {
            $params['mailbox'] = $_SESSION['imp']['mailbox'];
        }

        /* Should this be a download link? */
        $dload = (($actionID == 'download_attach') ||
                  ($actionID == 'download_render') ||
                  ($actionID == 'save_message'));

        return parent::urlView($mime_part, $actionID, $params, $dload);
    }

    /**
     * Generate a link to the view.php page.
     *
     * @param MIME_Part &$mime_part  See MIME_Contents::linkView().
     * @param integer $actionID      See MIME_Contents::linkView().
     * @param string $text           See MIME_Contents::linkView().
     * @param array $params          See MIME_Contents::linkView().
     *
     * @return string  See MIME_Contents::linkView().
     */
    function linkView(&$mime_part, $actionID, $text, $params = array())
    {
        if ($mime_part->getInformation('actionID')) {
            $actionID = $mime_part->getInformation('actionID');
        }

        /* If this is a 'download_attach or 'download_render' link, we do not
           want to show in a new window. */
        $dload = (($actionID == 'download_attach') ||
                  ($actionID == 'download_render'));

        /* If download attachment, add the 'thismailbox' param. */
        if ($actionID == 'download_attach') {
            $params['viewparams']['thismailbox'] = $_SESSION['imp']['thismailbox'];
        }

        if ($mime_part->getInformation('viewparams')) {
            foreach ($mime_part->getInformation('viewparams') as $key => $val) {
                $params['viewparams'][$key] = $val;
            }
        }

        return parent::linkView($mime_part, $actionID, $text, $params, $dload);
    }

    /**
     * Returns the full message text.
     *
     * @return string  The full message text.
     */
    function fullMessageText()
    {
        $imp_headers = &$this->getHeaderOb();
        return $imp_headers->getHeaderText() . $this->getBody();
    }

    /**
     * Returns the header object.
     *
     * @return IMP_Headers  The IMP_Headers object.
     */
    function &getHeaderOb()
    {
        require_once IMP_BASE . '/lib/MIME/Headers.php';
        $imp_headers = &new IMP_Headers($this->_index);
        return $imp_headers;
    }

    /**
     * Returns the IMAP index for the current message.
     *
     * @return integer  The message index.
     */
    function getMessageIndex()
    {
        return $this->_index;
    }

    /**
     * Rebuild the MIME_Part structure of a message from IMAP data.
     * This will store IMAP data in all parts of the message - for example,
     * all data for a multipart/mixed part will be stored in the base part,
     * and each part will contain its own data.  Note that if you want to
     * build a message string from the MIME_Part data after calling
     * rebuildMessage(), you should use IMP_Contents::toString() instead of
     * MIME_Part::toString().
     *
     * @return MIME_Message  A MIME_Message object with all of the body text
     *                       stored in the individual MIME_Parts.
     */
    function rebuildMessage()
    {
        $part = $this->_message->getBasePart();
        $this->_rebuildMessage($part);
        return $this->_message;
    }

    /**
     * Recursive function used to rebuild the MIME_Part structure of a
     * message.
     *
     * @access private
     *
     * @param MIME_Part $part  A MIME_Part object.
     */
    function _rebuildMessage($part)
    {
        $id = $part->getMIMEId();

        $mime_part = $this->getMIMEPart($id);
        $this->_setContents($mime_part, true);
        if ($this->_message->getPart($id) === $this->_message) {
            $this->_message = &$mime_part;
        } else {
            $this->_message->alterPart($id, $mime_part);
        }

        if ($part->getPrimaryType() == 'multipart') {
            /* Recursively process any subparts. */
            foreach ($part->getParts() as $mime) {
                $this->_rebuildMessage($mime);
            }
        }
    }

    /**
     * Render a MIME Part.
     *
     * @param MIME_Part &$mime_part  A MIME_Part object.
     *
     * @return string  The rendered data.
     */
    function renderMIMEPart(&$mime_part)
    {
        $this->_setContents($mime_part);
        $text = parent::renderMIMEPart($mime_part);

        /* Convert textual emoticons into graphical ones - but only for
         * text parts. */
        if (($mime_part->getPrimaryType() == 'text') &&
            $GLOBALS['prefs']->getValue('emoticons')) {
            require_once 'Horde/Text/Filter.php';
            $text = Text_Filter::filter($text, 'emoticons', array('entities' => true));
        }

        return $text;
    }

    /**
     * Saves a copy of the MIME_Contents object at the end of a request.
     *
     * @access private
     */
    function _addCacheShutdown()
    {
        /* Don't cache the $_bodypart array since we most likely will not use
         * them in the cached object (since the cache will generally be used
         * to view parts that weren't displayed originally).
         * However, we will lose the benefit of not having to
         * make calls to the mail server on subsequent page loads, so allow
         * users to change this behavior.  Only cache small messages as
         * storing large messages will make the session size too large and
         * will tax storage and performance on the server. */
        if (empty($GLOBALS['conf']['server']['cache_msgbody'])) {
            $this->_bodypart = array();
        } else {
            $size = 0;
            foreach ($this->_bodypart as $key => $val) {
                $part_size = strlen($val);
                if (($size + $part_size) < 10240) {
                    $size += $part_size;
                } else {
                    unset($this->_bodypart[$key]);
                }
            }
        }

        /* Never cache the full body part as we use this item much less
         * often than specific message body parts. */
        $this->_body = null;

        parent::_addCacheShutdown();
    }

    /**
     * Get the from address of the message.
     *
     * @return string  The from address of the message.
     */
    function getFromAddress()
    {
        require_once IMP_BASE . '/lib/MIME/Headers.php';
        $headers = &new IMP_Headers($this->getMessageIndex());
        return $headers->getFromAddress();
    }

    /**
     * Generate the list of MIME IDs to use for download all.
     *
     * @param boolean $forward  Generate a list of items that can be forwarded,
     *                          rather than downloaded.
     *
     * @return array  The list of MIME IDs that should be downloaded when
     *                downloading all attachments.
     */
    function getDownloadAllList($forward = false)
    {
        $downloads = array();

        $bodyid = $this->findBody();

        /* Here is what we consider 'forwardable':
         * All parts > 0
         * Part 0 if not multipart/mixed
         * NOT the body part (if one exists)
         *
         * Here is what we consider 'downloadable':
         * All parts not 'multipart/*' and 'message/*' except for
         *  'message/rfc822'
         * All parts that are not PGP or S/MIME signature information
         * NOT the body part (if one exists) */
        foreach ($this->_message->contentTypeMap() as $key => $val) {
            if ($key == $bodyid) {
                continue;
            }

            if ($forward) {
                if ($key == 0) {
                    if ($val != 'multipart/mixed') {
                        $downloads[] = $key;
                        break;
                    }
                } elseif (strpos($key, '.') === false) {
                    $downloads[] = $key;
                }
            } else {
                if (strpos($val, 'message/') === 0) {
                    if (strpos($val, 'message/rfc822') === 0) {
                        $downloads[] = $key;
                    }
                } elseif (((intval($key) != 1) && (strpos($val, '1.') === false)) &&
                          ((strpos($val, 'multipart/') === false)) &&
                          (strpos($val, 'application/x-pkcs7-signature') === false) &&
                          (strpos($val, 'application/pkcs7-signature') === false)) {
                    $downloads[] = $key;
                }
            }
        }

        return $downloads;
    }

    /**
     * Generate a download all link, if possible.
     *
     * @return string  The download link.
     */
    function getDownloadAllLink()
    {
        $url = null;

        $downloads_list = $this->getDownloadAllList();
        if (!empty($downloads_list)) {
            /* Create a dummy variable to pass to urlView() since we don't
               have a MIME_Part and we can't pass null by reference. */
            $dummy = 0;
            $url = $this->urlView($dummy, 'download_all', array('id' => 1));
            $url = Util::removeParameter($url, array('id'));
        }

        return $url;
    }

    /**
     * Prints out the status message for a given MIME Part.
     *
     * @param mixed $msg      See MIME_Contents::formatStatusMsg().
     * @param string $img     See MIME_Contents::formatStatusMsg().
     * @param boolean $print  Output this message when in a print view?
     *
     * @return string  The formatted status message.
     */
    function formatStatusMsg($msg, $img = null, $printable = true)
    {
        if (!$printable && IMP::printMode()) {
            return '';
        } else {
            return parent::formatStatusMsg($msg, $img);
        }
    }

    /**
     * Finds the main "body" text part (if any) in a message.
     * "Body" data is the first text part in the base MIME part.
     *
     * @return string  The MIME ID of the main "body" part.
     */
    function findBody()
    {
        if (isset($this->_body_id)) {
            return $this->_body_id;
        }

        if (!is_null($this->_message)) {
            /* Look for potential body parts. */
            $part = $this->_message->getBasePart();
            $primary_type = $part->getPrimaryType();
            if (($primary_type == MIME::type(TYPEMULTIPART)) ||
                ($primary_type == MIME::type(TYPETEXT))) {
                $this->_body_id = $this->_findBody($part);
                return $this->_body_id;
            }
        }

        return null;
    }

    /**
     * Processes a MIME Part and looks for "body" data.
     *
     * @access private
     *
     * @return string  The MIME ID of the main "body" part.
     */
    function _findBody($mime_part)
    {
        if (intval($mime_part->getMIMEId()) < 2 ||
            $mime_part->getInformation('alternative') === 0) {
            if ($mime_part->getPrimaryType() == MIME::type(TYPEMULTIPART)) {
                foreach ($mime_part->getParts() as $part) {
                    if (($partid = $this->_findBody($part))) {
                        return $partid;
                    }
                }
            } elseif ($mime_part->getPrimaryType() == MIME::type(TYPETEXT)) {
                if ($mime_part->getBytes() ||
                    $this->getBodyPart($mime_part->getMIMEId())) {
                    return $mime_part->getMIMEId();
                }
            }
        }

        return null;
    }

    /**
     * Creates a unique cache ID for this object.
     *
     * @access private
     *
     * @param integer $index  The IMAP index of the current message.
     *
     * @return string  A unique cache ID.
     */
    function _createCacheID($index = null)
    {
        if (is_null($index)) {
            if (isset($this)) {
                $index = $this->_index;
            } else {
                return parent::_createCacheID();
            }
        }
        return md5(implode('|', array($index, $_SESSION['imp']['thismailbox'])));
    }

    /**
     * Make sure the contents of the current part are set from IMAP server
     * data.
     *
     * @access private
     * @since IMP 3.1
     *
     * @param MIME_Part &$mime_part  The MIME_Part object to work with.
     * @param boolean $all           Download the entire parts contents?
     */
    function _setContents(&$mime_part, $all = false)
    {
        if (!$mime_part->getInformation('imp_contents_set') &&
            !$mime_part->getContents()) {
            $id = $mime_part->getMIMEId();
            if ($all && ($mime_part->getType() == 'message/rfc822')) {
                $id = substr($mime_part->getMIMEId(), 0, -2);
            }
            $mime_part->setContents($this->getBodyPart($id));
        }
        $mime_part->setInformation('imp_contents_set', true);
    }

    /**
     * Fetch part of a MIME message.
     *
     * @since IMP 3.1
     *
     * @param integer $id   The MIME ID of the part requested.
     * @param boolean $all  If this is a header part, should we return all text
     *                      in the body?
     *
     * @return MIME_Part  The MIME_Part.
     */
    function &getRawMIMEPart($id, $all = false)
    {
        $mime_part = $this->getMIMEPart($id);
        if (!is_a($mime_part, 'MIME_Part')) {
            $mime_part = null;
            return $mime_part;
        }
        $this->_setContents($mime_part, true);
        return $mime_part;
    }

    /**
     * Create a message string from a MIME message that has used
     * rebuildMessage() to build the data from the IMAP server.
     *
     * @since IMP 4.1
     *
     * @param MIME_Message $message  A MIME_Message object.
     * @param boolean $canonical     Return a canonical string?
     *
     * @return string  The contents of the MIME_Message object.
     */
    function toString($message, $canonical = false)
    {
        $text = '';

        $part = $message->getBasePart();
        foreach ($part->contentTypeMap() as $key => $val) {
            if (($key != 0) && (strpos($val, 'multipart/') === 0)) {
                $old = $part->getPart($key);
                $old->setContents('');
                $part->alterPart($key, $old);
            }
        }

        if ($message->getMIMEId() == 0) {
            $part->setContents('');
        }

        return ($canonical) ? $part->toCanonicalString() : $part->toString();
    }

}
