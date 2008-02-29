<form method="post" name="accounts" action="<?php echo $fetch_url ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="fetchmail_pref_save" />
<input type="hidden" name="edit_account" value="<?php if (!is_null($to_edit)) echo $to_edit; ?>" />
<input type="hidden" name="fm_driver" value="<?php echo $driver ?>" />

<?php echo _("Account's name:") ?><br />
<input name="fm_id" size="30" maxlength="60" value="<?php if (!is_null($to_edit)) echo $fm_account->getValue('id', $to_edit) ?>" /><br />

<?php echo _("Protocol:") ?><br />
<select name="fm_protocol">
<?php foreach ($fetchmail->getProtocolList() as $key => $val): ?>
  <option value="<?php echo $key ?>"<?php if (!is_null($to_edit) && ($fm_account->getValue('protocol', $to_edit) == $key)) echo ' selected="selected"' ?>><?php echo $val ?></option>
<?php endforeach; ?>
</select><br />

<?php echo _("Username:") ?><br />
<input name="fm_username" size="30" maxlength="60" value="<?php if (!is_null($to_edit)) echo $fm_account->getValue('username', $to_edit) ?>" /><br />

<?php echo _("Password") ?><br />
<input name="fm_password" size="30" maxlength="60" type="password" value="<?php if (!is_null($to_edit)) echo $fm_account->getValue('password', $to_edit) ?>" /><br />

<?php echo _("Server:") ?><br />
<input name="fm_server" size="30" maxlength="60" value="<?php if (!is_null($to_edit)) echo $fm_account->getValue('server', $to_edit) ?>" /><br />

<?php if ($conf['user']['allow_folders']): ?>
<?php echo _("Remote Mailbox:") ?><br />
<input name="fm_rmailbox" size="30" maxlength="60" value="<?php if (!is_null($to_edit)) echo $fm_account->getValue('rmailbox', $to_edit) ?>" /><br />

<?php echo _("Local Mailbox:") ?><br />
<select name="fm_lmailbox">
  <?php echo IMP::flistSelect('', false, array(), !is_null($to_edit) ? $fm_account->getValue('lmailbox', $to_edit) : '') ?>
</select><br />
<?php endif; ?>

<input type="checkbox" name="fm_onlynew" value="1"<?php if (!is_null($to_edit) && $fm_account->getValue('onlynew', $to_edit)) echo ' checked="checked"' ?> />
<?php echo _("Get only new messages? (IMAP only)") ?><br />

<input type="checkbox" name="fm_markseen" value="1"<?php if (!is_null($to_edit) && $fm_account->getValue('markseen', $to_edit)) echo ' checked="checked"' ?> />
<?php echo _("Mark messages as seen? (IMAP only)") ?><br />

<input type="checkbox" name="fm_del" value="1"<?php if (!is_null($to_edit) && $fm_account->getValue('del', $to_edit)) echo ' checked="checked"' ?> />
<?php echo _("Delete fetched messages from remote server?") ?><br />

<input type="checkbox" name="fm_loginfetch" value="1"<?php if (!is_null($to_edit) && $fm_account->getValue('loginfetch', $to_edit)) echo ' checked="checked"' ?> />
<?php echo _("Fetch messages upon logging on?") ?><br />

(<font color="red"><?php echo _("NOTE") ?>:</font> <?php printf(_("You need to have \"%s\" enabled in your maintenance options."), _("Perform maintenance operations on login?")) ?>)

<?php if ($conf['fetchmail']['show_account_colors']): ?>
<?php echo _("Color Indicator: "); ?><br />
<table>
  <tr>
<?php foreach ($fm_colors as $val): ?>
    <td bgcolor="<?php echo $val ?>">
      <input type="radio" name="fm_acctcolor" value="<?php echo $val ?>"<?php if (!is_null($to_edit) && $fm_account->getValue('acctcolor', $to_edit) == $val) echo ' checked="checked"' ?> />
    </td>
<?php endforeach; ?>
  </tr>
</table>
<?php endif; ?>

</td></tr></table></td></tr></table>

<br />
<table border="0" align="center" width="100%" cellspacing="0" cellpadding="0"><tr><td nowrap="nowrap">
<input type="submit" name="save" class="button" onclick="document.accounts.actionID.value='fetchmail_prefs_save';return true" value="<?php echo ($actionID == 'fetchmail_create') ? _("Create") : _("Save") ?>" />
<input type="submit" name="delete" class="button" onclick="document.accounts.actionID.value='fetchmail_prefs_delete'" value="<?php echo _("Delete") ?>" />
<input type="button" name="back" class="button" onclick="document.location.href='<?php echo $prefs_url ?>'" value="<?php echo _("Return to Fetch Mail") ?>" />
<input type="button" name="select" class="button" onclick="document.location.href='<?php echo $fetch_url ?>'" value="<?php echo _("Select Another Account") ?>" />
</td></tr></table>
</form>
