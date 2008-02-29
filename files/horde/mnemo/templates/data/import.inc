<form method="post" name="import_form" enctype="multipart/form-data" action="<?php echo Horde::applicationUrl('data.php') ?>">
<input type="hidden" name="actionID" value="<?php echo htmlspecialchars($next_step) ?>" />
<input type="hidden" name="import_step" value="<?php echo (int)$import_step ?>" />
<table cellspacing="0" cellpadding="0" border="0" width="100%">
<tr>
    <td>
        <table cellpadding="3" cellspacing="0" border="0" width="100%">
        <tr><td class="header" align="center"><?php printf(_("Import Notes, Step %d"), (int)$import_step) ?></td>
        </tr></table>
    </td>
</tr>
<tr>
    <td class="item">
    <table border="0"><tr><td>
        <?php echo _("Select the format of the source file:") ?><br />
        <select name="import_format">
            <option value="csv"><?php echo _("CSV") ?></option>
            <option value="vnote"><?php echo _("vNote") ?></option>
        </select><br />

<?php if (!$prefs->isLocked('default_notepad') && count($notepads = Mnemo::listNotepads(false, PERMS_EDIT)) > 1): ?>
        <br /><?php echo _("Which Notepad should the notes be added to?") ?><br />
        <select id="notepad_target" name="notepad_target">
        <?php
        $default = Mnemo::getDefaultNotepad(PERMS_EDIT);
        foreach ($notepads as $id => $notepad) {
            $sel = ($id == $default) ? ' selected="selected"' : '';
            printf('<option value="%s"%s>%s</option>',
                   htmlspecialchars($id), $sel, htmlspecialchars($notepad->get('name'))) . "\n";
        } ?>
        </select><br />
<?php else: ?>
        <input type="hidden" name="notepad_target" value="<?php echo htmlspecialchars(Mnemo::getDefaultNotepad(PERMS_EDIT)) ?>" />
<?php endif; ?>
        <br />

        <?php echo _("Select the file to import:") ?><br />
        <input type="file" name="import_file" class="fixed" size="40" />
        <br /><br />
        <input type="submit" value="<?php echo _("Next") ?>" class="button" />
    </td></tr></table>
    </td>
</tr>
</table>
</form>
