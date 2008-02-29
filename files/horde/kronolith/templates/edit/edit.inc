<div id="page">
<?php $showCalSelect = (!$GLOBALS['prefs']->isLocked('default_share') && count($calendars) > 1); ?>
<form action="<?php echo ($event->isInitialized() ? 'editeventaction.php' : 'addeventaction.php') ?>" method="post" name="event">
<?php Util::pformInput() ?>
<input type="hidden" name="year" value="<?php if (isset($year)) echo htmlspecialchars($year) ?>" />
<input type="hidden" name="month" value="<?php if (isset($month)) echo htmlspecialchars($month) ?>" />
<input type="hidden" name="mday" value="<?php if (isset($day)) echo htmlspecialchars($day) ?>" />
<input type="hidden" name="timestamp" value="<?php if (isset($timestamp)) echo htmlspecialchars($timestamp) ?>" />
<input type="hidden" name="new_category" value="" />
<?php if (isset($url)): ?>
<input type="hidden" name="url" value="<?php echo htmlspecialchars($url) ?>" />
<?php endif; ?>
<?php if ($event->isInitialized()): ?>
<input type="hidden" name="eventID" value="<?php echo htmlspecialchars($event->getID()) ?>" />
<?php endif; ?>
<?php if ($event->isInitialized()): ?>
<input type="hidden" name="existingcalendar" value="<?php echo htmlspecialchars($event->getCalendar()) ?>" />
<?php endif; ?>
<?php if (!$showCalSelect): ?>
<input type="hidden" name="targetcalendar" value="<?php echo htmlspecialchars($calendar_id) ?>" />
<?php endif; ?>


<!-- header -->
<h1 class="header">
 <?php echo $event->isInitialized() ? sprintf(_("Edit %s"), htmlspecialchars($event->getTitle())) : _("Add Event") ?>
</h1>

<table class="striped" cellspacing="0">
<!-- buttons -->
<tr>
 <td></td>
 <td colspan="4">
  <?php echo implode("\n", $buttons) ?>
  <input type="submit" name="cancel" class="button" value="<?php echo _("Cancel") ?>" onclick="self.location = '<?php echo $cancelurl ?>'; return false;" />
  <input type="button" name="resetButton" class="button" value="<?php echo _("Reset to Defaults") ?>" onclick="document.event.reset(); updateWday('start_wday'); updateWday('end_wday');" />
 </td>
</tr>

<!-- title -->
<tr>
 <td align="right"><strong><?php echo Horde::label('title', _("Tit_le")) ?>&nbsp;&nbsp;</strong></td>
 <td colspan="4">
  <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($event->getTitle()) ?>" size="40" maxlength="255" />
 </td>
</tr>

<!-- calendar -->
<?php if ($showCalSelect): ?>
<tr>
 <td align="right"><strong><?php echo Horde::label('targetcalendar', _("Cal_endar")) ?>&nbsp;&nbsp;</strong></td>
 <td colspan="4">
    <select id="targetcalendar" name="targetcalendar">
    <?php
    foreach ($calendars as $id => $cal) {
        $sel = ($id == $calendar_id) ? ' selected="selected"' : '';
        printf('<option value="%s"%s>%s</option>',
               htmlspecialchars($id), $sel, htmlspecialchars($cal->get('name'))) . "\n";
    } ?>
    </select>

 </td>
</tr>
<?php endif; ?>

<!-- category -->
<tr>
 <td align="right">
    <strong><?php echo Horde::label('category', _("Categor_y")) ?>&nbsp;&nbsp;</strong>
 </td>
 <td colspan="4"><?php echo $cManager->getSelect('category', $event->getCategory()) ?></td>
</tr>

<!-- location -->
<tr>
 <td align="right"><strong><?php echo Horde::label('location', _("Lo_cation")) ?>&nbsp;&nbsp;</strong></td>
 <td colspan="4">
  <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($event->getLocation()) ?>" size="40" maxlength="255" />
 </td>
</tr>

