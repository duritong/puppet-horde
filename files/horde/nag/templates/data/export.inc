<form method="post" name="export" action="<?php echo Horde::downloadUrl(_("tasks.csv"), null, 'data.php') ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="export" />

<h1 class="header">
 <?php echo _("Export Tasks") ?>
</h1>

<div class="headerbox">
 <p>
  <?php echo _("Select the export format:") ?><br />
  <select name="exportID">
   <option value="<?php echo EXPORT_CSV ?>">CSV</option>
   <option value="<?php echo EXPORT_ICALENDAR ?>">iCalendar (vTodo)</option>
  </select>
 </p>
 <input type="submit" value="<?php echo _("Export") ?>" class="button" />
</div>

</form>
