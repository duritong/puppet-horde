<form name="edit" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="editform" />
<input type="hidden" name="cid" value="<?php echo htmlspecialchars($share->getId()) ?>" />
<?php if (!empty($app)): ?>
<input type="hidden" name="app" value="<?php echo htmlspecialchars($app) ?>" />
<?php endif; ?>
<table width="100%" cellspacing="0">
<tr valign="middle">
  <td width="100%" colspan="6" class="header">
    <span class="rightFloat">
      <a href="#" onclick="javascript:window.close();" style="cursor:pointer"><?php echo Horde::img('close.png', _("Close Window"), '', $registry->getImageDir('horde')) ?></a>
    </span>
    <?php echo Horde::img('perms.png', '', '', $registry->getImageDir('horde')) ?>&nbsp;<?php printf(_("Edit permissions for %s"), htmlspecialchars($share->get('name'))) ?>
  </td>
</tr>
<tr><td>&nbsp;</td></tr>

<?php $owner = $share->get('owner'); ?>
<?php $userperms = $perm->getUserPermissions(); ?>

<!-- Ownership -->
<?php
if (isset($userperms[$owner])) {
    $operm = $userperms[$owner];
} else {
    $operm = PERMS_SHOW | PERMS_READ | PERMS_EDIT | PERMS_DELETE;
}
?>
<tr valign="middle">
  <td class="header leftAlign" width="55%">
    <?php echo Horde::img('perms.png', '', '', $registry->getImageDir('horde')) . '&nbsp;' . _("Owner Permissions") ?>
  </td>
  <td class="header" align="center" width="1%"><?php echo _("Show") ?></td>
  <td class="header" align="center" width="1%"><?php echo _("Read") ?></td>
  <td class="header" align="center" width="1%"><?php echo _("Edit") ?></td>
  <td class="header" align="center" width="1%"><?php echo _("Delete") ?></td>
  <td class="header rightAlign" width="40%">&nbsp;</td>
</tr>
<tr>
<?php if ($auth->hasCapability('list')): ?>
  <td class="light">
    <select name="owner">
      <option value=""><?php echo _("Select a new owner") . ':' ?></option>
      <option value="<?php echo htmlspecialchars($owner) ?>" selected="selected"><?php echo htmlspecialchars(Auth::removeHook($owner)) ?></option>
    <?php foreach ($userList as $user) { if ($user != $owner) { ?>
      <option value="<?php echo htmlspecialchars($user) ?>"><?php echo htmlspecialchars(Auth::removeHook($user)) ?></option>
    <?php } } ?>
    </select>
  </td>
<?php else: ?>
  <td class="light"><input type="text" name="owner" value="<?php echo htmlspecialchars(Auth::removeHook($owner)) ?>" /></td>
<?php endif; ?>
  <td align="center"><input type="checkbox" name="owner_show"<?php echo ($operm & PERMS_SHOW) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="owner_read"<?php echo ($operm & PERMS_READ) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="owner_edit"<?php echo ($operm & PERMS_EDIT) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="owner_delete"<?php echo ($operm & PERMS_DELETE) ? ' checked="checked"' : '' ?> /></td>
</tr>

<!-- Spacer -->
<tr><td>&nbsp;</td></tr>

<!-- Default Permissions -->
<tr valign="middle">
  <td class="header leftAlign">
    <?php echo Horde::img('perms.png', '', '', $registry->getImageDir('horde')) . '&nbsp;' . _("All Authenticated Users") ?>
  </td>
  <td class="header" align="center"><?php echo _("Show") ?></td>
  <td class="header" align="center"><?php echo _("Read") ?></td>
  <td class="header" align="center"><?php echo _("Edit") ?></td>
  <td class="header" align="center"><?php echo _("Delete") ?></td>
  <td class="header rightAlign">&nbsp;</td>
</tr>
<?php $dperm = $perm->getDefaultPermissions(); ?>
<tr>
  <td class="light">&nbsp;</td>
  <td align="center"><input type="checkbox" name="default_show"<?php echo ($dperm & PERMS_SHOW) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="default_read"<?php echo ($dperm & PERMS_READ) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="default_edit"<?php echo ($dperm & PERMS_EDIT) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="default_delete"<?php echo ($dperm & PERMS_DELETE) ? ' checked="checked"' : '' ?> /></td>
