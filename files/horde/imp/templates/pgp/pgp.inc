<input type="checkbox" id="use_pgp" name="use_pgp"<?php echo ($prefs->getValue('use_pgp')) ? ' checked="checked"' : '' ?> /> <label for="use_pgp"><?php echo _("Enable PGP functionality?") ?></label>&nbsp;<?php echo Help::link('imp', 'pgp-overview') ?><br />
<strong style="color:red"><?php echo _("NOTE") ?>:</strong> <?php echo _("PGP support requires popup windows to be used.  If your browser is currently set to disable popup windows, you must change this setting or else the PGP features will not work correctly.") ?><br />
<?php if (!$prefs->isLocked('pgp_attach_pubkey')): ?>
<input type="checkbox" id="pgp_attach_pubkey" name="pgp_attach_pubkey" <?php echo ($prefs->getValue('pgp_attach_pubkey')) ? "checked=\"checked\"" : "" ?> /> <label for="pgp_attach_pubkey"><?php echo _("Should your PGP public key to be attached to your messages by default?") ?></label>&nbsp;<?php echo Help::link('imp', 'pgp-option-attach-pubkey') ?><br />
<?php endif; ?>
<?php if (!$prefs->isLocked('pgp_scan_body')): ?>
<input type="checkbox" id="pgp_scan_body" name="pgp_scan_body" <?php echo ($prefs->getValue('pgp_scan_body')) ? "checked=\"checked\"" : "" ?> /> <label for="pgp_scan_body"><?php echo _("Should the body of text/plain messages be scanned for PGP data?") ?></label>&nbsp;<?php echo Help::link('imp', 'pgp-option-scan-body') ?><br />
<?php endif; ?>
</div>

<br class="spacer" />
<div class="header">
  <ul><li><?php echo Help::link('imp', 'pgp-manage-pubkey') ?></li></ul>
  <?php echo _("PGP Public Keyring") ?>
</div>

<div class="item">
<?php if (empty($pubkey_list)): ?>
<strong><?php echo _("No Keys in Keyring") ?></strong>
<?php elseif (is_a($pubkey_list, 'PEAR_Error')): ?>
<strong><?php echo _("Error") ?>:</strong> <?php echo $pubkey_list->getMessage() ?>
<?php else: ?>
<table>
<?php foreach ($pubkey_list as $val): $linkurl = Util::addParameter($selfURL, 'email', $val['email']); ?>
  <tr>
    <td><?php echo $val['name'] ?> (<?php echo $val['email'] ?>)</td>
    <td>&nbsp;&nbsp;[<?php echo Horde::link(Util::addParameter($linkurl, 'actionID', 'view_public_key'), sprintf(_("View %s Public Key"), $val['name']), null, 'view_key'); ?><?php echo _("View") ?></a>]&nbsp;[<?php echo Horde::link(Util::addParameter($linkurl, 'actionID', 'info_public_key'), sprintf(_("Information on %s Public Key"), $val['name']), null, 'info_key'); ?><?php echo _("Details") ?></a>]&nbsp;[<?php echo Horde::link(Util::addParameter($linkurl, 'actionID', 'delete_public_key'), sprintf(_("Delete %s Public Key"), $val['name']), null, null, "if (confirm('" . addslashes(_("Are you sure you want to delete this public key?")) . "')) { return true; } else { return false; }"); ?><?php echo _("Delete") ?></a>]</td>
  </tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>

<br class="spacer" />
<?php if (!$imp['file_upload']): ?>
  <span style="color:red"><?php echo _("Key import is not available. File upload is not enabled on this server.") ?></span>
<?php elseif (!$GLOBALS['prefs']->getValue('add_source')): ?>
  <strong><?php echo _("Key import is not available. You have no address book defined to add your contacts.") ?></strong>
<?php else: ?>
  <?php $public_import_url = Util::addParameter(Util::addParameter($selfURL, 'actionID', 'import_public_key'), 'reload', $selfURL); ?>
  <input type="submit" name="save" class="button" onclick="popup_imp('<?php echo $public_import_url ?>',750,275); return false;" value="<?php echo _("Import Public Key") ?>" />
  <?php echo Help::link('imp', 'pgp-import-pubkey') ?>
<?php endif; ?>

<br class="spacer" />
<div class="header">
  <ul><li><?php echo Help::link('imp', 'pgp-overview-personalkey') ?></li></ul>
  <?php echo _("Your PGP Public/Private Keys") ?>
</div>

<div class="item">
<?php if (is_a($secure_check, 'PEAR_Error')): ?>
<strong><?php echo _("PGP Personal Keypair support requires a secure web connection.") ?></strong>
</div>

<?php
elseif ($prefs->getValue('pgp_public_key') &&
        $prefs->getValue('pgp_private_key')):
    $viewpublic_url = Util::addParameter($selfURL, 'actionID', 'view_personal_public_key');
    $infopublic_url = Util::addParameter($selfURL, 'actionID', 'info_personal_public_key');
    $sendpublic_url = Util::addParameter($selfURL, 'actionID', 'send_public_key');
    $unload_url = Util::addParameter($selfURL, 'actionID', 'unset_passphrase');
    $viewprivate_url = Util::addParameter($selfURL, 'actionID', 'view_personal_private_key');
    $infoprivate_url = Util::addParameter($selfURL, 'actionID', 'info_personal_private_key');
?>