<!-- status -->
<tr>
 <td align="right">
    <strong><?php echo Horde::label('status', _("Stat_us")) ?>&nbsp;&nbsp;</strong>
 </td>
 <td colspan="4">
  <?php echo Kronolith::buildStatusWidget('status', $event->getStatus()) ?>
 </td>
</tr>

<?php require KRONOLITH_TEMPLATES . '/edit/edit_timespan.inc' ?>

<!-- alarm -->
<tr>
 <td align="right" rowspan="2" valign="top">
    <strong><?php $ak = Horde::getAccessKey(_("_Alarm")); echo Horde::highlightAccessKey(_("_Alarm"), $ak) ?>&nbsp;&nbsp;</strong>
 </td>
 <td valign="top">
  <?php
  if ($event->isInitialized() && $event->alarm > 0) {
      $alarm_set = true;
      if ($event->alarm % 10080 == 0) {
          $alarm_value = $event->alarm / 10080;
          $alarm_unit = 'week';
      } elseif ($event->alarm % 1440 == 0) {
          $alarm_value = $event->alarm / 1440;
          $alarm_unit = 'day';
      } elseif ($event->alarm % 60 == 0) {
          $alarm_value = $event->alarm / 60;
          $alarm_unit = 'hour';
      } else {
          $alarm_value = $event->alarm;
          $alarm_unit = 'min';
      }
  } else {
      $alarm_value = 15;
      $alarm_unit = 'min';
      $alarm_set = false;
  }
  ?>
  <input id="noalarm" name="alarm" type="radio" value="0"<?php if (!$alarm_set) echo ' checked="checked"' ?> />
 </td>
 <td>&nbsp;</td>
 <td valign="top" colspan="2">
  <label for="noalarm" accesskey="<?php echo $ak ?>"> <?php echo _("No alarm") ?></label>
 </td>
</tr>
<tr>
 <td valign="top">
  <input name="alarm" type="radio" value="1"<?php if ($alarm_set) echo ' checked="checked"' ?> />
 </td>
 <td>&nbsp;</td>
 <td valign="top" colspan="2">
  <input type="text" size="2" name="alarm_value" value="<?php echo $alarm_value ?>" onchange="document.event.alarm[1].checked=true" />&nbsp;
  <select name="alarm_unit" onchange="document.event.alarm[1].checked=true">
   <option value="1"<?php echo ($alarm_unit == 'min') ? ' selected="selected"' : '' ?>><?php echo _("Minute(s)") ?></option>
   <option value="60"<?php echo ($alarm_unit == 'hour') ? ' selected="selected"' : '' ?>><?php echo _("Hour(s)") ?></option>
   <option value="1440"<?php echo ($alarm_unit == 'day') ? ' selected="selected"' : '' ?>><?php echo _("Day(s)") ?></option>
   <option value="10080"<?php echo ($alarm_unit == 'week') ? ' selected="selected"' : '' ?>><?php echo _("Week(s)") ?></option>
  </select>
  <?php echo _("before the event starts") ?>
 </td>
</tr>

<!-- description -->
<tr>
 <td colspan="5" class="header">
  <span class="smallheader"><?php echo Horde::label('description', _("<strong>Descri_ption</strong> (optional)")) ?></span>
</td>
</tr>
<tr>
 <td>&nbsp;</td>
 <td colspan="4">
  <textarea id="description" name="description" cols="40" rows="4"><?php echo htmlspecialchars($event->getDescription()) ?></textarea>
 </td>
</tr>

<!-- attendees -->
<?php
$attendees_url = Util::addParameter('', 'timestamp', $timestamp);
if ($browser->hasFeature('javascript')) {
    Horde::addScriptFile('open_attendees_win.js');
    $attendees_link = 'javascript:open_attendees_win(\'' . $browser->escapeJSCode(substr($attendees_url, 1)) . '\');';
} else {
    $attendees_link = Horde::applicationUrl('attendees.php' . $attendees_url);
}
?>
<tr>
 <td colspan="5" class="header">
  <span class="smallheader"><?php echo Horde::label('attendees', _("<strong>Attendees</strong> (optional)")) ?></span>
 </td>
