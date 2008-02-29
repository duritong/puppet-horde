<?php
/**
 * $Horde: imp/smime.php,v 2.48.4.8 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 * Copyright 2002-2007 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

function _importKeyDialog($target)
{
    global $actionID, $notification, $prefs, $registry, $selfURL;

    $title = _("Import S/MIME Key");
    require IMP_TEMPLATES . '/common-header.inc';
    IMP::status();
    require IMP_TEMPLATES . '/smime/import_key.inc';
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

function _outputPassphraseDialog()
{
    global $notification, $prefs, $registry, $secure_check, $selfURL;

    if (is_a($secure_check, 'PEAR_Error')) {
        $notification->push($secure_check, 'horde.error');
    }

    $title = _("S/MIME Passphrase Input");
    require IMP_TEMPLATES . '/common-header.inc';
    $submit_url = Util::addParameter($selfURL, 'actionID', 'process_passphrase_dialog');
    IMP::status();
    require IMP_TEMPLATES . '/smime/passphrase.inc';
}

function _actionWindow()
{
    require_once 'Horde/SessionObjects.php';
    $oid = Util::getFormData('passphrase_action');
    $cacheSess = &Horde_SessionObjects::singleton();
    $cacheSess->setPruneFlag($oid, true);
    Util::closeWindowJS($cacheSess->query($oid));
}

function _reloadWindow()
{
    Util::closeWindowJS('opener.focus();opener.location.href="' . Util::getFormData('reload') . '";');
}

function _textWindowOutput($filename, $msg, $html = false)
{
    $type = ($html ? 'text/html' : 'text/plain') . '; charset=' . NLS::getCharset();
    $GLOBALS['browser']->downloadHeaders($filename, $type, true, strlen($msg));
    echo $msg;
}

function _printKeyInfo($cert)
{
    $key_info = $GLOBALS['imp_smime']->certToHTML($cert);
    if (empty($key_info)) {
        _textWindowOutput('S/MIME Key Information', _("Invalid key"));
    } else {
        _textWindowOutput('S/MIME Key Information', $key_info, true);
    }
}


@define('IMP_BASE', dirname(__FILE__));
$authentication = OP_HALFOPEN;
require_once IMP_BASE . '/lib/base.php';
require_once IMP_BASE . '/lib/Crypt/SMIME.php';

$imp_smime = &new IMP_SMIME();
$secure_check = $imp_smime->requireSecureConnection();
$selfURL = Horde::applicationUrl('smime.php');

/* Run through the action handlers */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'open_passphrase_dialog':
    if ($imp_smime->getPassphrase() !== false) {
        Util::closeWindowJS();
    } else {
        _outputPassphraseDialog();
    }
    exit;

case 'process_passphrase_dialog':
    if (is_a($secure_check, 'PEAR_Error')) {
        _outputPassphraseDialog();
    } elseif (Util::getFormData('passphrase')) {
        if ($imp_smime->storePassphrase(Util::getFormData('passphrase'))) {
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

case 'delete_key':
    $imp_smime->deletePersonalKeys();
    $notification->push(_("Personal S/MIME keys deleted successfully."), 'horde.success');
    break;

case 'delete_public_key':
    $result = $imp_smime->deletePublicKey(Util::getFormData('email'));
    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result, $result->getCode());
    } else {
        $notification->push(sprintf(_("S/MIME Public Key for \"%s\" was successfully deleted."), Util::getFormData('email')), 'horde.success');
    }
    break;

case 'import_public_key':
    _importKeyDialog('process_import_public_key');
    exit;

case 'process_import_public_key':
    $publicKey = _getImportKey();
    if (empty($publicKey)) {
        $notification->push(_("No S/MIME public key imported."), 'horde.error');
        $actionID = 'import_public_key';
        _importKeyDialog('process_import_public_key');
    } else {
        /* Add the public key to the storage system. */
        $key_info = $imp_smime->addPublicKey($publicKey);
        if (is_a($key_info, 'PEAR_Error')) {
            $notification->push($key_info, 'horde.error');
            $actionID = 'import_public_key';
            _importKeyDialog('process_import_public_key');
        } else {
            $notification->push(_("S/MIME Public Key successfully added."), 'horde.success');
            _reloadWindow();
        }
    }
    exit;