<table>
  <tr>
    <td><?php echo _("Your Public Key") ?>:</td>
    <td>&nbsp;&nbsp;[<?php echo Horde::link($viewpublic_url, _("View Personal Public Key"), null, 'view_key') . _("View") ?></a>]&nbsp;[<?php echo Horde::link($infopublic_url, _("Information on Personal Public Key"), null, 'info_key') . _("Details") ?></a>]&nbsp;[<?php echo Horde::link($sendpublic_url, _("Send Key to Public Keyserver")) . _("Send Key to Public Keyserver") ?></a>]</td>
    <td><?php echo Help::link('imp', 'pgp-personalkey-public') ?></td>
  </tr>
  <tr>
    <td><?php echo _("Your Private Key") ?>:</td>
    <td>&nbsp;&nbsp;[<?php echo (empty($passphrase)) ? Horde::link('#', _("Enter Passphrase"), null, null, htmlspecialchars($openpgpwin) . ' return false;') . _("Enter Passphrase") : Horde::link($unload_url, _("Unload Passphrase")) . _("Unload Passphrase") ?></a>]&nbsp;[<?php echo Horde::link($viewprivate_url, _("View Personal Private Key"), null, 'view_key') . _("View") ?></a>]&nbsp;[<?php echo Horde::link($infoprivate_url, _("Information on Personal Private Key"), null, 'info_key') . _("Details") ?></a>]</td>
    <td><?php echo Help::link('imp', 'pgp-personalkey-private') ?></td>
  </tr>
</table>
</div>

<br class="spacer" />
<div class="nowrap">
  <input type="submit" name="delete" class="button" onclick="if (confirm('<?php echo addslashes(_("Are you sure you want to delete your keypair? (This is NOT recommended!)")) ?>')) { document.prefs.actionID.value='delete_key'; return true; } else { return false; }" value="<?php echo _("Delete Current Keys") ?>" />
  <?php echo Help::link('imp', 'pgp-personalkey-delete') ?>
</div>

<?php else: ?>

<?php
require_once 'Horde/Identity.php';
$imp_identity = &Identity::singleton(array('imp', 'imp'));
$personal_import_url = Util::addParameter($selfURL, 'actionID', 'import_personal_public_key');
?>
<table>
  <tr>
    <td><?php echo _("Your Name") ?>:</td>
    <td>&nbsp;&nbsp;<input type="text" name="generate_realname" size="30" maxlength="60" class="fixed" value="<?php echo $imp_identity->getFullname(); ?>"/></td>
    <td><?php echo Help::link('imp', 'pgp-personalkey-create-name') ?></td>
  </tr>
  <tr>
    <td><?php echo _("Comment") ?>:</td>
    <td>&nbsp;&nbsp;<input type="text" name="generate_comment" size="30" maxlength="60" class="fixed" /></td>
    <td><?php echo Help::link('imp', 'pgp-personalkey-create-comment') ?></td>
  </tr>
  <tr>
    <td><?php echo _("E-mail Address") ?>:</td>
    <td>&nbsp;&nbsp;<input type="text" name="generate_email" size="30" maxlength="60" class="fixed" value="<?php echo $imp_identity->getFromAddress(); ?>"/></td>
    <td><?php echo Help::link('imp', 'pgp-personalkey-create-email') ?></td>
  </tr>
  <tr>
    <td><?php echo _("Key Length") ?>:</td>
    <td>&nbsp;
      <select name="generate_keylength">
        <option value="1024">1024</option>
        <option value="2048">2048</option>
      </select>
    </td>
    <td><?php echo Help::link('imp', 'pgp-personalkey-create-keylength') ?></td>
  </tr>
  <tr>
    <td><?php echo _("Passphrase") ?>:</td>
    <td>&nbsp;&nbsp;<input type="password" name="generate_passphrase1" size="30" maxlength="60" class="fixed" /></td>
    <td><?php echo Help::link('imp', 'pgp-personalkey-create-passphrase') ?></td>
  </tr>
  <tr>
    <td><?php echo _("Passphrase (Again)") ?>:</td>
    <td>&nbsp;&nbsp;<input type="password" name="generate_passphrase2" size="30" maxlength="60" class="fixed" /></td>
    <td>&nbsp;</td>
  </tr>
</table>
</div>

<br class="spacer" />
<div class="nowrap">
  <input type="submit" name="save" class="button" onclick="if (confirm('<?php echo addslashes(_("Key generation may take a long time to complete.  Continue with key generation?")) ?>')) { document.prefs.actionID.value='generate_key';return true; } else { return false; }" value="<?php echo _("Create Keys") ?>" />
  <?php if ($imp['file_upload']): ?>
  <input type="submit" name="save" class="button" onclick="popup_imp('<?php echo $personal_import_url ?>',750,275);return false;" value="<?php echo _("Import Keypair") ?>" />
  <?php endif; ?>
  <?php echo Help::link('imp', 'pgp-personalkey-create-actions') ?>
</div>

<?php endif; ?>

<br class="spacer" />
<div class="nowrap">
  <input type="submit" name="save" class="button" onclick="document.prefs.actionID.value='save_options';return true;" value="<?php echo _("Save Options") ?>" />
  <input type="button" name="back" class="button" onclick="document.location.href='<?php echo IMP::prefsURL(true) ?>'" value="<?php echo _("Return to Options") ?>" />
</div>

</form>
