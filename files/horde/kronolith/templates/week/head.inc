<div class="header" style="text-align:center">
<?php
if (!$print_view) echo Horde::link($this->link(-1), _("Last week")) . Horde::img('nav/left.png', '<', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
echo $this->days[$this->startDay]->getTime($prefs->getValue('date_format')) . ' - ' . $this->days[$this->endDay]->getTime($prefs->getValue('date_format'));
if (!$print_view) echo Horde::link($this->link(1), _("Next week")) . Horde::img('nav/right.png', '>', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
?>
</div>

<table cellspacing="0" width="100%" class="striped">
 <tr>
  <th class="control" width="1%" colspan="3">&nbsp;</th>

<?php foreach ($this->days as $day): ?>
  <th class="control" width="1%">&nbsp;</th>
  <th class="nowrap <?php echo ($day->isToday() ? 'selected-control' : 'control') ?>" width="<?php echo round(80 / count($this->days)) ?>%" colspan="<?php echo $day->_totalspan ?>">
<?php
if (Kronolith::getDefaultCalendar(PERMS_EDIT) && !$print_view &&
    (!empty($conf['hooks']['permsdenied']) ||
     Kronolith::hasPermission('max_events') === true ||
     Kronolith::hasPermission('max_events') > Kronolith::countEvents())) {
    $addurl = Util::addParameter('addevent.php', array('month' => $day->month,
                                                       'year' => $day->year,
                                                       'mday' => $day->mday,
                                                       'url' => Horde::selfUrl(true, false, true)));
    echo Horde::link(Horde::applicationUrl($addurl), _("Create a New Event"))
    . Horde::img('new_small.png', '+')
    . '</a>';
}
?>
   <strong><?php echo Horde::link($day->link(), '', 'widget') . $day->getTime('%A, %d.') ?></a></strong>
  </th>
<?php endforeach; ?>
 </tr>
