<tr>
<td colspan="3" class="control leftAlign">
<?php if ($imp_headers->getFlag('deleted')): ?>
<?php echo Horde::widget(Util::addParameter($self_link, 'actionID', 'undelete_message'), _("Undelete"), 'widget', '', '', _("Undelete"), true) ?>
<?php else: ?>
<?php echo Horde::widget(Util::addParameter($self_link, 'actionID', 'delete_message'), _("Delete"), 'widget', '', $delete_warning, _("_Delete"), true) ?>
<?php endif; ?>
 | <?php echo Horde::widget(IMP::composeLink($reply_author, array('actionID' => 'reply', 'index' => $index, 'identity' => $identity, 'array_index' => $array_index)), _("Reply"), 'widget', '', '', _("_Reply"), true); ?>
<?php if (!is_null($reply_list)): ?>
 | <?php echo Horde::widget(IMP::composeLink($reply_list, array('actionID' => 'reply_list', 'index' => $index, 'identity' => $identity, 'array_index' => $array_index)), _("Reply to List"), 'widget', '', '', _("Repl_y to List"), true) ?>
<?php endif; ?>
<?php if ($show_reply_all): ?>
 | <?php echo Horde::widget(IMP::composeLink(array(), array('actionID' => 'reply_all', 'index' => $index, 'identity' => $identity, 'array_index' => $array_index)), _("Reply to All"), 'widget', '', '', _("Reply to _All"), true) ?>
<?php endif; ?>
 | <?php echo Horde::widget(IMP::composeLink(array(), array('actionID' => 'forward', 'index' => $index, 'identity' => $identity, 'array_index' => $array_index)), _("Forward"), 'widget', '', '', _("Fo_rward"), true) ?>
 | <?php echo Horde::widget(IMP::composeLink(array(), array('actionID' => 'redirect_compose', 'index' => $index, 'array_index' => $array_index)), _("Redirect"), 'widget', '', '', _("Redirec_t"), true) ?>
<?php if (isset($imp_search) && !$imp_search->searchMboxID()): ?>
 | <?php echo Horde::widget($thread_link, _("View Thread"), 'widget', '', '', _("_View Thread"), true) ?>
<?php endif; ?>
<?php if ($show_blacklist_link): ?>
 | <?php echo Horde::widget(Util::addParameter($self_link, 'actionID', 'blacklist'), _("Blacklist"), 'widget', '', '', _("_Blacklist"), true) ?>
<?php endif; ?>
<?php if ($show_whitelist_link): ?>
 | <?php echo Horde::widget(Util::addParameter($self_link, 'actionID', 'whitelist'), _("Whitelist"), 'widget', '', '', _("_Whitelist"), true) ?>
<?php endif; ?>
<?php if (isset($source_link)): ?>
 | <?php echo $source_link . ' ' ?>
<?php endif; ?>
<?php if (!empty($conf['user']['allow_resume_all']) || (!empty($conf['user']['allow_resume_all_in_drafts']) && $mailbox_name == IMP::folderPref($prefs->getValue('drafts_folder'), true)) || ($imp_headers->getFlag('draft'))): ?>
 | <?php echo Horde::widget(IMP::composeLink(array(), array('actionID' => 'draft', 'mailbox' => $mailbox_name, 'index' => $index, 'array_index' => $array_index)), _("Resume"), 'widget', '', '', _("Resume"), true) ?>
<?php endif; ?>
 | <?php echo Horde::widget($save_link, _("Save as"), 'widget', '', '', _("Sa_ve as"), 2) ?>
 | <?php echo Horde::widget($print_link, _("Print"), 'widget', '_blank', 'popup(this.href); return false;', _("_Print"), true) ?>
<?php if ($conf['spam']['reporting'] && ($conf['spam']['spamfolder'] || $mailbox_name != IMP::folderPref($prefs->getValue('spam_folder'), true))): ?>
 | <?php echo Horde::widget('#', _("Report as Spam"), 'widget', '', "Submit('spam_report'); return false;", _("Report as Spam"), true) ?>
<?php endif; ?>
<?php if ($conf['notspam']['reporting'] && (!$conf['notspam']['spamfolder'] || $mailbox_name == IMP::folderPref($prefs->getValue('spam_folder'), true))): ?>
 | <?php echo Horde::widget('#', _("Report as Innocent"), 'widget', '', "Submit('notspam_report'); return false;", _("Report as Innocent"), true) ?>
<?php endif; ?>
</td>
</tr>