case 'view_public_key':
    $key = $imp_smime->getPublicKey(Util::getFormData('email'));
    if (is_a($key, 'PEAR_Error')) {
        $key = $key->getMessage();
    }
    _textWindowOutput('S/MIME Public Key', $key);
    exit;

case 'info_public_key':
    $key = $imp_smime->getPublicKey(Util::getFormData('email'));
    if (is_a($key, 'PEAR_Error')) {
        $key = $key->getMessage();
    }
    _printKeyInfo($key);
    exit;

case 'view_personal_public_key':
    _textWindowOutput('S/MIME Personal Public Key', $imp_smime->getPersonalPublicKey());
    exit;
case 'info_personal_public_key':
    _printKeyInfo($imp_smime->getPersonalPublicKey());
    exit;

case 'view_personal_private_key':
    _textWindowOutput('S/MIME Personal Private Key', $imp_smime->getPersonalPrivateKey());
    exit;

case 'import_personal_certs':
    _importKeyDialog('process_import_personal_certs');
    exit;

case 'process_import_personal_certs':
    if (!($pkcs12 = _getImportKey())) {
        $notification->push(_("No personal S/MIME certificates imported."), 'horde.error');
        $actionID = 'import_personal_certs';
        _importKeyDialog('process_import_personal_certs');
    } else {
        $res = $imp_smime->addFromPKCS12($pkcs12, Util::getFormData('upload_key_pass'), Util::getFormData('upload_key_pk_pass'));
        if (is_a($res, 'PEAR_Error')) {
            $notification->push(_("Personal S/MIME certificates NOT imported: ") . $res->getMessage(), 'horde.error');
            $actionID = 'import_personal_certs';
            _importKeyDialog('process_import_personal_certs');
        } else {
            $notification->push(_("S/MIME Public/Private Keypair successfully added."), 'horde.success');
            _reloadWindow();
        }
    }
    exit;

case 'save_attachment_public_key':
    require_once 'Horde/SessionObjects.php';

    $cacheSess = &Horde_SessionObjects::singleton();
    $cert = $cacheSess->query(Util::getFormData('cert'));

    /* Add the public key to the storage system. */
    $cert = $imp_smime->addPublicKey($cert, Util::getFormData('from'));
    if ($cert == false) {
        $notification->push(_("No Certificate found"), 'horde.error');
    } else {
        Util::closeWindowJS();
    }
    exit;

case 'unset_passphrase':
    if ($imp_smime->getPassphrase() !== false) {
        $imp_smime->unsetPassphrase();
        $notification->push(_("Passphrase successfully unloaded."), 'horde.success');
    }
    break;

case 'save_options':
    $prefs->setValue('use_smime', Util::getFormData('use_smime'));
    $notification->push(_("Preferences successfully updated."), 'horde.success');
    break;
}

/* Get list of Public Keys. */
$pubkey_list = $imp_smime->listPublicKeys();
if (is_a($pubkey_list, 'PEAR_Error')) {
    $notification->push($pubkey_list, $pubkey_list->getCode());
}

/* Get passphrase (if available). */
$passphrase = $imp_smime->getPassphrase();

require IMP_BASE . '/config/prefs.php';
require_once 'Horde/Prefs/UI.php';
$app = 'imp';
Prefs_UI::generateHeader('smime');

/* If S/MIME preference not active, or openssl PHP extension not available, do
 * NOT show S/MIME Admin screen. */
$openssl_check = $imp_smime->checkForOpenSSL();
if (!is_a($openssl_check, 'PEAR_Error') && $prefs->getValue('use_smime')) {
    $opensmimewin = $imp_smime->getJSOpenWinCode('open_passphrase_dialog');
    Horde::addScriptFile('popup.js');
    require IMP_TEMPLATES . '/smime/smime.inc';
} else {
    require IMP_TEMPLATES . '/smime/notactive.inc';
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
