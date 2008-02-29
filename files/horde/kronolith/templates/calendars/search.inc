<h1 class="header"><?php echo _("Search Calendars") ?></h1>

<div class="item">
<form action="<?php echo Horde::selfUrl() ?>">
<?php echo Util::formInput() ?>
<?php echo Horde::label('search', _("Search for calendars containing:")) ?><br />
<input type="text" name="search" value="<?php echo htmlspecialchars($search) ?>" />
<input type="submit" name="submit" class="button" value="<?php echo _("Search") ?>" />
</form>
</div>

<?php if ($results): ?>
<br class="spacer" />

<div class="header">
  <?php printf(_("%d calendars found"), count($results)) ?>
  <span class="smallheader"><?php echo _("(Click on calendar name to switch the calendar's visibility in the main window)") ?></span>
</div>
<table cellspacing="0" class="striped" width="100%">
<?php $i = 0; foreach ($results as $calendar => $share): ?>
  <tr id="<?php echo htmlspecialchars($calendar) ?>">
    <td><?php echo Horde::link('#', '', '', '', 'if (window.opener) { window.opener.location.href = window.opener.selfUrl+\'' . $calendar . '\'; } return false') . htmlspecialchars($share->get('name')) . '</a>' ?></td>
  <tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
