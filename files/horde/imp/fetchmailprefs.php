<?php
/**
 * $Horde: imp/fetchmailprefs.php,v 1.39.4.4 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2002-2007 Nuno Loureiro <nuno@co.sapo.pt>
 * Copyright 2004-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
$authentication = OP_HALFOPEN;
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Fetchmail.php';

/* Initialize Fetchmail libraries. */
$fm_account = &new IMP_Fetchmail_Account();

$driver = Util::getFormData('fm_driver');
$fetch_url = Horde::applicationUrl('fetchmailprefs.php');
$prefs_url = Util::addParameter(IMP::prefsURL(true), 'group', 'fetchmail', false);
$to_edit = null;

/* Handle clients without javascript. */
$actionID = Util::getFormData('actionID');
if (is_null($actionID)) {
    if (Util::getPost('edit')) {
        $actionID = 'fetchmail_prefs_edit';
    } elseif (Util::getPost('save')) {
        $actionID = 'fetchmail_prefs_save';
    } elseif (Util::getPost('delete')) {
        $actionID = 'fetchmail_prefs_delete';
    } elseif (Util::getPost('back')) {
        header('Location: ' . $prefs_url);
        exit;
    } elseif (Util::getPost('select')) {
        header('Location: ' . $fetch_url);
        exit;
    }
}

/* Run through the action handlers */
switch ($actionID) {
case 'fetchmail_create':
    if ($driver) {
        $fetchmail = &IMP_Fetchmail::factory($driver, array());
    }
    break;

case 'fetchmail_prefs_edit':
    $to_edit = Util::getFormData('account');
    $driver = $fm_account->getValue('driver', $to_edit);
    $fetchmail = &IMP_Fetchmail::factory($driver, array());
    break;

case 'fetchmail_prefs_save':
    $to_edit = Util::getFormData('edit_account');
    if ($to_edit == '') {
        $to_edit = $fm_account->add();
    }

    $fetchmail = &IMP_Fetchmail::factory($driver, array());

    $id = Util::getFormData('fm_id', _("Unnamed"));
    $fm_account->setValue('id', $id, $to_edit);

    foreach ($fetchmail->getParameterList() as $val) {
        $fm_account->setValue($val, Util::getFormData('fm_' . $val), $to_edit);
    }

    $prefs->setValue('fetchmail_login', (array_sum($fm_account->getAll('loginfetch'))) ? true : false);

    $notification->push(sprintf(_("The account \"%s\" has been saved."), $id), 'horde.success');
    break;

case 'fetchmail_prefs_delete':
    $to_delete = Util::getFormData('edit_account');
    if (!is_null($to_delete)) {
        $deleted_account = $fm_account->delete($to_delete);
        $notification->push(sprintf(_("The account \"%s\" has been deleted."), $deleted_account['id']), 'horde.success');
        $prefs->setValue('fetchmail_login', (array_sum($fm_account->getAll('loginfetch'))) ? true : false);
        $actionID = null;
    } else {
        $notification->push(_("You must select an account to be deleted."), 'horde.warning');
    }
    break;
}

/* Show the header. */
require_once 'Horde/Prefs/UI.php';
require IMP_BASE . '/config/prefs.php';
$app = 'imp';

Prefs_UI::generateHeader();
require IMP_TEMPLATES . '/fetchmail/top.inc';

if (empty($actionID)) {
    /* If actionID is still empty, we haven't selected an account
     * yet. */
    $accounts = $fm_account->getAll('id');
    require IMP_TEMPLATES . '/fetchmail/account_select.inc';
} elseif (($actionID == 'fetchmail_create') && empty($driver)) {
    /* We are creating an account and need to select the type. */
    require IMP_TEMPLATES . '/fetchmail/driver_select.inc';
} else {
    $fm_colors = IMP_Fetchmail::listColors();
    require IMP_TEMPLATES . '/fetchmail/manage.inc';
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
