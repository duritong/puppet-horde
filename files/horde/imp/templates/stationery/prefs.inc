<form method="post" name="prefs" action="<?php echo Horde::selfUrl() ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="update" />
<input type="hidden" name="group" value="<?php echo $group ?>" />
<input type="hidden" name="app" value="<?php echo $app ?>" />

<div class="header">
  <?php Prefs_UI::generateNavigationCell('compose') ?>
  <?php echo _("Stationery") ?>
</div>

<p class="item">
<select name="stationery" onchange="document.prefs.edit.click()">
  <option value=""><?php echo _("Create new stationery") ?></option>
<?php foreach ($stationery_list as $stationery_id => $stationery_choice): ?>
  <option value="<?php echo $stationery_id ?>"<?php if ($selected === $stationery_id) echo ' selected="selected"' ?>><?php echo $stationery_choice['n'] . ' ' . ($stationery_choice['t'] == 'html' ? _("(HTML)") : _("(Plain Text)")) ?></option>
<?php endforeach; ?>
</select>
<input type="submit" name="edit" class="button" value="<?php echo _("Edit") ?>" />
<br /><br />

<?php if (!is_null($stationery)): if (!is_null($id)): ?>
<input type="hidden" name="id" value="<?php echo $id ?>" />
<?php endif; ?>
<input type="hidden" name="last_type" value="<?php echo $stationery['t'] ?>" />
<?php echo Horde::label('name', _("Stationery name:")) ?><br />
<input type="text" name="name" id="name" value="<?php echo $stationery['n'] ?>" /><br />
<?php echo Horde::label('type', _("Stationery type:")) ?><br />
<select name="type" id="type" onchange="document.prefs.submit()">
  <option value="plain"<?php if ($stationery['t'] == 'plain') echo ' selected="selected"' ?>><?php echo _("Text") ?></option>
  <option value="html"<?php if ($stationery['t'] == 'html') echo ' selected="selected"' ?>><?php echo _("HTML") ?></option>
</select><br />
<?php echo Horde::label('content', _("Stationery:")) ?><br />
<textarea name="content" id="content" rows="15" cols="80" class="fixed"><?php echo $stationery['c'] ?></textarea><br />
<em><?php echo _("You can use the following placeholders in the stationery text: %c for the current message content; %s for the signature.") ?></em>
<?php endif; ?>
</p>

<?php if (!is_null($stationery)): ?>
<input type="submit" name="save" class="button" value="<?php echo _("Save Stationery") ?>" />&nbsp;&nbsp;
<?php if (!is_null($id)): ?>
<input type="submit" name="delete" class="button" value="<?php echo _("Delete Stationery") ?>" />&nbsp;&nbsp;
<?php endif; endif; ?>
<input type="button" name="back" class="button" onclick="document.location.href='<?php echo Util::addParameter($compose_url, 'group', 'compose') ?>'" value="<?php echo htmlspecialchars(_("Return to \"Message Composition\""), ENT_COMPAT, NLS::getCharset()) ?>" />

</form>
