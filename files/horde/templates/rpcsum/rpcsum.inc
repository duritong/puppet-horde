<script type="text/javascript">
<!--

var cancelSubmit = false;

var fields = new Array(
<?php for ($i = 0; $i < count($rpc_servers); $i++): ?>
    new Array(
        "<?php echo rawurlencode($rpc_servers[$i]['url']) ?>",
        "<?php echo rawurlencode($rpc_servers[$i]['user']) ?>"
    )<?php if ($i + 1 < count($rpc_servers)) echo ','; ?>

<?php endfor; ?>
);

function newChoice()
{
    var action = document.prefs.server.options[document.prefs.server.selectedIndex].value;

    if (action == -1) {
        document.prefs.save.value = '<?php echo addslashes(_("Create")) ?>';
        document.prefs.reset();
    } else {
        document.prefs.save.value = '<?php echo addslashes(_("Change")) ?>';
        updateForm(action);
    }
}

function updateForm(server)
{
    document.prefs.edit_server.value = server;
    document.prefs.url.value = decodeURIComponent(fields[server][0]);
    document.prefs.user.value = decodeURIComponent(fields[server][1]);
}

function clearForm()
{
    document.prefs.edit_server.value = '';
    document.prefs.url.value =  fields[server][0];
    document.prefs.user.value = fields[server][1];
    document.prefs.server.selectedIndex = 0;
    cancelSubmit = true;
}

function checkSubmit()
{
    cancel = cancelSubmit;
    cancelSubmit = false;
<?php if ($browser->getBrowser() == 'msie' && $browser->getMajor() >= 4): ?>
    window.event.returnValue = !cancel;
<?php endif; ?>
    return !cancel;
}

//-->
</script>

<?php echo _("Your remote servers:") ?><br />
<select name="server" onchange="javascript:newChoice()">
  <option value="-1"><?php echo _("Select a server") ?></option>
<?php for ($i = 0; $i < count($rpc_servers); ++$i): ?>
  <option value="<?php echo $i ?>"><?php echo htmlspecialchars($rpc_servers[$i]['url']) ?></option>
<?php endfor; ?>
</select>
</td></tr>

<noscript>
</table></td></tr>
<tr>
  <td valign="middle">
    <br />
    <input type="submit" name="edit" class="button" value="<?php echo _("Edit") ?>" />
    <input type="submit" name="delete" class="button" value="<?php echo _("Delete") ?>" />
    <br />&nbsp;
  </td>
</tr>

<tr><td class="item"><table>
</noscript>

<tr valign="top"><td class="item">

<input type="hidden" name="edit_server" value="" />
<?php echo _("Remote URL (http://www.example.com/horde):") ?><br />
<input type="text" name="url" size="30" maxlength="60" /><br />

<?php echo _("Username:") ?><br />
<input type="text" name="user" size="30" maxlength="60" /><br />

<?php echo _("Password:") ?><br />
<input type="password" name="passwd" size="30" maxlength="60" /><br />

</td></tr></table></td></tr></table>

<p>
 <input type="submit" name="save" class="button" onclick="document.prefs.actionID.value='save'; return true" value="<?php echo $actionID == 'edit' ? _("Change") : _("Create") ?>" />&nbsp;
<script type="text/javascript">
document.write('<input type="submit" name="delete" class="button" onclick="document.prefs.actionID.value=\'delete\'" value="<?php echo _("Delete") ?>" />&nbsp;');
</script>
 <input type="submit" name="new" class="button" onclick="clearForm();document.prefs.save.value='<?php echo _("Create") ?>';" value="<?php echo _("Reset") ?>" />&nbsp;
 <input type="button" class="button" onclick="cancelSubmit=true;document.location.href='<?php echo Horde::applicationUrl(Util::addParameter('services/prefs.php', 'app', 'horde'), true) ?>'" value="<?php echo _("Return to Options") ?>" />
</p>

</form>
