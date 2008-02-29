<?php
$notepads = Mnemo::listNotepads();

// Check here for guest notepads so that we don't get multiple
// messages after redirects, etc.
if (!Auth::getAuth() && !count(Mnemo::listNotepads())) {
    $notification->push(_("No notepads are available to guests."));
}
?>
<form action="list.php" method="post" name="notepadsmenu">
<?php echo Util::formInput() ?>
<div id="menu">
<?php if (!$prefs->isLocked('default_notepad') && (count($notepads) > 1 || !count($GLOBALS['display_notepads']))): ?>
 <div style="float:right">
<select name="display_notepad" onchange="notepadSubmit();">
  <option value=""><?php echo _("Select notepads to display:") ?></option>
<?php foreach ($notepads as $id => $notepad): ?>
  <option value="<?php echo htmlspecialchars($id) ?>"<?php echo (in_array($id, $GLOBALS['display_notepads']) ? ' class="selected"' : '') . '>' . htmlspecialchars($notepad->get('name')) ?></option>
<?php endforeach; ?>
</select>
 </div>
<?php endif; ?>

 <?php echo Mnemo::getMenu('string') ?>
 <br class="clear" />
</div>
</form>

<script type="text/javascript">
<!--
function notepadSubmit()
{
    document.notepadsmenu.submit();
    return false;
}
// -->
</script>

<?php $GLOBALS['notification']->notify(array('listeners' => 'status')) ?>
