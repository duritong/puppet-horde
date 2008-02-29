<?php if (!$prefs->isLocked('trash_folder') && !$prefs->getValue('use_vtrash')): ?>
<script type="text/javascript">
<!--

function newTrashFolderName()
{
    document.prefs.trash.onchange = null;
    if (document.prefs.trash[document.prefs.trash.selectedIndex].value == '') {
        var folder = window.prompt('<?php echo addslashes(_("Enter the name for your new trash folder")) ?>', document.prefs.trash_new.value ? document.prefs.trash_new.value : '');
        if (folder != '') {
            document.prefs.trash_new.value = folder;
            document.prefs.trash[1].text = '<?php echo addslashes(_("Create a new trash folder")) ?> [' + document.prefs.trash_new.value + ']';
        }
    }
    document.prefs.trash.onchange = newTrashFolderName;
}

// -->
</script>

<table border="0">
 <tr>
  <td><?php echo _("Trash folder:") ?><br />
   <select name="trash" onchange="newTrashFolderName();"><option value="<?php echo IMP_PREF_NO_FOLDER ?>"><?php echo _("None") ?></option><?php echo IMP::flistSelect(_("Create a new trash folder"), true, array('INBOX'), IMP::folderPref($prefs->getValue('trash_folder'), true)) ?></select>
   <input type="hidden" name="trash_new" value="" />
  </td>
 </tr>
</table>
<?php endif; ?>
