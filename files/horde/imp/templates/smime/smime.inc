<input id="use_smime" type="checkbox" name="use_smime"<?php echo ($prefs->getValue('use_smime')) ? ' checked="checked"' : '' ?> /> <?php echo Horde::label('use_smime', _("Enable S/MIME functionality?")) ?>&nbsp;<?php echo Help::link('imp', 'smime-overview') ?><br />
<strong style="color:red"><?php echo _("NOTE") ?>:</strong> <?php echo _("S/MIME support requires popup windows to be used.  If your browser is currently set to disable popup windows, you must change this setting or else the S/MIME features will not work correctly.") ?><br />
</div>

<br class="spacer" />
<div class="header">
  <ul>
    <li><?php echo Help::link('imp', 'smime-manage-pubkey') ?></li>
  </ul>
  <?php echo _("S/MIME Public Keyring") ?>
</div>

<div class="item">
<?php if (empty($pubkey_list)): ?>
<strong><?php echo _("No Public Keys Found") ?></strong>

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
<div class="nowrap">
<?php if (!$imp['file_upload']): ?>
  <span style="color:red"><?php echo _("Key import is not available. File upload is not enabled on this server.") ?></span>
<?php elseif (!$GLOBALS['prefs']->getValue('add_source')): ?>
  <strong><?php echo _("Key import is not available. You have no address book defined to add your contacts.") ?></strong>
<?php else: ?>
  <?php $public_import_url = Util::addParameter($selfURL, array('actionID' => 'import_public_key', 'reload' => $selfURL)); ?>
  <input type="submit" name="save" class="button" onclick="popup_imp('<?php echo $public_import_url ?>',750,275); return false;" value="<?php echo _("Import Public Key") ?>" />
<?php endif; ?>
</div>

<br class="spacer" />
<div class="header">
  <ul>
    <li><?php echo Help::link('imp', 'smime-overview-personalkey') ?></li>
  </ul>
  <?php echo _("Your S/MIME Public/Private Certificates") ?>
</div>

<div class="item">
<?php if (is_a($secure_check, 'PEAR_Error')): ?>
<strong><?php echo _("S/MIME Personal Keypair support requires a secure web connection.") ?></strong>
</div>

<?php
elseif ($prefs->getValue('smime_public_key') &&
        $prefs->getValue('smime_private_key')):
    $viewpublic_url = Util::addParameter($selfURL, 'actionID', 'view_personal_public_key');
    $infopublic_url = Util::addParameter($selfURL, 'actionID', 'info_personal_public_key');
    $unload_url = Util::addParameter($selfURL, 'actionID', 'unset_passphrase');
    $viewprivate_url = Util::addParameter($selfURL, 'actionID', 'view_personal_private_key');
?>
<table>
  <tr>
    <td><?php echo _("Your Public Key") ?>:</td>
    <td>&nbsp;&nbsp;[<?php echo Horde::link($viewpublic_url, _("View Personal Public Key"), null, 'view_key') . _("View") ?></a>]&nbsp;[<?php echo Horde::link($infopublic_url, _("Information on Personal Public Key"), null, 'info_key') . _("Details") ?></a>]</td>
  </tr>
  <tr>
    <td><?php echo _("Your Private Key") ?>:</td>
    <td>&nbsp;&nbsp;<?php if ($passphrase !== null): ?>[<?php echo (empty($passphrase)) ? Horde::link('#', _("Enter Passphrase"), null, null, htmlspecialchars($opensmimewin) . ' return false;') . _("Enter Passphrase") : Horde::link($unload_url, _("Unload Passphrase")) . _("Unload Passphrase") ?></a>]&nbsp;<?php endif; ?>[<?php echo Horde::link($viewprivate_url, _("View Personal Private Key"), null, 'view_key') . _("View") ?></a>]</td>
  </tr>
</table>
</div>

<br class="spacer" />
<div class="nowrap">
  <input type="submit" name="delete" class="button" onclick="if (confirm('<?php echo addslashes(_("Are you sure you want to delete your keypair? (This is NOT recommended!)")) ?>')) { document.prefs.actionID.value='delete_key'; return true; } else { return false; }" value="<?php echo _("Delete Current Keys") ?>" />
  <?php echo Help::link('imp', 'smime-delete-personal-certs') ?>
</div>

<?php else: ?>
<strong><?php echo _("No Personal Certificate Present") ?></strong>
</div>

<br class="spacer" />
<div class="nowrap">
<?php   if ($imp['file_upload']): ?>
  <?php $personal_import_url = Util::addParameter($selfURL, 'actionID', 'import_personal_certs'); ?>
  <input type="submit" name="save" class="button" onclick="popup_imp('<?php echo $personal_import_url ?>',750,275); return false;" value="<?php echo _("Import Personal Certificate") ?>" />
<?php   else: ?>
  <span style="color:red"><?php echo _("Certificate import is not available. File upload is not enabled on this server.") ?></span>
<?php   endif; ?>
</div>
<?php endif; ?>

<br class="spacer" />
<div class="nowrap">
  <input type="submit" name="save" class="button" onclick="document.prefs.actionID.value='save_options';return true;" value="<?php echo _("Save Options") ?>" />&nbsp;
  <input type="button" name="back" class="button" onclick="document.location.href='<?php echo IMP::prefsURL(true) ?>'" value="<?php echo _("Return to Options") ?>" />
</div>

</form>
