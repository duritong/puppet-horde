<script type="text/javascript">
<!--

var editURL = decodeURIComponent('<?php echo rawurlencode(Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/services/shares/edit.php?app=mnemo', true), 'share', '@ID@', false)) ?>');
var cancelSubmit = false;
var fields = new Array();
<?php foreach ($personal_notepads as $id => $notepads): ?>
fields['<?php echo htmlspecialchars($id) ?>'] = new Array(
        "<?php echo rawurlencode(String::convertCharset($notepads->get('name'), NLS::getCharset(), 'UTF-8')) ?>",
        "<?php echo rawurlencode(String::convertCharset($notepads->get('desc'), NLS::getCharset(), 'UTF-8')) ?>");
<?php endforeach; ?>

function newChoice()
{
    var action = document.shares.share.options[document.shares.share.selectedIndex].value;
    if (action == -1) {
        document.shares.save.value = '<?php echo _("Create") ?>';
        document.shares.accesslist.style.display = 'none';
        document.shares.reset();
        clearForm();
    } else {
        document.shares.save.value = '<?php echo _("Change") ?>';
        document.shares.accesslist.style.display = 'block';
        updateForm(action);
    }
}

function updateForm(share)
{
    document.shares.edit_share.value = share;
    document.shares.id.value = decodeURIComponent(fields[share][0]);
    document.shares.description.value = decodeURIComponent(fields[share][1]);
}

function clearForm()
{
    document.shares.edit_share.value = '';
    document.shares.id.value = '';
    document.shares.description.value = '';
    document.shares.accesslist.style.display = 'none';
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

<form method="post" name="shares" action="<?php echo Horde::applicationUrl('notepads.php') ?>" onsubmit="return checkSubmit()">
<input type="hidden" name="actionID" value="save" />

<table width="100%" cellspacing="0">
<tr><td class="header" align="left"><strong><?php echo _("Notepads") ?></strong></td></tr>
<tr><td class="item"><table>

<tr valign="top"><td class="item">
<?php echo _("Your notepads:") ?><br />
<select name="share" onchange="javascript:newChoice()">
  <option value="-1"><?php echo _("Select a notepad") ?></option>
<?php foreach ($personal_notepads as $id => $notepad): ?>
  <option value="<?php echo htmlspecialchars($id) ?>"><?php echo htmlspecialchars($notepad->get('name')) ?></option>
<?php endforeach; ?>
</select>
</td></tr>

<tr><td class="item"><table>

<tr valign="top"><td class="item">

<input type="hidden" name="edit_share" value="<?php echo isset($to_edit) ? htmlspecialchars($to_edit) : '' ?>" />
<?php echo _("Notepad's name:") ?><br />
<input name="id" size="30" maxlength="60" /><br />

<input type="button" name="accesslist" class="button" onclick="popup(editURL.replace(/%40ID%40/, document.shares.edit_share.value));" value="<?php echo _("Permissions") ?>" style="display:none" />
<br />

<?php echo _("Description:") ?><br />
<textarea name="description" rows="6" cols="80"></textarea><br />

</td></tr></table></td></tr>
<tr>
  <td>
    <table width="100%">
    <tr>
      <td>
        <input type="submit" name="save" class="button" onclick="document.shares.actionID.value = 'save'; return true" value="<?php echo _("Create") ?>" />
        <input type="submit" name="delete" class="button" onclick="document.shares.actionID.value = 'delete'" value="<?php echo _("Delete") ?>" />
        <input type="submit" name="new" class="button" onclick="clearForm(); document.shares.save.value = '<?php echo _("Create") ?>';" value="<?php echo _("Reset") ?>" />
        <input type="submit" name="back" class="button" onclick="cancelSubmit = true; document.location.href = '<?php echo Horde::url(Util::addParameter($registry->get('webroot', 'horde') . '/services/prefs.php', array('app' => 'mnemo', 'group' => 'share')), true) ?>'" value="<?php echo _("Back to Notepad Options") ?>" />
      </td>
    </tr>
    </table>
  </td>
</tr>
</table>
</td></tr></table>
</form>