</tr>

<!-- Spacer -->
<tr><td>&nbsp;</td></tr>

<!-- Guest Permissions -->
<tr valign="middle">
  <td class="header leftAlign">
    <?php echo _("Guest Permissions") ?>
  </td>
  <td class="header" align="center"><?php echo _("Show") ?></td>
  <td class="header" align="center"><?php echo _("Read") ?></td>
  <td class="header" align="center"><?php echo _("Edit") ?></td>
  <td class="header" align="center"><?php echo _("Delete") ?></td>
  <td class="header rightAlign">&nbsp;</td>
</tr>
<?php $gperm = $perm->getGuestPermissions(); ?>
<tr>
  <td class="light">&nbsp;</td>
  <td align="center"><input type="checkbox" name="guest_show"<?php echo ($gperm & PERMS_SHOW) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="guest_read"<?php echo ($gperm & PERMS_READ) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="guest_edit"<?php echo ($gperm & PERMS_EDIT) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="guest_delete"<?php echo ($gperm & PERMS_DELETE) ? ' checked="checked"' : '' ?> /></td>
</tr>

<!-- Spacer -->
<tr><td>&nbsp;</td></tr>

<!-- Creator Permissions -->
<tr valign="middle">
  <td class="header leftAlign">
    <?php echo Horde::img('user.png', '', '', $registry->getImageDir('horde')) . '&nbsp;' . _("Object Creator") ?>
  </td>
  <td class="header" align="center"><?php echo _("Show") ?></td>
  <td class="header" align="center"><?php echo _("Read") ?></td>
  <td class="header" align="center"><?php echo _("Edit") ?></td>
  <td class="header" align="center"><?php echo _("Delete") ?></td>
  <td class="header rightAlign"></td>
</tr>
<?php $cperm = $perm->getCreatorPermissions(); ?>
<tr>
  <td class="light">&nbsp;</td>
  <td align="center"><input type="checkbox" name="creator_show"<?php echo ($cperm & PERMS_SHOW) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="creator_read"<?php echo ($cperm & PERMS_READ) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="creator_edit"<?php echo ($cperm & PERMS_EDIT) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="creator_delete"<?php echo ($cperm & PERMS_DELETE) ? ' checked="checked"' : '' ?> /></td>
</tr>

<!-- Spacer -->
<tr><td>&nbsp;</td></tr>

<!-- User Permissions -->
<tr valign="middle">
  <td class="header leftAlign">
    <?php echo Horde::img('user.png', '', '', $registry->getImageDir('horde')) . '&nbsp;' . _("Individual Users") ?>
  </td>
  <td class="header" align="center"><?php echo _("Show") ?></td>
  <td class="header" align="center"><?php echo _("Read") ?></td>
  <td class="header" align="center"><?php echo _("Edit") ?></td>
  <td class="header" align="center"><?php echo _("Delete") ?></td>
  <td class="header rightAlign"></td>
</tr>
<?php foreach ($userperms as $user => $uperm) { if ($user != $owner) { ?>
<tr>
  <td class="light"><?php echo htmlspecialchars(Auth::removeHook($user)) ?><input type="hidden" name="u_names[<?php echo htmlspecialchars($user) ?>]" value="<?php echo htmlspecialchars($user) ?>" /></td>
  <td align="center"><input type="checkbox" name="u_show[<?php echo htmlspecialchars($user) ?>]"<?php echo ($uperm & PERMS_SHOW) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="u_read[<?php echo htmlspecialchars($user) ?>]"<?php echo ($uperm & PERMS_READ) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="u_edit[<?php echo htmlspecialchars($user) ?>]"<?php echo ($uperm & PERMS_EDIT) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="u_delete[<?php echo htmlspecialchars($user) ?>]"<?php echo ($uperm & PERMS_DELETE) ? ' checked="checked"' : '' ?> /></td>
</tr>
<?php } } ?>
<!-- New user row -->
<tr>
<?php if ($auth->hasCapability('list')): ?>
  <td class="light">
    <select name="u_names[||new]">
      <option value=""><?php echo _("Select a user to add") . ':' ?></option>
    <?php foreach ($userList as $user) { if (!isset($userperms[$user])) { ?>
      <option value="<?php echo htmlspecialchars($user) ?>"><?php echo htmlspecialchars(Auth::removeHook($user)) ?></option>
    <?php } } ?>
    </select>
  </td>
