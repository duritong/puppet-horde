<script type="text/javascript">
function updateCharCount()
{
<?php if (($GLOBALS['browser']->isBrowser('msie') &&
           $GLOBALS['browser']->getMajor() >= 4) ||
          ($GLOBALS['browser']->isBrowser('mozilla') &&
           $GLOBALS['browser']->getMajor() >= 5)): ?>
    document.getElementById('charcount').innerHTML = document.getElementById('memo_body').value.replace(/[\r\n]/g, '').length;
<?php endif; ?>
}
</script>
<?php echo $cManager->getJavaScript('memo', 'memo_category') ?>
<form method="post" name="memo" action="memo.php">
<?php Util::pFormInput() ?>
<input type="hidden" name="actionID" value="save_memo" />
<input type="hidden" name="memo" value="<?php echo htmlspecialchars($memo_id) ?>" />
<input type="hidden" name="new_category" value="" />
<input type="hidden" name="memolist_original" value="<?php echo htmlspecialchars($memolist_id) ?>" />
<?php if ($prefs->isLocked('default_notepad') || count($notepads) <= 1): ?>
<input type="hidden" name="notepad_target" value="<?php echo htmlspecialchars($memolist_id) ?>" />
<?php endif; ?>
<h1 class="header"><?php echo htmlspecialchars($title) ?></h1>

<table cellspacing="0" width="100%">
<tr>
    <td class="item" nowrap="nowrap" align="right" valign="top"><strong><?php echo Horde::label('memo_body', _("Note _Text")) ?>&nbsp;(<?php printf(_("%s characters"), '<span id="charcount">' . String::length(str_replace(array("\r", "\n"), '', $memo_body)) . '</span>') ?>):</strong>&nbsp;</td>
    <td class="item">
        <textarea name="memo_body" id="memo_body" class="fixed" cols="80" rows="20" onchange="updateCharCount()" onclick="updateCharCount()" onkeypress="setTimeout('updateCharCount()', 1)"><?php echo htmlspecialchars($memo_body) ?></textarea>
    </td>
    <td class="item" width="100%">&nbsp;</td>
</tr>
<?php if (!$prefs->isLocked('default_notepad') && count($notepads) > 1): ?>
<tr>
    <td class="item nowrap" align="right" valign="top"><strong><?php echo Horde::label('notepad_target', _("Note_pad")) ?></strong>&nbsp;</td>
    <td class="item">

    <select id="notepad_target" name="notepad_target">
    <?php
    foreach ($notepads as $id => $notepad) {
        $sel = ($id == $memolist_id) ? ' selected="selected"' : '';
        printf('<option value="%s"%s>%s</option>',
               htmlspecialchars($id), $sel, htmlspecialchars($notepad->get('name'))) . "\n";
    } ?>
    </select>

    </td>
    <td class="item">&nbsp;</td>
</tr>
<?php endif; ?>
<tr>
    <td class="item" align="right" valign="top"><strong><?php echo Horde::label('memo_category', _("_Category")) ?></strong>&nbsp;</td>
    <td class="item"><?php echo $cManager->getSelect('memo_category', $memo_category) ?></td>
    <td class="item">&nbsp;</td>
</tr>
<tr>
    <td class="item" align="right"><input type="submit" class="button" value="<?php echo _("Save") ?>" onclick="return checkCategory();" /></td>
    <td class="item" align="right">
        <input type="reset" class="button" value="<?php echo _("Undo changes") ?>" />&nbsp;
<?php
if ($memo_id):
    $memourl = Util::addParameter('memo.php', array('memo' => $memo_id,
                                                    'memolist' => $memolist_id));

    $share = $GLOBALS['mnemo_shares']->getShare($memolist_id);
    if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_DELETE)):
?>
        <input type="button" class="button" value="<?php echo _("Delete this note") ?>" onclick="window.location='<?php echo addslashes(Horde::applicationUrl(Util::addParameter($memourl, 'actionID', 'delete_memos'))) ?>';" />&nbsp;
<?php endif; endif; ?>
    </td>
    <td class="item">&nbsp;</td>
</tr>
</table>
</form>
