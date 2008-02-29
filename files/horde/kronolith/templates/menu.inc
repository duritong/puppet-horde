<?php
$remote_calendars = unserialize($prefs->getValue('remote_cals'));
$current_user = Auth::getAuth();
$my_calendars = array();
$shared_calendars = array();
foreach (Kronolith::listCalendars() as $id => $cal) {
    if ($cal->get('owner') == $current_user) {
        $my_calendars[$id] = $cal;
    } else {
        $shared_calendars[$id] = $cal;
    }
}

if (strstr($_SERVER['PHP_SELF'], 'month.php') !== false ||
    strstr($_SERVER['PHP_SELF'], 'week.php') !== false ||
    strstr($_SERVER['PHP_SELF'], 'workweek.php') !== false ||
    strstr($_SERVER['PHP_SELF'], 'day.php') !== false): ?>
<form action="<?php echo Horde::selfUrl() ?>" method="post" name="calendarSelector">
<?php else: ?>
<form action="<?php echo Horde::applicationUrl($prefs->getValue('defaultview') . '.php') ?>" method="post" name="calendarSelector">
<?php endif; ?>
<?php echo Util::formInput() ?>
<input type="hidden" name="timestamp" value="<?php echo Kronolith::currentTimestamp() ?>" />

<div id="menu">
<?php if (!$prefs->isLocked('display_cals') && ((count($remote_calendars) || count($GLOBALS['all_calendars']) > 1) || !count($display_calendars))): ?>
 <span style="float:right">
<select name="display_cal" onchange="calendarSubmit()">
<?php if ($browser->hasFeature('optgroup')): ?>
  <option value=""><?php echo _("Select calendars to display:") ?></option>
<?php endif; ?>
<?php if (count($my_calendars)): ?>
<?php if ($browser->hasFeature('optgroup')): ?>
  <optgroup label="<?php echo _("My Calendars:") ?>">
<?php else: ?>
  <option value=""><?php echo _("My Calendars:") ?></option>
<?php endif; ?>
<?php foreach ($my_calendars as $id => $cal): ?>
  <option value="<?php echo htmlspecialchars($id) ?>"<?php echo (in_array($id, $display_calendars) ? ' class="selected"' : '') . '>' . htmlspecialchars($cal->get('name')) ?></option>
<?php endforeach; ?>
<?php if ($browser->hasFeature('optgroup')): ?>
  </optgroup>
<?php endif; ?>
<?php endif; ?>
<?php if (count($shared_calendars)): ?>
<?php if ($browser->hasFeature('optgroup')): ?>
  <optgroup label="<?php echo _("Shared Calendars:") ?>">
<?php else: ?>
  <option value=""><?php echo _("Shared Calendars:") ?></option>
<?php endif; ?>
<?php foreach ($shared_calendars as $id => $cal): ?>
  <option value="<?php echo htmlspecialchars($id) ?>"<?php echo (in_array($id, $display_calendars) ? ' class="selected"' : '') . '>[' . htmlspecialchars($cal->get('owner')) . '] ' . htmlspecialchars($cal->get('name')) ?></option>
<?php endforeach; ?>
<?php if ($browser->hasFeature('optgroup')): ?>
  </optgroup>
<?php endif; ?>
<?php endif; ?>
<?php if (count($remote_calendars)): ?>
<?php if ($browser->hasFeature('optgroup')): ?>
  <optgroup label="<?php echo _("Remote Calendars:") ?>">
<?php else: ?>
  <option value=""><?php echo _("Remote Calendars:") ?></option>
<?php endif; ?>
<?php foreach ($remote_calendars as $id => $cal): ?>
  <option value="remote_<?php echo htmlspecialchars($cal['url']) ?>"<?php echo (in_array($cal['url'], $display_remote_calendars) ? ' class="selected"' : '') . '>' . htmlspecialchars($cal['name']) ?></option>
<?php endforeach; ?>
<?php if ($browser->hasFeature('optgroup')): ?>
  </optgroup>
<?php endif; ?>
<?php endif; ?>
</select>
 </span>
<?php endif; ?>

 <?php echo Kronolith::getMenu('string') ?>
 <br class="clear" />
</div>
</form>

<script type="text/javascript">
<!--
var loading;
function calendarSubmit()
{
    document.calendarSelector.submit();
    return false;
}
// -->
</script>

<?php $GLOBALS['notification']->notify(array('listeners' => 'status')) ?>
