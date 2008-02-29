<form method="post" name="contacts" action="browse.php">
<?php echo Util::formInput() ?>
<table cellspacing="0" width="100%" class="linedRow">
<thead>
 <tr>
<?php if ($this->showMark): ?>
  <th class="item leftAlign" width="1%" style="cursor:pointer" onclick="document.contacts.checkAll.checked = !document.contacts.checkAll.checked; SelectAll();" nowrap="nowrap"><input type="checkbox" name="checkAll" onclick="document.contacts.checkAll.checked = !document.contacts.checkAll.checked; SelectAll();" <?php echo Horde::getAccessKeyAndTitle(_("Check _All/None")) ?> /></th>
<?php endif; ?>
<?php if ($this->showEdit): ?>
  <th class="item" width="1%"><?php echo Horde::img('edit.png', _("Edit"), '', $GLOBALS['registry']->getImageDir('horde')) ?></th>
<?php endif; ?>
<?php if ($this->showVcard): ?>
  <th class="item" width="1%"><?php echo Horde::img('mime/vcard.png', _("vCard"), '', $GLOBALS['registry']->getImageDir('horde')) ?></th>
<?php endif; ?>
<?php if ($this->showGroup): ?>
  <th class="item" width="1%"><?php echo Horde::img('group.png', _("List")) ?></th>
<?php endif; ?>
  <th class="<?php echo ($this->showSort && $sortby == 0) ? 'selected' : 'item' ?> leftAlign" width="<?php echo $width ?>%" nowrap="nowrap">
<?php if ($this->showSort): ?>
   <?php if ($GLOBALS['prefs']->getValue('sortby') == 0): ?>
      <a class="widget" href="#" onclick="Sort(0, <?php echo abs(1 - $sortdir) ?>); return false; "><?php echo Horde::img($sortdir ? 'za.png' : 'az.png', _("Sort Direction"), null, $GLOBALS['registry']->getImageDir('horde')) ?></a>
      <a class="widget" href="#" onclick="Sort(0, <?php echo abs(1 - $sortdir) ?>); return false;">
   <?php else: ?>
      <a class="widget" href="#" onclick="Sort(0, <?php echo $sortdir ?>); return false;">
   <?php endif; ?>
<?php endif; ?>
   <?php echo $GLOBALS['attributes']['name']['label'] ?>
<?php if ($this->showSort): ?>
   </a>
<?php endif; ?>
  </th>
  <?php for ($i = 1; $i <= count($GLOBALS['columns']); $i++): ?>
  <th class="<?php echo ($this->showSort && $sortby == $i) ? 'selected' : 'item' ?> leftAlign" width="<?php echo $width ?>%" nowrap="nowrap">
<?php if ($this->showSort): ?>
   <?php if ($GLOBALS['prefs']->getValue('sortby') == $i): ?>
      <a class="widget" href="#" onclick="Sort(<?php echo $i ?>, <?php echo abs(1 - $sortdir) ?>); return false; "><?php echo Horde::img($sortdir ? 'za.png' : 'az.png', _("Sort Direction"), null, $GLOBALS['registry']->getImageDir('horde')) ?></a>
      <a class="widget" href="#" onclick="Sort(<?php echo $i ?>, <?php echo abs(1 - $sortdir) ?>); return false;">
   <?php else: ?>
      <a class="widget" href="#" onclick="Sort(<?php echo $i ?>, <?php echo $sortdir ?>); return false;">
   <?php endif; ?>
<?php endif; ?>
   <?php echo $GLOBALS['attributes'][$GLOBALS['columns'][$i - 1]]['label'] ?>
<?php if ($this->showSort): ?>
   </a>
<?php endif; ?>
  </th>
  <?php endfor; ?>
 </tr>
</thead>
<tbody>
