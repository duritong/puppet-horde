<form name="edit_group" method="post" action="groups.php">
<input type="hidden" name="actionID" value="editform" />
<input type="hidden" name="cid" value="<?php echo $group->getId() ?>" />
<?php Util::pformInput() ?>

<h1 class="header">
 <?php echo Horde::img('user.png') . ' ' . htmlspecialchars($group->getShortName()) ?>
</h1>

<label for="email"><?php echo _("Email Address") ?></label>
<input type="text" name="email" id="email" size="50" value="<?php echo htmlspecialchars($group->get('email')) ?>" />
<br />
<br />

<h1 class="header">
 <?php echo Horde::img('group.png') . ' ' . _("Members") ?>
</h1>

<table class="linedRow nowrap" cellspacing="0" width="100%">
 <tr class="control">
  <th width="10%" class="leftAlign"><?php echo _("User") ?></th>
  <th width="90%" class="leftAlign"><?php echo _("Remove") ?></th>
 </tr>
<?php foreach ($users as $user): ?>
 <tr>
  <td><?php echo htmlspecialchars($user) ?></td>
  <td><input type="checkbox" name="remove[<?php echo htmlspecialchars($user) ?>]" /></td>
 </tr>
<?php endforeach; ?>
</table>
<br />

<?php if (count($inherited_users)): ?>
<h1 class="header">
 <?php echo Horde::img('group.png') . ' ' . _("Inherited Members") ?>
</h1>
<ul>
<?php foreach ($inherited_users as $user): ?>
 <li><?php echo htmlspecialchars($user) ?></li>
<?php endforeach; ?>
</ul>
<br />
<?php endif; ?>

<h1 class="header">
 <?php echo Horde::img('user.png') . ' ' . _("Add Members") ?>
</h1>
<?php if ($auth->hasCapability('list')): ?>
<select name="new_user[]" multiple="multiple">
<?php foreach ($user_list as $user) { if (!in_array($user, $all_users)) { ?>
 <option value="<?php echo $user ?>"><?php echo $user ?></option>
<?php } } ?>
</select>
<?php else: ?>
<input type="text" name="new_user" />
<?php endif; ?>
<input type="submit" class="button" value="<?php printf(htmlspecialchars(_("Save \"%s\""), ENT_COMPAT, NLS::getCharset()), $group->getShortName()) ?>" />
<br />
<br />
