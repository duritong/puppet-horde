<?php if (!is_a($secure_check, 'PEAR_Error')): ?>
<form method="post" name="passphrase_dialog" action="<?php echo $submit_url ?>">
<?php if ($reload = Util::getFormData('reload')): ?>
<input type="hidden" name="reload" value="<?php echo htmlspecialchars(html_entity_decode($reload)) ?>" />
<?php endif; ?>
<?php if (($action = Util::getFormData('passphrase_action'))): ?>
<input type="hidden" name="passphrase_action" value="<?php echo $action ?>" />
<?php endif; ?>

<table border="0" cellspacing="0" cellpadding="2">
 <tr>
  <td>
   <table width="100%" cellspacing="0" cellpadding="2">
    <tr>
     <td class="smallheader leftAlign">
      <?php echo Horde::img('locked.png', _("PGP"), null, $registry->getImageDir('horde')) ?>
     </td>
     <td class="smallheader leftAlign">
      <?php printf(_("Please enter the passphrase for your PGP private key. You will only need to enter this passphrase once per %s session."),
                   $registry->get('name')) ?>
     </td>
    </tr>
   </table>
  </td>
 </tr>

 <tr>
  <td class="light leftAlign">
   <strong><?php echo _("Passphrase:") ?></strong>&nbsp;
   <input type="password" name="passphrase" size="15" maxlength="60" class="fixed" />&nbsp;
   <input type="submit" name="submit" class="button" value="<?php echo _("Submit") ?>" />&nbsp;
   <input type="button" class="button" onclick="window.close();" value="<?php echo _("Cancel") ?>" />
  </td>
 </tr>
</table>

</form>

<script type="text/javascript">
document.passphrase_dialog.passphrase.focus();
</script>
<?php endif; ?>

</body>
</html>
