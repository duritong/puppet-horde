<?php
/**
 * $Horde: ingo/forward.php,v 1.11.8.6 2007/01/02 13:55:02 jan Exp $
 *
 * Copyright 2003-2007 Todd Merritt <tmerritt@email.arizona.edu>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('INGO_BASE',  dirname(__FILE__));
require_once INGO_BASE . '/lib/base.php';

/* Redirect if forward is not available. */
if (!in_array(INGO_STORAGE_ACTION_FORWARD, $_SESSION['ingo']['script_categories'])) {
    $notification->push(_("Forward is not supported in the current filtering driver."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('filters.php', true));
    exit;
}

/* Get the forward object. */
$forward = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_FORWARD);

/* Perform requested actions. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'update':
    $forward->setForwardAddresses(Util::getFormData('addresses'));
    $forward->setForwardKeep((intval(Util::getFormData('keep_copy')) == 1));
    if (is_a($result = $ingo_storage->store($forward), 'PEAR_Error')) {
        $notification->push($result);
    } else {
        $notification->push(_("Changes saved."), 'horde.success');
        if ($prefs->getValue('auto_update')) {
            Ingo::updateScript();
        }
    }
    break;
}

/* Get the forward rule. */
$filters = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_FILTERS);
$fwd_rule = $filters->findRule(INGO_STORAGE_ACTION_FORWARD);

$title = _("Forwards Edit");
require INGO_TEMPLATES . '/common-header.inc';
require INGO_TEMPLATES . '/menu.inc';
require INGO_TEMPLATES . '/forward/forward.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
