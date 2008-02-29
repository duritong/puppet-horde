<form action="list.php" method="post" name="tasklistsmenu">
<?php echo Util::formInput() ?>
<?php $_menu_tasklists = Nag::listTasklists() ?>
<div id="menu">
<?php if (!$prefs->isLocked('default_tasklist') && (count($_menu_tasklists) > 1 || !count($GLOBALS['display_tasklists']))): ?>
 <div style="float:right">
<select name="display_tasklist" onchange="tasklistSubmit();">
  <option value=""><?php echo _("Select tasklists to display:") ?></option>
<?php foreach ($_menu_tasklists as $id => $tasklist): ?>
  <option value="<?php echo htmlspecialchars($id) ?>"<?php echo (in_array($id, $GLOBALS['display_tasklists']) ? ' class="selected"' : '') . '>' . htmlspecialchars($tasklist->get('name')) ?></option>
<?php endforeach; ?>
</select>
 </div>
<?php endif; ?>

 <?php echo Nag::getMenu('string') ?>
 <br class="clear" />
</div>
</form>

<script type="text/javascript">
<!--
function tasklistSubmit()
{
    document.tasklistsmenu.submit();
    return false;
}
// -->
</script>

<?php Nag::status();