</tr>

<tr>
 <td></td>
 <td>
  <input id="sendupdates" type="checkbox" name="sendupdates" />
 </td>
 <td colspan="3">
  <label for="sendupdates"><?php echo _("Send updates/invitations to all attendees when saving this event") ?></label>
 </td>
</tr>
<tr>
 <td></td>
 <td colspan="4">
  <input type="button" name="editattendees" class="button" value="<?php echo _("Edit Attendees") ?>" onclick="<?php echo $attendees_link ?>" />
 </td>
</tr>

<!-- recurrence -->
<tr>
 <td colspan="5" class="header">
  <span class="smallheader"><?php echo _("<strong>Recurrence</strong> (optional)") ?></span>
 </td>
</tr>
<tr>
 <td align="right" valign="top"><strong><?php echo Horde::label('recurnone', _("Pattern")) ?>&nbsp;&nbsp;</strong></td>
 <td valign="top" colspan="4">
  <table cellspacing="0" width="100%">
   <tr>
    <td class="nowrap">
     <input id="recurnone" type="radio" name="recur" onclick="clearFields(0);" value="<?php echo KRONOLITH_RECUR_NONE ?>"<?php if ($event->hasRecurType(KRONOLITH_RECUR_NONE)) echo ' checked="checked"' ?> /><label for="recurnone"> <?php echo _("No recurrence") ?></label>
    </td>
   </tr>
   <tr>
    <td class="nowrap">
     <input id="recurdaily" type="radio" name="recur" onclick="setInterval('recur_daily_interval');" value="<?php echo KRONOLITH_RECUR_DAILY ?>"<?php if ($event->hasRecurType(KRONOLITH_RECUR_DAILY)) echo ' checked="checked"' ?> /><label for="recurdaily"> <?php echo _("Daily: Recurs every") ?>&nbsp;</label>
     <input type="text" name="recur_daily_interval" size="2" onkeypress="setRecur(1);" onchange="setRecur(1);" value="<?php echo $event->hasRecurType(KRONOLITH_RECUR_DAILY) ? $event->getRecurInterval() : '' ?>" />&nbsp;<?php echo _("day(s)") ?>
    </td>
   </tr>
   <tr>
    <td class="nowrap">
     <input id="recurweekly" type="radio" name="recur" onclick="setInterval('recur_weekly_interval');" value="<?php echo KRONOLITH_RECUR_WEEKLY ?>"<?php if ($event->hasRecurType(KRONOLITH_RECUR_WEEKLY)) echo ' checked="checked"' ?> /><label for="recurweekly"> <?php echo _("Weekly: Recurs every") ?>&nbsp;</label>
     <input type="text" name="recur_weekly_interval" size="2" onkeypress="setRecur(2);" onchange="setRecur(2);" value="<?php echo $event->hasRecurType(KRONOLITH_RECUR_WEEKLY) ? $event->getRecurInterval() : '' ?>" />&nbsp;
     <?php echo _("week(s) on:") ?><br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
     <label for="mo"><?php echo _("Mo") ?></label><input id="mo" type="checkbox" name="weekly[]" onclick="setInterval('recur_weekly_interval');setRecur(2);" value="<?php echo HORDE_DATE_MASK_MONDAY ?>"<?php if ($event->recurOnDay(HORDE_DATE_MASK_MONDAY)) echo ' checked="checked"' ?> />&nbsp;
     <label for="tu"><?php echo _("Tu") ?></label><input id="tu" type="checkbox" name="weekly[]" onclick="setInterval('recur_weekly_interval');setRecur(2);" value="<?php echo HORDE_DATE_MASK_TUESDAY ?>"<?php if ($event->recurOnDay(HORDE_DATE_MASK_TUESDAY)) echo ' checked="checked"' ?> />&nbsp;
     <label for="we"><?php echo _("We") ?></label><input id="we" type="checkbox" name="weekly[]" onclick="setInterval('recur_weekly_interval');setRecur(2);" value="<?php echo HORDE_DATE_MASK_WEDNESDAY ?>"<?php if ($event->recurOnDay(HORDE_DATE_MASK_WEDNESDAY)) echo ' checked="checked"' ?> />&nbsp;
     <label for="th"><?php echo _("Th") ?></label><input id="th" type="checkbox" name="weekly[]" onclick="setInterval('recur_weekly_interval');setRecur(2);" value="<?php echo HORDE_DATE_MASK_THURSDAY ?>"<?php if ($event->recurOnDay(HORDE_DATE_MASK_THURSDAY)) echo ' checked="checked"' ?> />&nbsp;
     <label for="fr"><?php echo _("Fr") ?></label><input id="fr" type="checkbox" name="weekly[]" onclick="setInterval('recur_weekly_interval');setRecur(2);" value="<?php echo HORDE_DATE_MASK_FRIDAY ?>"<?php if ($event->recurOnDay(HORDE_DATE_MASK_FRIDAY)) echo ' checked="checked"' ?> />&nbsp;
     <label for="sa"><?php echo _("Sa") ?></label><input id="sa" type="checkbox" name="weekly[]" onclick="setInterval('recur_weekly_interval');setRecur(2);" value="<?php echo HORDE_DATE_MASK_SATURDAY ?>"<?php if ($event->recurOnDay(HORDE_DATE_MASK_SATURDAY)) echo ' checked="checked"' ?> />&nbsp;
     <label for="su"><?php echo _("Su") ?></label><input id="su" type="checkbox" name="weekly[]" onclick="setInterval('recur_weekly_interval');setRecur(2);" value="<?php echo HORDE_DATE_MASK_SUNDAY ?>"<?php if ($event->recurOnDay(HORDE_DATE_MASK_SUNDAY)) echo ' checked="checked"' ?> />&nbsp;
    </td>
   </tr>
   <tr>
    <td class="nowrap">
     <input id="recurmonthday" type="radio" name="recur" onclick="setInterval('recur_day_of_month_interval');" value="<?php echo KRONOLITH_RECUR_DAY_OF_MONTH ?>"<?php if ($event->hasRecurType(KRONOLITH_RECUR_DAY_OF_MONTH)) echo ' checked="checked"' ?> /><label for="recurmonthday"> <?php echo _("Monthly: Recurs every") ?>&nbsp;</label>
     <input type="text" name="recur_day_of_month_interval" size="2" onkeypress="setRecur(3);" onchange="setRecur(3);" value="<?php echo $event->hasRecurType(KRONOLITH_RECUR_DAY_OF_MONTH) ? $event->getRecurInterval() : '' ?>" />&nbsp;<?php echo _("month(s)") . ' ' . _("on the same date") ?>
    </td>
   </tr>
   <tr>
    <td class="nowrap">
     <input id="recurmonthweek" type="radio" name="recur" onclick="setInterval('recur_week_of_month_interval');" value="<?php echo KRONOLITH_RECUR_WEEK_OF_MONTH ?>"<?php if ($event->hasRecurType(KRONOLITH_RECUR_WEEK_OF_MONTH)) echo ' checked="checked"' ?> /><label for="recurmonthweek"> <?php echo _("Monthly: Recurs every") ?>&nbsp;</label>
     <input type="text" name="recur_week_of_month_interval" size="2" onkeypress="setRecur(4);" onchange="setRecur(4);" value="<?php echo $event->hasRecurType(KRONOLITH_RECUR_WEEK_OF_MONTH) ? $event->getRecurInterval() : '' ?>" />&nbsp;<?php echo _("month(s)") . ' ' . _("on the same weekday") ?>
    </td>
   </tr>
   <tr>
    <td class="nowrap">
     <input id="recuryear" type="radio" name="recur" onclick="setInterval('recur_yearly_interval');" value="<?php echo KRONOLITH_RECUR_YEARLY ?>"<?php if ($event->hasRecurType(KRONOLITH_RECUR_YEARLY)) echo ' checked="checked"' ?> /><label for="recuryear"> <?php echo _("Yearly: Recurs every") ?>&nbsp;</label>
     <input type="text" name="recur_yearly_interval" size="2" onkeypress="setRecur(5);" onchange="setRecur(5);" value="<?php echo $event->hasRecurType(KRONOLITH_RECUR_YEARLY) ? $event->getRecurInterval() : '' ?>" />&nbsp;<?php echo _("year(s)") ?>
    </td>
   </tr>
  </table>
 </td>
