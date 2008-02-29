<?php
$server_key = IMP::getAutoLoginServer(true);
$rightAlign = empty($nls['rtl'][$language]) ? 'right' : 'left';
$leftAlign = empty($nls['rtl'][$language]) ? 'left' : 'right';
$tabindex = 1;

/* Build the <select> widget for the servers and hordeauth servers lists. */
$show_list = ($conf['server']['server_list'] == 'shown');
if ($show_list) {
    $hordeauth_servers_list = $servers_list = '';
    $isAuth = Auth::isAuthenticated();
    foreach ($servers as $key => $val) {
        $sel = (IMP::isPreferredServer($val, $key)) ? ' selected="selected"' : '';
        $servers_option = "<option value=\"$key\"$sel>" . $val['name'] . '</option>';

        if (empty($curServer['hordeauth']) || !$isAuth) {
            $servers_list .= $servers_option;
        } elseif ($isAuth) {
            $hordeauth_servers_list .= $servers_option;
        }
    }
}

/* Build the <select> widget containing the available languages. */
if (!$prefs->isLocked('language')) {
    $_SESSION['horde_language'] = NLS::select();
    $langs = '';
    foreach ($nls['languages'] as $key => $val) {
        $sel = ($key == $_SESSION['horde_language']) ? ' selected="selected"' : '';
        $langs .= "<option value=\"$key\"$sel>$val</option>";
    }
}
?>

<script type="text/javascript">
<!--

function setFocus()
{
    if (document.imp_login.imapuser.value == "") {
        document.imp_login.imapuser.focus();
    } else {
        document.imp_login.pass.focus();
    }
}

<?php if ($show_list && !empty($hordeauth_servers_list)): ?>
<?php $reloadurl = Util::addParameter(Horde::selfUrl(), array('autologin' => $autologin, 'server_key' => '')); ?>
function imp_reload()
{
    window.top.document.location = '<?php echo $reloadurl ?>' + document.imp_login.server_key[document.imp_login.server_key.selectedIndex].value;
}
<?php endif; ?>

function submit_login(e)
{
    if (typeof e != 'undefined' && !enter_key_trap(e)) {
        return;
    }

<?php if ($show_list): ?>
    if (document.imp_login.server[document.imp_login.server.selectedIndex].value.substr(0, 1) == "_") {
        return false;
    }
<?php endif; ?>
    if (document.imp_login.imapuser.value == "") {
        alert('<?php echo addslashes(_("Please provide your username.")) ?>');
        document.imp_login.imapuser.focus();
        return false;
    } else if (document.imp_login.pass.value == "") {
        alert('<?php echo addslashes(_("Please provide your password.")) ?>');
        document.imp_login.pass.focus();
        return false;
    } else {
        document.imp_login.loginButton.disabled = true;
<?php if (!empty($ie_clientcaps)): ?>
        try {
            document.imp_login.ie_version.value = objCCaps.getComponentVersion("{89820200-ECBD-11CF-8B85-00AA005B4383}","componentid");
        } catch (e) {
        }
<?php endif; ?>
        document.imp_login.submit();
        return true;
    }
}
//-->
</script>

<form name="imp_login" action="<?php echo $formAction ?>" method="post"<?php echo $imp_auth ? ' target="_parent"' : '' ?>>
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="<?php echo htmlspecialchars($actionID) ?>" />
<input type="hidden" name="url" value="<?php echo htmlspecialchars(Util::getFormData('url')) ?>" />
<input type="hidden" name="load_frameset" value="<?php echo $imp_auth ? 1 : 0 ?>" />
<input type="hidden" name="autologin" value="<?php echo $autologin ? 1 : 0 ?>" />
<?php if (!empty($formData)): ?>
<input type="hidden" name="formData" value="<?php echo htmlspecialchars($formData) ?>" />
<?php endif; ?>
<?php if (!empty($ie_clientcaps)): ?>
<input type="hidden" name="ie_version" value="" />
<?php endif; ?>
<?php if (!($show_list && !empty($hordeauth_servers_list))): ?>
<input type="hidden" name="server_key" value="<?php echo htmlspecialchars($server_key) ?>" />
<?php endif; ?>
<?php $args = IMP::getComposeArgs(); foreach ($args as $arg => $value): ?>
<input type="hidden" name="<?php echo htmlspecialchars($arg) ?>" value="<?php echo htmlspecialchars($value) ?>" />
<?php endforeach; ?>

<div id="menu">
<?php
$menu = &new Menu(HORDE_MENU_MASK_NONE);
$imenu = $menu->render();
if ($imenu): ?>
 <span style="float:<?php echo $rightAlign ?>"><?php echo $imenu ?></span>
