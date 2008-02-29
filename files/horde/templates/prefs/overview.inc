<div class="prefsOverview">
<?php foreach ($columns as $key => $column): ?>
 <div style="width:<?php echo $span - 1 ?>%">
  <div>
   <h2><?php echo htmlspecialchars($key) ?></h2>

   <dl>
   <?php foreach ($column as $group => $gvals): ?>
    <?php if (Prefs_UI::groupIsEditable($group)): ?>
     <?php if (!empty($gvals['url'])): ?>
      <dt><?php echo Horde::widget(Horde::applicationUrl($gvals['url'], $app), $gvals['label'], '', '', '', $gvals['label']) ?></dt>
     <?php else: ?>
      <dt><?php echo Horde::widget(Util::addParameter(Horde::selfUrl(), array('group' => $group, 'app' => $GLOBALS['app'])), $gvals['label'], '', '', '', $gvals['label']) ?></dt>
     <?php endif; ?>
     <dd><?php echo htmlspecialchars($gvals['desc']) ?></dd>
    <?php endif; ?>
   <?php endforeach; ?>
   </dl>
  </div>
 </div>
<?php endforeach; ?>
</div>
