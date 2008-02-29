<?php
/**
 * This script displays a rendered MIME_Part object.
 * The following are potential URL parameters that we should honor:
 *   'actionID' -- The action ID to perform
 *     -> 'compose_attach_preview'
 *     -> 'download_all'
 *     -> 'download_attach'
 *     -> 'download_render'
 *     -> 'save_message'
 *     -> 'view_attach'
 *     -> 'view_source'
 *   'ctype'    -- The content-type to use instead of the content-type
 *                 found in the original MIME_Part object
 *   'id'       -- The MIME part to display
 *   'index'    -- The index of the message; only used for IMP_Contents
 *                 objects
 *   'zip'      -- Download in .zip format?
 *
 * $Horde: imp/view.php,v 2.199.4.11 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

/* We can't register this session as 'readonly' if we are doing a
 * 'view_attach' since some MIME_Drivers may need to create temporary cache
 * files to correctly generate links. */
if (isset($_GET['actionID']) && $_GET['actionID'] != 'view_attach') {
    $session_control = 'readonly';
}

$no_compress = true;
@define('IMP_BASE', dirname(__FILE__));
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/MIME/Contents.php';

$index = Util::getFormData('index');
$id = Util::getFormData('id');
$actionID = Util::getFormData('actionID');

/* 'compose_attach_preview' doesn't use IMP_Contents since there is no
 * IMAP message data - rather, we must use the IMP_Compose object to
 * get the necessary MIME_Part. */
if ($actionID == 'compose_attach_preview') {
    /* Initialize the IMP_Compose:: object. */
    require_once IMP_BASE . '/lib/Compose.php';
    $imp_compose = &new IMP_Compose(array('cacheID' => Util::getFormData('messageCache')));
    $mime = $imp_compose->buildAttachment($id);

    /* Create a dummy MIME_Contents() object so we can use the view
     * code below.  Then use the 'view_attach' handler to output to
     * the user. */
    $contents = &new IMP_Contents(new MIME_Message());
    $actionID = 'view_attach';
} else {
    /* Prevent blind fetching of attachments without knowing the MIME ID.
     * Index *can* be empty (think embedded MIME parts - there is no
     * corresponding message index) - but see below; id can be part 0 (whole
     * message) so just make sure that it's specified. */
    if (!in_array($actionID, array('save_message', 'download_all'))
        && is_null($id)) {
        exit;
    }

    /* Get cached item, if available. */
    if (!($contents = &IMP_Contents::getCache())) {
        /* If we make it to here without an index, then something is broken
         * since there is nothing in the cache and we have no way to create
         * a viewable object. */
        if (empty($index)) {
            exit;
        }
        $contents = &IMP_Contents::singleton($index . IMP_IDX_SEP . $_SESSION['imp']['thismailbox']);
    }

    if (!in_array($actionID, array('download_attach', 'download_all', 'save_message', 'view_source'))) {
        $mime = $contents->getDecodedMIMEPart($id);
        if (($ctype = Util::getFormData('ctype'))) {
            $mime->setType($ctype);
        }
    }
}

/* Run through action handlers */
switch ($actionID) {
case 'download_all':
    $tosave = array();
    $headers = &$contents->getHeaderOb();
    $headers->buildHeaders();
    $zipfile = trim(preg_replace('/[^\w-+_\. ]/', '_', $headers->getValue('subject')), ' _');
    if (empty($zipfile)) {
        $zipfile = _("attachments.zip");
    } else {
        $zipfile .= '.zip';
    }
    foreach ($contents->getDownloadAllList() as $val) {
        $mime = $contents->getDecodedMIMEPart($val);
        $tosave[] = array('data' => $mime->getContents(), 'name' => $mime->getName(true, true));
    }
    if (is_a($contents, 'PEAR_Error')) {
        Horde::fatal($contents, __FILE__, __LINE__);
    }

    require_once 'Horde/Compress.php';
    $horde_compress = &Horde_Compress::singleton('zip');
    $body = $horde_compress->compress($tosave);
    $browser->downloadHeaders($zipfile, 'application/zip', false, strlen($body));
    echo $body;
    exit;

case 'download_attach':
case 'download_render':
    switch ($actionID) {
    case 'download_attach':
        /* Make sure we get the entire contents of the part. */
        $mime = $contents->getDecodedMIMEPart($id, true);
        $body = $mime->getContents();
        $type = $mime->getType(true);
        break;

    case 'download_render':
        $body = $contents->renderMIMEPart($mime);
        $type = $contents->getMIMEViewerType($mime);
        break;
    }

    $name = $mime->getName(true, true);

    /* Compress output? */
    if (($actionID == 'download_attach') && Util::getFormData('zip')) {
        require_once 'Horde/Compress.php';
        $horde_compress = &Horde_Compress::singleton('zip');
        $body = $horde_compress->compress(array(array('data' => $body, 'name' => $name)));
        $name .= '.zip';
        $type = 'application/zip';
    }
    $browser->downloadHeaders($name, $type, false, strlen($body));
    echo $body;
    exit;

case 'view_attach':
    $body = $contents->renderMIMEPart($mime);
    $type = $contents->getMIMEViewerType($mime);
    $browser->downloadHeaders($mime->getName(true, true), $type, true, strlen($body));
    echo $body;
    exit;

case 'view_source':
    $msg = $contents->fullMessageText();
    $browser->downloadHeaders('Message Source', 'text/plain', true, strlen($msg));
    echo $msg;
    exit;

case 'save_message':
    require_once IMP_BASE . '/lib/MIME/Headers.php';
    $imp_headers = &new IMP_Headers($index);

    $name = 'saved_message';
    if (($subject = $imp_headers->getOb('subject', true))) {
        $name = trim(preg_replace('/[^\w-+_\. ]/', '_', $subject), ' _');
    }

    if (!($from = $imp_headers->getFromAddress())) {
        $from = '<>';
    }
    $date = strftime('%a %b %d %H:%M:%S %Y', $imp_headers->getOb('udate'));
    $body = 'From ' . $from . ' ' . $date . "\n" . $contents->fullMessageText();
    $body = str_replace("\r\n", "\n", $body);

    $browser->downloadHeaders($name . '.eml', 'message/rfc822', false, strlen($body));
    echo $body;
    exit;
}
