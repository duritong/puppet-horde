<?php
/**
 * $Horde: imp/fetchmail.php,v 1.40.8.6 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2002-2007 Nuno Loureiro <nuno@co.sapo.pt>
 * Copyright 2004-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Fetchmail.php';
require_once 'Horde/Prefs/UI.php';

/* No fetchmail for POP3 accounts. */
if ($_SESSION['imp']['base_protocol'] == 'pop3') {
    echo _("Your account does not support fetching external mail.");
    exit;
}

/* Initialize Fetchmail libraries. */
$fm_account = new IMP_Fetchmail_Account();

/* Run through the action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'fetchmail_fetch':
    $fetch_list = Util::getFormData('accounts');
    if (!empty($fetch_list)) {
        IMP_Fetchmail::fetchMail($fetch_list);

        /* Go to the download folder. */
        $lmailbox = $fm_account->getValue('lmailbox', $fetch_list[0]);
        $url = Util::addParameter(Horde::applicationUrl('mailbox.php'), 'mailbox', $lmailbox);
        if ($prefs->getValue('fetchmail_popup')) {
            Util::closeWindowJS('opener.focus();opener.location.href="' . $url . '";');
        } else {
            header('Location: ' . $url);
        }
        exit;
    }
    break;
}

$accounts = $fm_account->getAll('id');
$fetch_url = Horde::applicationUrl('fetchmail.php');
$title = _("Other Mail Accounts");

require IMP_TEMPLATES . '/common-header.inc';

if ($prefs->getValue('fetchmail_popup')) {
    $cancel_js = 'window.close();';
} else {
    require IMP_TEMPLATES . '/menu.inc';

    if (!Util::getFormData('lmailbox')) {
        $mbox = 'INBOX';
    } else {
        $mbox = Util::getFormData('lmailbox');
    }
    $cancel_js = 'window.location = \'' . Util::addParameter(Horde::applicationUrl('mailbox.php'), 'mailbox', $mbox) . '\';';
}

require IMP_TEMPLATES . '/fetchmail/fetchmail.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