<?php endif; ?>
 <h1 align="center"><?php echo $title ?></h1>
</div>
<?php $notification->notify(array('listeners' => 'status')) ?>

<table width="100%">
 <tr>
  <td align="center">
   <table align="center">

<?php if (!empty($reason)): ?>
    <tr>
     <td colspan="2" class="notice"><?php echo $reason ?></td>
    </tr>
<?php endif; ?>

<?php if ($show_list && !empty($hordeauth_servers_list)): ?>
    <tr>
     <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Server") ?></strong></td>
     <td class="light" align="<?php echo $leftAlign ?>">
      <select tabindex="<?php echo $tabindex++ ?>" name="server_key" onchange="imp_reload();">
<?php echo $hordeauth_servers_list ?>
      </select>
      <input tabindex="<?php echo $tabindex++ ?>" type="button" class="button" onclick="imp_reload();" value="<?php echo _("Login") ?>" />
     </td>
    </tr>
<?php endif; ?>

<?php if ($show_list && !empty($servers_list)): ?>
    <tr>
     <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Server") ?></strong></td>
     <td class="light" align="<?php echo $leftAlign ?>">
      <select tabindex="<?php echo $tabindex++ ?>" name="server"><?php echo $servers_list ?></select>
     </td>
    </tr>
<?php elseif (!empty($conf['server']['change_server'])): ?>
    <tr>
     <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Server") ?></strong></td>
     <td align="<?php echo $leftAlign ?>" class="light">
      <input type="text" tabindex="<?php echo $tabindex++ ?>" name="server" value="<?php echo Util::getFormData('server', $server_key) ?>" onchange="serverChangeHandler()" style="direction:ltr" />

<?php if (!empty($conf['server']['change_port'])): ?>
      <input type="text" size="4" name="port" tabindex="<?php echo $tabindex++ ?>" value="<?php echo Util::getFormData('port', $servers[$server_key]['port']) ?>" style="direction:ltr" />
<?php endif; ?>

<?php if (!empty($conf['server']['change_protocol'])): ?>
<?php $protocol = Util::getFormData('protocol', $servers[$server_key]['protocol']) ?>
      <select tabindex="<?php echo $tabindex++ ?>" name="protocol" onchange="updatePort();">
       <option value="imap"<?php if ($protocol == 'imap') echo ' selected="selected"' ?>>IMAP</option>
       <option value="imap/notls"<?php if ($protocol == 'imap/notls') echo ' selected="selected"' ?>><?php echo _("IMAP, no TLS") ?></option>
       <option value="imap/ssl"<?php if ($protocol == 'imap/ssl') echo ' selected="selected"' ?>><?php echo _("IMAP over SSL") ?></option>
       <option value="imap/ssl/novalidate-cert"<?php if ($protocol == 'imap/ssl/novalidate-cert') echo ' selected="selected"' ?>><?php echo _("IMAP over SSL (self-signed)") ?></option>
       <option value="pop3"<?php if ($protocol == 'pop3') echo ' selected="selected"' ?>>POP3</option>
       <option value="pop3/ssl/novalidate-cert"<?php if ($protocol == 'pop3/ssl/novalidate-cert') echo ' selected="selected"' ?>><?php echo _("POP3 over SSL (self-signed)") ?></option>
      </select>
<?php endif; ?>

     </td>
    </tr>
<?php endif; ?>
    <tr>
     <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Username") ?></strong></td>
     <td align="<?php echo $leftAlign ?>" class="light" nowrap="nowrap">
      <input type="text" tabindex="<?php echo $tabindex++ ?>" name="imapuser" value="<?php echo htmlspecialchars(Util::getFormData('imapuser')) ?>" style="direction:ltr" />
<?php
    if (!empty($conf['hooks']['vinfo'])) {
        @include_once HORDE_BASE . '/config/hooks.php';
        if (function_exists('_imp_hook_vinfo')) {
            echo '<strong>@' . call_user_func('_imp_hook_vinfo', 'vdomain') . '</strong>';
        }
    }
?>
     </td>
    </tr>

    <tr>
     <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Password") ?></strong></td>
     <td align="<?php echo $leftAlign ?>">
      <input type="password" tabindex="<?php echo $tabindex++ ?>" name="pass" style="direction:ltr" />
     </td>
    </tr>

