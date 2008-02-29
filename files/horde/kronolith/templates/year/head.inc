<table class="nopadding" cellspacing="0" width="100%">
<tr>
  <th class="header">
  <?php echo (!$print_view) ? Horde::link($prevurl, _("Last Year")) . Horde::img('nav/left.png', '<', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  <?php echo $title ?>
  <?php echo (!$print_view) ? Horde::link($nexturl, _("Next Year")) . Horde::img('nav/right.png', '>', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  </th>
</tr>
</table>
<table class="nopadding" cellspacing="5" width="100%"><tr>
