<tr>
  <td class="leftAlign"><img src="<?php echo $viewer->getIcon($mime->getType()) ?>" height="16" width="16" border="0" alt="[Icon]" />&nbsp;</td>
  <td class="leftAlign"><strong><?php echo ($file_num) ?>. <?php echo $mime->getName(true, true) ?></strong>&nbsp;&nbsp;(<?php echo $mime->getType() ?>)&nbsp;&nbsp;&nbsp;<strong><?php echo _("Size") ?>:</strong>&nbsp;<?php echo $mime->getSize() ?> KB<?php if (isset($preview_url)): ?>&nbsp;&nbsp;&nbsp;[<?php echo Horde::link($preview_url, _("Preview") . ' ' . $mime->getName(), 'link', 'compose_preview_window') . _("Preview") ?></a>]<?php endif; ?></td>
</tr>

<tr>
  <td>&nbsp;</td>
  <td>
    <table border="0" width="100%" cellpadding="0">
      <tr>
        <td class="leftAlign" valign="middle"><strong><?php echo _("Disposition") ?>:</strong>&nbsp;</td>
        <td class="leftAlign">
          <select name="file_disposition_<?php echo $file_num ?>">
            <option value="attachment"<?php if ($disposition == 'attachment') echo ' selected="selected"' ?>><?php echo _("Attachment") ?></option>
            <option value="inline"<?php if ($disposition == 'inline') echo ' selected="selected"' ?>><?php echo _("Inline") ?></option>
          </select>
        </td>
      </tr>

      <tr>
        <td class="leftAlign" valign="middle"><strong><?php echo _("Description") ?>:</strong>&nbsp;</td>
        <td class="leftAlign">
          <input type="text" size="40" name="file_description_<?php echo $file_num ?>" value="<?php echo $mime->getDescription() ?>" />
        </td>
      </tr>

      <tr>
        <td class="leftAlign" valign="middle"><strong><?php echo _("Delete") ?>?</strong>&nbsp;</td>
        <td class="leftAlign"><input type="checkbox" name="delattachments[]" value="<?php echo $file_num ?>" /></td>
      </tr>
    </table>
  </td>
</tr>
