<form method="post" name="export" action="<?php echo Horde::downloadUrl(_("contacts.csv"), null, Horde::applicationUrl('data.php')) ?>">
<?php echo Util::formInput() ?>
<input type="hidden" name="actionID" value="export" />

<h1 class="header">
 <?php echo _("Export Address Book") ?>
</h1>

<div class="item">
 <label for="exportID"><?php echo _("Select the export format:") ?></label><br />
 <select name="exportID" id="exportID">
  <option value="<?php echo EXPORT_CSV ?>"><?php echo _("Comma separated values") ?></option>
  <option value="<?php echo EXPORT_OUTLOOKCSV ?>"><?php echo _("Comma separated values (Microsoft Outlook)") ?></option>
  <option value="<?php echo EXPORT_TSV ?>"><?php echo _("Tab separated values") ?></option>
  <option value="<?php echo EXPORT_VCARD ?>"><?php echo _("vCard") ?></option>
 </select><br />

<?php if ($actionID == 'select' && Util::getFormData('objectkeys')): ?>
 <br />
 <input type="radio" name="selected" id="selected1" value="1" checked="checked" />
 <label for="selected1"><?php echo _("Export only the selected contacts.") ?></label>
<?php foreach (Util::getFormData('objectkeys') as $key => $value): ?>
 <input type="hidden" name="objectkeys[<?php echo htmlspecialchars($key) ?>]" value="<?php echo htmlspecialchars($value) ?>" />
<?php endforeach; ?>
 <br />
 <input type="radio" name="selected" id="selected0" value="0" />
 <label for="selected0"><?php echo _("Export the following address book completely.") ?></label>
 <br />
<?php endif; ?>
<?php if (count($source_options) > 1): ?>
 <br />
 <label for="source"><?php echo _("Select the address book to export from:") ?></label><br />
 <select name="source" id="source">
  <?php echo implode('', $source_options) ?>
 </select><br />
<?php else: ?>
 <input type="hidden" name="source" value="<?php echo htmlspecialchars($unique_source) ?>" />
<?php endif; ?>
 <br />

 <input type="submit" value="<?php echo _("Export") ?>" class="button" />
</div>
</form>
