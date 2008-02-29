<?php
/**
 * $Horde: ingo/blacklist.php,v 1.34.8.9 2007/01/02 13:55:02 jan Exp $
 *
 * Copyright 2002-2007 Mike Cochrane <mike@graftonhall.co.nz>
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('INGO_BASE', dirname(__FILE__));
require_once INGO_BASE . '/lib/base.php';

/* Redirect if blacklist is not available. */
if (!in_array(INGO_STORAGE_ACTION_BLACKLIST, $_SESSION['ingo']['script_categories'])) {
    $notification->push(_("Blacklist is not supported in the current filtering driver."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('filters.php', true));
    exit;
}

/* Get the backend. */
$have_mark = false;
$scriptor = &Ingo::loadIngoScript();
if ($scriptor) {
    /* Determine if this scriptor supports mark-as-deleted. */
    $avail_actions = $scriptor->availableActions();
    if (in_array(INGO_STORAGE_ACTION_FLAGONLY, $avail_actions)) {
        $have_mark = true;
    }
}

/* Get the blacklist object. */
$blacklist = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_BLACKLIST);
if (is_a($blacklist, 'PEAR_Error')) {
    $notification->push($blacklist);
    $blacklist = new Ingo_Storage_Blacklist();
}

/* Perform requested actions. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'create_folder':
    $blacklist_folder = Ingo::createFolder(Util::getFormData('new_folder_name'));
    break;

case 'rule_update':
    switch (Util::getFormData('action')) {
    case 'delete':
        $folder = '';
        break;

    case 'mark':
        $folder = INGO_BLACKLIST_MARKER;
        break;

    case 'folder':
        $folder = Util::getFormData('actionvalue');
        break;
    }

    if (($folder == INGO_BLACKLIST_MARKER) && !$have_mark) {
        $notification->push("Not supported by this script generator.", 'horde.error');
    } else {
        $ret = $blacklist->setBlacklist(Util::getFormData('blacklist'));
        if (is_a($ret, 'PEAR_Error')) {
            $notification->push($ret, $ret->getCode());
        } else {
            $blacklist->setBlacklistFolder($folder);
            if (!$ingo_storage->store($blacklist)) {
                $notification->push(_("Error saving changes."), 'horde.error');
            } else {
                $notification->push(_("Changes saved."), 'horde.success');
            }

            if ($prefs->getValue('auto_update')) {
                /* This does its own $notification->push() on error: */
                Ingo::updateScript();
            }
        }

        /* Update the timestamp for the rules. */
        $_SESSION['ingo']['change'] = time();
    }

    break;
}

/* Create the folder listing. */
if (!isset($blacklist_folder)) {
    $blacklist_folder = $blacklist->getBlacklistFolder();
}
$field_num = $have_mark ? 2 : 1;
$folder_list = Ingo::flistSelect($blacklist_folder, 'filters', 'actionvalue',
                                 'document.filters.action[' . $field_num .
                                 '].checked=true');

/* Get the blacklist rule. */
$filters = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_FILTERS);
$bl_rule = $filters->findRule(INGO_STORAGE_ACTION_BLACKLIST);

/* Include new folder JS if necessary. */
if ($registry->hasMethod('mail/createFolder')) {
    Horde::addScriptFile('new_folder.js');
}

$title = _("Blacklist Edit");
require INGO_TEMPLATES . '/common-header.inc';
require INGO_TEMPLATES . '/menu.inc';
require INGO_TEMPLATES . '/blacklist/blacklist.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
