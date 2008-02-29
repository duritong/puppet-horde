<?php
/**
 * $Horde: imp/pgp.php,v 2.79.6.9 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

function _printKeyInfo($key = '')
{
    $key_info = $GLOBALS['imp_pgp']->pgpPrettyKey($key);

    if (empty($key_info)) {
        _textWindowOutput('PGP Key Information', _("Invalid key"));
    } else {
        _textWindowOutput('PGP Key Information', $key_info);
    }
}

function _outputPassphraseDialog()
{
    global $notification, $prefs, $registry, $secure_check, $selfURL;

    if (is_a($secure_check, 'PEAR_Error')) {
        $notification->push($secure_check, 'horde.warning');
    }

    $title = _("PGP Passphrase Input");
    require IMP_TEMPLATES . '/common-header.inc';
    $submit_url = Util::addParameter($selfURL, 'actionID', 'process_passphrase_dialog');
    IMP::status();
    require IMP_TEMPLATES . '/pgp/passphrase.inc';
}

function _importKeyDialog($target)
{
    global $actionID, $notification, $prefs, $registry, $selfURL;

    $title = _("Import PGP Key");
    require IMP_TEMPLATES . '/common-header.inc';
    IMP::status();
    require IMP_TEMPLATES . '/pgp/import_key.inc';
}

function _actionWindow()
{
    $oid = Util::getFormData('passphrase_action');
    require_once 'Horde/SessionObjects.php';
    $cacheSess = &Horde_SessionObjects::singleton();
    $cacheSess->setPruneFlag($oid, true);
    Util::closeWindowJS($cacheSess->query($oid));
}

function _reloadWindow()
{
    Util::closeWindowJS('opener.focus();opener.location.href="' . Util::getFormData('reload') . '";');
}

function _getImportKey()
{
    $key = Util::getFormData('import_key');
    if (!empty($key)) {
        return $key;
    }

    $res = Browser::wasFileUploaded('upload_key', _("key"));
    if (!is_a($res, 'PEAR_Error')) {
        return file_get_contents($_FILES['upload_key']['tmp_name']);
    } else {
        $GLOBALS['notification']->push($res, 'horde.error');
        return;
    }
}

function _textWindowOutput($filename, $msg)
{
    $GLOBALS['browser']->downloadHeaders($filename, 'text/plain; charset=' . NLS::getCharset(), true, strlen($msg));
    echo $msg;
}


@define('IMP_BASE', dirname(__FILE__));
$authentication = OP_HALFOPEN;
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Crypt/PGP.php';

$imp_pgp = &new IMP_PGP();
$secure_check = $imp_pgp->requireSecureConnection();
$selfURL = Horde::applicationUrl('pgp.php');

/* Run through the action handlers */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'generate_key':
    /* Check that fields are filled out (except for Comment) and that the
       passphrases match. */
    $realname = Util::getFormData('generate_realname');
    $email = Util::getFormData('generate_email');
    $comment = Util::getFormData('generate_comment');
    $keylength = Util::getFormData('generate_keylength');
    $passphrase1 = Util::getFormData('generate_passphrase1');
    $passphrase2 = Util::getFormData('generate_passphrase2');

    if (empty($realname) || empty($email)) {
        $notification->push(_("Name and/or email cannot be empty"), 'horde.error');
    } elseif (empty($passphrase1) || empty($passphrase2)) {
        $notification->push(_("Passphrases cannot be empty"), 'horde.error');
    } elseif ($passphrase1 !== $passphrase2) {
        $notification->push(_("Passphrases do not match"), 'horde.error');
    } else {
        $result = $imp_pgp->generatePersonalKeys($realname, $email, $passphrase1, $comment, $keylength);
        if (is_a($result, 'PEAR_Error')) {
            $notification->push($result, $result->getCode());
        } else {
            $notification->push(_("Personal PGP keypair generated successfully."), 'horde.success');
        }
    }
    break;

