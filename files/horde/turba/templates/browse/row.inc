<?php

if ($ob->hasValue('name')) {
    $link_text = htmlspecialchars(Turba::formatName($ob));
} else {
    $link_text = _("[no value]");
}

$edit_url = $vcard_url = '&nbsp;';
if ($ob->hasValue('__key')) {
    $url = Util::addParameter($ob->isGroup() ? 'browse.php' : 'display.php',
                              array('source' => $ob->getSource(),
                                    'key' => $ob->getValue('__key')));

    $vcard_url = Util::addParameter('vcard.php', array('source' => $ob->getSource(),
                                                       'key' => $ob->getValue('__key')));
    $vcard_url = Horde::link(Horde::applicationUrl($vcard_url), _("Download vCard")) . Horde::img('mime/vcard.png', _("Download vCard"), '', $GLOBALS['registry']->getImageDir('horde')) . '</a>';

    $cell = Horde::link(Horde::applicationUrl($url), sprintf(_("View \"%s\""), $ob->getValue('name'))) .
        $link_text . '</a>';
    if ($ob->hasPermission(PERMS_EDIT)) {
        $edit_url = Util::addParameter('edit.php', array('source' => $ob->getSource(),
                                                         'key' => $ob->getValue('__key'),
                                                         'url' => Horde::selfUrl(true)));
        $edit_url = Horde::link(Horde::applicationUrl($edit_url), sprintf(_("Edit \"%s\""), $ob->getValue('name'))) . Horde::img('edit.png', _("Edit"), '', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
    }
} else {
    $cell = $link_text;
}

?>
<tr>
<?php if ($this->showMark): ?>
  <td><input type="checkbox" class="checkbox" name="objectkeys[]" value="<?php echo htmlspecialchars($ob->getSource() . ':' . $ob->getValue('__key')) ?>" /></td>
<?php endif; ?>
<?php if ($this->showEdit): ?>
  <td><?php echo $edit_url ?></td>
<?php endif; ?>
<?php if ($this->showVcard): ?>
  <td><?php echo $vcard_url ?></td>
<?php endif; ?>
<?php if ($this->showGroup): ?>
  <td><?php echo $ob->isGroup() ? Horde::img('group.png', _("Group")) : '&nbsp;' ?></td>
<?php endif; ?>
  <td<?php if ($this->showSort && $sortby == 0) echo ' class="linedRowSelectedCol"' ?>><?php
echo $cell;

// We purposefully do this before the </td> so that if we end up
// including a script file (say, for the IMP compose window), it's in
// a legal spot.
//
// Build the columns to display.
$shown_columns = array();
for ($c = 1; $c <= count($GLOBALS['columns']); $c++) {
    if ($ob->hasValue($GLOBALS['columns'][$c - 1])) {
        $shown_columns[$c] = htmlspecialchars($ob->getValue($GLOBALS['columns'][$c - 1]));
        if ($GLOBALS['attributes'][$GLOBALS['columns'][$c - 1]]['type'] == 'email') {
            $shown_columns[$c] = Turba::formatEmailAddresses($shown_columns[$c], $ob->getValue('name'));
        }
    } else {
        $shown_columns[$c] = '&nbsp;';
    }
}

echo '</td>';
foreach ($shown_columns as $column => $value) {
    echo '<td';
    if ($this->showSort && $column == $sortby) {
        echo ' class="linedRowSelectedCol"';
    }
    echo '>' . $value . '</td>';
}
?>
</tr>
