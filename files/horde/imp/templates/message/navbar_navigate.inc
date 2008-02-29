<tr class="control">
  <td nowrap="nowrap" class="leftAlign">
<?php if ($imp['base_protocol'] != 'pop3'): ?>
    <select name="flag<?php echo $nav_id ?>" onchange="flagMessage(<?php echo $nav_id ?>);">
      <option value="" selected="selected"><?php echo _("Mark as:") ?></option>
      <option value="0Seen"><?php echo _("Unseen") ?></option>
      <option value="Flagged"><?php echo _("Important") ?></option>
      <option value="0Flagged"><?php echo _("Not Important") ?></option>
      <option value="Answered"><?php echo _("Answered") ?></option>
      <option value="0Answered"><?php echo _("Unanswered") ?></option>
      <option value="Draft"><?php echo _("Draft") ?></option>
      <option value="0Draft"><?php echo _("Not Draft") ?></option>
    </select>
  </td>
<?php endif; ?>
  <td nowrap="nowrap" class="leftAlign">
<?php if ($conf['user']['allow_folders']): ?>
    <?php echo Horde::widget('#', _("Move to folder"), 'widget', '', "Transfer('move_message'," . $nav_id . "); return false;", _("Move"), true) ?> |
    <?php echo Horde::widget('#', _("Copy to folder"), 'widget', '', "Transfer('copy_message'," . $nav_id . "); return false;", _("Copy"), true) ?>
    <select name="target<?php echo $nav_id ?>" onchange="updateFolders(<?php echo $nav_id ?>);">
      <?php echo $options ?>
    </select>
<?php endif; ?>
  </td>
  <td width="65%" class="rightAlign" nowrap="nowrap">
    <?php echo Horde::widget($mailbox_url, sprintf(_("Back to %s"), htmlspecialchars($page_label)), 'widget', '', '', sprintf(_("Bac_k to %s"), htmlspecialchars($page_label)), true) ?>&nbsp;&nbsp;
<?php if (!empty($prev_url)): ?>
    <?php echo Horde::link($prev_url, _("Previous Message"), '', '', '', '', '<', empty($navbar_included) ? array('id' => 'prev') : array()) . Horde::img('nav/left.png', '<', array('width' => '16', 'height' => '16', 'align' => 'middle'), $registry->getImageDir('horde')) ?></a>
<?php else: ?>
    <?php echo Horde::img('nav/left-grey.png', '', 'width="16" height="16" align="middle"', $registry->getImageDir('horde')) ?>
<?php endif; ?>
<?php if (!empty($next_url)): ?>
    <?php echo Horde::link($next_url, _("Next Message"), '', '', '', '', '>', empty($navbar_included) ? array('id' => 'next') : array()) . Horde::img('nav/right.png', '>', array('width' => '16', 'height' => '16', 'align' => 'middle'), $registry->getImageDir('horde')) ?></a>
<?php else: ?>
    <?php echo Horde::img('nav/right-grey.png', '', 'width="16" height="16" align="middle"', $registry->getImageDir('horde')) ?>
<?php endif; $navbar_included = true; ?>
  </td>
</tr>
