<script language="JavaScript" type="text/javascript">
<!--

function submit_form()
{
<?php if (empty($hordeauth)): ?>
    if (document.vacation.password.value == "") {
        alert('<?php echo addslashes(_("You must provide your password")) ?>');
        document.vacation.password.focus();
        return false;
    }
<?php endif; ?>
    if (document.vacation.mess.value == "" && document.vacation.mode.value == "set") {
        alert('<?php echo addslashes(_("You must provide some message text to set a vacation notice")) ?>');
        document.vacation.mess.focus();
        return false;
    } else {
        return true;
    }
}
//-->
</script>

<div id="menu"><div class="leftFloat">
<?php
require_once 'Horde/Menu.php';
$menu = &new Menu(HORDE_MENU_MASK_ALL & ~HORDE_MENU_MASK_PREFS);
echo $menu->render();
?>
</div></div>
<?php $notification->notify(array('listeners' => 'status')) ?>

<h1 class="header">
  <?php echo _("Change Vacation Notices") ?>
</h1>

<form method="post" action="main.php" name="vacation">
<?php echo Util::formInput() ?>
<div class="vacationArea">

<p>
  <?php echo _("A vacation notice will automatically reply to any incoming mail you receive while you are away or unable to attend to your e-mail -- for example while on vacation or out of town on business.") ?>
  <?php echo _("It could be used as a courtesy to let others know why you are not answering their e-mail to you.") ?>
  <?php echo _("This form lets you install or remove a vacation notice for your e-mail account. You should edit the default message to meet your needs.") ?>
</p>

<p>
<input type="radio" name="mode" id="mode_set" value="set"<?php if (!$onVacation) echo ' checked="checked"' ?> />
<?php echo Horde::label('mode_set', _("Set/install vacation notice")) ?>
<br />
<input type="radio" name="mode" id="mode_unset" value="unset"<?php if ($onVacation) echo ' checked="checked"' ?> />
<?php echo Horde::label('mode_unset', _("Unset/remove vacation notice")) ?>
</p>

<?php if ($GLOBALS['conf']['vacation']['subject']): ?>
<p>
<?php echo Horde::label('subject', _("Subject:")) ?>
<br />
<input name="subject" id="subject" type="text" size="70" value="<?php echo htmlspecialchars($cursubject) ?>" />
</p>
<?php endif; ?>

<?php if ($GLOBALS['conf']['vacation']['from']): ?>
<p>
<?php echo Horde::label('from', _("From:")) ?>
<br />
<input name="from" id="from" type="text" size="70" value="<?php echo htmlspecialchars($curfrom) ?>" />
</p>
<?php endif; ?>

<p>
<?php echo Horde::label('message', _("Message:")) ?>
<br />
<textarea name="mess" id="message" rows="8" cols="70"><?php echo htmlspecialchars($curmessage) ?></textarea>
</p>

<?php if ($GLOBALS['conf']['aliases']['show']): ?>
<p>
<?php echo Horde::label('alias', _("Alias addresses for this account (optional):")) ?>
<br />
<input name="alias" id="alias" type="text" size="70" value="<?php if (!empty($alias)) echo htmlspecialchars($alias, ENT_QUOTES); ?>" />
<?php echo Help::link('vacation', 'vacation-alias') ?>
</p>
<?php endif; ?>

<?php if (empty($hordeauth)): ?>
<p>
<?php echo _("For your protection and safety, you must identify yourself with your login password to verify this change.") ?>
<?php echo _("Then submit the form so that your vacation notice can be updated.") ?>
</p>

<p>
<?php echo Horde::label('password', _("Your password:")) ?>
<br />
<input type="password" name="password" id="password" size="12" maxlength="32" />
</p>
<?php endif; ?>

<p>
<input class="button" type="submit" name="submit" value="<?php echo _("Submit") ?>" onclick="return submit_form();" />
</p>

</div>
</form>
