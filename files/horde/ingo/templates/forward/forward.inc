<form method="post" name="ingo_forward" action="<?php echo Horde::applicationUrl('forward.php') ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="update" />
<div class="header">
  <ul>
    <li><?php echo Help::link('ingo', 'forward') ?></li>
  </ul>
  <?php echo _("Forward") ?>
<?php if (!empty($fwd_rule['disable'])): ?>
  &nbsp;[<font color="red"><?php echo _("Disabled") ?></font>]
<?php endif; ?>
</div>
<table cellspacing="0" cellpadding="0">
  <tr class="item0">
    <td style="padding:5px" align="left">
      <input type="checkbox" name="keep_copy" id="keep_copy" value="1"<?php if ($forward->getForwardKeep()) echo ' checked="checked"' ?> />
      <?php echo Horde::label('keep_copy', _("_Keep a copy of messages in this account?")) ?>
    </td>
    <td width="40" align="right">
      <?php echo Help::link('ingo', 'forward-keepcopy') ?>&nbsp;
    </td>
  </tr><tr class="item1">
    <td style="padding:5px" align="left">
      <?php echo Horde::label('addresses', _("_Address(es) to forward to:")) ?>
      <br />
      <textarea name="addresses" id="addresses" rows="5" cols="40"><?php echo htmlspecialchars(implode("\n", $forward->getForwardAddresses())) ?></textarea>
    </td>
    <td width="40" align="right">
      <?php echo Help::link('ingo', 'forward-addresses') ?>&nbsp;
    </td>
  </tr><tr class="control">
    <td colspan="2" class="control">
      <input class="button" type="submit" value="&nbsp;<?php echo _("Save") ?>&nbsp;" />
      <input class="button" type="button" onclick="document.location.href='<?php echo addslashes(Horde::applicationUrl('filters.php', true)) ?>'" value="<?php echo _("Return to Rules List") ?>" />
    </td>
  </tr>
</table>
</form>
