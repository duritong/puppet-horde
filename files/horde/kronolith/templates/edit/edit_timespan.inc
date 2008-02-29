<!-- timespan header -->
<tr>
 <td align="left" colspan="5" class="header">
  <span class="smallheader"><strong><?php echo _("Time span") ?></strong></span>
 </td>
</tr>

<!-- start date -->
<tr>
 <td align="right">
    <strong><?php echo Horde::label('start[year]', _("Sta_rt")) ?>&nbsp;&nbsp;</strong>
 </td>
 <td colspan="2" align="right"><?php echo _("On") ?>&nbsp;</td>
 <td align="left">
  <?php echo $event->html('start[year]') ?>
  -
  <?php echo $event->html('start[month]') ?>
  -
  <?php echo $event->html('start[day]') ?>
<?php if ($browser->hasFeature('dom')): ?>
  <span id="start_wday"></span>
  <script type="text/javascript">
   updateWday('start_wday');
  </script>
<?php endif; ?>
<?php if ($GLOBALS['browser']->hasFeature('dom')) {
    echo '</td><td>';
    Horde::addScriptFile('open_calendar.js', 'horde');
    echo '<div id="goto" class="control" style="position:absolute;visibility:hidden;padding:1px"></div>';
    echo Horde::link('#', _("Select a date"), '', '', 'openCalendar(\'startimg\', \'start\', \'' . addslashes($event->js('start')) . '\'); return false;') . Horde::img('calendar.png', _("Set start date"), 'align="top" id="startimg"', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
} ?>
 </td>
</tr>

<!-- start time -->
<tr>
 <td>&nbsp;</td>
 <td colspan="2" align="right"><?php echo Horde::label('start_hour', _("At")) ?>&nbsp;</td>
 <td align="left" colspan="2">
  <?php echo $event->html('start_hour') ?>&nbsp;:&nbsp;<?php echo $event->html('start_min') ?>
  <?php
  if (!$prefs->getValue('twentyFour')) {
      if ($event->start->hour < 12) {
          $am = ' checked="checked"';
          $pm = '';
      } else {
          $am = '';
          $pm = ' checked="checked"';
      }
  ?>
  <input id="am" type="radio" name="am_pm" value="AM"<?php echo $am ?> onclick="document.event.whole_day.checked=false;updateEndDate();" /><label for="am" onclick="document.event.whole_day.checked=false;updateEndDate();">AM</label>
  <input id="pm" type="radio" name="am_pm" value="PM"<?php echo $pm ?> onclick="document.event.whole_day.checked=false;updateEndDate();" /><label for="pm" onclick="document.event.whole_day.checked=false;updateEndDate();">PM</label>
  <?php } ?>
 </td>
</tr>

<!-- end date -->
<tr>
 <td align="right"><strong><?php echo Horde::label('duration', _("En_d")) ?>&nbsp;&nbsp;</strong></td>
 <td align="left">
  <input type="radio" name="end_or_dur" id="duration" value="0" checked="checked" />
 </td>
 <td align="right">
  <?php echo Horde::label('end[year]', _("On")) ?>&nbsp;
 </td>
 <td align="left">
  <?php echo $event->html('end[year]') ?>
  -
  <?php echo $event->html('end[month]') ?>
  -
  <?php echo $event->html('end[day]') ?>
 <?php if ($browser->hasFeature('dom')): ?>
  <span id="end_wday"></span>
  <script type="text/javascript">
   updateWday('end_wday');
  </script>
<?php endif; ?>
<?php if ($GLOBALS['browser']->hasFeature('dom')) {
    echo '</td><td rowspan="2" valign="top">';
    Horde::addScriptFile('open_calendar.js', 'horde');
    echo Horde::link('#', _("Select a date"), '', '', 'openCalendar(\'endimg\', \'end\', \'' . addslashes($event->js('end')) . '\'); return false;') . Horde::img('calendar.png', _("Set end date"), 'align="top" id="endimg"', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
} ?>
 </td>
</tr>

<!-- end time -->
<tr>
 <td>&nbsp;</td>
 <td align="left">&nbsp;</td>
 <td align="right">
  <?php echo _("At") ?>&nbsp;
 </td>
 <td colspan="2" align="left">
  <?php echo $event->html('end_hour') ?>&nbsp;:&nbsp;<?php echo $event->html('end_min') ?>
  <?php
  if (!$prefs->getValue('twentyFour')) {
      if ($event->end->hour < 12) {
          $am = ' checked="checked"';
          $pm = '';
      } else {
          $am = '';
          $pm = ' checked="checked"';
      }
  ?>
  <input id="eam" type="radio" name="end_am_pm" value="AM"<?php echo $am ?> onclick="document.event.end_or_dur[0].checked=true;updateDuration()" /><label for="eam" onclick="document.event.end_or_dur[0].checked=true;updateDuration()">AM</label>
  <input id="epm" type="radio" name="end_am_pm" value="PM"<?php echo $pm ?> onclick="document.event.end_or_dur[0].checked=true;updateDuration()" /><label for="epm" onclick="document.event.end_or_dur[0].checked=true;updateDuration()">PM</label>
  <?php } ?>
 </td>
</tr>

<!-- duration -->
<tr>
 <td align="right" valign="top"><strong><?php echo Horde::label('duration', _("Duration")) ?>&nbsp;&nbsp;</strong></td>
 <td align="left" valign="top">
  <input type="radio" name="end_or_dur" value="1" />
 </td>
 <td>&nbsp;</td>
 <td align="left" valign="top">
  <input id="allday" name="whole_day" type="checkbox" value="1" onclick="setWholeDay(this.value); updateEndDate(); document.event.end_or_dur[1].checked = true"<?php $dur = $event->getDuration(); if ($dur->wholeDay) echo ' checked="checked"' ?> />
  <?php echo Horde::label('allday', _("All day")) ?><br />
  <?php printf(_("%s Day(s) %s Hour(s) %s Minutes"), $event->html('dur_day'), $event->html('dur_hour'), $event->html('dur_min')) ?>
<?php if ($GLOBALS['browser']->hasFeature('dom')): ?>
 </td>
 <td rowspan="2" valign="top" align="right">&nbsp;
<?php endif; ?>
 </td>
</tr>
