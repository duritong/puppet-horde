<tr class="<?php echo $style ?>">
  <td>
<?php
if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_EDIT) && empty($task['completed'])) {
    $label = sprintf(_("Complete \"%s\""), $task['name']);
    echo Horde::link($task['complete_link'], $label) . Horde::img('unchecked.png', $label) . '</a>';
} else {
    echo Nag::formatCompletion($task['completed']);
}
?>
  </td>

<?php if ($showTasklist): ?>
  <td><?php echo htmlspecialchars($owner) ?></td>
<?php endif; ?>
  <td><?php echo Nag::formatPriority($task['priority']) ?></td>
  <td>
    <?php
    if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_EDIT)) {
        $label = sprintf(_("Edit \"%s\""), $task['name']);
        echo Horde::link($task['edit_link'], $label) . Horde::img('edit.png', $label, null, $registry->getImageDir('horde')) . '</a>';
    }
    ?>
  </td>
  <td><?php $task_name = strlen($task['name']) ? htmlspecialchars($task['name']) : _("[none]"); if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_READ)) { $link = strlen($task['desc']) ? 'linkTooltip' : 'link'; echo Horde::$link($task['view_link'], '', '', '', '', wordwrap($task['desc'])) . $task_name . '</a>'; } else echo $task_name; ?></td>
  <td><?php echo strlen($task['desc']) ? Horde::img('note.png', _("Task Note")) : '&nbsp;' ?></td>
  <td><?php echo (!empty($task['alarm']) && !empty($task['due'])) ?
    Horde::img('alarm_small.png', _("Task Alarm")) : '&nbsp;' ?>
  </td>
  <td>
    <?php echo !empty($task['due']) ? strftime($dateFormat, $task['due']) : '&nbsp;' ?>
  </td>
  <td class="category<?php echo md5($task['category']) ?>"><?php echo strlen($task['category']) ? htmlspecialchars($task['category']) : _("Unfiled") ?></td>
</tr>
