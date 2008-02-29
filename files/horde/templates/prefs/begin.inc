<form method="post" name="prefs" action="<?php echo Horde::selfUrl() ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="update_prefs" />
<input type="hidden" name="group" value="<?php echo htmlspecialchars($group) ?>" />
<input type="hidden" name="app" value="<?php echo htmlspecialchars($app) ?>" />

<div class="header">
 <?php Prefs_UI::generateNavigationCell($group) ?>
 <?php echo htmlspecialchars($prefGroups[$group]['label']) ?>
 <br class="clear" />
</div>

<div class="item">
