<?php
  $use_smime_locked = $prefs->isLocked('use_smime');
  if ($use_smime_locked): ?>
<strong><?php echo _("S/MIME support is disabled on this system.") ?></strong>
<?php else: ?>
<input id="use_smime" type="checkbox" name="use_smime"<?php echo ($prefs->getValue('use_smime')) ? ' checked="checked"' : '' ?> /> <?php echo Horde::label('use_smime', _("Enable S/MIME functionality?")) ?>&nbsp;<?php echo Help::link('imp', 'smime-overview') ?><br />
<?php endif; ?>
</div>

<br class="spacer" />
<div class="nowrap">
<?php if (!$use_smime_locked): ?>
  <input type="submit" name="save" class="button" onclick="document.prefs.actionID.value='save_options'; return true;" value="<?php echo _("Save Options") ?>" />&nbsp;
<?php endif; ?>
  <input type="button" name="back" class="button" onclick="document.location.href='<?php echo IMP::prefsURL(true) ?>'" value="<?php echo _("Return to Options") ?>" />
</div>
</form>
