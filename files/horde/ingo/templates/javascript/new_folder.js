function newFolderName(name)
{
    var form = document.getElementsByName(name);

    if (form[0].actionvalue.selectedIndex == 1){
        var folder = window.prompt('<?php echo addslashes(_("Please enter the name of the new folder:")) ?>\n', '');

        if ((folder != null) && (folder != '')) {
            form[0].actionID.value = 'create_folder';
            form[0].new_folder_name.value = folder;
            form[0].submit();
        }
    }

    return true;
}
