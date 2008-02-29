<script type="text/javascript">
<!--
function performAction(id, value)
{
    document.attendeesForm.actionID.value = id;
    document.attendeesForm.actionValue.value = value;
    document.attendeesForm.submit();
    return false;
}

function switchTimestamp(timestamp)
{
    document.attendeesForm.timestamp.value = timestamp;
    document.attendeesForm.submit();
    return false;
}

function switchView(view)
{
    document.attendeesForm.view.value = view;
    document.attendeesForm.submit();
    return false;
}
// -->
</script>

<form method="post" action="attendees.php" name="attendeesForm">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="add" />
<input type="hidden" name="actionValue" value="" />
<input type="hidden" name="view" value="<?php echo htmlspecialchars($view) ?>" />
<input type="hidden" name="timestamp" value="<?php echo htmlspecialchars($timestamp) ?>" />

<h1 class="header"><?php echo htmlspecialchars($title) ?></h1>

<table width="100%" cellspacing="0" class="linedRow">

<!-- attendee list header -->
<tr class="item nowrap leftAlign">
 <th width="2%"><?php echo $delimg ?></th>
 <th width="48%"><?php echo htmlspecialchars(_("Email Address")) ?></th>
 <th width="25%"><?php echo htmlspecialchars(_("Attendance")) ?></th>
 <th width="25%"><?php echo htmlspecialchars(_("Response")) ?></th>
</tr>

<!-- attendees -->
<?php $i = 0; if (empty($attendees)): ?>
 <tr><td colspan="4"><em><?php echo _("No attendees") ?></em></td></tr>
<?php else: foreach ($attendees as $email => $status): ?>
 <tr>
  <td><?php echo Horde::link('#', sprintf(_("Remove %s"), $email), '', '', "performAction('remove', decodeURIComponent('" . rawurlencode($email) . "')); return false;") . Horde::img('delete.png', '', null, $registry->getImageDir('horde')) . '</a>' ?></td>
  <td><?php echo htmlspecialchars($email) ?></td>
  <td>
   <select name="<?php echo "attendance_$i" ?>" onchange="performAction('changeatt', document.attendeesForm.<?php echo "attendance_$i" ?>.value + ' ' + decodeURIComponent('<?php echo rawurlencode($email) ?>'));">
    <option value="<?php echo KRONOLITH_PART_REQUIRED ?>"<?php if ($status['attendance'] == KRONOLITH_PART_REQUIRED)  echo ' selected="selected"' ?>><?php echo Kronolith::partToString(KRONOLITH_PART_REQUIRED) ?></option>
    <option value="<?php echo KRONOLITH_PART_OPTIONAL ?>"<?php if ($status['attendance'] == KRONOLITH_PART_OPTIONAL)  echo ' selected="selected"' ?>><?php echo Kronolith::partToString(KRONOLITH_PART_OPTIONAL) ?></option>
    <option value="<?php echo KRONOLITH_PART_NONE ?>"<?php if ($status['attendance'] == KRONOLITH_PART_NONE)  echo ' selected="selected"' ?>><?php echo Kronolith::partToString(KRONOLITH_PART_NONE) ?></option>
   </select>
  </td>
  <td><?php echo Kronolith::responseToString($status['response']) ?></td>
 </tr>
 <?php $i++; ?>
<?php endforeach; ?>
<?php endif; ?>
</table>

<br />

<table width="100%" cellspacing="2" class="nowrap control">
 <tr>
  <td class="rightAlign">&nbsp;<strong><?php echo Horde::highlightAccessKey(_("Add attendees"), $ak = Horde::getAccessKey(_("Add attendees"))) ?></strong></td>
  <td><input type="text" name="newAttendees" size="50" <?php echo (!empty($ak)) ? 'accesskey="' . $ak . '" ' : '' ?>/></td>
<?php if ($registry->hasMethod('contacts/search')): ?>
  <td align="center"><?php echo Horde::link('#', '', 'widget', null, 'window.open(\'' . Horde::applicationUrl('contacts.php') . '\', \'contacts\', \'toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes,width=550,height=270,left=100,top=100\'); return false;') . Horde::img('addressbook_browse.png') . '<br />' . _("Address Book") . '</a>' ?></td>
  <td align="center"><?php echo Horde::link('#', '', 'widget', null, "return performAction('expand', '');") . Horde::img('expand.png') . '<br />' . _("Expand Names") . '</a>' ?></td>
  <td width="100%">&nbsp;</td>
<?php endif; ?>
 </tr>
</table>

<br />
<div>
 <input type="submit" class="button" name="addNew" value="<?php echo htmlspecialchars(_("Save Attendees")) ?>" />
 <input type="submit" class="button" name="addNewClose" value="<?php echo htmlspecialchars(_("Save and Finish")) ?>" />
 <?php if (!empty($attendees)): ?><input type="submit" class="button" name="clearAll" value="<?php echo htmlspecialchars(_("Clear all attendees")) ?>" /><?php endif; ?>
</div>

<br />
<?php echo $tabs->render($view) . $vfb_html ?>
</form>
