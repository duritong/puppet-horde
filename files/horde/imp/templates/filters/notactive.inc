<div class="header">
  <?php Prefs_UI::generateNavigationCell('filters') ?>
  <?php echo _("Filters NOT Active") ?>
</div>
<table border="0" align="center" width="100%" cellspacing="0" cellpadding="3">
  <tr>
    <td class="item">
      <table border="0">
        <tr valign="top">
          <td class="item">
            &nbsp;<strong><?php echo _("Filter support is disabled on this system.") ?></strong>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<br />
<table border="0" align="center" width="100%" cellspacing="0" cellpadding="0">
  <tr>
    <td nowrap="nowrap">
      <input type="button" name="back" class="button" onclick="document.location.href='<?php echo IMP::prefsURL(true) ?>'" value="<?php echo _("Return to Options") ?>" />
    </td>
  </tr>
</table>
