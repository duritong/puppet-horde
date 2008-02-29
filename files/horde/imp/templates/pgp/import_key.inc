<?php
    switch ($actionID) {
    case 'import_public_key':
        $title = _("Import Public PGP Key");
        $insert_text = _("Insert Public PGP Key Here");
        break;

    case 'import_personal_public_key':
        $title = _("Import Personal Public PGP Key");
        $insert_text = _("Insert Personal Public PGP Key Here");
        break;

    case 'import_personal_private_key':
        $title = _("Import Personal Private PGP Key");
        $insert_text = _("Insert Personal Private PGP Key Here");
        break;
    }
?>
<form enctype="multipart/form-data" method="post" name="import_pgp_key" action="<?php echo $selfURL ?>">
<?php if ($GLOBALS['browser']->hasQuirk('broken_multipart_form')): ?>
<input type="hidden" name="msie_formdata_is_broken" value="" />
<?php endif; ?>
<input type="hidden" name="reload" value="<?php echo htmlspecialchars(html_entity_decode(Util::getFormData('reload'))) ?>" />
<input type="hidden" name="actionID" value="<?php echo $target ?>" />
<?php Util::pformInput(); ?>

<table border="0" cellspacing="0" cellpadding="2" width="100%">
 <tr>
  <td class="header leftAlign" nowrap="nowrap"><?php echo $title ?></td>
 </tr>

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

 <tr>
  <td class="item leftAlign">
   <table border="0" cellpadding="0" width="100%">
    <tr>
     <td class="item leftAlign">
      <strong><?php echo _("Upload") ?>:</strong>&nbsp;<input name="upload_key" type="file" size="40" class="fixed" />
     </td>
    </tr>
   </table>
  </td>
 </tr>

 <tr>
  <td nowrap="nowrap" align="center" class="header" valign="middle">
   <input type="submit" name="import" class="button" value="<?php echo _("Import Key") ?>" />
   <input type="submit" name="import" class="button" onclick="window.close();" value="<?php echo _("Cancel") ?>" />
  </td>
 </tr>
</table>

</form>
</body>
</html>
