<form method="post" name="import_form" enctype="multipart/form-data" action="data.php">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="<?php echo htmlspecialchars($next_step) ?>" />
<input type="hidden" name="import_step" value="<?php echo (int)$import_step ?>" />

<h1 class="header">
 <?php printf(_("Import Calendar, Step %d"), (int)$import_step) ?>
</h1>

<div class="item">
    <?php echo _("Select the format of the source file:") ?><br />
    <select name="import_format">
      <option value="csv"><?php echo _("Comma separated values") ?></option>
      <option value="icalendar"><?php echo _("vCalendar/iCalendar") ?></option>
    </select><br />

    <?php if (!$prefs->isLocked('default_share') && count($calendars = Kronolith::listCalendars(false, PERMS_EDIT)) > 1): ?>
    <?php echo _("Select the calendar to import to:") ?><br />
    <select name="importCal">
      <?php foreach ($calendars as $id => $cal) {
          printf('<option value="%s">%s</option>', htmlspecialchars($id), htmlspecialchars($cal->get('name'))) . "\n";
      } ?>
    </select><br /><br />
    <?php else: ?>
    <input type="hidden" name="importCal" value="<?php echo htmlspecialchars(Kronolith::getDefaultCalendar(PERMS_EDIT)) ?>" />
    <?php endif; ?>
    <?php echo _("Select the file to import:") ?><br />
    <input type="file" name="import_file" class="fixed" size="40" />
    <br /><br />
    <input type="checkbox" align="middle" name="purge" id="purge" value="1" />
    <label for="purge"><?php echo _("Replace existing calendar with the imported one? <strong>Warning: This deletes all entries in your current calendar.</strong>") ?></label>
    <br /><br />
    <input type="submit" value="<?php echo _("Next") ?>" class="button" />
</div>
</form>
