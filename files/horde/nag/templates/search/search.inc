<form method="post" name="search" action="list.php">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="search_tasks" />

<h1 class="header">
 <?php echo _("Search") ?>
</h1>

<div class="headerbox">

<table cellspacing="0" width="100%" class="linedRow">
 <tr>
  <td class="rightAlign nowrap">
   <strong><?php echo Horde::label('search_pattern', _("Search _Text")) ?>:</strong>&nbsp;
  </td>
  <td width="100%">
   <input name="search_pattern" id="search_pattern" type="text" size="50" maxlength="100" />
  </td>
 </tr>
 <tr>
  <td class="rightAlign nowrap">
   <strong><?php echo _("Search Scope") ?>:</strong>&nbsp;
  </td>
  <td width="100%">
   <input id="name" name="search_name" type="checkbox" checked="checked" /> <?php echo Horde::label('name', _("N_ame")) ?>&nbsp;
   <input id="desc" name="search_desc" type="checkbox" /> <?php echo Horde::label('desc', _("_Description")) ?>&nbsp;
   <input id="category" name="search_category" type="checkbox" /> <?php echo Horde::label('category', _("_Category")) ?>
  </td>
 </tr>
</table>
<br />

<input type="submit" class="button" value="<?php echo _("Search") ?>" />
<input type="reset" class="button" value="<?php echo _("Clear Form") ?>" />

</div>

</form>
