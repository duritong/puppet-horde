<form method="post" name="prefs" action="<?php echo Horde::selfUrl() ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="update_prefs" />
<input type="hidden" name="group" value="<?php echo $group ?>" />
<input type="hidden" name="app" value="<?php echo $app ?>" />

<div class="header">
  <?php Prefs_UI::generateNavigationCell('filters') ?>
  <?php echo _("Filter Settings") ?>
</div>
<p class="item">
<?php if (!empty($filters_link)): ?>
<?php echo Horde::img('filters.png', _("Edit Filters")) . '&nbsp;&nbsp;' . Horde::link(Horde::url($filters_link), _("Edit your filter rules")) . _("Edit your filter rules") ?></a>&nbsp;
<?php echo Help::link('imp', 'filter-edit-rules') ?><br />
<?php endif; ?>

<?php if (!empty($blacklist_link)): ?>
<?php echo Horde::img('filters.png', _("Edit Blacklist")) . '&nbsp;&nbsp;' . Horde::link(Horde::url($blacklist_link), _("Edit your blacklist")) . _("Edit your blacklist") ?></a>&nbsp;
<?php echo Help::link('imp', 'filter-edit-blacklist') ?><br />
<?php endif; ?>

<?php if (!empty($whitelist_link)): ?>
<?php echo Horde::img('filters.png', _("Edit Whitelist")) . '&nbsp;&nbsp;' . Horde::link(Horde::url($whitelist_link), _("Edit your whitelist")) . _("Edit your whitelist") ?></a>&nbsp;
<?php echo Help::link('imp', 'filter-edit-whitelist') ?><br />
<?php endif; ?>
</p>

<?php if (!$login_locked || !$display_locked || !$menuitem_locked): ?>
<br />
<h1 class="header">
  <?php echo _("Filter Options") ?>
</h1>
<p class="item" style="padding:1px;">
<?php if (!$login_locked): ?>
<input type="checkbox" id="filter_login" name="filter_login" <?php echo ($prefs->getValue('filter_on_login')) ? "checked=\"checked\"" : "" ?> />&nbsp;
<?php echo Horde::label('filter_login', _("Apply filter rules upon logging on?")) ?>&nbsp;
<?php echo Help::link('imp', 'filter-on-login') ?><br />
<?php endif; ?>

<?php if (!$display_locked): ?>
<input type="checkbox" id="filter_display" name="filter_display" <?php echo ($prefs->getValue('filter_on_display')) ? "checked=\"checked\"" : "" ?> />&nbsp;
<?php echo Horde::label('filter_display', _("Apply filter rules whenever INBOX is displayed?")) ?>&nbsp;
<?php echo Help::link('imp', 'filter-on-display') ?><br />
<?php endif; ?>

<?php if (!$anymailbox_locked): ?>
<input type="checkbox" id="filter_any_mailbox" name="filter_any_mailbox" <?php echo ($prefs->getValue('filter_any_mailbox')) ? "checked=\"checked\"" : "" ?> />&nbsp;
<?php echo Horde::label('filter_anymailbox', _("Allow filter rules to be applied in any mailbox?")) ?>&nbsp;
<?php echo Help::link('imp', 'filter-any-mailbox') ?><br />
<?php endif; ?>

<?php if (!$menuitem_locked): ?>
<input type="checkbox" id="filter_menuitem" name="filter_menuitem" <?php echo ($prefs->getValue('filter_menuitem')) ? "checked=\"checked\"" : "" ?> />&nbsp;
<?php echo Horde::label('filter_menuitem', _("Show the filter icon on the menubar?")) ?>
<?php endif; ?>
</p>

<input type="submit" name="save" class="button" onclick="document.prefs.actionID.value='update_prefs';return true;" value="<?php echo _("Save Options") ?>" />&nbsp;&nbsp;
<input type="button" name="back" class="button" onclick="document.location.href='<?php echo IMP::prefsURL(true) ?>'" value="<?php echo _("Return to Options") ?>" />
<?php endif; ?>

</form>
