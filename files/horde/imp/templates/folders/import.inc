<form name="fmanager" method="post" enctype="multipart/form-data" action="<?php
echo $folders_url ?>">
<input type="hidden" name="import_folder" value="<?php echo $folder_list[0] ?>" />
<input type="hidden" name="actionID" value="" />

<table width="100%" cellspacing="0" cellpading="0">
  <tr>
    <td class="header">
      <?php echo _("Import mbox File") ?>
      </td>
  </tr>

  <tr class="item">
    <td>
      <br />&nbsp;&nbsp;
      <?php printf(_("Import mbox file %s into folder %s."),
                   '&nbsp;&nbsp;<input name="mbox_upload" type="file" size="30" class="fixed" />&nbsp;&nbsp;',
                   '<strong>' . String::convertCharset($folder_list[0], 'UTF7-IMAP') . '</strong>') ?>
      &nbsp;&nbsp;
      <input type="button" class="button" value="<?php echo _("Import") ?>" onclick="submitAction('import_mbox')"/>
      <input type="button" class="button" value="<?php echo _("Cancel") ?>" onclick="submitAction(0)"/>
      <br /><br />
    </td>
  </tr>
</table>

</form>