case 'delete_key':
    $imp_pgp->deletePersonalKeys();
    $notification->push(_("Personal PGP keys deleted successfully."), 'horde.success');
    break;

case 'import_public_key':
    _importKeyDialog('process_import_public_key');
    exit;

case 'process_import_public_key':
    $publicKey = _getImportKey();
    if (empty($publicKey)) {
        $notification->push(_("No PGP public key imported."), 'horde.error');
        $actionID = 'import_public_key';
        _importKeyDialog('process_import_public_key');
    } else {
        /* Add the public key to the storage system. */
        $key_info = $imp_pgp->addPublicKey($publicKey);
        if (is_a($key_info, 'PEAR_Error')) {
            $notification->push($key_info, 'horde.error');
            $actionID = 'import_public_key';
            _importKeyDialog('process_import_public_key');
        } else {
            foreach ($key_info['signature'] as $sig) {
                $notification->push(sprintf(_("PGP Public Key for \"%s (%s)\" was successfully added."), $sig['name'], $sig['email']), 'horde.success');
            }
            _reloadWindow();
        }
    }
    exit;

case 'import_personal_public_key':
    _importKeyDialog('process_import_personal_public_key');
    exit;

case 'process_import_personal_public_key':
    $actionID = 'import_personal_public_key';
    /* Check the public key. */
    if (!($publicKey = _getImportKey())) {
        /* No public key imported - Redo public key import screen. */
        $notification->push(_("No personal PGP public key imported."), 'horde.error');
        _importKeyDialog('process_import_personal_public_key');
    } else {
        if (!($key_info = $imp_pgp->pgpPacketInformation($publicKey)) ||
            !isset($key_info['public_key'])) {
            /* Invalid public key imported - Redo public key import screen. */
            $notification->push(_("Invalid personal PGP public key."), 'horde.error');
            _importKeyDialog('process_import_personal_public_key');
        } else {
            /* Success in importing public key - Move on to private key
             * now. */
            $imp_pgp->addPersonalPublicKey($publicKey);
            $notification->push(_("PGP public key successfully added."), 'horde.success');
            $actionID = 'import_personal_private_key';
            _importKeyDialog('process_import_personal_private_key');
        }
    }
    exit;

case 'process_import_personal_private_key':
    $actionID = 'import_personal_private_key';
    /* Check the private key. */
    if (!($privateKey = _getImportKey())) {
        /* No private key imported - Redo private key import screen. */
        $notification->push(_("No personal PGP private key imported."), 'horde.error');
        _importKeyDialog('process_import_personal_private_key');
    } else {
        if (!($key_info = $imp_pgp->pgpPacketInformation($privateKey)) ||
            !isset($key_info['secret_key'])) {
            /* Invalid private key imported - Redo private key import
             * screen. */
            $notification->push(_("Invalid personal PGP private key."), 'horde.error');
            _importKeyDialog('process_import_personal_private_key');
        } else {
            /* Personal public and private keys have been imported
             * successfully - close the import popup window. */
            $imp_pgp->addPersonalPrivateKey($privateKey);
            $notification->push(_("PGP private key successfully added."), 'horde.success');
            _reloadWindow();
        }
    }
    exit;

case 'view_public_key':
    $key = $imp_pgp->getPublicKey(Util::getFormData('email'));
    if (is_a($key, 'PEAR_Error')) {
        $key = $key->getMessage();
    }
    _textWindowOutput('PGP Public Key', $key);
    exit;

case 'view_personal_public_key':
    _textWindowOutput('PGP Personal Public Key', $imp_pgp->getPersonalPublicKey());
    exit;

case 'info_public_key':
    $key = $imp_pgp->getPublicKey(Util::getFormData('email'));
    if (is_a($key, 'PEAR_Error')) {
        $key = $key->getMessage();
    }
    _printKeyInfo($key);
    exit;

case 'info_personal_public_key':
    _printKeyInfo($imp_pgp->getPersonalPublicKey());
    exit;

