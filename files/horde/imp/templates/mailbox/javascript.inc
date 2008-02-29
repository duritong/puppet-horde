<script type="text/javascript">
<!--

// Set up left (37) and right (39) arrows to go to the previous/next page.
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

function AnySelected()
{
    for (i = 0; i < document.messages.elements.length; i++) {
        if (document.messages.elements[i].checked) return true;
    }
    return false;
}

var rowColors = new Array();
function selectRow(rowId)
{
    rowOb = document.getElementById('row' + rowId);
    if (!rowOb) {
        return;
    }

    if (rowOb.className.indexOf('selectedRow') != -1) {
        rowOb.className = rowColors[rowId];
    } else {
        rowColors[rowId] = rowOb.className;
        rowOb.className = 'selectedRow';
    }
}

function getIndexByElement(elem)
{
    for (var i = 0; i < elem.form.elements.length; i++) {
        if (elem == elem.form.elements[i]) return i;
    }
    return null;
}

function Submit(actID)
{
    if (!AnySelected()) {
        window.alert('<?php echo addslashes(_("You must select at least one message first.")) ?>');
        return;
    }

<?php if ($_SESSION['imp']['base_protocol'] == 'pop3'): ?>
    if (actID == 'delete_messages') {
        if (!window.confirm('<?php echo addslashes(_("Are you sure you wish to PERMANENTLY delete these messages?")) ?>')) {
            return;
        }
    }
<?php endif; ?>
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

function makeSelection(whichForm)
{
    switch (parseInt(whichForm)) {
    case -1:
        if (document.messages.checkAll.checked) {
            flag = "!<?php echo IMP_ALL ?>";
        } else {
            flag = "<?php echo IMP_ALL ?>";
        }
        break;

    case 1:
        flag = document.select1.filter.options[document.select1.filter.selectedIndex].value;
        break;

    default:
        flag = document.select2.filter.options[document.select2.filter.selectedIndex].value;
    }

    if (flag.substring(0, 1) == "!") {
        selectFlagged(parseInt(flag.substring(1)), false);
    } else if (flag.substring(0, 1) == "+") {
        selectFlagged(flag.substring(0,1), null);
    } else {
        selectFlagged(parseInt(flag), true);
    }

    // Reset the form.
    switch (parseInt(whichForm)) {
    case -1:
        break;

    case 1:
        document.select1.reset();
        break;

    default:
        document.select2.reset();
    }
}

var startrange = -1;

function selectRange(e)
{
    var checkBox = (e.srcElement || e.target);
    var endrange = getIndexByElement(checkBox);

    if (startrange >= 0 && e.shiftKey) {
        if (document.messages.elements[startrange].checked == document.messages.elements[endrange].checked) {
            if (startrange > endrange) {
                var tmp = endrange;
                endrange = startrange;
                startrange = tmp;
            }
            for (var i = startrange + 1; i < endrange; i++) {
                document.messages.elements[i].checked = document.messages.elements[startrange].checked;
                selectRow(document.messages.elements[i].id.replace(/check/, ''));
            }
        }
        startrange = -1;
    } else {
        startrange = getIndexByElement(checkBox);
    }
}

function onClickHandler(e)
{
    var e = e || window.event;
    var elem = (e.srcElement || e.target);

    // Range selection/deselection.
    if (elem.name == "indices[]") {
        selectRange(e);
    }
}

document.onclick = onClickHandler;

<?php if (!empty($GLOBALS['conf']['user']['allow_folders'])): ?>
function updateFolders(whichForm)
{
    if (document.copymove2) {
        if ((whichForm == 1 && document.copymove1.targetMailbox.options[document.copymove1.targetMailbox.selectedIndex].value != "") ||
            (whichForm == 2 && document.copymove2.targetMailbox.options[document.copymove2.targetMailbox.selectedIndex].value != "")) {
            if (whichForm == 1) {
                document.copymove2.targetMailbox.selectedIndex = document.copymove1.targetMailbox.selectedIndex;
            } else {
                document.copymove1.targetMailbox.selectedIndex = document.copymove2.targetMailbox.selectedIndex;
            }
        }
    }
}

function Transfer(actID, whichForm)
{
    if (AnySelected()) {
        if (whichForm == 1) {
            document.messages.targetMbox.value = document.copymove1.targetMailbox.options[document.copymove1.targetMailbox.selectedIndex].value;
        } else {
            document.messages.targetMbox.value = document.copymove2.targetMailbox.options[document.copymove2.targetMailbox.selectedIndex].value;
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
    } else {
        window.alert('<?php echo addslashes(_("You must select at least one message first.")) ?>');
    }
}
<?php endif; ?>

// Put everything reliant on IMAP flags in this section.
var Flags;

function selectFlagged(flag, val)
{
    shift = 0;
    for (var i = 0; i < document.messages.elements.length; i++) {
        while (document.messages.elements[i].name != "indices[]") {
            i++;
            shift++;
            if (!document.messages.elements[i]) {
                return;
            }
        }

        if (flag == '+') {
            check = !document.messages.elements[i].checked;
        } else if (flag & Flags[i - shift]) {
            check = val;
        } else {
            check = !val;
        }

        if (document.messages.elements[i].checked != check) {
            document.messages.elements[i].checked = check;
            selectRow(document.messages.elements[i].id.replace(/check/, ''));
        }
    }
}

<?php if ($_SESSION['imp']['base_protocol'] != 'pop3'): ?>
function flagMessages(whichForm)
{
    if ((whichForm == 1 && document.select1.flag.options[document.select1.flag.selectedIndex].value != "") ||
        (whichForm == 2 && document.select2.flag.options[document.select2.flag.selectedIndex].value != "")) {
        if (AnySelected()) {
            if (whichForm == 1) {
                document.messages.flag.value = document.select1.flag.options[document.select1.flag.selectedIndex].value;
            } else {
                document.messages.flag.value = document.select2.flag.options[document.select2.flag.selectedIndex].value;
            }

            document.messages.actionID.value = 'flag_messages';
            document.messages.submit();
        } else {
            if (whichForm == 1) {
                document.select1.flag.selectedIndex = 0;
            } else {
                document.select2.flag.selectedIndex = 0;
            }
            window.alert('<?php echo addslashes(_("You must select at least one message first.")) ?>');
        }
    }
}
<?php endif; ?>

<?php if ($GLOBALS['browser']->hasFeature('dom')): ?>
function getMessage(id, offset, index)
{
    for (i = 0; i < messagelist.length; i++) {
        if (messagelist[i] == id) {
            j = i + offset;
            if (j >= 0 && j < messagelist.length) {
                if (index) {
                    return j;
                } else {
                    return messagelist[j];
                }
            }
        }
    }
    return '';
}

function onKeyDownHandler(e)
{
    var e = e || window.event;
    var o = (e.srcElement || e.target);
    var next;
    var old;
    var id;

    if (e.altKey || e.ctrlKey) {
        switch (e.keyCode) {
        // Up
        case 38:
            if (o.id.substr(0, 5) == 'check' && o.tagName == 'INPUT') {
                old = o.id.substr(5, o.id.length - 5);
                id = getMessage(old, -1);
                next = document.getElementById('check' + id);
            } else if (o.id.substr(0, 7) == 'subject' && o.tagName == 'A') {
                if (e.altKey) {
                    return;
                }
                old = o.id.substr(7, o.id.length - 7);
                id = getMessage(old, -1);
                next = document.getElementById('subject' + id);
            } else {
                id = messagelist[messagelist.length - 1];
                if (e.altKey) {
                    next = document.getElementById('check' + id);
                } else {
                    next = document.getElementById('subject' + id);
                }
            }
            break;
        // Down
        case 40:
            if (o.id.substr(0, 5) == 'check' && o.tagName == 'INPUT') {
                old = o.id.substr(5, o.id.length - 5);
                id = getMessage(old, 1);
                next = document.getElementById('check' + id);
            } else if (o.id.substr(0, 7) == 'subject' && o.tagName == 'A') {
                if (e.altKey) {
                    return;
                }
                old = o.id.substr(7, o.id.length - 7);
                id = getMessage(old, 1);
                next = document.getElementById('subject' + id);
            } else {
                id = messagelist[0];
                if (e.altKey) {
                    next = document.getElementById('check' + id);
                } else {
                    next = document.getElementById('subject' + id);
                }
            }
            break;
        // Left
        case 37:
            if (e.altKey) {
                return;
            }
            if (o.id.substr(0, 5) == 'check' && o.tagName == 'INPUT') {
                old = o.id.substr(5, o.id.length - 5);
                id = getMessage(old, -1);
                next = document.getElementById('subject' + id);
            } else if (o.id.substr(0, 7) == 'subject' && o.tagName == 'A') {
                id = o.id.substr(7, o.id.length - 7);
                next = document.getElementById('check' + id);
            } else {
                id = messagelist[messagelist.length - 1];
                next = document.getElementById('subject' + id);
            }
            break;
        // Right
        case 39:
            if (e.altKey) {
                return;
            }
            if (o.id.substr(0, 5) == 'check' && o.tagName == 'INPUT') {
                id = o.id.substr(5, o.id.length - 5);
                next = document.getElementById('subject' + id);
            } else if (o.id.substr(0, 7) == 'subject' && o.tagName == 'A') {
                old = o.id.substr(7, o.id.length - 7);
                id = getMessage(old, 1);
                next = document.getElementById('check' + id);
            } else {
                id = messagelist[0];
                next = document.getElementById('check' + id);
            }
            break;
        default:
            return;
        }
    } else if (e.keyCode == 32 && o.id.substr(0, 7) == 'subject' && o.tagName == 'A') {
        id = o.id.substr(7, o.id.length - 7);
        var checkbox = document.getElementById('check' + id);
        checkbox.checked = !checkbox.checked;
        selectRow(id);
    } else {
        return;
    }

    if (next) {
        next.focus();
        var row = document.getElementById('row' + id);
        if (e.altKey && next.tagName == 'INPUT') {
            next.checked = !next.checked;
            selectRow(next.id.replace(/check/, ''));
        } else if (old != next && row.className.indexOf('-over') == -1) {
            row.className = row.className + '-over';
        }
        var index = getMessage(id, 0, true);
        if (old) {
            row = document.getElementById('row' + old);
            if (old != next) {
                row.className = row.className.replace(/-over/, '');
            }
            index = getMessage(old, 0, true);
        }
    }

    e.returnValue = false;
<?php if ($GLOBALS['browser']->isBrowser('mozilla')): ?>
    e.preventDefault();
<?php endif; ?>
}

document.onkeydown = onKeyDownHandler;
<?php endif; ?>

//-->
</script>
