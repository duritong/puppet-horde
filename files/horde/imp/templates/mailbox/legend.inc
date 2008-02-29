<tr>
 <td class="control" colspan="6">
  <div class="mailboxLegend">
   <ul>
    <li><div><div class="unseen"><?php echo Horde::img('mail_unseen.png', _("Unseen")) ?></div><?php echo _("Unseen") ?></div></li>
    <li><div><div class="answered"><?php echo Horde::img('mail_answered.png', _("Answered")) ?></div><?php echo _("Answered") ?></div></li>
    <li><div><div class="important"><?php echo Horde::img('mail_flagged.png', _("Important")) ?></div><?php echo _("Important") ?></div></li>
    <li><div><div class="deleted"><?php echo Horde::img('mail_deleted.png', _("Deleted")) ?></div><?php echo _("Deleted") ?></div></li>
    <li><div><div><?php echo Horde::img('mail_draft.png', _("Draft")) ?></div><?php echo _("Draft") ?></div></li>
    <li><div><div><?php echo Horde::img('mail_personal.png', _("Personal")) ?></div><?php echo _("Personal") ?></div></li>
<?php if ($conf['mailbox']['show_xpriority']): ?>
    <li><div><div class="important"><?php echo Horde::img('mail_priority_high.png', _("High Priority")) ?></div><?php echo _("High Priority") ?></div></li>
    <li><div><div><?php echo Horde::img('mail_priority_low.png', _("Low Priority")) ?></div><?php echo _("Low Priority") ?></div></li>
<?php endif; ?>
<?php if ($conf['mailbox']['show_attachments']): ?>
    <li><div><div><?php echo Horde::img('attachment.png', _("Attachment")) ?></div><?php echo _("Attachment") ?></div></li>
    <li><div><div><?php echo Horde::img('signed.png', _("Signed")) ?></div><?php echo _("Signed") ?></div></li>
    <li><div><div><?php echo Horde::img('encrypted.png', _("Encrypted")) ?></div><?php echo _("Encrypted") ?></div></li>
<?php endif; ?>
   </ul>
   <div class="clear">&nbsp;</div>
  </div>
 </td>
</tr>