<?php if (!empty($conf['server']['change_smtphost'])): ?>
    <tr style="height: 1em;"><td colspan="2"></td></tr>
    <tr>
     <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Outbound Server") ?></strong></td>
     <td align="<?php echo $leftAlign ?>" class="light">
      <input type="text" tabindex="<?php echo $tabindex++ ?>" name="smtphost" value="<?php echo Util::getFormData('smtphost', $server_key) ?>" style="direction:ltr" />
<?php if (!empty($conf['server']['change_smtpport'])): ?>
      <input type="text" size="4" name="smtpport" tabindex="<?php echo $tabindex++ ?>" value="<?php echo Util::getFormData('smtpport', $servers[$server_key]['smtpport']) ?>" style="direction:ltr" /> <?php echo _("SMTP"); ?>
<?php endif; ?>
     </td>
    </tr>
<?php endif; ?>

<?php if (!empty($conf['server']['change_smtphost'])): ?>
    <tr style="height: 1em;"><td colspan="2"></td></tr>
<?php endif; ?>
<?php if (!$prefs->isLocked('language')): ?>
    <tr>
     <td align="<?php echo $rightAlign ?>" class="light"><strong><?php echo _("Language") ?></strong></td>
     <td align="<?php echo $leftAlign ?>" class="light">
      <select tabindex="<?php echo $tabindex++ ?>" name="new_lang" onchange="selectLang()" style="direction:ltr"><?php echo $langs ?></select> </td>
    </tr>
<?php endif; ?>

    <tr>
     <td>&nbsp;</td>
     <td align="<?php echo $leftAlign ?>" class="light">
      <input type="submit" class="button" name="loginButton" tabindex="<?php echo $tabindex++ ?>" value="<?php echo _("Log in") ?>" onclick="return submit_login();" />
     </td>
    </tr>

<?php if ($conf['signup']['allow'] && isset($auth) && $auth->hasCapability('add')): ?>
    <tr>
     <td>&nbsp;</td>
     <td class="light"><?php echo Horde::link(Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/signup.php'), 'url', Util::getFormData('url')), _("Don't have an account? Sign up."), 'light') . _("Don't have an account? Sign up.") ?></a></td>
    </tr>
<?php endif; ?>

   </table>
  </td>
 </tr>
</table>
</form>

<script type="text/javascript">
<!--

<?php if (!empty($conf['server']['change_protocol'])): ?>
function updatePort()
{
<?php if (!empty($conf['server']['change_port'])): ?>
    if (document.imp_login.protocol.options[document.imp_login.protocol.selectedIndex].value == 'imap') {
        document.imp_login.port.value = 143;
    } else if (document.imp_login.protocol.options[document.imp_login.protocol.selectedIndex].value == 'imap/ssl' ||
               document.imp_login.protocol.options[document.imp_login.protocol.selectedIndex].value == 'imap/ssl/novalidate-cert') {
        document.imp_login.port.value = 993;
    } else if (document.imp_login.protocol.options[document.imp_login.protocol.selectedIndex].value == 'pop3') {
        document.imp_login.port.value = 110;
    } else if (document.imp_login.protocol.options[document.imp_login.protocol.selectedIndex].value == 'pop3/ssl' ||
               document.imp_login.protocol.options[document.imp_login.protocol.selectedIndex].value == 'pop3/ssl/novalidate-cert') {
        document.imp_login.port.value = 995;
    }
<?php endif; ?>
}
<?php endif; ?>

<?php if (!empty($conf['server']['change_server'])): ?>
function serverChangeHandler(ctl)
{
<?php if (!empty($conf['server']['change_smtphost'])): ?>
    document.imp_login.smtphost.value = document.imp_login.server.value;
<?php endif; ?>
}
<?php endif; ?>

<?php if (!$prefs->isLocked('language')): ?>
function selectLang()
{
    // We need to reload the login page here, but only if the user hasn't
    // already entered a username and password.
    if (document.imp_login.imapuser.value == '' &&
        document.imp_login.pass.value == '') {
        var lang_page = 'login.php?new_lang=' + document.imp_login.new_lang[document.imp_login.new_lang.selectedIndex].value;
<?php
    $url = Util::getFormData('url');
    if (!empty($url)) {
        echo "lang_page += '&url=" . urlencode($url) . "';";
    }
?>
        self.location = lang_page;
    }
}
<?php endif; ?>

<?php if ($imp_auth): ?>
if (parent.frames.horde_main) {
    document.imp_login.target = '_parent';
}
<?php endif; ?>

// Set up the enter keytrap code.
if (typeof document.captureEvents != 'undefined') {
    document.captureEvents(Event.KEYPRESS);
    document.onkeypress = submit_login;
}
//-->
</script>
