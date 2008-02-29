<table cellspacing="0" width="100%">
<tr>
  <th class="header" width="20%">
  <?php echo (!$print_view) ? Horde::link($prevurl, _("Last Month")) . Horde::img('nav/left.png', '<', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  <?php echo $title ?>
  <?php echo (!$print_view) ? Horde::link($nexturl, _("Next Month")) . Horde::img('nav/right.png', '>', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  </th>
  <td class="header" width="20%"></td>
  <th class="header" width="20%">
  <?php echo (!$print_view) ? Horde::link($prevurl, _("Last Month")) . Horde::img('nav/left.png', '<', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  <?php echo $title ?>
  <?php echo (!$print_view) ? Horde::link($nexturl, _("Next Month")) . Horde::img('nav/right.png', '>', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  </th>
  <td class="header" width="20%"></td>
  <th class="header" width="20%">
  <?php echo (!$print_view) ? Horde::link($prevurl, _("Last Month")) . Horde::img('nav/left.png', '<', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  <?php echo $title ?>
  <?php echo (!$print_view) ? Horde::link($nexturl, _("Next Month")) . Horde::img('nav/right.png', '>', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  </th>
</tr>

<tr><td colspan="5"><table class="monthgrid" cellspacing="1" width="100%">

<tr>
<?php
$dayofweek = 0;
$offset = $prefs->getValue('week_start_monday') ? 1 : 0;
for ($day = (1 - $startday); $day < (1 - $startday) + $daysInView; $day++):
?>
  <th class="item"><?php echo strftime('%A', mktime(0, 0, 0, $month, $day + $offset, $year)) ?></th>
<?php $dayofweek++; if (($dayofweek % 7 == 0) && ($dayofweek != 0)): ?>
  <th>&nbsp;</th>
<?php endif; endfor; ?>
</tr>