case 'view_personal_private_key':
    _textWindowOutput('PGP Personal Private Key', $imp_pgp->getPersonalPrivateKey());
    exit;

case 'info_personal_private_key':
    _printKeyInfo($imp_pgp->getPersonalPrivateKey());
    exit;

case 'delete_public_key':
    $result = $imp_pgp->deletePublicKey(Util::getFormData('email'));
    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result, $result->getCode());
    } else {
        $notification->push(sprintf(_("PGP Public Key for \"%s\" was successfully deleted."), Util::getFormData('email')), 'horde.success');
    }
    break;

case 'pgp_enable':
    $prefs->setValue('use_pgp', Util::getFormData('use_pgp'));
    break;

case 'save_options':
    $prefs->setValue('use_pgp', Util::getFormData('use_pgp'));
    $prefs->setValue('pgp_attach_pubkey', Util::getFormData('pgp_attach_pubkey'));
    $prefs->setValue('pgp_scan_body', Util::getFormData('pgp_scan_body'));
    $notification->push(_("Preferences successfully updated."), 'horde.success');
    break;

case 'save_attachment_public_key':
    require_once 'Horde/SessionObjects.php';
    require_once 'Horde/MIME/Part.php';

    /* Retrieve the key from the cache. */
    $cache = &Horde_SessionObjects::singleton();
    $mime_part = $cache->query(Util::getFormData('mimecache'));
    $mime_part->transferDecodeContents();

    /* Add the public key to the storage system. */
    $key_info = $imp_pgp->addPublicKey($mime_part->getContents());
    if (is_a($key_info, 'PEAR_Error')) {
        $notification->push($key_info, $key_info->getCode());
    } else {
        Util::closeWindowJS();
    }
    exit;

case 'open_passphrase_dialog':
    if ($imp_pgp->getPassphrase()) {
        Util::closeWindowJS();
    } else {
        _outputPassphraseDialog();
    }
    exit;

case 'process_passphrase_dialog':
    if (is_a($secure_check, 'PEAR_Error')) {
        _outputPassphraseDialog();
    } elseif (Util::getFormData('passphrase')) {
        if ($imp_pgp->storePassphrase(Util::getFormData('passphrase'))) {
            if (Util::getFormData('passphrase_action')) {
                _actionWindow();
            } elseif (Util::getFormData('reload')) {
                _reloadWindow();
            } else {
                Util::closeWindowJS();
            }
        } else {
            $notification->push("Invalid passphrase entered.", 'horde.error');
            _outputPassphraseDialog();
        }
    } else {
        $notification->push("No passphrase entered.", 'horde.error');
        _outputPassphraseDialog();
    }
    exit;

case 'unset_passphrase':
    $imp_pgp->unsetPassphrase();
    $notification->push(_("Passphrase successfully unloaded."), 'horde.success');
    break;

case 'send_public_key':
    $result = $imp_pgp->sendToPublicKeyserver($imp_pgp->getPersonalPublicKey());
    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result, $result->getCode());
    } else {
        $notification->push(_("Key successfully sent to the public keyserver."), 'horde.success');
    }
    break;
}

/* Get list of Public Keys on keyring. */
$pubkey_list = $imp_pgp->listPublicKeys();
if (is_a($pubkey_list, 'PEAR_Error')) {
    $notification->push($pubkey_list, $pubkey_list->getCode());
}

/* Get passphrase (if available). */
$passphrase = $imp_pgp->getPassphrase();

require IMP_BASE . '/config/prefs.php';
require_once 'Horde/Prefs/UI.php';
$app = 'imp';
Prefs_UI::generateHeader('pgp');

/* If PGP preference not active, do NOT show PGP Admin screen. */
if ($prefs->getValue('use_pgp')) {
    $openpgpwin = $imp_pgp->getJSOpenWinCode('open_passphrase_dialog');
    Horde::addScriptFile('popup.js');
    require IMP_TEMPLATES . '/pgp/pgp.inc';
} else {
    require IMP_TEMPLATES . '/pgp/notactive.inc';
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
