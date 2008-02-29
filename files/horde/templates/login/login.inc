<?php
$rightAlign = empty($nls['rtl'][$language]) ? 'right' : 'left';
$leftAlign = empty($nls['rtl'][$language]) ? 'left' : 'right';
?>
<script type="text/javascript">
<!--
function setFocus()
{
    if (document.horde_login.horde_user.value == "") {
        document.horde_login.horde_user.focus();
    } else {
        document.horde_login.horde_pass.focus();
    }
}
function submit_login(e)
{
    if (typeof e != 'undefined' && !enter_key_trap(e)) {
        return;
    }

    if (document.horde_login.horde_user.value == "") {
        alert("<?php echo addslashes(_("Please provide your username and password")) ?>");
        document.horde_login.horde_user.focus();
        return false;
    } else if (document.horde_login.horde_pass.value == "") {
        alert("<?php echo addslashes(_("Please provide your username and password")) ?>");
        document.horde_login.horde_pass.focus();
        return false;
    } else {
        document.horde_login.loginButton.disabled = true;
<?php if (!empty($ie_clientcaps)): ?>
        try {
            document.horde_login.ie_version.value = objCCaps.getComponentVersion("{89820200-ECBD-11CF-8B85-00AA005B4383}", "componentid");
        } catch (e) {
        }
<?php endif; ?>
        document.horde_login.submit();
        return true;
    }
}
//-->
</script>

<form name="horde_login" method="post" action="<?php echo Horde::selfUrl(false, true, false, true) ?>"<?php echo $conf['menu']['always'] ? ' target="_parent"' : '' ?>>
<input type="hidden" name="url" value="<?php echo htmlspecialchars(Util::getFormData('url')) ?>" />
<?php if (!empty($ie_clientcaps)): ?>
<input type="hidden" name="ie_version" value="" />
<?php endif; ?>

<div id="menu">
<?php
$menu = &new Menu(HORDE_MENU_MASK_NONE);
$hmenu = $menu->render();
if ($hmenu): ?>
 <span style="float:<?php echo $rightAlign ?>"><?php echo $hmenu ?></span>
<?php endif; ?>
 <h1 style="text-align:center"><?php printf(_("Welcome to %s"), $registry->get('name')) ?></h1>
</div>

<br class="spacer" />
<?php $notification->notify(array('listeners' => 'status')) ?>

<table width="100%"><tr><td align="center"><table width="300" align="center">

<?php if (!empty($reason)): ?>
<tr>
  <td colspan="2" class="notice"><?php echo $reason ?></td>
</tr>
<?php endif; ?>

<tr>
    <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Username") ?></strong>&nbsp;</td>
    <td align="<?php echo $leftAlign ?>"><input class="fixed" type="text" name="horde_user" value="<?php echo htmlspecialchars(Util::getFormData('horde_user')) ?>" style="direction:ltr" /></td>
</tr>

<tr>
    <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Password") ?></strong>&nbsp;</td>
    <td align="<?php echo $leftAlign ?>"><input class="fixed" type="password" name="horde_pass" value="" style="direction:ltr" /></td>
</tr>

<?php if (!$prefs->isLocked('language')): ?>
<tr>
    <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Language") ?></strong></td>
    <td align="<?php echo $leftAlign ?>" class="light" style="direction:ltr"><?php echo $langs ?></td>
</tr>
<?php endif; ?>

<tr>
    <td>&nbsp;</td>
    <td align="<?php echo $leftAlign ?>" class="light"><input name="loginButton" class="button" value="<?php echo _("Log in") ?>" type="submit" onclick="return submit_login();" /></td>
</tr>

<?php if (!empty($conf['signup']['allow']) && $auth->hasCapability('add')): ?>
<tr>
    <td>&nbsp;</td>
    <td align="<?php echo $leftAlign ?>" class="light"><?php echo Horde::link(Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/signup.php'), 'url', Util::getFormData('url')), _("Don't have an account? Sign up."), 'light') . _("Don't have an account? Sign up.") ?></a></td>
</tr>
<?php endif; ?>
<?php if ($auth->hasCapability('resetpassword')): ?>
<tr>
    <td>&nbsp;</td>
    <td class="light"><?php echo Horde::link(Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/services/resetpassword.php'), 'url', Util::getFormData('url')), _("Forgot your password?"), 'light') . _("Forgot your password?") ?></a></td>
</tr>
<?php endif; ?>

</table></td></tr></table>
</form>

<?php if (@is_readable(HORDE_BASE . '/config/motd.php')) require HORDE_BASE . '/config/motd.php'; ?>

<script type="text/javascript">
<!--
<?php if (!$prefs->isLocked('language')):
    $lang_url = Horde::applicationUrl('login.php', true);
    if (($url = Util::getFormData('url'))) {
        $lang_url = Util::addParameter($lang_url, 'url', $url, false);
    }
    $lang_url = Util::addParameter($lang_url, 'new_lang', '', false);
?>
function selectLang()
{
    // We need to reload the login page here, but only if the user
    // hasn't already entered a username and password.
    if (document.horde_login.horde_user.value == '' &&
        document.horde_login.horde_pass.value == '') {
        var lang_page = '<?php echo $lang_url ?>' + document.horde_login.new_lang[document.horde_login.new_lang.selectedIndex].value;
        self.location = lang_page;
    }
}
<?php endif; ?>

if (parent.frames.horde_main) {
    document.horde_login.target = '_parent';
}

// Set up the enter keytrap code.
if (typeof document.captureEvents != 'undefined') {
    document.captureEvents(Event.KEYPRESS);
    document.onkeypress = submit_login;
}
//-->
</script>
