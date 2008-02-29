<?php

require_once 'Horde/MIME/Contents.php';
// TODO: for BC - Remove for Horde 4.0
require MIMP_BASE . '/config/mime_drivers.php';

/**
 * The MIMP_Contents:: class extends the MIME_Contents:: class and contains
 * all functions related to handling the content and output of mail messages
 * in MIMP.
 *
 * $Horde: mimp/lib/MIME/Contents.php,v 1.48.2.1 2007/01/02 13:55:10 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package MIMP
 */
class MIMP_Contents extends MIME_Contents {

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
     * Attempts to return a reference to a concrete MIMP_Contents instance.
     * Ensures that only one MIMP_Contents instance for any given message is
     * available at any one time.
     *
     * This method must be invoked as:
     *   $mimp_contents = &MIMP_Contents::singleton($index);
     *
     * @param string $index  The IMAP index.
     *
     * @return MIMP_Contents  The MIMP_Contents object or null.
     */
    function &singleton($index)
    {
        static $instance = array();

        if (empty($instance[$index])) {
            $instance[$index] = &new MIMP_Contents($index);
            $message = $instance[$index]->getMIMEMessage();
            if (is_a($message, 'PEAR_Error')) {
                return $message;
            }
        }

        return $instance[$index];
    }

    /**
     * Constructor
     *
     * @param integer $index  The IMAP message index string.
     */
    function MIMP_Contents($index)
    {
        $this->_index = $index;

        /* Get the MIME_Message object for the given index. */
        require_once 'Horde/MIME/Structure.php';
        $ob = &MIME_Structure::parse(@imap_fetchstructure($_SESSION['mimp']['stream'], $index, FT_UID));

        parent::MIME_Contents($ob);
    }

    /**
     * Returns the entire body of the message.
     *
     * @return string  The text of the body of the message.
     */
    function getBody()
    {
        if (empty($this->_body)) {
            $this->_body = @imap_body($_SESSION['mimp']['stream'], $this->_index, FT_UID);
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
            $this->_bodypart[$id] = @imap_fetchbody($_SESSION['mimp']['stream'], $this->_index, $id, FT_UID);
            $this->_bodypart[$id] = str_replace("\r\n", "\n", $this->_bodypart[$id]);
        }

        return $this->_bodypart[$id];
    }

    /**
     * Return the attachment list, in Horde_Mobile:: objects for
     * header listing.
     *
     * @param Horde_Mobile_block $hb  The Horde_Mobile_block object to add to.
     */
    function getAttachments(&$hb)
    {
        $msg = '';

        foreach ($this->_atc as $key => $val) {
            $hb->add(new Horde_Mobile_text(_("Attachment") . ': ', array('b')));
            $t = &$hb->add(new Horde_Mobile_text(sprintf('%s (%s)', $this->_atc[$key][2], $this->_atc[$key][3]) . "\n"));
            $t->set('linebreaks', true);
        }
    }

    /**
     * Return all viewable message parts (plain text, for
     * Horde_Mobile:: to deal with).
     *
     * @return string  The full message.
     */
    function getMessage()
    {
        $msg = '';
        $partDisplayed = false;

        foreach ($this->_parts as $key => $value) {
            if (!empty($value)) {
                if ($msg) {
                    $msg .= "\n\n";
                }
                $msg .= $value;
                $partDisplayed = true;
            }
        }

        if (!$partDisplayed) {
            $msg .= _("There are no parts that can be displayed inline.");
        }

        return $msg;
    }

    /**
     * Returns an array summarizing a part of a MIME message.
     *
     * @param MIME_Part &$mime_part  The MIME_Part to summarize.
     * @param boolean $guess         Is this a temporary guessed-type part?
     *
     * @return array  See MIME_Contents::partSummary().
     *                Ignores guessed parts.
     */
    function partSummary(&$mime_part, $guess = false)
    {
        if ($guess) {
            return array();
        } else {
            $summary = parent::partSummary($mime_part);
            array_walk($summary, array($this, '_stripTags'));
            return $summary;
        }
    }

    /**
     * array_walk() callback to strip HTML.
     */
    function _stripTags(&$value)
    {
        $value = strip_tags($value);
    }