<?php else: ?>
  <td class="light"><input type="text" name="u_names[||new]" /></td>
<?php endif; ?>
  <td align="center"><input type="checkbox" name="u_show[||new]" /></td>
  <td align="center"><input type="checkbox" name="u_read[||new]" /></td>
  <td align="center"><input type="checkbox" name="u_edit[||new]" /></td>
  <td align="center"><input type="checkbox" name="u_delete[||new]" /></td>
</tr>

<!-- Spacer -->
<tr><td>&nbsp;</td></tr>

<!-- Group Permissions -->
<tr valign="middle">
  <td class="header leftAlign">
    <?php echo Horde::img('group.png', '', '', $registry->getImageDir('horde')) . '&nbsp;' . _("Groups") ?>
  </td>
  <td class="header" align="center"><?php echo _("Show") ?></td>
  <td class="header" align="center"><?php echo _("Read") ?></td>
  <td class="header" align="center"><?php echo _("Edit") ?></td>
  <td class="header" align="center"><?php echo _("Delete") ?></td>
  <td class="header rightAlign">&nbsp;</td>
</tr>
<?php $groupperms = $perm->getGroupPermissions(); foreach ($groupperms as $group => $gperm): if (!$groups->exists($groups->getGroupName($group))) continue; ?>
<tr>
  <td class="light"><?php echo htmlspecialchars($groups->getGroupName($group)) ?><input type="hidden" name="g_names[<?php echo htmlspecialchars($group) ?>]" value="<?php echo htmlspecialchars($group) ?>" /></td>
  <td align="center"><input type="checkbox" name="g_show[<?php echo htmlspecialchars($group) ?>]"<?php echo ($gperm & PERMS_SHOW) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="g_read[<?php echo htmlspecialchars($group) ?>]"<?php echo ($gperm & PERMS_READ) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="g_edit[<?php echo htmlspecialchars($group) ?>]"<?php echo ($gperm & PERMS_EDIT) ? ' checked="checked"' : '' ?> /></td>
  <td align="center"><input type="checkbox" name="g_delete[<?php echo htmlspecialchars($group) ?>]"<?php echo ($gperm & PERMS_DELETE) ? ' checked="checked"' : '' ?> /></td>
</tr>
<?php endforeach; ?>
<!-- New group row -->
<tr>
  <td class="light">
    <select name="g_names[||new]">
      <option value=""><?php echo _("Select a group to add") . ':' ?></option>
    <?php foreach ($groupList as $gid => $group) { if (empty($groupperms[$gid])) { ?>
      <option value="<?php echo htmlspecialchars($gid) ?>"><?php echo htmlspecialchars($group) ?></option>
    <?php } } ?>
    </select>
  </td>
  <td align="center"><input type="checkbox" name="g_show[||new]" /></td>
  <td align="center"><input type="checkbox" name="g_read[||new]" /></td>
  <td align="center"><input type="checkbox" name="g_edit[||new]" /></td>
  <td align="center"><input type="checkbox" name="g_delete[||new]" /></td>
</tr>

<tr>
 <td>&nbsp;</td>
</tr>

<tr>
 <td colspan="7">
  <input type="submit" class="button" value="<?php printf(_("Save %s"), htmlspecialchars($share->get('name'))) ?>" />
  <input type="submit" class="button" name="save_and_finish" value="<?php echo htmlspecialchars(_("Save and Finish")) ?>" />
<?php if (@count($children)): ?>
  <input type="submit" class="button" onclick="document.edit.actionID.value = 'editforminherit';" value="<?php echo _("Apply to Child Shares") ?>" />
<?php endif; ?>
 </td>
</tr>

</table>
</form>
<br />
