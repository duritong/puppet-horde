<?php if (!$prefs->isLocked('sent_mail_folder')): ?>
<?php echo Horde::label('sent_mail', _("Sent mail folder:")) ?><br />
<select name="sent_mail" id="sent_mail">
  <option value=""><?php echo _("None") ?></option>
  <option value="-1"><?php echo _("Use Default Value") ?></option>
  <?php echo MIMP::flistSelect('', true, array('INBOX'), $identity->getValue('sent_mail_folder')) ?>
</select>
<?php endif; ?>
