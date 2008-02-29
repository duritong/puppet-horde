<div class="header leftAlign">
 <span class="smallheader rightAlign rightFloat">
  <strong><?php echo count($tasks) == 1 ? _("1 task") : sprintf(_("%d tasks"), count($tasks)) ?></strong>
 </span>
 <?php echo htmlspecialchars($title) . ' ' . Horde::link(Horde::selfUrl(true), _("Refresh List")) . Horde::img('reload.png', _("Refresh List"), '', $registry->getImageDir('horde')) . '</a>' ?>
</div>
