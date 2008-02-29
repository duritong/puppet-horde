<br />
<table align="center" width="300">
<tr>
  <td colspan="2" class="header"><span class="smallheader">
    <?php echo _("Here is the message you were composing:") ?>
  </td>
</tr>

<tr>
  <td width="5%" class="light rightAlign" valign="top"><strong><?php echo _("To") ?></strong>&nbsp;</td>
  <td width="95%" class="text"><?php echo htmlspecialchars(_getFormData('to')) ?></td>
</tr>

<tr>
  <td class="light rightAlign" valign="top"><strong><?php echo _("Cc") ?></strong>&nbsp;</td>
  <td class="text"><?php echo htmlspecialchars(_getFormData('cc')) ?></td>
</tr>

<tr>
  <td class="light rightAlign" valign="top"><strong><?php echo _("Bcc") ?></strong>&nbsp;</td>
  <td class="text"><?php echo htmlspecialchars(_getFormData('bcc')) ?></td>
</tr>

<tr>
  <td class="light rightAlign" valign="top"><strong><?php echo _("Subject") ?></strong>&nbsp;</td>
  <td class="text"><?php echo htmlspecialchars(_getFormData('subject')) ?></td>
</tr>

<?php if (_getFormData('message')): ?>
<tr>
  <td class="text" colspan="2"><span class="fixed"><?php echo nl2br(htmlspecialchars(_getFormData('message'))) ?></span></td>
</tr>
<?php endif; ?>

</table>
<br />
