<script type="text/javascript">
<!--

// Set up left (37) and right (39) arrows to go to the previous/next
// message.
setKeybinding(37, 'arrowHandler');
setKeybinding(39, 'arrowHandler');

function arrowHandler(e, key)
{
    if (!document.getElementById || e.altKey || e.shiftKey || e.ctrlKey) {
        return false;
    }

    var loc;
    if (key == 37) {
        loc = document.getElementById('prev');
    } else if (key == 39) {
        loc = document.getElementById('next');
    }

    if (loc) {
        document.location.href = loc.href;
    }
    return true;
}

<?php if ($_SESSION['imp']['base_protocol'] != 'pop3'): ?>
function flagMessage(form)
{
    if ((form == 1 && document.messages.flag1.options[document.messages.flag1.selectedIndex].value != "") ||
        (form == 2 && document.messages.flag2.options[document.messages.flag2.selectedIndex].value != "")) {
        if (form == 1) {
            document.messages.flag.value = document.messages.flag1.options[document.messages.flag1.selectedIndex].value;
        } else {
            document.messages.flag.value = document.messages.flag2.options[document.messages.flag2.selectedIndex].value;
        }

        document.messages.actionID.value = 'flag_message';
        document.messages.submit();
    }
}
<?php endif; ?>

function Transfer(actID, form)
{
    if (form == 1) {
        document.messages.targetMbox.value = document.messages.target1.options[document.messages.target1.selectedIndex].value;
    } else {
        document.messages.targetMbox.value = document.messages.target2.options[document.messages.target2.selectedIndex].value;
    }

    // Check for a mailbox actually being selected.
    if (document.messages.targetMbox.value == '*new*') {
        var newFolder = window.prompt('<?php echo addslashes(_("You are copying/moving to a new folder.")) . '\n' . addslashes(_("Please enter a name for the new folder:")) ?>\n', '');
        if (newFolder != null && newFolder != '') {
            document.messages.newMbox.value = 1;
            document.messages.targetMbox.value = newFolder;
            document.messages.actionID.value = actID;
            document.messages.submit();
        }
    } else {
        if (document.messages.targetMbox.value == '') {
            window.alert('<?php echo addslashes(_("You must select a target mailbox first.")) ?>');
        } else {
            document.messages.actionID.value = actID;
            document.messages.submit();
        }
    }
}

function Submit(actID)
{
    if (actID == 'spam_report') {
        if (!window.confirm('<?php echo addslashes(_("Are you sure you wish to report this message as spam?")) ?>')) {
            return;
        }
    }
    if (actID == 'notspam_report') {
        if (!window.confirm('<?php echo addslashes(_("Are you sure you wish to report this message as innocent?")) ?>')) {
            return;
        }
    }
    document.messages.actionID.value = actID;
    document.messages.submit();
}

function updateFolders(form)
{
    if (form == 1) {
        document.messages.target2.selectedIndex = document.messages.target1.selectedIndex;
    } else {
        document.messages.target1.selectedIndex = document.messages.target2.selectedIndex;
    }
}

// -->
</script>
