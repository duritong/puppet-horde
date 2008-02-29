<table class="nopadding" cellspacing="0" width="100%">
<tr>
  <th class="header">
  <?php echo (!$print_view) ? Horde::link($prevurl, _("Last Month")) . Horde::img('nav/left.png', '<', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  <?php echo $title ?>
  <?php echo (!$print_view) ? Horde::link($nexturl, _("Next Month")) . Horde::img('nav/right.png', '>', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>' : '' ?>
  </th>
</tr>

<tr><td><table class="monthgrid nopadding" cellspacing="1" width="100%">

<tr>
<?php if (!$prefs->getValue('week_start_monday')): ?>
<th class="item"><?php echo _("Sunday") ?></th>
<?php endif; ?>
<th class="item"><?php echo _("Monday") ?></th>
<th class="item"><?php echo _("Tuesday") ?></th>
<th class="item"><?php echo _("Wednesday") ?></th>
<th class="item"><?php echo _("Thursday") ?></th>
<th class="item"><?php echo _("Friday") ?></th>
<th class="item"><?php echo _("Saturday") ?></th>
<?php if ($prefs->getValue('week_start_monday')): ?>
<th class="item"><?php echo _("Sunday") ?></th>
<?php endif; ?>
</tr>
