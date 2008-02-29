<form method="post" name="memo" enctype="multipart/form-data" action="<?php echo Horde::applicationUrl('list.php') ?>">
<input type="hidden" name="actionID" value="search_memos" />
<table cellspacing="0" width="100%">
<tr><td align="left" class="header"><strong><?php echo _("Search") ?></strong></td></tr></table>

<table cellspacing="0" width="100%">
<tr>
  <td class="item" align="right" valign="top" nowrap="nowrap">
    <strong><?php echo Horde::label('search_pattern', _("Search _Text")) ?>:</strong>&nbsp;
  </td>
  <td class="item" width="100%">
    <input name="search_pattern" id="search_pattern" type="text" value="" size="50" maxlength="100" />
  </td>
</tr>
<tr>
  <td class="item" align="right" valign="top" nowrap="nowrap">
    <strong><?php echo _("Search Scope") ?>:</strong>&nbsp;
  </td>
  <td class="item" width="100%">
    <input name="search_desc" id="search_desc" type="checkbox" checked="checked" /><?php echo Horde::label('search_desc', _("_First Line")) ?>&nbsp;
    <input name="search_body" id="search_body" type="checkbox" /><?php echo Horde::label('search_body', _("F_ull Text")) ?>
  </td>
</tr>
</table>

<br />

<table width="100%" cellspacing="0">
<tr>
  <td>
    <input type="submit" class="button" value="<?php echo _("Search") ?>" />&nbsp;
    <input type="reset" class="button" value="<?php echo _("Clear Form") ?>" />
  </td>
</tr>
</table>
</form>
