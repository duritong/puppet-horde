<div id="page">

<!-- header -->
<h1 class="header">
 <?php echo @htmlspecialchars($event->getTitle(), ENT_COMPAT, NLS::getCharset()) ?>
 <?php if (!$print_view): ?> <small>[ <?php echo implode(' | ', $mylinks) ?> ]</small><?php endif; ?>
</h1>

<table cellspacing="0" width="100%" class="striped">

<!-- category -->
<tr>
 <td width="10%" class="rightAlign"><strong><?php echo _("Category") ?>&nbsp;&nbsp;</strong></td>
 <td width="90%">
  <?php echo @htmlspecialchars($category ? $category : _("Unfiled"), ENT_COMPAT, NLS::getCharset()) ?>
 </td>
</tr>

<!-- location -->
<tr>
 <td class="rightAlign"><strong><?php echo _("Location") ?>&nbsp;&nbsp;</strong></td>
 <td><?php echo empty($location) ? '&nbsp;' : @htmlspecialchars($location, ENT_COMPAT, NLS::getCharset()) ?> </td>
</tr>

<!-- status -->
<tr>
 <td class="rightAlign"><strong><?php echo _("Status") ?>&nbsp;&nbsp;</strong></td>
 <td><?php echo empty($status) ? '&nbsp;' : htmlspecialchars($status) ?> </td>
</tr>

<?php if (!empty($owner)): ?>
<!-- owner -->
<tr>
 <td class="rightAlign"><strong><?php echo _("Owner") ?>&nbsp;&nbsp;</strong></td>
 <td><?php echo @htmlspecialchars($owner, ENT_COMPAT, NLS::getCharset()) ?> </td>
</tr>
<?php endif; ?>

<!-- start date -->
<tr>
 <td class="rightAlign"><strong><?php echo _("Start On") ?>&nbsp;&nbsp;</strong></td>
 <td><?php echo strftime($prefs->getValue('date_format'), $event->start->timestamp()) . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $event->start->timestamp()) ?></td>
</tr>

<!-- end date -->
<tr>
 <td class="rightAlign"><strong><?php echo _("End On") ?>&nbsp;&nbsp;</strong></td>
 <td><?php echo strftime($prefs->getValue('date_format'), $event->end->timestamp()) . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $event->end->timestamp()) ?></td>
</tr>

<!-- alarm -->
<tr>
 <td class="rightAlign"><strong><?php echo _("Alarm") ?>&nbsp;&nbsp;</strong></td>
 <td>
<?php
if ($event->isInitialized() && $event->alarm > 0):
    if ($event->alarm % 10080 == 0) {
        $alarm_value = $event->alarm / 10080;
        $alarm_unit = _("Week(s)");
    } elseif ($event->alarm % 1440 == 0) {
        $alarm_value = $event->alarm / 1440;
        $alarm_unit = _("Day(s)");
    } elseif ($event->alarm % 60 == 0) {
        $alarm_value = $event->alarm / 60;
        $alarm_unit = _("Hour(s)");
    } else {
        $alarm_value = $event->alarm;
        $alarm_unit = _("Minute(s)");
    }
?>
  <?php echo $alarm_value . ' ' . $alarm_unit ?>
<?php else: ?>
  <?php echo _("No alarm") ?>
<?php endif; ?>
 </td>
</tr>

<?php if (isset($created)): ?>
<tr>
  <td class="nowrap rightAlign"><strong><?php echo _("Created") ?></strong>&nbsp;&nbsp;</td>
  <td class="nowrap"><?php echo strftime($prefs->getValue('date_format'), $created) . ' ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:i a', $created) ?></td>
</tr>
<?php endif; ?>

<?php if (isset($modified)): ?>
<tr>
  <td class="nowrap rightAlign"><strong><?php echo _("Last Modified") ?></strong>&nbsp;&nbsp;</td>
  <td class="nowrap"><?php echo strftime($prefs->getValue('date_format'), $modified) . ' ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:i a', $modified) ?></td>
</tr>
<?php endif; ?>


<?php if (!empty($description)): ?>
<!-- description -->
<tr>
 <td colspan="2" class="header"><span class="smallheader"><?php echo _("Description") ?></span></td>
</tr>
<tr>
 <td colspan="2"><?php require_once 'Horde/Text/Filter.php'; echo Text_Filter::filter($description, 'text2html', array('parselevel' => TEXT_HTML_MICRO, 'class' => 'text')) ?></td>
</tr>
<?php endif; ?>

<?php if (!$event->hasRecurType(KRONOLITH_RECUR_NONE)): ?>
<!-- recurrence -->
<tr>
 <td colspan="2" class="header"><span class="smallheader"><?php echo _("Recurrence") ?></span></td>
</tr>
<tr>
 <td class="rightAlign" valign="top"><strong><?php echo _("Pattern") ?>&nbsp;&nbsp;</strong></td>
 <td valign="top">
