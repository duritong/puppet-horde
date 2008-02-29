<?php
/**
 * $Horde: horde/services/portal/edit.php,v 1.44.8.7 2007/11/22 00:16:14 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2003-2007 Mike Cochrane <mike@graftonhall.co.nz>
 * Copyright 2003-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/../..');
require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/Block/Collection.php';
require_once 'Horde/Block/Layout/Manager.php';
require_once 'Horde/Identity.php';

if (!Auth::isAuthenticated()) {
    Horde::authenticationFailureRedirect();
}

// Get full name for title.
$identity = &Identity::singleton();
$fullname = $identity->getValue('fullname');
if (empty($fullname)) {
    $fullname = Auth::getAuth();
}

// Instantiate the blocks objects.
$blocks = &Horde_Block_Collection::singleton('portal');
$layout_pref = @unserialize($prefs->getValue('portal_layout'));
if (!is_array($layout_pref)) {
    $layout_pref = array();
}
if (!count($layout_pref)) {
    $layout_pref = Horde_Block_Collection::getFixedBlocks();
}
$layout = &Horde_Block_Layout_Manager::singleton('portal', $blocks, $layout_pref);

// Handle requested actions.
$layout->handle(Util::getFormData('action'),
                (int)Util::getFormData('row'),
                (int)Util::getFormData('col'));
if ($layout->updated()) {
    $prefs->setValue('portal_layout', $layout->serialize());
}

$title = _("My Portal Layout");
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/portal/menu.inc';
$notification->notify(array('listeners' => 'status'));
require HORDE_TEMPLATES . '/portal/edit.inc';
require HORDE_TEMPLATES . '/common-footer.inc';
