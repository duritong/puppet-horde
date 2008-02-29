<?php
    switch ($actionID) {
    case 'import_personal_certs':
        $title = _("Import Personal S/MIME Certificates");
        break;

    case 'import_public_key':
        $title = _("Import Public S/MIME Key");
        $insert_text = _("Insert Certificate Here");
        break;
    }
    $certimport = ($actionID == 'import_personal_certs');
?>
<form enctype="multipart/form-data" method="post" name="import_smime_key" action="<?php echo $selfURL ?>">
<?php if ($GLOBALS['browser']->hasQuirk('broken_multipart_form')): ?>
<input type="hidden" name="msie_formdata_is_broken" value="" />
<?php endif; ?>
<input type="hidden" name="reload" value="<?php echo htmlspecialchars(html_entity_decode(Util::getFormData('reload'))); ?>" />
<input type="hidden" name="actionID" value="<?php echo $target ?>" />
<?php Util::pformInput(); ?>

<div class="header">
<?php if ($certimport): ?>
  <ul>
    <li><?php echo Help::link('imp', 'smime-import-personal-certs') ?></li>
  </ul>
<?php endif; ?>
  <?php echo $title ?></td>
</div>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<?php if (isset($insert_text)): ?>
 <tr>
  <td class="item leftAlign">
   <table border="0" cellpadding="0" width="100%">
    <tr>
     <td class="item leftAlign"><strong><?php echo $insert_text ?>:</strong></td>
    </tr>
    <tr>
     <td class="item leftAlign">
      <textarea name="import_key" rows="6" cols="80" class="fixed"></textarea>
     </td>
    </tr>
   </table>
  </td>
 </tr>

 <tr>
  <td class="item leftAlign"><font color="red"><strong>--<?php echo _("OR") ?>--</strong></font></td>
 </tr>
<?php endif; ?>

 <tr>
  <td class="item leftAlign"<?php if ($certimport): ?> colspan="2"<?php endif; ?>>
   <table border="0" cellpadding="0" width="100%">
    <tr>
     <td class="item leftAlign">
      <strong><?php echo _("Upload") ?>:</strong>&nbsp;<input name="upload_key" type="file" size="40" class="fixed" />
     </td>
    </tr>
   </table>
  </td>
 </tr>

<?php if ($certimport): ?>
 <tr>
  <td class="item leftAlign" colspan="2">
   <table border="0" cellpadding="0" width="100%">
    <tr>
     <td class="item leftAlign">
      <strong><?php echo _("Password") ?>:</strong>&nbsp;<input name="upload_key_pass" type="password" size="30" class="fixed" />
     </td>
    </tr>
    <tr>
     <td class="item leftAlign">
      <strong><?php echo _("Private Key Password") ?>:</strong>&nbsp;<input name="upload_key_pk_pass" type="password" size="30" class="fixed" />
     </td>
    </tr>
   </table>
  </td>
 </tr>
<?php endif; ?>
</table>

<input type="submit" name="import" class="button" value="<?php echo _("Import Key") ?>" />
<input type="submit" name="import" class="button" onclick="window.close();" value="<?php echo _("Cancel") ?>" />

</form>
</body>
</html>
