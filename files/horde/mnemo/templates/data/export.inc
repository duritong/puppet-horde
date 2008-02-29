<form method="post" name="export" action="<?php echo Horde::downloadUrl(_("notes.csv"), null, Horde::applicationUrl('data.php')) ?>">
<input type="hidden" name="actionID" value="export" />
<br />
<table cellspacing="0" cellpadding="0" border="0" width="100%">
<tr>
    <td>
        <table cellpadding="3" cellspacing="0" border="0" width="100%">
        <tr><td class="header" align="center"><?php echo _("Export Notes") ?></td>
        </tr></table>
    </td>
</tr>
<tr>
    <td class="item">
    <table border="0"><tr><td>
        <?php echo _("Select the export format:") ?><br />
        <select name="exportID">
            <option value="<?php echo EXPORT_CSV ?>">CSV</option>
        </select><br /><br />
        <input type="submit" value="<?php echo _("Export") ?>" class="button" />
    </td></tr></table>
    </td>
</tr>
</table>
</form>
