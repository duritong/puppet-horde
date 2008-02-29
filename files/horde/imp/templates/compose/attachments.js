<script type="text/javascript">
<!--

function attachmentChanged()
{
    var usedFields = 0;
    var fields = new Array();
    for (var i = 0; i < document.compose.elements.length; i++) {
        if (document.compose.elements[i].type == 'file' &&
            document.compose.elements[i].name.substr(0, 7) == 'upload_') {
            fields[fields.length] = document.compose.elements[i];
        }
    }

<?php
$max_attachments = $imp_compose->additionalAttachmentsAllowed();
if ($max_attachments !== true):
?>
    if (fields.length == <?php echo $max_attachments ?>) {
        return;
    }
<?php endif; ?>

    for (var i = 0; i < fields.length; i++) {
        if (fields[i].value.length > 0) {
            usedFields++;
        }
    }

    if (usedFields == fields.length) {
        var lastRow = document.getElementById('attachment_row_' + usedFields);
        if (lastRow) {
            var newRow = document.createElement('TR');
            newRow.id = 'attachment_row_' + (usedFields + 1);
            var td = document.createElement('TD');
            newRow.appendChild(td);
            td.align = 'left';
            var strong = document.createElement('STRONG');
            td.appendChild(strong);
            strong.appendChild(document.createTextNode('<?php echo _("File") ?> ' + (usedFields + 1) + ':'));
            td.appendChild(document.createTextNode(' '));
            var file = document.createElement('INPUT');
            file.type = 'file';
            td.appendChild(file);
            file.name = 'upload_' + (usedFields + 1);
            file.onchange = function() { attachmentChanged(); };
            file.size = 25;
            file.className = 'fixed';
            td = document.createElement('TD');
            newRow.appendChild(td);
            td.align = 'left';
            var select = document.createElement('SELECT');
            td.appendChild(select);
            select.name = 'upload_disposition_' + (usedFields + 1);
            select.options[0] = new Option('<?php echo _("Attachment") ?>', 'attachment', true);
            select.options[1] = new Option('<?php echo _("Inline") ?>', 'inline');
            lastRow.parentNode.insertBefore(newRow, lastRow.nextSibling);
        }
    }
}

if (document.compose.to && document.compose.to.value == "") {
    document.compose.to.focus();
} else if (document.compose.subject.value == "") {
    document.compose.subject.focus();
} else {
<?php if ($rtemode): ?>
    document.compose.editor.focus();
<?php else: ?>
    document.compose.message.focus();
<?php endif; ?>
}

// -->
</script>