</tr>

<!-- recur end date -->
<tr>
 <td align="right" valign="top">
    <strong><?php echo Horde::label('recur_enddate[year]', _("Recur Until")) ?>&nbsp;&nbsp;</strong>
 </td>
 <td colspan="3">
  <input id="recurnoend" type="radio" name="recur_enddate_type" value="none"<?php echo ($event->hasRecurEnd() ? '' : ' checked="checked"') ?> /><label for="recurnoend"> <?php echo _("No end date") ?></label><br />
  <input type="radio" name="recur_enddate_type" value="date"<?php echo ($event->hasRecurEnd() ? ' checked="checked"' : '') ?> />
  <?php echo $event->html('recur_enddate[year]') ?> - <?php echo $event->html('recur_enddate[month]') ?> - <?php echo $event->html('recur_enddate[day]') ?>
<?php if ($browser->hasFeature('dom')): ?>
   <span id="recur_end_wday"></span>
   <script type="text/javascript">
    updateWday("recur_end_wday");
   </script>
<?php endif; ?>
<?php if ($GLOBALS['browser']->hasFeature('dom')) {
    echo '</td><td>';
    Horde::addScriptFile('open_calendar.js', 'horde');
    echo Horde::link('#', _("Select a date"), '', '', 'openCalendar(\'recur_enddateimg\', \'recur_enddate\', \'' . addslashes($event->js('recur_enddate')) . '\'); return false;') . Horde::img('calendar.png', _("Set recurrence end date"), 'align="top" id="recur_enddateimg"', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
} ?>
 </td>
</tr>

<?php if ($conf['metadata']['keywords']): ?>
<!-- keywords -->
<tr class="smallheader">
 <td colspan="5" class="header">
  <span class="smallheader"><?php echo _("<strong>Keywords</strong> (optional)") ?></span>
 </td>
</tr>
<tr>
 <td></td>
 <td colspan="4">
  <table cellspacing="2">
   <?php include_once KRONOLITH_BASE . '/config/keywords.php'; $count = 0 ?>
   <?php foreach ($keywords as $cat => $list):
       if ($count % 3 == 0): ?>
   <tr>
   <?php endif; ?>
    <td valign="top">
     <strong><?php echo $cat ?></strong><br />
    <?php for ($k = 0; $k < count($list); ++$k): ?>
     <input type="checkbox" id="keyword<?php echo htmlspecialchars($k) ?>" name="keywords[]" value="<?php echo htmlspecialchars($list[$k]) ?>"<?php if ($event->hasKeyword($list[$k])) echo ' checked="checked"' ?> />
     <?php echo Horde::label('keyword' . $k, htmlspecialchars($list[$k])) ?><br />
    <?php endfor; ?>
    </td>
   <?php if (($count % 3 == 2) || ($count == count($keywords) - 1)): ?>
   </tr>
   <?php endif; ++$count ?>
   <?php endforeach; ?>
  </table>
 </td>
</tr>
<?php endif; ?>

<!-- buttons -->
<tr>
 <td></td>
 <td colspan="4">
  <?php echo implode("\n", $buttons) ?>
  <input type="submit" name="cancel" class="button" value="<?php echo _("Cancel") ?>" onclick="self.location = '<?php echo $cancelurl ?>'; return false;" />
  <input type="button" name="resetButton" class="button" value="<?php echo _("Reset to Defaults") ?>" onclick="document.event.reset(); updateWday('start_wday'); updateWday('end_wday');" />
  </td>
 </tr>
</table>
</form>
</div>
<script type="text/javascript">
document.event.title.focus();
</script>
