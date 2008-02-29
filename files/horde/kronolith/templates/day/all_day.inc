 <tr>
<?php if ($first_row): ?>
  <td width="1%" class="nowrap" align="right"<?php echo $rowspan ?>><strong><?php echo $addeventurl ?></strong></td>
  <td width="1%"<?php echo $rowspan ?> style="width:5px; height:<?php echo round(20 / $this->_slotsPerHour) ?>px">&nbsp;</td>
  <td width="1%"<?php echo $rowspan ?>>&nbsp;</td>
<?php endif; ?>
  <?php echo $row ?>
 </tr>