    /**
     * Returns the full message text for a given message/mailbox.
     *
     * @param integer $index  The index/mailbox of the message. If empty, will
     *                        use the current message index.
     *
     * @return string  The full message text.
     */
    function fullMessageText($index = null)
    {
        if (is_null($index)) {
            $index = $this->_index;
        } elseif ($index != $this->_index) {
            $this->_body = '';
            $this->_index = $index;
        }

        require_once MIMP_BASE . '/lib/MIME/Headers.php';
        $mimp_headers = &new MIMP_Headers($index);
        return $mimp_headers->getHeaderText() . $this->getBody();
    }

    /**
     * Rebuild the MIME_Part structure of a message from IMAP data.
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
    function _rebuildMessage($part, $decode)
    {
        $id = $part->getMIMEId();

        $mime_part = &$this->getMIMEPart($id);
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
        return parent::renderMIMEPart($mime_part);
    }

    /**
     * Render MIME Part data.
     *
     * @access private
     *
     * @param MIME_Part &$mime_part  A MIME_Part object.
     * @param boolean $attachment    Render MIME Part attachment info?
     *
     * @return string  The rendered data.
     */
    function _renderMIMEPart(&$mime_part, $attachment = false)
    {
        /* Get the MIME_Viewer object for this MIME part */
        $viewer = &$this->getMIMEViewer($mime_part);
        if (!is_a($viewer, 'MIME_Viewer')) {
            return '';
        }

        $mime_part->transferDecodeContents();

        /* If this is a text/* part, AND the text is in a different
         * character set than the browser, convert to the current
         * character set. */
        $charset = $mime_part->getCharset();
        if ($charset) {
            $charset_upper = String::upper($charset);
            if ($charset_upper != 'US-ASCII') {
                $default_charset = String::upper(NLS::getCharset());
                if ($charset_upper != $default_charset) {
                    $mime_part->setContents(String::convertCharset($mime_part->getContents(), $charset, $default_charset));
                }
            }
        }

        $viewer->setMIMEPart($mime_part);
        $params = array(&$this);
        if ($attachment) {
            return $viewer->renderAttachmentInfo($params);
        } else {
            return $viewer->render($params);
        }
    }

    /**
     * Prints out the status message for a given MIME Part.
     *
     * @param string $msg     The message to output.
     * @param string $img     An image link to add to the beginning of the
     *                        message.
     * @param boolean $print  Output this message when in a print view?
     * @param string $class   An optional style for the status box.
     *
     * @return string  The formatted status message string.
     */
    function formatStatusMsg($msg, $img = null, $printable = true,
                             $class = null)
    {
        if (!is_array($msg)) {
            $msg = array($msg);
        }
        return implode("\n", $msg) . "\n\n";
    }

    /**
     * Finds the main "body" text part (if any) in a message.
     *
     * @return string  The MIME ID of the main "body" part.
     */
    function findBody()
    {
        if (isset($this->_body_id)) {
            return $this->_body_id;
        }

        /* Look for potential body parts. */
        $part = $this->_message->getBasePart();
        $primary_type = $part->getPrimaryType();
        if (($primary_type == MIME::type(TYPEMULTIPART)) ||
            ($primary_type == MIME::type(TYPETEXT))) {
            $this->_body_id = $this->_findBody($part);
            return $this->_body_id;
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
     * Make sure the contents of the current part are set from IMAP server
     * data.
     *
     * @access private
     *
     * @param MIME_Part &$mime_part  The MIME_Part object to work with.
     * @param boolean $all           Download the entire parts contents?
     */
    function _setContents(&$mime_part, $all = false)
    {
        if (!$mime_part->getInformation('mimp_contents_set') &&
            !$mime_part->getContents()) {
            $id = $mime_part->getMIMEId();
            if ($all && ($mime_part->getType() == 'message/rfc822')) {
                $id = substr($mime_part->getMIMEId(), 0, -2);
            }
            $mime_part->setContents($this->getBodyPart($id));
        }
        $mime_part->setInformation('mimp_contents_set', true);
    }

}
