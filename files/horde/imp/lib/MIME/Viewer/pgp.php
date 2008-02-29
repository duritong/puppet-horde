<?php

require_once IMP_BASE . '/lib/Crypt/PGP.php';

/**
 * The IMP_MIME_Viewer_pgp class allows viewing/decrypting of PGP
 * formatted messages.  This class implements RFC 3156.
 *
 * This class handles the following MIME types:
 *   application/pgp-encryption
 *   application/pgp-keys
 *   application/pgp-signature
 *
 * This class may add the following parameters to the URL:
 *   'rawpgpkey' -- Display the PGP Public Key in raw, text format
 *
 * $Horde: imp/lib/MIME/Viewer/pgp.php,v 1.96.6.15 2007/03/09 11:43:18 jan Exp $
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
class IMP_MIME_Viewer_pgp extends MIME_Viewer {

    /**
     * IMP_PGP object.
     *
     * @var IMP_PGP
     */
    var $_imp_pgp;

    /**
     * The address of the sender.
     *
     * @var string
     */
    var $_address;

    /**
     * Pointer to the MIME_Contents item.
     *
     * @var MIME_Contents
     */
    var $_contents = null;

    /**
     * Classwide cache for icons for status messages.
     *
     * @var string
     */
    var $_icon = null;

    /**
     * Classwide cache for status messages.
     *
     * @var array
     */
    var $_status = array();

    /**
     * The MIME content-type of this part.
     *
     * @var string
     */
    var $_type = 'text/html';

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
        global $conf, $prefs;

        /* Set the MIME_Contents class variable. */
        $this->_contents = &$params[0];

        $msg = '';

        if (empty($this->_imp_pgp) && !empty($conf['utils']['gnupg'])) {
            $this->_imp_pgp = &new IMP_PGP();
        }

        /* Determine the address of the sender. */
        if (empty($this->_address)) {
            $base_ob = &$this->_contents->getBaseObjectPtr();
            $this->_address = $base_ob->getFromAddress();
        }

        /* We need to insert JavaScript code now if PGP support is active. */
        if (!empty($conf['utils']['gnupg']) &&
            $prefs->getValue('use_pgp') &&
            !Util::getFormData('rawpgpkey')) {
            $msg = Util::bufferOutput(array('Horde', 'addScriptFile'), 'popup.js');
        }

        /* For RFC 2015/3156, there are 3 types of messages:
             +  multipart/encrypted
             +  multipart/signed
             +  application/pgp-keys */
        switch ($this->mime_part->getType()) {
        case 'application/pgp-keys':
            $msg .= $this->_outputPGPKey();
            break;

        case 'multipart/signed':
        case 'application/pgp-signature':
            $msg .= $this->_outputPGPSigned();
            break;

        case 'multipart/encrypted':
        case 'application/pgp-encrypted':
            $msg .= $this->_outputPGPEncrypted();
            break;
        }

        return $msg;
    }

    /**
     * Return the content-type of the output.
     *
     * @return string  The MIME content type of the output.
     */
    function getType()
    {
        return $this->_type;
    }

    /**
     * Generates HTML output for 'application/pgp-keys' MIME_Parts..
     *
     * @access private
     *
     * @return string  The HTML output.
     */
    function _outputPGPKey()
    {
        global $conf, $prefs;

        $mime = &$this->mime_part;
        $part = $this->_contents->getDecodedMIMEPart($mime->getMIMEId());

        if (empty($conf['utils']['gnupg'])) {
            $text = '<pre>' . $part->getContents() . '</pre>';
        } elseif (Util::getFormData('rawpgpkey')) {
            $text = $part->getContents();
            $this->_type = 'text/plain';
        } else {
            require_once 'Horde/Text.php';

            $pgp_key = $mime->getContents();

            /* Initialize status message. */
            $this->_initStatus($this->getIcon($mime->getType()), _("PGP"));
            $this->_status[] = _("This PGP Public Key was attached to the message.");

            if ($prefs->getValue('use_pgp') &&
                $GLOBALS['registry']->hasMethod('contacts/addField') &&
                $prefs->getValue('add_source')) {
                $this->_status[] = Horde::link('#', _("Click to Save PGP Public Key in your Address book"), null, null, $this->_imp_pgp->savePublicKeyURL($mime) . ' return false;') . _("Click to Save PGP Public Key in your Address book") . '</a>.';
            }
            $this->_status[] = sprintf(_("The raw text of the PGP Public Key can be viewed %s."), $this->_contents->linkViewJS($part, 'view_attach', _("HERE"), _("PGP Public Key Text"), null, array('rawpgpkey' => 1)));

            $text = $this->_outputStatus(false) .
                '<span class="fixed">' . nl2br(str_replace(' ', '&nbsp;', $this->_imp_pgp->pgpPrettyKey($pgp_key))) . '</span>';
        }

        return $text;
    }

    /**
     * Generates HTML output for 'multipart/signed' and
     * 'application/pgp-signature' MIME_Parts.
     *
     * @access private
     *
     * @return string  The HTML output.
     */
    function _outputPGPSigned()
    {
        global $conf, $prefs;

        $active = ($prefs->getValue('use_pgp') && !empty($conf['utils']['gnupg']));
        $mime = &$this->mime_part;
        $mimetype = $mime->getType();
        $text = '';

        $signenc = $mime->getInformation('pgp_signenc');
        if ($signenc) {
            $this->_status[] = _("The message below has been encrypted with PGP.");
        }

        $this->_initStatus($this->getIcon($mimetype), _("PGP"));
        $this->_status[] = _("The message below has been digitally signed via PGP.");

        if (!$active) {
            $this->_status[] = _("PGP support is not currently active so the digital signature is unable to be verified.");
        }

        /* Store PGP results in $sig_result; store text in $data. */
        $sig_result = '';
        if ($mimetype == 'multipart/signed') {
            /* If the MIME ID is 0, we need to store the body of the message
               in the MIME_Part object. */
            if (!$signenc) {
                if (($mimeID = $mime->getMIMEId())) {
                    $mime->setContents($this->_contents->getBodyPart($mimeID));
                } else {
                    $mime->setContents($this->_contents->getBody());
                }
                $mime->splitContents();
            }

            /* Data that is signed appears in the first MIME subpart. */
            $subpart = $mime->getPart($mime->getRelativeMIMEId(1));
            $signature_data = rtrim($subpart->getCanonicalContents(), "\r");

            require_once 'Horde/MIME/Structure.php';
            $mime_message = &MIME_Structure::parseTextMIMEMessage($signature_data);

            /* The PGP signature appears in the second MIME subpart. */
            $subpart = $mime->getPart($mime->getRelativeMIMEId(2));
            if ($subpart && $subpart->getType() == 'application/pgp-signature') {
                if ($active) {
                    $subpart->transferDecodeContents();
                    $sig_result = $this->_imp_pgp->verifySignature($signature_data, $this->_address, $subpart->getContents());
                }
            } else {
                $this->_status[] = _("The message below does not appear to be in the correct PGP format (according to RFC 2015).");
            }
        } elseif ($mimetype == 'application/pgp-signature') {
            /* Get the signed message to output. */
            require_once 'Horde/MIME/Message.php';
            $mime_message = &new MIME_Message();
            $mime_message->setType('text/plain');
            $mime->transferDecodeContents();

            if (empty($this->_imp_pgp)) {
                $mime_message->setContents($mime->getContents());
            } else {
                $mime_message->setContents($this->_imp_pgp->getSignedMessage($mime));
            }

            /* Pass the signed text straight through to PGP program */
            if ($active) {
                $sig_result = $this->_imp_pgp->verifySignature($mime->getContents(), $this->_address);
            }
        }

        $text = $this->_outputStatus();

        if ($active) {
            $text .= $this->_outputPGPSignatureTest($sig_result);
        }

        /* We need to stick the output into a MIME_Contents object. */
        $mc = &new MIME_Contents($mime_message, array('download' => 'download_attach', 'view' => 'view_attach'), array(&$this->_contents));
        $mc->buildMessage();

        return $text . '<table cellpadding="0" cellspacing="0">' . $mc->getMessage(true) . '</table>';
    }

    /**
     * Generates HTML output for 'multipart/encrypted' and
     * 'application/pgp-encrypted' MIME_Parts.
     *
     * @access private
     *
     * @return string  The HTML output.
     */
    function _outputPGPEncrypted()
    {
        global $conf, $prefs;

        $mime = &$this->mime_part;
        $mimetype = $mime->getType();
        $text = '';

        $this->_initStatus($this->getIcon($mimetype), _("PGP"));

        /* Print out message now if PGP is not active. */
        if (empty($conf['utils']['gnupg']) || !$prefs->getValue('use_pgp')) {
            $this->_status[] = _("PGP support is not currently active so the message is unable to be decrypted.");
            return $this->_outputStatus();
        }

        if ($mimetype == 'multipart/encrypted') {
            /* PGP control information appears in the first MIME subpart. We
               don't currently need to do anything with this information. The
               encrypted data appears in the second MIME subpart. */
            $subpart = $mime->getPart($mime->getRelativeMIMEId(2));
            if (!$subpart) {
                return $text;
            }
            $encrypted_data = $this->_contents->getBodyPart($subpart->getMIMEId());
        } elseif ($mimetype == 'application/pgp-encrypted') {
            $encrypted_data = $mime->getContents();
        } else {
            return $text;
        }

        /* Check if this is a literal compressed message. */
        $info = $this->_imp_pgp->pgpPacketInformation($encrypted_data);
        $literal = !empty($info['literal']);
        $this->_status[] = $literal ? _("The message below has been compressed with PGP.") : _("The message below has been encrypted with PGP.");

        if (!$literal && !$this->_imp_pgp->getPersonalPrivateKey()) {
            $this->_status[] = _("No personal private key exists so the message is unable to be decrypted.");
            return $this->_outputStatus();
        }

        if (!$literal && !$this->_imp_pgp->getPassphrase()) {
            $url = $this->_imp_pgp->getJSOpenWinCode('open_passphrase_dialog');
            $this->_status[] = Horde::link('#', _("You must enter the passphrase for your PGP private key to view this message"), null, null, $url . ' return false;') . _("You must enter the passphrase for your PGP private key to view this message") . '</a>';
            $text .= $this->_outputStatus() .
                '<script type="text/javascript">' . $url . '</script>';
        } else {
            if ($mimetype == 'multipart/encrypted') {
                if ($subpart->getType() == 'application/octet-stream') {
                    $decrypted_data = $this->_imp_pgp->decryptMessage($encrypted_data, !$literal);
                    if (is_a($decrypted_data, 'PEAR_Error')) {
                        $this->_status[] = _("The message below does not appear to be a valid PGP encrypted message. Error: ") . $decrypted_data->getMessage();
                        $text .= $this->_outputStatus();
                    } else {
                        /* We need to check if this is a signed/encrypted
                           message. */
                        require_once 'Horde/MIME/Structure.php';
                        $mime_message = &MIME_Structure::parseTextMIMEMessage($decrypted_data->message);
                        if (!$mime_message) {
                            require_once 'Horde/Text/Filter.php';
                            $text .= $this->_signedOutput($decrypted_data->sig_result);
                            $decrypted_message = String::convertCharset($decrypted_data->message, $subpart->getCharset());
                            $text .= '<span class="fixed">' . Text_Filter::filter($decrypted_message, 'text2html', array('parselevel' => TEXT_HTML_SYNTAX)) . '</span>';
                        } else {
                            $mimetype = $mime_message->getType();
                            if (($mimetype == 'multipart/signed') ||
                                ($mimetype == 'application/pgp-signature')) {
                                $mime_message->setInformation('pgp_signenc', true);
                                $mime_message->setContents($decrypted_data->message);
                                $mime_message->splitContents();
                            } else {
                                $text .= $this->_signedOutput($decrypted_data->sig_result);
                            }

                            /* We need to stick the output into a
                               MIME_Contents object. */
                            $mc = &new MIME_Contents($mime_message, array('download' => 'download_attach', 'view' => 'view_attach'), array(&$this->_contents));
                            $mc->buildMessage();
                            if ($mime_message->getInformation('pgp_signenc')) {
                                $text .= $mc->getMessage(true);
                            } else {
                                $text .= '<table cellpadding="0" cellspacing="0">' . $mc->getMessage(true) . '</table>';
                            }
                        }
                    }
                } else {
                    $this->_status[] = _("The message below does not appear to be in the correct PGP format (according to RFC 2015).");
                    $text .= $this->_outputStatus();
                }
            } elseif ($mimetype == 'application/pgp-encrypted') {
                $decrypted_data = $this->_imp_pgp->decryptMessage($encrypted_data, !$literal);
                if (is_a($decrypted_data, 'PEAR_Error')) {
                    $decrypted_message = $decrypted_data->getMessage();
                    $text .= $this->_outputStatus();
                } else {
                    $text .= $this->_signedOutput($decrypted_data->sig_result);
                    $decrypted_message = String::convertCharset($decrypted_data->message, $mime->getCharset());
                }

                require_once 'Horde/Text/Filter.php';
                $text .= '<span class="fixed">' . Text_Filter::filter($decrypted_message, 'text2html', array('parselevel' => TEXT_HTML_SYNTAX)) . '</span>';
            }
        }

        return $text;
    }

    /**
     * Generates HTML output for the PGP signature test.
     *
     * @access private
     *
     * @param string $result  Result string of the PGP output concerning the
     *                        signature test.
     *
     * @return string  The HTML output.
     */
    function _outputPGPSignatureTest($result)
    {
        $text = '';

        if (is_a($result, 'PEAR_Error')) {
            $this->_initStatus($GLOBALS['registry']->getImageDir('horde') . '/alerts/error.png', _("Error"));
            $result = $result->getMessage();
        } else {
            $this->_initStatus($GLOBALS['registry']->getImageDir('horde') . '/alerts/success.png', _("Success"));
            /* This message has been verified but there was no output from the
               PGP program. */
            if (empty($result)) {
               $result = _("The message below has been verified.");
            }
        }

        require_once 'Horde/Text/Filter.php';

        $this->_status[] = Text_Filter::filter($result, 'text2html', array('parselevel' => TEXT_HTML_NOHTML));

        return $this->_outputStatus();
    }

    /**
     * Output signed message status.
     *
     * @access private
     *
     * @param string $result  The signature result.
     *
     * @return string  HTML output.
     */
    function _signedOutput($result)
    {
        if (!empty($result)) {
            $this->_status[] = _("The message below has been digitally signed via PGP.");
            $text = $this->_outputStatus() .
                $this->_outputPGPSignatureTest($result);
        } else {
            $text = $this->_outputStatus();
        }

        return $text;
    }

    /* Various formatting helper functions */
    function _initStatus($src, $alt = '')
    {
        if (is_null($this->_icon)) {
            $this->_icon = Horde::img($src, $alt, 'height="16" width="16"', '') . '&nbsp;';
        }
    }

    function _outputStatus($printable = true)
    {
        $output = '';
        if (!empty($this->_status)) {
            $output = $this->_contents->formatStatusMsg($this->_status, $this->_icon, $printable);
        }
        $this->_icon = null;
        $this->_status = array();
        return $output;
    }

}
