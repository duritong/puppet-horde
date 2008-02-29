<?php echo $cManager->getJavaScript('task', 'task_category') ?>
<form method="post" name="task" action="task.php">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="save_task" />
<input type="hidden" name="task" value="<?php echo htmlspecialchars($task_id) ?>" />
<input type="hidden" name="tasklist_original" value="<?php echo htmlspecialchars($tasklist_id) ?>" />
<input type="hidden" name="new_category" value="" />
<?php if ($prefs->isLocked('default_tasklist') || count($tasklists) <= 1): ?>
<input type="hidden" name="tasklist_target" value="<?php echo htmlspecialchars($tasklist_id) ?>" />
<?php endif; ?>


<h1 class="header"><?php echo htmlspecialchars($title) ?></h1>

<table cellspacing="0" width="100%" class="striped">
 <tr>
  <td align="right" valign="top"><strong><?php echo Horde::label('task_name', _("N_ame")) ?></strong>&nbsp;</td>
  <td width="100%">
   <input id="task_name" name="task_name" type="text" value="<?php echo htmlspecialchars($task_name) ?>" size="50" maxlength="100" />
  </td>
 </tr>

<?php if (!$prefs->isLocked('default_tasklist') && count($tasklists) > 1): ?>
<input type="hidden" name="tasklist_target" value="<?php echo htmlspecialchars($tasklist_id) ?>" />
 <tr>
  <td valign="top"><strong><?php echo Horde::label('tasklist_target', _("_Task List")) ?></strong>&nbsp;</td>
  <td>
   <select id="tasklist_target" name="tasklist_target">
   <?php
   foreach ($tasklists as $id => $tasklist) {
       $sel = ($id == $tasklist_id) ? ' selected="selected"' : '';
       printf('<option value="%s"%s>%s</option>',
              htmlspecialchars($id), $sel, htmlspecialchars($tasklist->get('name'))) . "\n";
   } ?>
   </select>
  </td>
 </tr>
<?php endif; ?>

<tr>
  <td align="right" valign="top"><strong><?php echo Horde::label('task_category', _("_Category")) ?></strong>&nbsp;</td>
  <td><?php echo $cManager->getSelect('task_category', $task_category) ?></td>
 </tr>
 <tr>
  <td align="right" valign="top" class="nowrap"><strong><?php echo Horde::label('due_type_none', _("Due _By")) ?></strong>&nbsp;</td>
  <td>
   <input id="due_type_none" name="due_type" type="radio" value="none" <?php echo $none_checked ?> />
   <?php echo Horde::label('due_type_none', _("No due date.")) ?>
   <br />

   <input name="due_type" type="radio" value="specified" <?php echo $specified_checked ?> />
   <?php echo $day_widget . ' ' . $month_widget . ' ' . $year_widget ?>
   <?php if ($GLOBALS['browser']->hasFeature('javascript')) {
             Horde::addScriptFile('open_calendar.js', 'horde');
             echo '<div id="goto" class="control" style="position:absolute;visibility:hidden;padding:1px"></div>';
             echo Horde::link('#', _("Select a date"), '', '', 'openCalendar(\'dueimg\', \'due\', \'document.task.due_type[1].checked = true;\'); return false;') . Horde::img('calendar.png', _("Calendar"), 'align="top" id="dueimg"', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
         } ?>
   <br />

   <input type="radio" style="visibility:hidden" />
   <?php echo $hour_widget . ' ' . $minute_widget . ' ' . $am_pm_widget ?>
  </td>
 </tr>

<!-- alarm -->
 <tr>
  <td align="right" valign="top"><strong><?php echo Horde::label('noalarm', _("Ala_rm")) ?>&nbsp;&nbsp;</strong></td>
  <td valign="top">
   <input id="noalarm" name="alarm" type="radio" value="0"<?php if (!$alarm_set) echo ' checked="checked"' ?>/>
   <?php echo Horde::label('noalarm', _("None")) ?><br />
   <input name="alarm" type="radio" value="1"<?php if ($alarm_set) echo ' checked="checked"' ?> />
   <input type="text" size="2" name="alarm_value" value="<?php echo $alarm_value ?>" onchange="document.task.alarm[1].checked=true" />&nbsp;
   <select name="alarm_unit" onchange="document.task.alarm[1].checked=true">
    <option value="1"<?php echo ($alarm_unit == 'min') ? ' selected="selected"' : '' ?>><?php echo _("Minute(s)") ?></option>
    <option value="60"<?php echo ($alarm_unit == 'hour') ? ' selected="selected"' : '' ?>><?php echo _("Hour(s)") ?></option>
    <option value="1440"<?php echo ($alarm_unit == 'day') ? ' selected="selected"' : '' ?>><?php echo _("Day(s)") ?></option>
    <option value="10080"<?php echo ($alarm_unit == 'week') ? ' selected="selected"' : '' ?>><?php echo _("Week(s)") ?></option>
   </select>
  </td>
 </tr>
 <tr>
  <td align="right"><strong><?php echo Horde::label('task_priority', _("_Priority")) ?></strong>&nbsp;</td>
  <td>
   <?php echo Nag::buildPriorityWidget('task_priority', $task_priority) ?>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top" class="nowrap"><strong><?php echo Horde::label('task_completed', _("Co_mpleted?")) ?></strong>&nbsp;</td>
  <td>
   <?php echo Nag::buildCompletionWidget('task_completed', $task_completed) ?>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top"><strong><?php echo Horde::label('task_desc', _("_Description")) ?></strong>&nbsp;</td>
  <td>
   <textarea id="task_desc" name="task_desc" cols="50" rows="5"><?php echo htmlspecialchars($task_desc) ?></textarea>
  </td>
 </tr>
</table>

<p>
 <input type="submit" class="button" value="<?php echo _("Save") ?>" onclick="return checkCategory();" />&nbsp;
 <input type="reset" class="button" value="<?php echo _("Undo changes") ?>" />&nbsp;
 <?php if ($task_id):
     $taskurl = Util::addParameter('task.php', array('task' => $task_id,
                                                     'tasklist' => $tasklist_id));

     $share = $GLOBALS['nag_shares']->getShare($tasklist_id);
     if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_DELETE)): ?>
   <input type="button" class="button" value="<?php echo _("Delete this task") ?>" onclick="window.location='<?php echo addslashes(Horde::applicationUrl(Util::addParameter($taskurl, 'actionID', 'delete_tasks'))) ?>';" />&nbsp;
  <?php endif; ?>
 <?php endif; ?>
</p>
</form>
