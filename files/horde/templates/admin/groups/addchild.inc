<form name="add_child" method="post" action="groups.php">
<input type="hidden" name="actionID" value="addchildform" />
<input type="hidden" name="cid" value="<?php echo $cid ?>" />
<?php Util::pformInput() ?>

<h1 class="header">
 <?php echo Horde::img('group.png') ?>&nbsp;<?php printf(_("Add a child group to \"%s\""), $gname) ?>
</h1>

<input type="text" name="child" />
<input type="submit" class="button" value="<?php echo _("Add") ?>" />
</form>
<br />
