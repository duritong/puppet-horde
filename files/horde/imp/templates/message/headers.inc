<?php $i = 0; ?>
<table width="100%" cellspacing="0">
<?php if ($all_headers): ?>
  <?php foreach ($full_h as $head => $val): ?><tr>
  <?php if (in_array($head, $basic_headers)) $val = $imp_headers->getValue($head); ?>
  <td class="item<?php echo $i ?> rightAlign nowrap" valign="top"><strong><?php echo $head ?>:</strong>&nbsp;</td>
  <td class="item<?php echo $i ?> msgheader" width="100%"><?php echo $val ?></td>
</tr><?php $i = ($i + 1) % 2; ?>
  <?php endforeach; ?>
<?php else: ?>
  <?php foreach ($basic_headers as $head => $str): ?>
    <?php if ($val = $imp_headers->getValue($head)): ?>
<tr>
  <td class="item<?php echo $i ?> rightAlign nowrap" valign="top"><strong><?php echo $str ?>:</strong>&nbsp;</td>
  <td class="item<?php echo $i ?> msgheader" width="100%"><?php echo $val ?></td>
</tr>
    <?php $i = ($i + 1) % 2; endif; ?>
  <?php endforeach; ?>
<?php endif; ?>
<?php if (!empty($user_hdrs) && count($custom_hdrs) > 0): ?>
  <?php foreach ($custom_hdrs as $head => $val): ?><tr>
  <td class="item<?php echo $i ?> rightAlign nowrap" valign="top"><strong><?php echo $head ?>:</strong>&nbsp;</td>
  <td class="item<?php echo $i ?> msgheader" width="100%"><?php echo $val ?></td>
</tr><?php $i = ($i + 1) % 2; ?>
  <?php endforeach; ?>
<?php endif; ?>
<?php if ($list_headers && $list_exists): ?>
  <?php foreach ($imp_headers->listHeaders() as $head => $str): ?>
    <?php if ($val = $imp_headers->getValue($head)): ?>
<tr>
  <td class="item<?php echo $i ?> rightAlign nowrap" valign="top"><strong><?php echo $str ?>:</strong>&nbsp;</td>
  <td class="item<?php echo $i ?> msgheader" width="100%"><?php echo $val ?></td>
</tr>
    <?php $i = ($i + 1) % 2; endif; ?>
  <?php endforeach; ?>
<?php endif; ?>
<?php if ($val = $imp_headers->getValue('priority')): ?><tr>
  <td class="item<?php echo $i ?> rightAlign" valign="top"><strong><?php echo _("Priority") ?>:</strong>&nbsp;</td>
  <td class="item<?php echo $i ?> msgheader" width="100%"><?php echo $val ?></td>
</tr><?php $i = ($i + 1) % 2; endif; ?>
<?php if ($show_parts || ($downloadall_link && !$printer_friendly)): ?>
<tr>
  <td class="item<?php echo $i ?> rightAlign" valign="top"><strong><?php echo _("Part(s)") ?>:</strong>&nbsp;</td>
<?php if ($show_parts): ?>
  <td class="item<?php echo $i ?> msgheader" width="100%"><table cellspacing="2"><?php echo $attachments ?></table></td>
<?php endif; ?>
<?php if ($downloadall_link && !$printer_friendly): ?>
<?php if ($show_parts): ?>
<tr>
  <td class="item<?php echo $i ?> rightAlign" valign="top"></td>
<?php endif; ?>
  <td class="item<?php echo $i ?> msgheader" width="100%"><?php echo Horde::link($downloadall_link, _("Download All Attachments (in .zip file)")) . _("Download All Attachments (in .zip file)") . ' ' . Horde::img('compressed.png', _("Download All Attachments (in .zip file)"), '', $registry->getImageDir('horde') . '/mime') ?></a></td>
<?php endif; ?>
</tr>
<?php $i = ($i + 1) % 2; endif; ?>
<?php if (!$printer_friendly): ?>
<tr>
  <td class="item<?php echo $i ?> rightAlign" valign="top"><strong><?php echo _("Headers") ?>:</strong>&nbsp;</td>
  <td class="item<?php echo $i ?> msgheader" width="100%">
    <?php if ($all_headers || $list_headers): ?>
      <?php echo Horde::link($headersURL) . _("Show Limited Headers") ?></a>
    <?php endif; ?>
    <?php if (!$all_headers): ?>
      <?php if ($list_headers): ?>
      &nbsp;|&nbsp;
      <?php endif; ?>
      <?php echo Horde::link(Util::addParameter($headersURL, 'show_all_headers', 1)) . _("Show All Headers") ?></a>
    <?php endif; ?>
    <?php if ($list_exists && !$list_headers): ?>
      &nbsp;|&nbsp;&nbsp;<?php echo Horde::link(Util::addParameter($headersURL, 'show_list_headers', 1)) . _("Show Mailing List Information") ?></a>
    <?php endif; ?>
  </td>
</tr>
<?php endif; ?>
</table>
