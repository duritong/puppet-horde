<form method="post" name="search" action="<?php echo Horde::applicationUrl('search.php') ?>">
<input type="hidden" name="actionID" value="update_search" />
<input type="hidden" name="delete_field_id" value="" />
<?php if ($edit_query_id): ?>
<input type="hidden" name="edit_query_id" value="<?php echo htmlspecialchars($edit_query_id) ?>" />
<?php endif; ?>
<?php if (!empty($search['mbox'])): ?>
<input type="hidden" name="mbox" value="<?php echo htmlspecialchars($search['mbox']) ?>" />
<?php elseif ($subscribe): ?>
<input type="hidden" name="show_subscribed_only" value="<?php echo htmlspecialchars($shown) ?>" />
<?php endif; ?>
<div align="center">
<table border="0" cellspacing="0" cellpadding="2" width="100%">
 <tr>
  <td class="header leftAlign">
<?php if ($edit_query_vfolder): ?>
   <strong><?php echo _("Edit Virtual Folder") ?></strong>
<?php elseif ($edit_query_id): ?>
   <strong><?php echo _("Edit Search Query") ?></strong>
<?php else: ?>
   <strong><?php printf(_("Search %s"), (!empty($search['mbox'])) ? htmlspecialchars(IMP::displayFolder($search['mbox'])) : '') ?></strong>
<?php endif; ?>
  </td>
  <td class="header rightAlign"><?php echo Help::link('imp', 'search') ?></td>
 </tr>
 <tr>
  <td valign="top" colspan="2">
   <input type="submit" class="button" value="<?php echo $submit_label ?>" onclick="return formCheck();" />
   <input type="button" class="button" value="<?php echo _("Reset") ?>" onclick="document.search.actionID.value = 'reset_search';document.search.submit();return true;" />
  </td>
 </tr>

 <tr><td colspan="2"></td></tr>

 <tr>
  <td class="smallheader leftAlign" colspan="2"><?php echo _("Message matches") ?></td>
 </tr>

 <tr>
  <td class="item" colspan="2">
   <table cellspacing="0" cellpadding="0" width="100%">
    <tr>
     <td class="item1 leftAlign" nowrap="nowrap">
      <input type="radio" name="search_match" id="search_match_or" value="or"<?php if ($search['field_end'] > 0) echo ' onchange="document.search.submit();return true;" onclick="document.search.submit();return true;"'; if ($search['match'] == 'or') echo ' checked="checked"'; ?> />
      <em><?php echo Horde::label('search_match_or', _("Match Any Query")) ?></em>&nbsp;
     </td>
     <td class="item1 leftAlign" nowrap="nowrap">
      <input type="radio" name="search_match" id="search_match_and" value="and"<?php if ($search['field_end'] > 0) echo ' onchange="document.search.submit();return true;" onclick="document.search.submit();return true;"'; if (is_null($search['match']) || ($search['match'] == 'and')) echo ' checked="checked"'; ?> />
      <em><?php echo Horde::label('search_match_and', _("Match All Queries")) ?></em>
     </td>
     <td class="item1" width="100%">&nbsp;</td>
    </tr>
   </table>
  </td>
 </tr>

 <tr>
  <td class="item0 leftAlign" colspan="2">
   <table border="0" cellpadding="1" cellspacing="1" width="100%">
