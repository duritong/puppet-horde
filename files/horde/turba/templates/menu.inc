<form name="directory_list" action="browse.php" method="get">
<?php echo Util::formInput() ?>
<div id="menu">
<?php if ($browse_source_count > 1): ?>
 <span class="rightFloat">
  <select name="source" onchange="document.directory_list.submit();"><?php echo $browse_source_options ?></select>
  <noscript><input class="button" type="submit" value="<?php echo _("Browse") ?>" /></noscript>
 </span>
<?php endif; ?>
<span class="leftFloat">
 <?php echo Turba::getMenu('string') ?>
</span>
</div>
</form>
<?php $GLOBALS['notification']->notify(array('listeners' => 'status')) ?>
