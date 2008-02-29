<script type="text/javascript">
<!--
function confirmCancel()
{
    if (window.confirm('<?php echo addslashes(_("Cancelling this message will permanently discard its contents.")) . '\n' . addslashes(_("Are you sure you want to do this?")) ?>')) {
        <?php echo $cancel_js ?>
    }
}

function redirectSubmit()
{
    if (document.redirect.to.value == '') {
        alert('<?php echo addslashes(_("You must specify a recipient.")) ?>');
        document.redirect.to.focus();
        return false;
    }

    return true;
}

function uniqSubmit()
{
    now = new Date();
    document.redirect.submitBtn.disabled = true;
    document.redirect.cancelBtn.disabled = true;
    if (document.redirect.style && document.redirect.style.cursor) {
        document.redirect.style.cursor = "wait";
    }
    document.redirect.submit();
}
//-->
</script>

<form method="post" action="<?php echo Util::addParameter(Horde::url('compose.php'), 'uniq', base_convert(microtime(), 10, 36)) ?>" name="redirect">
<input type="hidden" name="mailbox" value="<?php echo htmlspecialchars($mailbox) ?>" />
<input type="hidden" name="index" value="<?php echo htmlspecialchars($index) ?>" />
<input type="hidden" name="actionID" value="redirect_send" />

<?php echo IMP::status(); $tabindex = 0; ?>

<h1 class="header">
  <?php echo htmlspecialchars($title) ?>
</h1>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<?php if ($GLOBALS['registry']->hasMethod('contacts/search')): ?>
<?php $contacts_url = Util::addParameter(Horde::applicationUrl('contacts.php'), array('formname' => 'redirect', 'to_only' => 1)); ?>
<tr>
  <td>&nbsp;</td>
  <td class="item">
    <table cellspacing="0" width="100%">
      <tr>
        <td align="center"><?php echo Horde::link('#', _("Address Book"), 'widget', null, 'window.open(\'' . $contacts_url . '\', \'contacts\', \'toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes,width=550,height=300,left=100,top=100\'); return false;') . Horde::img('addressbook_browse.png') . '<br />' . _("Address Book") . '</a>' ?></td>
        <td align="center"><?php echo Horde::link('#', _("Expand Names"), 'widget', null, "document.redirect.actionID.value='redirect_expand_addr'; uniqSubmit(); return false;") . Horde::img('expand.png') . '<br />' . _("Expand Names") . '</a>' ?></td>
      </tr>
    </table>
  </td>
</tr>
<?php endif; ?>

<tr>
  <td class="light rightAlign" valign="middle"><strong><?php echo Horde::label('to', _("To")) ?>&nbsp;</strong></td>
  <td class="item leftAlign">
      <table border="0" width="100%" cellpadding="0">
<?php if (is_array($header['to'])): $first_to = true; ?>
<?php foreach ($header['to'] as $to_item): ?>
        <tr>
<?php if (count($to_item) > 1): ?>
          <td class="leftAlign">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td width="350">
                  <select id="to" tabindex="<?php echo $tabindex++ ?>" name="to_list[<?php echo htmlspecialchars($to_item[0]) ?>]" style="width:350px">
                    <option value="" selected="selected"><?php echo _("Please select or edit in the box to the right:") ?></option>
<?php for ($i = 1; $i < count($to_item); $i++): ?>
                    <option value="<?php echo htmlspecialchars($to_item[$i]) ?>"><?php echo htmlspecialchars($to_item[$i]) ?></option>
<?php endfor; ?>
                  </select>
                </td>
                <td width="150">
                  <input type="text" size="15" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="to_field[<?php echo htmlspecialchars($to_item[0]) ?>]" value="<?php echo htmlspecialchars($to_item[0]) ?>" style="width:150px" />
                </td>
              </tr>
            </table>
          </td>
<?php else: ?>
          <td class="leftAlign"><input type="text" id="to" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="to_field[]" value="<?php echo htmlspecialchars($to_item) ?>" /></td>
<?php endif; ?>
<?php if ($first_to): $first_to = false; ?>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-to') ?>&nbsp;</td>
<?php else: ?>
          <td>&nbsp;</td>
<?php endif; ?>
        </tr>
<?php endforeach; ?>
        <tr>
          <td class="leftAlign"><input type="text" id="to" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="to_new" /></td>
          <td>&nbsp;</td>
        </tr>
<?php else: ?>
        <tr>
          <td class="leftAlign"><input type="text" id="to" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="to" value="<?php echo htmlspecialchars($header['to']) ?>" <?php echo ($browser->hasFeature('iframes')) ? 'onchange="expandField(this)" /><span id="expandingto" class="inProgress" style="visibility:hidden;">' . _("Expanding") . '&hellip;</span>' : '/>'; ?></td>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-to') ?>&nbsp;</td>
        </tr>
<?php endif; ?>
      </table>
    </td>
  </tr><tr>
    <td>&nbsp;</td>
    <td>
      <input name="submitBtn" type="submit" class="button" onclick="return redirectSubmit();" value="<?php echo _("Redirect Message") ?>" />
      <input name="cancelBtn" type="button" class="button" onclick="confirmCancel();" value="<?php echo _("Cancel Message") ?>" />
    </td>
  </tr>
</table>
</form>

<br />

<?php if ($browser->hasFeature('javascript')): ?>
<script type="text/javascript">
<!--

document.redirect.to.focus();

//-->
</script>
<?php endif; ?>

<?php if ($GLOBALS['prefs']->getValue('auto_expand')): ?>
<iframe id="autoexpand" name="autoexpand" style="width:0px;height:0px;border-width:0px" src="<?php echo Horde::applicationUrl('expand.php') ?>"></iframe>
<?php endif; ?>
