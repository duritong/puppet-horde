<?php
$memourl = Util::addParameter('memo.php', array('memo' => $memo_id,
                                                'memolist' => $memolist_id));

$share = $GLOBALS['mnemo_shares']->getShare($memolist_id);
?>
<div class="header">
 <span class="smallheader" style="float:right">
 <?php if (!$print_view) : ?>
 <?php if ($share->hasPermission(Auth::getAuth(), PERMS_EDIT)): ?>
   <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($memourl, 'actionID', 'modify_memo')), _("Edit"), 'smallheader', '', '', _("_Edit")) ?>
   |
 <?php endif; ?>
 <?php if ($share->hasPermission(Auth::getAuth(), PERMS_DELETE)): ?>
   <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($memourl, 'actionID', 'delete_memos')), _("Delete"), 'smallheader', '', $prefs->getValue('delete_opt') ? 'return window.confirm(\'' . addslashes(_("Really delete this note?")) . '\');' : '', _("_Delete")) ?>
   |
 <?php endif; ?>
 <?php echo Horde::widget(Horde::applicationUrl('list.php'), _("Back to Notepad"), 'smallheader', '', '', _("_Back to Notepad")) ?>&nbsp;&nbsp;
 <?php endif; ?>
 </span>
 <?php echo _("Note Details") ?>
</div>

<table cellspacing="0" width="100%" class="headerbox striped nowrap">
<tr>
  <td align="right"><strong><?php echo _("Category") ?></strong>&nbsp;</td>
  <td width="100%"><?php echo htmlspecialchars($memo['category'] ? $memo['category'] : _("Unfiled")) ?></td>
</tr>

<?php if (isset($created)): ?>
<tr>
  <td align="right"><strong><?php echo _("Created") ?></strong>&nbsp;</td>
  <td><?php echo strftime($prefs->getValue('date_format'), $created) . ' ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:i a', $created) ?></td>
</tr>
<?php endif; ?>

<?php if (isset($modified)): ?>
<tr>
  <td align="right"><strong><?php echo _("Last Modified") ?></strong>&nbsp;</td>
  <td><?php echo strftime($prefs->getValue('date_format'), $modified) . ' ' . date($prefs->getValue('twentyFour') ? 'G:i' : 'g:i a', $modified) ?></td>
</tr>
<?php endif; ?>

<tr>
  <td valign="top" align="right"><strong><?php echo _("Note Text") ?></strong>&nbsp;</td>
  <td class="fixed" style="white-space:normal"><?php echo Text_Filter::filter($memo['body'], 'text2html', array('parselevel' => TEXT_HTML_MICRO)) ?></td>
</tr>
</table>
