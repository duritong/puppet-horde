 <tr>
  <td width="1%" align="left" colspan="3">
   <?php if (!$print_view) echo Horde::link('#', _("Show Calendar"), '', '', 'open_calendar_search();') . Horde::img('new_big.png', _("Show Calendar")) . '</a>' ?>
  </td>
<?php foreach ($this->days as $day): ?>
  <td class="control" width="1%">&nbsp;</td>
<?php if (!count($this->_currentCalendars)): ?>
  <td width="<?php echo floor(90 / count($this->days)) ?>%" class="<?php echo ($day->isToday() ? 'selected-control' : 'control') ?>">&nbsp;</td>
<?php endif; ?>
<?php foreach ($this->_currentCalendars as $cid => $cal): ?>
  <td class="<?php echo ($day->isToday() ? 'selected-control' : 'control') ?>" width="<?php echo floor((90/count($this->days)) / count($this->_currentCalendars)) ?>%" colspan="<?php echo $day->_span[$cid] ?>">
   <strong><?php echo htmlspecialchars($cal->get('name')); if (!$print_view) echo ' ' . Horde::link(Util::addParameter(Horde::selfUrl(), 'display_cal', $cal->getShortName()), sprintf(_("Hide %s"), $cal->get('name'))) . Horde::img('delete-small.png', _("Hide"), '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' ?></strong>
  </td>
<?php endforeach; endforeach; ?>
 </tr>
