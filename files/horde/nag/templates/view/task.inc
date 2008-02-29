<div class="header">
 <span class="smallheader" style="float:right">
<?php
    if (!$print_view) {
        $taskurl = Util::addParameter('task.php', 'task', $task['task_id']);
        $taskurl = Util::addParameter($taskurl, 'tasklist', $tasklist_id);
        $share = $GLOBALS['nag_shares']->getShare($tasklist_id);
    ?>
    <?php if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_EDIT)): ?>
      <?php if (!$task['completed']): ?>
        <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($taskurl, 'actionID', 'complete_task')), _("Complete"), 'smallheader', '', '', _("_Complete")) ?> |
      <?php endif; ?>
      <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($taskurl, 'actionID', 'modify_task')), _("Edit"), 'smallheader', '', '', _("_Edit")) ?>
    <?php endif; ?>
    <?php if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_DELETE)): ?>
      | <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($taskurl, 'actionID', 'delete_tasks')), _("Delete"), 'smallheader', '', $prefs->getValue('delete_opt') ? 'return window.confirm(\'' . addslashes(_("Really delete this task?")) . '\');' : '', _("_Delete")) ?>
    <?php endif; ?>
    <?php } ?>
 </span>
 <?php echo htmlspecialchars($task['name']) ?>
</div>

<table cellspacing="0" width="100%" class="headerbox striped">
 <tr>
  <td align="right" valign="top"><strong><?php echo _("Category") ?></strong>&nbsp;</td>
  <td width="100%"><?php echo htmlspecialchars($task['category'] ? $task['category'] : _("Unfiled")) ?></td>
 </tr>
 <tr>
  <td align="right" valign="top"><strong><?php echo _("Due By") ?></strong>&nbsp;</td>
  <td><?php echo Nag::formatDate($task['due']) ?></td>
 </tr>
 <tr>
  <td align="right" valign="top"><strong><?php echo _("Alarm") ?></strong>&nbsp;</td>
  <td><?php echo $alarm_text ?></td>
 </tr>
 <tr>
  <td align="right" valign="top"><strong><?php echo _("Priority") ?></strong>&nbsp;</td>
  <td><?php echo Nag::formatPriority($task['priority']) ?></td>
 </tr>
 <tr>
  <td align="right" valign="top"><strong><?php echo _("Completed?") ?></strong>&nbsp;</td>
  <td>
   <?php if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_EDIT) && empty($task['completed'])): ?>
    <?php echo Horde::link(Horde::applicationUrl(Util::addParameter($taskurl, 'actionID', 'complete_task')), _("Complete Task"), 'widget') . Horde::img('unchecked.png', _("Complete Task")) . '</a>' ?>
   <?php else: ?>
    <?php echo Nag::formatCompletion($task['completed']) ?></a></td>
   <?php endif; ?>
  </td>
 </tr>

<?php if (isset($created)): ?>
 <tr>
  <td align="right"><strong><?php echo _("Created") ?></strong>&nbsp;</td>
  <td><?php echo strftime($prefs->getValue('date_format'), $created) . ' ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:i a', $created) ?></td>
 </tr>
<?php endif; ?>

<?php if (isset($modified)): ?>
 <tr>
  <td align="right"><strong><?php echo _("Last Modified") ?></strong>&nbsp;</td>
  <td><?php echo strftime($prefs->getValue('date_format'), $modified) . ' ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:i a', $modified) ?></td>
 </tr>
<?php endif; ?>

 <tr>
  <td class="text" style="padding:3px" colspan="2">
   <?php echo Text_Filter::filter($task['desc'], 'text2html', array('parselevel' => TEXT_HTML_MICRO, 'class' => null)) ?>
  </td>
 </tr>
</table>
