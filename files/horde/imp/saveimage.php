<?php
/**
 * $Horde: imp/saveimage.php,v 1.1.2.6 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2005-2007 Michael Slusarz <slusarz@bigworm.curecanti.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/MIME/Contents.php';

$id = Util::getFormData('id');
$index = Util::getFormData('index');

/* Run through the action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'save_image':
    $contents = &IMP_Contents::singleton($index);
    $mime_part = $contents->getDecodedMIMEPart($id);
    $image_data = array(
        'filename' => $mime_part->getName(true, true),
        'description' => $mime_part->getDescription(true),
        'data' => $mime_part->getContents(),
        'type' => $mime_part->getType()
    );
    $res = $registry->call('images/saveImage', array(null, Util::getFormData('gallery'), $image_data));
    if (is_a($res, 'PEAR_Error')) {
        $notification->push($res, 'horde.error');
        break;
    }
    Util::closeWindowJS();
    exit;
}

/* Build the list of galleries. */
$gallerylist = $registry->call('images/selectGalleries', array(null, PERMS_EDIT));

$title = _("Save Image");
require IMP_TEMPLATES . '/common-header.inc';
IMP::status();
require IMP_TEMPLATES . '/saveimage/saveimage.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
