<?php
if (!$prefs->isLocked('default_notepad')):
    $notepads = Mnemo::listNotepads();
    if (($default_notepad = $prefs->getValue('default_notepad')) == null ||
        !isset($notepads[$default_notepad])) {
        $default_notepad = Auth::getAuth();
    }
?>

<?php echo _("Your default notepad:") ?><br />
<select name="default_notepad">
<?php foreach ($notepads as $id => $notepad): ?>
    <option value="<?php echo htmlspecialchars($id) ?>"<?php if ($id == $default_notepad) echo ' selected="selected"' ?>><?php echo htmlspecialchars($notepad->get('name')) ?></option>
<?php endforeach; ?>
</select><br /><br />
<?php endif; ?>
