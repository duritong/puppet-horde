<div class="header">
 <span style="width:30%" class="leftFloat">
  <span dir="ltr"><?php echo htmlspecialchars($title) ?>&nbsp;</span>
  <?php echo Horde::link($refresh_url, $refresh_title, '', '', '', '', $refresh_ak) . Horde::img('reload.png', _("Reload"), '', $registry->getImageDir('horde')) ?></a>

<?php if (isset($filter_url)): ?>
  <?php echo Horde::link($filter_url, sprintf(_("Apply Filters to %s"), $title)) . Horde::img('filters.png', _("Apply Filters")) ?></a>
<?php endif; ?>

<?php if (!$search_mbox): ?>
  <?php echo Horde::link(Util::addParameter(Horde::applicationUrl('search.php'), 'search_mailbox', $imp['mailbox']), sprintf(_("Search %s"), $title)) . Horde::img('search.png', _("Search"), '', $registry->getImageDir('horde')) ?></a>
<?php elseif ($imp_search->isEditableVFolder()): ?>
  <?php echo Horde::link($imp_search->editURL(), sprintf(_("Edit Virtual Folder Definition for %s"), htmlspecialchars($title))) . Horde::img('edit.png', sprintf(_("Edit Virtual Folder Definition for %s"), $title), '', $registry->getImageDir('horde')) ?></a>
  <?php echo Horde::link($imp_search->deleteURL(), sprintf(_("Delete Virtual Folder Definition for %s"), htmlspecialchars($title)), null, null, "if (confirm('" . addslashes(_("Are you sure you want to delete this Virtual Folder Definition?")) . "')) { return true; } else { return false; }") . Horde::img('delete.png', sprintf(_("Delete Virtual Folder Definition for %s"), $title), '', $registry->getImageDir('horde')) ?></a>
<?php elseif (!$imp_search->isVFolder()): ?>
  <?php echo Horde::link($imp_search->editURL(), _("Edit Search Query")) . Horde::img('edit.png', _("Edit Search Query"), '', $registry->getImageDir('horde')) ?></a>
<?php endif; ?>
 </span>

<?php if ($pageOb->pagecount > 1): ?>
 <span style="width:35%; text-align:center" class="leftFloat nowrap">
  <?php printf(_("Page %d of %d"), $pageOb->page, $pageOb->pagecount) ?>
 </span>
 <span style="width:35%" class="rightFloat rightAlign nowrap">
  <?php echo $msg_count ?>
 </span>
<?php else: ?>
 <span style="width:70%" class="rightFloat rightAlign nowrap">
  <?php echo $msg_count ?>
 </span>
<?php endif; ?>

<?php if ( $GLOBALS['browser']->isBrowser('msie') && $GLOBALS['browser']->getMajor() < 7): ?>
 <br class="clear" />
<?php else: ?>
 <div class="clear"></div>
<?php endif; ?>
</div>

<table class="nopadding" width="100%" cellspacing="0">
