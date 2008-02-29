<?php
  $use_pgp_locked = $prefs->isLocked('use_pgp');
  if ($use_pgp_locked): ?>
<strong><?php echo _("PGP support is disabled on this system.") ?></strong>
<?php else: ?>
<input type="checkbox" id="use_pgp" name="use_pgp"<?php echo ($prefs->getValue('use_pgp')) ? ' checked="checked"' : '' ?> /> <?php echo Horde::label('use_pgp', _("Enable PGP functionality?")) ?>&nbsp;<?php echo Help::link('imp', 'pgp-overview') ?><br />
</div>
<?php endif; ?>

<br class="spacer" />
<div class="nowrap">
<?php if (!$use_pgp_locked): ?>
  <input type="submit" name="save" class="button" onclick="document.prefs.actionID.value='pgp_enable'; return true;" value="<?php echo _("Save Options") ?>" />
<?php endif; ?>
  <input type="button" name="back" class="button" onclick="document.location.href='<?php echo IMP::prefsURL(true) ?>'" value="<?php echo _("Return to Options") ?>" />
</div>

</form>
