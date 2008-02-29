<form name="fm_perform" action="<?php echo $fetch_url ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="fetchmail_fetch" />

<h1 class="header">
  <?php echo _("Other Mail Accounts") ?>
</h1>
<div class="item">
<?php if ($accounts): ?>
  <?php echo _("Check all mail servers you want to fetch mail from:") ?>&nbsp;<br />

  <ul>
<?php foreach ($accounts as $key => $val): ?>
    <li>
      <input type="checkbox" name="accounts[]" checked="checked" value="<?php echo $key ?>" />
      <?php echo htmlspecialchars($fm_account->getValue('id', $key)) ?>
    </li>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <?php echo _("Check all mail servers you want to fetch mail from:") ?>&nbsp;<br />
<?php endif; ?>
</div>
<div class="nowrap">
<?php if ($accounts): ?>
  <input type="submit" name="fetch" class="button" value="<?php echo _("Fetch Mail") ?>" />
<?php endif; ?>
  <input type="button" name="back" class="button" onclick="<?php echo $cancel_js ?>" value="<?php echo _("Close") ?>" />
</div>
</form>
