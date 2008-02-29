<form method="post" name="vacation" action="<?php echo Horde::applicationUrl('vacation.php') ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="rule_update" />
<h1 class="header">
 <?php echo _("Vacation") ?>
 <?php if (!empty($vac_rule['disable'])): ?>[<span class="form-error"><?php echo _("Disabled") ?></span>]<?php endif; ?>
 <?php echo Help::link('ingo', 'vacation') ?>
</h1>

<table width="100%" cellspacing="0">
  <tr class="item0">
    <td>
      <?php echo Horde::label('addresses', _("_My email addresses:")) ?>
      <br />
      <textarea name="addresses" id="addresses" rows="5" cols="40"><?php echo htmlspecialchars(implode("\n", $vacation->getVacationAddresses())) ?></textarea>
    </td>
    <td width="40" class="rightAlign">
      <?php echo Help::link('ingo', 'vacation-myemail') ?>&nbsp;
    </td>
  </tr>
  <tr class="item1">
    <td>
      <?php echo Horde::label('excludes', _("_Addresses to not send responses to:")) ?>
      <br />
      <textarea name="excludes" id="excludes" rows="10" cols="40"><?php echo htmlspecialchars(implode("\n", $vacation->getVacationExcludes())) ?></textarea>
    </td>
    <td width="40" class="rightAlign">
      <?php echo Help::link('ingo', 'vacation-noresponse') ?>&nbsp;
    </td>
  </tr>
  <tr class="item0">
    <td>
      <input type="checkbox" name="ignorelist" id="ignorelist" value="1"<?php if ($vacation->getVacationIgnorelist()) echo ' checked="checked"'; ?> />
      <?php echo Horde::label('ignorelist', _("Do _not send responses to bulk or list messages")) ?>
    </td>
    <td width="40" class="rightAlign">
      <?php echo Help::link('ingo', 'vacation-bulk') ?>&nbsp;
    </td>
  </tr>
  <tr class="item0">
    <td>
      <?php echo Horde::label('days', _("N_umber of days between vacation replies:")) ?>
      <input type="text" name="days" id="days" size="2" maxlength="2" value="<?php echo (int)$vacation->getVacationDays() ?>" />
    </td>
    <td width="40" class="rightAlign">
      <?php echo Help::link('ingo', 'vacation-days') ?>&nbsp;
    </td>
  </tr>
  <tr class="item1">
    <td>
      <?php echo Horde::label('subject', _("Subje_ct of vacation message:")) ?> &nbsp;
      <input type="text" name="subject" id="subject" size="40" value="<?php echo htmlspecialchars($vacation->getVacationSubject()) ?>" />
    </td>
    <td width="40" class="rightAlign">
      <?php echo Help::link('ingo', 'vacation-subject') ?>&nbsp;
    </td>
  </tr>
  <tr class="item0">
    <td>
      <?php echo Horde::label('reason', _("R_eason")) ?>:
      <br />
      <textarea name="reason" id="reason" rows="10" cols="40"><?php echo htmlspecialchars($vacation->getVacationReason()) ?></textarea>
    </td>
    <td width="40" class="rightAlign">
      <?php echo Help::link('ingo', 'vacation-reason') ?>&nbsp;
    </td>
  </tr>
  <tr>
    <td colspan="2" class="control">
      <input class="button" type="submit" value="&nbsp;<?php echo _("Save") ?>&nbsp;" />
      <input class="button" type="button" onclick="document.location.href='<?php echo Horde::applicationUrl('filters.php', true) ?>'" value="<?php echo _("Return to Rules List") ?>" />
    </td>
  </tr>
</table>
</form>
