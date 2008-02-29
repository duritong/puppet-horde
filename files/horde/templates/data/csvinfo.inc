<form method="post" name="import_form" action="data.php">
<?php echo Util::formInput() ?>
<input type="hidden" name="actionID" value="<?php echo (int)$next_step ?>" />
<input type="hidden" name="import_format" value="csv" />
<input type="hidden" name="import_step" value="<?php echo (int)$import_step ?>" />
<h1 class="header">
 <?php printf(_("Import, Step %d"), (int)$import_step) ?>
</h1>
<div class="headerbox text">
    <?php echo _("Here is the beginning of the file:") ?><br />
    <pre><?php echo htmlspecialchars($_SESSION['import_data']['first_lines']) ?></pre><br />
    <label for="header"><?php echo _("Does the first row contain the field names? If yes, check this box:") ?></label>
    <input id="header" type="checkbox" name="header" value="1" checked="checked" /><br />
    <?php echo _("What is the delimiter character?") ?>
    <input type="text" name="sep" size="1" value="<?php echo htmlspecialchars($_SESSION['import_data']['sep']) ?>" /><br />
    <?php if (isset($_SESSION['import_data']['quote'])): ?>
    <?php echo _("What is the quote character?") ?>
    <input type="text" name="quote" size="1" value="<?php echo htmlspecialchars($_SESSION['import_data']['quote']) ?>" /><br />
    <?php endif; ?>
    <?php if (isset($_SESSION['import_data']['fields'])): ?>
    <?php echo _("How many fields (columns) are there?") ?>
    <input type="text" name="fields" size="2" value="<?php echo (int)$_SESSION['import_data']['fields'] ?>" /><br />
    <?php endif; ?>
</div>
<input type="submit" value="<?php echo _("Next") ?>" class="button" />
</form>