<?php if ($event->hasRecurType(KRONOLITH_RECUR_DAILY)): ?>
   <?php echo _("Daily: Recurs every") . ' ' . $event->getRecurInterval() . ' ' . _("day(s)") ?>
<?php elseif ($event->hasRecurType(KRONOLITH_RECUR_WEEKLY)):
    $weekdays = array();
    if ($event->recurOnDay(HORDE_DATE_MASK_MONDAY))    $weekdays[] = _("Monday");
    if ($event->recurOnDay(HORDE_DATE_MASK_TUESDAY))   $weekdays[] = _("Tuesday");
    if ($event->recurOnDay(HORDE_DATE_MASK_WEDNESDAY)) $weekdays[] = _("Wednesday");
    if ($event->recurOnDay(HORDE_DATE_MASK_THURSDAY))  $weekdays[] = _("Thursday");
    if ($event->recurOnDay(HORDE_DATE_MASK_FRIDAY))    $weekdays[] = _("Friday");
    if ($event->recurOnDay(HORDE_DATE_MASK_SATURDAY))  $weekdays[] = _("Saturday");
    if ($event->recurOnDay(HORDE_DATE_MASK_SUNDAY))    $weekdays[] = _("Sunday");
    echo _("Weekly: Recurs every") . ' ' . $event->getRecurInterval() . ' ' . _("week(s) on:") . ' ' . implode(', ', $weekdays) ?>
<?php elseif ($event->hasRecurType(KRONOLITH_RECUR_DAY_OF_MONTH)): ?>
   <?php echo _("Monthly: Recurs every") . ' ' . $event->getRecurInterval() . ' ' . _("month(s)") . ' ' . _("on the same date") ?>
<?php elseif ($event->hasRecurType(KRONOLITH_RECUR_WEEK_OF_MONTH)): ?>
   <?php echo _("Monthly: Recurs every") . ' ' . $event->getRecurInterval() . ' ' . _("month(s)") . ' ' . _("on the same weekday") ?>
<?php elseif ($event->hasRecurType(KRONOLITH_RECUR_YEARLY)): ?>
   <?php echo _("Yearly: Recurs every") . ' ' . $event->getRecurInterval() . ' ' . _("year(s)") ?>
<?php endif; ?>
 </td>
</tr>

<!-- recur end date -->
<tr>
 <td class="rightAlign"><strong><?php echo _("Recur Until") ?>&nbsp;&nbsp;</strong></td>
 <td><?php echo $event->hasRecurEnd() ? strftime($prefs->getValue('date_format'), $event->recurEnd->timestamp()) . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $event->recurEnd->timestamp()) : _("No end date") ?></td>
</tr>
<?php endif; ?>

<?php if ($conf['metadata']['keywords'] && count($keyword_list)): ?>
<tr>
 <td colspan="2" class="header"><span class="smallheader"><?php echo _("Keywords") ?></span></td>
</tr>
<?php foreach ($keyword_list as $cat => $list): ?>
<tr>
 <td class="rightAlign" valign="top"><strong><?php echo htmlspecialchars($cat) ?>&nbsp;&nbsp;</strong></td>
 <td>
<?php foreach ($list as $entry): ?>
  <?php echo htmlspecialchars($entry) ?><br />
<?php endforeach; ?>
 </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</table>

<?php if (!empty($attendees)): ?>
<table cellspacing="0">
<!-- attendees -->
<tr>
 <td colspan="3" class="header"><span class="smallheader"><?php echo _("Attendees") ?></span></td>
</tr>
 <tr class="item">
  <td width="50%"><strong><?php echo _("Email Address") ?>&nbsp;&nbsp;</strong></td>
  <td width="25%"><strong><?php echo _("Attendance") ?>&nbsp;&nbsp;</strong></td>
  <td width="25%"><strong><?php echo _("Response") ?>&nbsp;&nbsp;</strong></td>
 </tr>
 <?php $link_emails = $GLOBALS['registry']->hasMethod('mail/compose'); foreach ($attendees as $email => $status): $full = empty($status['name']) ? $email : MIME::trimEmailAddress($status['name'] . (strpos($email, '@') === false ? '' : ' <' . $email . '>')) ?>
 <tr>
  <td class="nowrap"><?php echo ($link_emails && strpos($email, '@') !== false) ? (Horde::link($GLOBALS['registry']->call('mail/compose', array(array('to' => $full))), sprintf(_("Send an email to %s"), empty($status['name']) ? $email : $status['name'])) . htmlspecialchars($full) . '</a>') : htmlspecialchars($full) ?></td>
  <td><?php echo Kronolith::partToString($status['attendance']) ?></td>
  <td><?php echo Kronolith::responseToString($status['response']) ?></td>
 </tr>
 <?php endforeach; ?>
</tr>
</table>
<?php endif; ?>

</div>
