 <tr>
  <th class="control" width="10%" colspan="3" align="left">
    <?php if (!$print_view) echo Horde::link('#', _("Show Calendar"), '', '', 'open_calendar_search();') . Horde::img('new_big.png', _("Show Calendar")) . '</a>' ?>
  </th>
<?php if (!count($this->_currentCalendars)): ?>
  <th class="control" width="90%">&nbsp;</th>
<?php endif; ?>
<?php $i = 0; foreach ($this->_currentCalendars as $cid => $cal): ?>
  <th class="control" width="<?php echo round(90 / count($this->_currentCalendars)) ?>%" colspan="<?php echo $this->_span[$cid] ?>">
   <strong><?php echo htmlspecialchars($cal->get('name')); if (!$print_view) echo ' ' . Horde::link(Util::addParameter(Horde::selfUrl(), 'display_cal', $cal->getShortName()), sprintf(_("Hide %s"), $cal->get('name'))) . Horde::img('delete-small.png', _("Hide"), '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' ?></strong>
  </th>
<?php endforeach; ?>
 </tr>
