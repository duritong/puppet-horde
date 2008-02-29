<div class="control">
 <?php if ($GLOBALS['browser']->hasFeature('javascript')): ?>&nbsp;<input type="checkbox" class="checkbox" name="checkAll" onclick="toggleSelection()" <?php echo Horde::getAccessKeyAndTitle(_("Check _All/None")) ?> /><?php endif; ?>
 <select name="action_choose<?php echo ($i > 0) ? 1 : 0 ?>" onchange="chooseAction(<?php echo ($i > 0) ? 1 : 0 ?>); document.fmanager.action_choose<?php echo ($i > 0) ? 1 : 0 ?>.selectedIndex = 0;">
   <option selected="selected"><?php echo _("Choose Action:") ?></option>
<?php if (!empty($GLOBALS['conf']['hooks']['permsdenied']) || (IMP::hasPermission('create_folders') && IMP::hasPermission('max_folders'))): ?>
   <option value="create_folder"><?php echo _("Create Folder") ?></option>
<?php endif; ?>
   <option value="rename_folder"><?php echo _("Rename Folder(s)") ?></option>
   <option value="delete_folder_confirm"><?php echo _("Delete Folder(s)") ?></option>
   <?php if ($prefs->getValue('subscribe')): ?>
   <option value="subscribe_folder"><?php echo _("Subscribe to Folder(s)") ?></option>
   <option value="unsubscribe_folder"><?php echo _("Unsubscribe from Folder(s)") ?></option>
   <?php endif; ?>
   <?php if (!$prefs->isLocked('nav_poll')): ?>
   <option value="poll_folder"><?php echo _("Check Folder(s) for New Mail") ?></option>
   <option value="nopoll_folder"><?php echo _("Do Not Check Folder(s) for New Mail") ?></option>
   <?php endif; ?>
   <option value="mark_folder_seen"><?php echo _("Mark All Messages in Folder as Seen") ?></option>
   <option value="mark_folder_unseen"><?php echo _("Mark All Messages in Folder as Unseen") ?></option>
   <option value="download_folder"><?php echo _("Download Folder(s)") ?></option>
   <option value="download_folder_zip"><?php echo _("Download Folder(s) [.zip format]") ?></option>
   <option value="folders_empty_mailbox_confirm"><?php echo _("Empty Folder(s)") ?></option>
   <?php if (!$prefs->getValue('use_trash')): ?>
   <option value="expunge_folder"><?php echo _("Purge Folder(s)") ?></option>
   <?php endif; ?>
   <?php if ($imp['file_upload']): ?>
   <option value="import_mbox"><?php echo _("Import Messages") ?></option>
   <?php endif; ?>
   <option value="rebuild_tree"><?php echo _("Rebuild Folder Tree") ?></option>
 </select>
 <?php if (!$GLOBALS['browser']->hasFeature('javascript')): ?>
 <input name="btn_action" type="submit" class="button" value="<?php echo _("Go"); ?>" />
 <?php endif; ?>
 <?php echo Help::link('imp', 'folder-options') ?>
 &nbsp;
 <?php if ($prefs->getValue('subscribe')): ?>
 <?php echo Horde::widget(Util::addParameter($folders_url, 'actionID', 'toggle_subscribed_view'), $subToggleText, 'widget', '', '', $subToggleText, true); ?>&nbsp;|
 <?php endif; ?>
 <?php echo Horde::widget(Util::addParameter($folders_url, 'actionID', 'expand_all_folders'), _("Expand All Folders"), 'widget', '', '', _("Expand All"), true); ?>&nbsp;|
 <?php echo Horde::widget(Util::addParameter($folders_url, 'actionID', 'collapse_all_folders'), _("Collapse All Folders"), 'widget', '', '', _("Collapse All"), true); ?>&nbsp;
 <?php echo Help::link('imp', 'folder-widgets') ?>
</div>
