<tr>
 <td class="control" colspan="6">

<table width="100%">
 <tr>
  <td class="leftAlign">
<?php if ($trashMbox): ?>
   <?php echo Horde::widget('#', _("Delete"), 'widget', '', "if (confirm('" . addslashes(_("Are you sure you wish to permanently delete these messages?")) . "')) { Submit('delete_messages'); } return false;", _("_Delete")); ?>
<?php else: ?>
   <?php echo Horde::widget('#', _("Delete"), 'widget', '', "Submit('delete_messages'); return false;", _("_Delete")); ?>
<?php endif; ?>
<?php if ($showdelete['purge'] || !is_null($vtrash)): ?>
   | <?php echo Horde::widget('#', _("Undelete"), 'widget', '', "Submit('undelete_messages'); return false;", _("_Undelete")); ?>
<?php endif; ?>
<?php if ($show_blacklist_link): ?>
   | <?php echo Horde::widget('#', _("Blacklist"), 'widget', '', "Submit('blacklist'); return false;", _("_Blacklist")); ?>
<?php endif; ?>
<?php if ($show_whitelist_link): ?>
   | <?php echo Horde::widget('#', _("Whitelist"), 'widget', '', "Submit('whitelist'); return false;", _("_Whitelist")); ?>
<?php endif; ?>
   | <?php echo Horde::widget('#', _("Forward"), 'widget', '', "Submit('fwd_digest'); return false;", _("Fo_rward")) ?>
<?php if ($conf['spam']['reporting'] && ($conf['spam']['spamfolder'] || $imp['mailbox'] != IMP::folderPref($prefs->getValue('spam_folder'), true))): ?>
   | <?php echo Horde::widget('#', _("Report as Spam"), 'widget', '', "Submit('spam_report'); return false;", _("Report as Spam")); ?>
<?php endif; ?>
<?php if ($conf['notspam']['reporting'] && (!$conf['notspam']['spamfolder'] || $imp['mailbox'] == IMP::folderPref($prefs->getValue('spam_folder'), true))): ?>
   | <?php echo Horde::widget('#', _("Report as Innocent"), 'widget', '', "Submit('notspam_report'); return false;", _("Report as Innocent")); ?>
<?php endif; ?>
   | <?php echo Horde::widget('#', _("View Messages"), 'widget', '', "Submit('view_messages'); return false;", _("View Messages")); ?>
  </td>
  <td class="rightAlign">
<?php if ($showdelete['purge']): ?>
<?php $mailbox_link = Util::addParameter($mailbox_url, 'page', $pageOb->page); ?>
<?php if (isset($deleted_prompt)): ?>
   <?php echo Horde::widget(Util::addParameter($mailbox_link, 'actionID', 'hide_deleted'), $deleted_prompt, 'widget', '', '', $deleted_prompt); ?> |
<?php endif; ?>
   <?php echo Horde::widget(Util::addParameter($mailbox_link, 'actionID', 'expunge_mailbox'), _("Purge Deleted"), 'widget', '', '', _("Pur_ge Deleted")); ?>
<?php endif; ?>
<?php if ($trashMbox): ?>
   <?php echo Horde::widget(Util::addParameter($mailbox_url, 'actionID', 'empty_mailbox'), _("Empty Trash folder"), 'widget', '', "return confirm('" . addslashes(_("Are you sure you wish to empty this folder?")) . "');", _("Empty Trash folder")); ?>
<?php endif; ?>
  </td>
 </tr>
</table>

 </td>
</tr>
