/**
 * IMP Folders Javascript
 *
 * Provides the javascript to help the folders.php script.
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * $Horde: imp/templates/javascript/folders.js,v 1.1.2.2 2006/01/11 05:48:23 slusarz Exp $
 */

var tooMany = '<?php echo addslashes(_("Please select only one folder for this operation.")) ?>';
var downloadConfirm = '<?php echo addslashes(_("All messages in the following folder(s) will be downloaded into one MBOX file:")) ?>';
var downloadSure = '<?php echo addslashes(_("This may take some time. Are you sure you want to continue?")) ?>';
var displayNames;

function buttonCount()
{
    numSel = 0;
    for (var i = 0; i < document.fmanager.elements.length; i++) {
        if (document.fmanager.elements[i].checked) {
            numSel++;
        }
    }
    return numSel;
}

function getSelectedFolders()
{
    var sel = "";
    var folder = 0;
    for (var a = 0; a < document.fmanager.elements.length; a++) {
        if (document.fmanager.elements[a].name == 'folder_list[]') {
            if (document.fmanager.elements[a].checked == 1) {
                sel = sel + displayNames[folder] + "\n";
            }
            folder++;
        }
    }

    if (sel.charAt(sel.length - 1) == "\n") {
        sel = sel.substring(0, sel.length - 1);
    }

    return sel;
}

function chooseAction(i)
{
    var action;

    if (i == 0) {
        action = document.fmanager.action_choose0.options[document.fmanager.action_choose0.selectedIndex].value;
    } else {
        action = document.fmanager.action_choose1.options[document.fmanager.action_choose1.selectedIndex].value;
    }

    if (action == 'create_folder') {
        createMailbox();
    } else if (action == 'rebuild_tree') {
        submitAction(action);
    } else if (buttonCount() == 0) {
        alert('<?php echo addslashes(_("Please select a folder before you perform this action.")) ?>');
    } else if (action == 'rename_folder') {
        renameMailbox();
    } else if (action == 'subscribe_folder' ||
               action == 'unsubscribe_folder' ||
               action == 'poll_folder' ||
               action == 'expunge_folder' ||
               action == 'nopoll_folder' ||
               action == 'mark_folder_seen' ||
               action == 'mark_folder_unseen' ||
               action == 'delete_folder_confirm' ||
               action == 'folders_empty_mailbox_confirm') {
        submitAction(action);
    } else if (action == 'download_folder' ||
               action == 'download_folder_zip') {
        downloadMailbox(action);
    } else if (action == 'import_mbox') {
        if (buttonCount() > 1) {
            alert('<?php echo addslashes(_("Only one folder should be selected for this action.")) ?>');
        } else {
            submitAction(action);
        }
    }
}

function submitAction(a)
{
    document.fmanager.actionID.value = a;
    document.fmanager.submit();
}

function createMailbox()
{
    var count = buttonCount();
    if (count > 1) {
        window.alert(tooMany);
        return;
    }

    var mbox;
    if (count == 1) {
        mbox = window.prompt('<?php echo addslashes(_("You are creating a sub-folder to ")) ?>' + getSelectedFolders() + '.\n<?php echo addslashes(_("Please enter the name of the new folder:")) ?>\n', '');
    } else {
        mbox = window.prompt('<?php echo addslashes(_("You are creating a top-level folder.")) . '\n' . addslashes(_("Please enter the name of the new folder:")) ?>\n', '');
    }

    if (mbox != null && mbox != '') {
        document.fmanager.new_mailbox.value = mbox;
        document.fmanager.actionID.value = 'create_folder';
        document.fmanager.submit();
    }
}

function downloadMailbox(actionid)
{
    var count = buttonCount();
    if (window.confirm(downloadConfirm + "\n" + getSelectedFolders() + "\n" + downloadSure)) {
        document.fmanager.actionID.value = actionid;
        document.fmanager.submit();
    }
}

function renameMailbox()
{
    newnames = '';
    oldnames = '';

    var j = 0;
    while (document.fmanager.elements[j].name != "folder_list[]") {
        j++;
    }

    for (var i = j; i < document.fmanager.elements.length; i++) {
        if (document.fmanager.elements[i].type == "checkbox" && document.fmanager.elements[i].checked) {
            tmp = window.prompt('<?php echo addslashes(_("You are renaming the folder: ")) ?>' +
                                displayNames[i - j] + "\n" +
                                '<?php echo addslashes(_("Please enter the new name:")) ?>', displayNames[i - j]);
            if (tmp == null || tmp == '') {
                return false;
            }
            newnames = newnames + tmp + "\n";
            oldnames = oldnames + document.fmanager.elements[i].value + "\n";
        }
    }
    if (newnames.charAt(newnames.length - 1) == "\n") {
        newnames = newnames.substring(0, newnames.length - 1);
    }
    if (oldnames.charAt(oldnames.length - 1) == "\n") {
        oldnames = oldnames.substring(0, oldnames.length - 1);
    }
    document.fmanager.new_names.value = newnames;
    document.fmanager.old_names.value = oldnames;
    document.fmanager.actionID.value = 'rename_folder';
    document.fmanager.submit();
    return true;
}

function toggleSelection()
{
    var total = 0;
    var checked = 0;
    for (var i = 0; i < document.fmanager.elements.length; i++) {
        if (document.fmanager.elements[i].name == "folder_list[]") {
            total++;
            if (document.fmanager.elements[i].checked) {
                checked++;
            }
        }
    }  
    
    var new_value = (total != checked);
    for (var i = 0; i < document.fmanager.elements.length; i++) {
        if (document.fmanager.elements[i].name == "folder_list[]") {
            document.fmanager.elements[i].checked = new_value;
        }
    }  
}
