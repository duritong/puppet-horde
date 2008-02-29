<form method="post" name="filters" action="<?php echo Horde::applicationUrl('whitelist.php') ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="rule_update" />
<div class="header">
  <ul>
    <li><?php echo Help::link('ingo', 'whitelist') ?></li>
  </ul>
  <?php echo _("Whitelist") ?>
<?php if (!empty($wl_rule['disable'])): ?>
  &nbsp;[<font color="red"><?php echo _("Disabled") ?></font>]
<?php endif; ?>
</div>
<table cellspacing="0" cellpadding="0">
  <tr class="item0">
    <td align="left">
      <?php echo Horde::label('whitelist', _("Wh_itelist addresses:")) ?><br />
      <textarea name="whitelist" id="whitelist" rows="15" cols="80"><?php echo htmlspecialchars(implode("\n", $whitelist->getWhitelist())) ?></textarea>
    </td><td align="right">
      <?php echo Help::link('ingo', 'whitelist-addresses') ?>&nbsp;
    </td>
  </tr><tr>
    <td colspan="2" class="control">
      <input class="button" type="submit" value="&nbsp;<?php echo _("Save") ?>&nbsp;" />
      <input class="button" type="button" onclick="document.location.href='<?php echo Horde::applicationUrl('filters.php', true) ?>'" value="<?php echo _("Return to Rules List") ?>" />
    </td>
  </tr>
</table>
</form>
