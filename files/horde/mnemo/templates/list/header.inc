<div class="header">
 <span class="rightFloat">
  <strong><?php if (count($memos) == 1) { echo _("1 note"); } elseif (count($memos) == 0) { echo _("No notes"); } else { printf(_("%d notes"), count($memos)); } ?></strong>
 </span>
 <?php echo Horde::link(Horde::applicationUrl('list.php'), _("Refresh List")) .
            Horde::img('reload.png', _("Refresh List"), '', $registry->getImageDir('horde')) ?></a>
 <strong><?php echo htmlspecialchars($title) ?></strong>
</div>
