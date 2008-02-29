<script type="text/javascript">
<!--

function confirmCancel()
{
    if (window.confirm('<?php echo addslashes(_("Cancelling this message will permanently discard its contents.")) . '\n' . addslashes(_("Are you sure you want to do this?")) ?>')) {
        <?php echo $cancel_js ?>
        return true;
    } else {
        return false;
    }
}

<?php if ($browser->isBrowser('msie')): ?>
function subjectTab()
{
    if (event.keyCode == 9 && !event.shiftKey) {
        event.returnValue = false;
        document.compose.message.focus();
    }
}
<?php endif;

$js = "var identities = new Array(\n";
foreach ($identities as $ident) {
    $js .= '    new Array(';
    $js .= '"' . str_replace("\n", ($rtemode ? '<br />' : '') . '\n', addslashes($ident[0])) . '", ';
    if ($ident[1]) {
        $js .= 'true, ';
    } else {
        $js .= 'false, ';
    }
    if (!empty($conf['user']['select_sentmail_folder']) &&
        !$prefs->isLocked('sent_mail_folder')) {
        $js .= (isset($ident[2])) ? ('"' . $ident[2] . '", ') : 'null, ';
    } else {
        $js .= (isset($ident[2])) ? '"\"' . IMP::displayFolder($ident[2]) . '\"", ' : '"", ';
    }
    if ($ident[3]) {
        $js .= 'true, ';
    } else {
        $js .= 'false, ';
    }

    if (isset($ident[4])) {
        $js .= '"' . $ident[4] . '"';
    } else {
        $js .= '""';
    }

    $js .= "),\n";
}
$js = substr($js, 0, -2) . "\n";
echo $js;
?>
);

function change_identity(id)
{
    var pos;

    var last = identities[document.compose.last_identity.value];
    var next = identities[id];
    var msg = document.compose.message.value.replace(/\r\n/g, '\n');

<?php if ($rtemode): ?>
    next[0] = next[0].replace(/^<br \/>\n/, '').replace(/ +/g, ' '); //.replace(/<br \/>/, '<BR>');
    last[0] = last[0].replace(/^<br \/>\n/, '').replace(/ +/g, ' '); //.replace(/<br \/>/, '<BR>');
<?php else: ?>
    next[0] = next[0].replace(/^\n/, '');
    last[0] = last[0].replace(/^\n/, '');
<?php endif; ?>

    if (last[1]) {
        pos = msg.indexOf(last[0]);
    } else {
        pos = msg.lastIndexOf(last[0]);
    }
    if (pos != -1) {
        if (next[1] == last[1]) {
            msg = msg.substring(0, pos) + next[0] + msg.substring(pos + last[0].length, msg.length);
        } else if (next[1]) {
            msg = next[0] + msg.substring(0, pos) + msg.substring(pos + last[0].length, msg.length);
        } else {
            msg = msg.substring(0, pos) + msg.substring(pos + last[0].length, msg.length) + next[0];
        }
        document.compose.message.value = msg.replace(/\r\n/g, '\n').replace(/\n/g, '\r\n');
        document.compose.last_identity.value = id;
        window.status = '<?php echo addslashes(_("The signature was successfully replaced.")) ?>';
    } else {
        window.status = '<?php echo addslashes(_("The signature could not be replaced.")) ?>';
    }

<?php if (!empty($conf['user']['select_sentmail_folder']) &&
          !$prefs->isLocked('sent_mail_folder')): ?>
    var field = document.compose.sent_mail_folder;
    for (var i = 0; i < field.options.length; i++) {
        if (field.options[i].value == next[2]) {
            field.selectedIndex = i;
            break;
        }
    }
<?php else: ?>
    if (document.getElementById && document.createTextNode) {
        folder_text = document.getElementById('sent_mail_folder');
        if (folder_text) {
            if (folder_text.firstChild) {
                folder_text.replaceChild(document.createTextNode(next[2]), folder_text.firstChild);
            } else {
                folder_text.appendChild(document.createTextNode(next[2]));
            }
        }
    } else if (document.all) {
        folder_text = document.all.sent_mail_folder;
        folder_text.innerText = next[2];
    }
<?php endif; ?>
    if (document.compose.save_sent_mail) {
        document.compose.save_sent_mail.checked = next[3];
    }
    document.compose.bcc.value = next[4];
}

function uniqSubmit(actionID)
{
    if ((actionID == 'send_message') &&
        (document.compose.subject.value == '') &&
        !window.confirm('<?php echo addslashes(_("The message does not have a Subject entered.")) . '\n' . addslashes(_("Send message without a Subject?")) ?>')) {
        return false;
    }

    if (document.compose.style && document.compose.style.cursor) {
        document.compose.style.cursor = "wait";
    }
    document.compose.actionID.value = actionID;
<?php if ($rtemode): ?>
    document.compose.onsubmit();
<?php endif; ?>
    document.compose.submit();
}

// -->
</script>
