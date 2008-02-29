<?php
/**
 * $Horde: imp/acl.php,v 1.23.10.9 2007/01/02 13:54:53 jan Exp $
 *
 * Copyright 2000-2007 Chris Hastie <imp@oak-wood.co.uk>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
$authentication = OP_HALFOPEN;
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Folder.php';
require_once 'Horde/IMAP/ACL.php';

$prefs_url = IMP::prefsURL(true);

/* Redirect back to the options screen if ACL is not enabled. */
if ($prefs->isLocked('acl') ||
    !(isset($_SESSION['imp']['acl']) &&
      is_array($_SESSION['imp']['acl']))) {
    $notification->push(_("Folder sharing is not enabled."), 'horde.error');
    header('Location: ' . $prefs_url);
    exit;
}

$params = array(
    'hostspec' => $_SESSION['imp']['server'],
    'port' => $_SESSION['imp']['port'],
    'protocol' => $_SESSION['imp']['protocol'],
    'username' => $_SESSION['imp']['user'],
    'password' => Secret::read(Secret::getKey('imp'), $_SESSION['imp']['pass'])
);

if (isset($_SESSION['imp']['acl']['params'])) {
    $params = array_merge($params, $_SESSION['imp']['acl']['params']);
}
$ACLDriver = IMAP_ACL::singleton($_SESSION['imp']['acl']['driver'], $params);

/* Check selected driver is supported. Redirect to options screen with
 * error message if not */
$error = null;
if (!$ACLDriver->isSupported()) {
    $error = _("This server does not support sharing folders.");
} else {
    $error = $ACLDriver->getError();
}

if ($error) {
    $notification->push($error, 'horde.error');
    header('Location: ' . $prefs_url);
    exit;
}

$acl = Util::getFormData('acl');
$folder = Util::getFormData('folder');
$protected  = $ACLDriver->getProtected();
$share_user = Util::getFormData('share_user');
$ok_form = true;

/* Run through the action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'imp_acl_set':
    if (!$share_user) {
        $notification->push(_("No user specified."), 'horde.error');
        $ok_form = false;
    }
    if (!$folder) {
        $notification->push(_("No folder selected."), 'horde.error');
        $ok_form = false;
    }
    if (in_array($share_user, $protected)) {
        $notification->push(_("Permissions for this user cannot be changed."), 'horde.error');
        $ok_form = false;
    }

    if ($ok_form) {
        $result = $ACLDriver->createACL($folder, $share_user, $acl);
        if (is_a($result, 'PEAR_Error')) {
            $notification->push($result);
        } elseif (!count($acl)) {
            $notification->push(sprintf(_("All rights on folder \"%s\" successfully removed for user \"%s\"."), $folder, $share_user), 'horde.success');
        } else {
            $notification->push(sprintf(_("User \"%s\" successfully given the specified rights for the folder \"%s\"."), $share_user, $folder), 'horde.success');
        }
    }
    break;

case 'imp_acl_edit':
    if (!$share_user) {
        $notification->push(_("No user specified."), 'horde.error');
        $ok_form = false;
    }
    if (!$folder) {
        $notification->push(_("No folder selected."), 'horde.error');
        $ok_form = false;
    }
    if (in_array($share_user, $protected)) {
        $notification->push(_("Permissions for this user cannot be changed."), 'horde.error');
        $ok_form = false;
    }

    if ($ok_form) {
        $result = $ACLDriver->editACL($folder, $share_user, $acl);
        if (is_a($result, 'PEAR_Error')) {
            $notification->push($result);
        } else {
            if ($acl) {
                $notification->push(sprintf(_("User \"%s\" successfully given the specified rights for the folder \"%s\"."), $share_user, $folder), 'horde.success');
            } else {
                $notification->push(sprintf(_("User \"%s\" successfully revoked the specified rights for the folder \"%s\"."), $share_user, $folder), 'horde.success');
            }
        }
    }
    break;
}

$imp_folder = &IMP_Folder::singleton();
$rights = $ACLDriver->getRights();

if (empty($folder)) {
    $folder = 'INBOX';
}

if (count($imp_folder->flist_IMP())) {
    $options = IMP::flistSelect('', true, array(), $folder);
}

$curr_acl = $ACLDriver->getACL($folder);
$canEdit = $ACLDriver->canEdit($folder, $_SESSION['imp']['user']);

if (is_a($curr_acl, 'PEAR_Error')) {
    $notification->push($curr_acl, 'horde_error');
    $curr_acl = array();
} else {
    /* Set up javascript arrays. */
    if (count($curr_acl)) {
        $js_user = '';
        foreach (array_keys($rights) as $right) {
            $js_right[$right] = '';
        }

        foreach ($curr_acl as $curr_user => $granted) {
            if (strlen($js_user) > 0) {
                $js_user .= ', ';
                foreach (array_keys($rights) as $right) {
                    $js_right[$right] .= ', ';
                }
            }

            $js_user .= '"' . $curr_user . '"';

            foreach (array_keys($rights) as $right) {
                $js_right[$right] .= (empty($granted[$right])) ? '"0"' : '"1"';
            }
        }
    }
}

require_once 'Horde/Prefs/UI.php';
require IMP_BASE . '/config/prefs.php';
$app = 'imp';

Prefs_UI::generateHeader();
require IMP_TEMPLATES . '/acl/acl.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
