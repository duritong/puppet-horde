<?php
/**
 * $Horde: imp/filterprefs.php,v 2.16.10.6 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
$authentication = OP_HALFOPEN;
require_once IMP_BASE . '/lib/base.php';
require_once 'Horde/Prefs/UI.php';
require IMP_BASE . '/config/prefs.php';

/* Are preferences locked? */
$login_locked = $prefs->isLocked('filter_on_login') || empty($_SESSION['imp']['filteravail']);
$display_locked = $prefs->isLocked('filter_on_display') || empty($_SESSION['imp']['filteravail']);
$anymailbox_locked = $prefs->isLocked('filter_any_mailbox') || empty($_SESSION['imp']['filteravail']);
$menuitem_locked = $prefs->isLocked('filter_menuitem');

/* Run through the action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'update_prefs':
    if (!$login_locked) {
        $prefs->setValue('filter_on_login', Util::getFormData('filter_login'));
    }
    if (!$display_locked) {
        $prefs->setValue('filter_on_display', Util::getFormData('filter_display'));
    }
    if (!$anymailbox_locked) {
        $prefs->setValue('filter_any_mailbox', Util::getFormData('filter_any_mailbox'));
    }
    if (!$menuitem_locked) {
        $prefs->setValue('filter_menuitem', Util::getFormData('filter_menuitem'));
    }
    $notification->push(_("Preferences successfully updated."), 'horde.success');
    break;
}

/* Get filter links. */
if ($registry->hasMethod('mail/showBlacklist')) {
    $blacklist_link = $registry->link('mail/showBlacklist');
}
if ($registry->hasMethod('mail/showWhitelist')) {
    $whitelist_link = $registry->link('mail/showWhitelist');
}
if ($registry->hasMethod('mail/showFilters')) {
    $filters_link = $registry->link('mail/showFilters');
}

/* Show the header. */
require_once 'Horde/Prefs/UI.php';
require IMP_BASE . '/config/prefs.php';
$app = 'imp';
$group = 'filters';

Prefs_UI::generateHeader();

/* If filters are disabled. */
if (empty($blacklist_link) && empty($whitelist_link) && empty($filters_link)) {
    require IMP_TEMPLATES . '/filters/notactive.inc';
} else {
    $selfURL = Horde::applicationUrl('filterprefs.php');
    require IMP_TEMPLATES . '/filters/prefs.inc';
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
