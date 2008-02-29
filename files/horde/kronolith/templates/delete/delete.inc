<?php
if (empty($url)) {
    $url = Util::addParameter('month.php', array('month' => $month,
                                                 'year' => $year));
    $url = Horde::applicationUrl($url, true);
} else {
    // Escape URLs that came from client-side input.
    $url = htmlspecialchars($url);
}
?>
<form action="deleventaction.php" method="post" name="delete">
<?php echo Util::formInput() ?>
<input type="hidden" name="year" value="<?php if (isset($year)) echo htmlspecialchars($year) ?>" />
<input type="hidden" name="month" value="<?php if (isset($month)) echo htmlspecialchars($month) ?>" />
<input type="hidden" name="mday" value="<?php if (isset($day)) echo htmlspecialchars($day) ?>" />
<input type="hidden" name="url" value="<?php echo $url ?>" />
<input type="hidden" name="eventID" value="<?php echo htmlspecialchars($event->getId()) ?>" />
<input type="hidden" name="calendar" value="<?php echo htmlspecialchars($event->getCalendar()) ?>" />

<h1 class="header">
 <?php printf(_("Delete %s"), htmlspecialchars($event->getTitle())) ?>
</h1>
<br />

<div class="box">
<?php if (!empty($event->attendees)): ?>
 <p><input id="sendupdates" type="checkbox" class="checkbox" name="sendupdates" checked="checked" /><label for="sendupdates"> <?php echo _("Send a cancel notification to all attendees when deleting this event") ?></label></p>
<?php endif ?>

 <p><?php echo _("Delete the current event only, this occurrence and all future occurences, or all occurences?") ?></p>

 <input type="submit" class="button" name="current" value="<?php echo _("Current") ?>" />
 <input type="submit" class="button" name="future" value="<?php echo _("Future") ?>" />
 <input type="submit" class="button" name="all" value="<?php echo _("All") ?>" />
 <a class="button" href="<?php echo $url ?>"><?php echo _("Cancel") ?></a>
</div>

</form>
