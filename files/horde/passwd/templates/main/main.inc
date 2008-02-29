<script type="text/javascript">
<!--

function submit_form()
{
    if (document.passwd.oldpassword.value == "") {
        alert('<?php echo addslashes(_("Please provide your current password")); ?>');
        document.passwd.oldpassword.focus();
        return false;
    } else if (document.passwd.newpassword0.value == "") {
        alert('<?php echo addslashes(_("Please provide a new password")); ?>');
        document.passwd.newpassword0.focus();
        return false;
    } else if (document.passwd.newpassword1.value == "") {
        alert('<?php echo addslashes(_("Please verify your new password")); ?>');
        document.passwd.newpassword1.focus();
        return false;
    } else if (document.passwd.newpassword0.value != document.passwd.newpassword1.value) {
        alert('<?php echo addslashes(_("Your passwords do not match")); ?>');
        document.passwd.newpassword0.value = '';
        document.passwd.newpassword1.value = '';
        document.passwd.newpassword0.focus();
        return false;
    } else {
        return true;
    }
}

function setFocus()
{
    if (document.passwd.oldpassword.value == "") {
        document.passwd.oldpassword.focus();
    } else if (document.passwd.newpassword0.value == "") {
        document.passwd.newpassword0.focus();
    } else {
        document.passwd.newpassword1.focus();
    }
}
//-->
</script>

<div id="menu">
<?php
require_once 'Horde/Menu.php';
$menu = &new Menu(HORDE_MENU_MASK_ALL & ~HORDE_MENU_MASK_PREFS);
echo $menu->render();
$item = 1;
?>
</div>
<?php $notification->notify(array('listeners' => 'status')) ?>

<form method="post" action="<?php echo Horde::url('main.php', false, -1, true) ?>" name="passwd">
<?php echo Util::formInput() ?>
<?php if ($conf['backend']['backend_list'] == 'hidden'): ?>
<input type="hidden" name="backend" value="<?php echo $backend_key ?>" />
<?php endif; if ($conf['user']['change'] !== true): ?>
<input type="hidden" name="userid" value="<?php echo htmlspecialchars($userid) ?>" />
<?php endif; ?>
<input type="hidden" name="return_to" value="<?php echo htmlspecialchars(Util::getFormData('return_to')) ?>" />

<h1 class="header">
 <?php echo _("Change your password") ?>
</h1>

<table cellspacing="0" width="100%">
<?php if ($conf['backend']['backend_list'] == 'hidden'): ?>
<tr class="header">
 <td colspan="3" class="control leftAlign">
  <?php printf(_("Changing password on %s"), htmlspecialchars($backends[$backend_key]['name'])) ?>
 </td>
</tr>
<tr class="control">
 <td colspan="3" class="control leftAlign">
  <?php echo _("For a nearly unguessable and easy to remember password use a nice phrase, e.g. 'Imagination is more important than knowledge, A. Einstein', then the password will be for example: 'IimitK1stein'.<br/><i>The new password here must contain: capital+small letters + at least 1 number</i>") ?>
 </td>
</tr>
<?php endif; ?>

<?php if ($conf['user']['change'] === true): ?>
<tr class="item<?php echo $item++ % 2 ?>">
 <td class="rightAlign">
  <strong><?php echo _("Username:") ?></strong>
 </td>
 <td class="leftAlign">
  <input type="text" name="userid" value="<?php echo htmlspecialchars($userid) ?>" />
 </td>
 <td class="rightAlign">
  <?php echo Help::link('passwd', 'passwd-username') ?>
 </td>
</tr>
<?php endif; ?>

<tr class="item<?php echo $item++ % 2 ?>">
 <td width="15%" class="rightAlign">
  <strong><?php echo _("Old password:") ?></strong>
 </td>
 <td class="leftAlign">
  <input type="password" tabindex="1" name="oldpassword" size="32" />
 </td>
 <td class="rightAlign">
  <?php echo Help::link('passwd', 'passwd-old-password') ?>
 </td>
</tr>

<tr class="item<?php echo $item++ % 2 ?>">
 <td class="rightAlign">
  <strong><?php echo _("New password:") ?></strong>
 </td>
 <td class="leftAlign">
  <input type="password" tabindex="2" name="newpassword0" size="32" />
 </td>
 <td class="rightAlign">
  <?php echo Help::link('passwd', 'passwd-new-password') ?>
 </td>
</tr>

<tr class="item<?php echo $item++ % 2 ?>">
 <td class="rightAlign">
  <strong><?php echo _("Confirm new password:") ?></strong>
 </td>
 <td class="leftAlign">
  <input type="password" tabindex="3" name="newpassword1" size="32" />
 </td>
 <td class="rightAlign">
  <?php echo Help::link('passwd', 'passwd-confirm-password') ?>
 </td>
</tr>

<?php if ($conf['backend']['backend_list'] == 'shown'): ?>
<tr class="item<?php echo $item++ % 2 ?>">
 <td class="rightAlign">
  <strong><?php echo _("Change password on:") ?></strong>
 </td>
 <td class="leftAlign" style="direction: ltr">
  <select name="backend" tabindex="4">
   <?php echo $backends_list ?>
  </select>
 </td>
 <td class="rightAlign">
     <?php echo Help::link('passwd', 'passwd-server') ?>
 </td>
</tr>
<?php endif; ?>

<tr class="control">
 <td colspan="3" class="control leftAlign">
  <input class="button" type="submit" name="submit" value="<?php echo _("Change Password") ?>" onclick="return submit_form();" />
  <input class="button" type="reset" name="reset" value="<?php echo _("Reset") ?>" />
 </td>
</tr>
</table>
</form>
