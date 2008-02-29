<?php
if (!$prefs->isLocked('default_tasklist')):
    $tasklists = Nag::listTasklists();
    if (($default_tasklist = $prefs->getValue('default_tasklist')) == null ||
        !isset($tasklists[$default_tasklist])) {
        $default_tasklist = Auth::getAuth();
    }
?>

<?php echo _("Your default task list:") ?><br />
<select name="default_tasklist" class="fixed">
<?php foreach ($tasklists as $id => $tasklist): ?>
    <option value="<?php echo htmlspecialchars($id) ?>"<?php if ($id == $default_tasklist) echo ' selected="selected"' ?>><?php echo htmlspecialchars($tasklist->get('name')) ?></option>
<?php endforeach; ?>
</select><br /><br />
<?php endif; ?>
