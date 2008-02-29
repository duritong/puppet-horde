 <tr style="vertical-align:top">
<?php if ($cond_num > 0): ?>
  <td>
   <strong><?php echo ($rule['combine'] == INGO_STORAGE_COMBINE_ALL) ? _("and") : _("or") ?></strong>
  </td>
<?php elseif (($cond_num == 0) && !$lastfield): ?>
  <td>&nbsp;</td>
<?php endif; ?>
  <td>
   <select onchange="document.rule.submit(); return true;" name="field[<?php echo (int)$cond_num ?>]">
    <?php echo $field_select ?>
   </select>
<?php if ($header_entry): ?>
   <input name="userheader[<?php echo (int)$cond_num ?>]" value="<?php echo htmlspecialchars($userheader) ?>" />
<?php endif; ?>
  </td>
<?php if ($lastfield): ?>
  <td colspan="2">&nbsp;</td>
<?php else: ?>
  <td>
   <select onchange="document.rule.submit(); return true;" name="match[<?php echo (int)$cond_num ?>]">
    <?php echo $match_select ?>
   </select>
  </td>
  <td>
   <?php echo $match_value ?>
  </td>
<?php endif; ?>
<?php if (!$lastfield): ?>
  <td><?php echo Horde::link('javascript:delete_condition(' . (int)$cond_num . ');', _("Delete Condition")) . Horde::img('delete.png', _("Delete Condition"), null, $registry->getImageDir('horde')) ?></a></td>
<?php elseif ($cond_num != 0): ?>
  <td>&nbsp;</td>
<?php endif; ?>
 </tr>
