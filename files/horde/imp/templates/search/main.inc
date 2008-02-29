   </table>
  </td>
 </tr>

 <tr>
  <td class="smallheader leftAlign" colspan="2"><?php echo _("Message flags") ?></td>
 </tr>

 <tr>
  <td class="item" colspan="2">
   <table cellspacing="0" cellpadding="0" width="100%">
    <tr>
     <td class="item1 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_seen_0" name="search_flag[<?php echo IMP_SEARCH_FLAG_SEEN ?>]" value="0" <?php if (!empty($search['flag']) && $search['flag'][IMP_SEARCH_FLAG_SEEN] == 0) echo 'checked="checked" '; ?>/>
      <em><?php echo Horde::label('flag_seen_0', _("Old messages")) ?></em>
     </td>
     <td class="item1 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_seen_1" name="search_flag[<?php echo IMP_SEARCH_FLAG_SEEN ?>]" value="1" <?php if (!empty($search['flag']) && $search['flag'][IMP_SEARCH_FLAG_SEEN] == 1) echo 'checked="checked" '; ?>/>
      <em><?php echo Horde::label('flag_seen_1', _("New messages")) ?></em>
     </td>
     <td class="item1 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_seen_2" name="search_flag[<?php echo IMP_SEARCH_FLAG_SEEN ?>]" value="2" <?php if (empty($search['flag']) || $search['flag'][IMP_SEARCH_FLAG_SEEN] == 2) echo 'checked="checked" '; ?> />
      <em><?php echo _("Both") ?></em>
     </td>
     <td class="item1" width="100%">&nbsp;</td>
    </tr>

    <tr>
     <td class="item0 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_answered_0" name="search_flag[<?php echo IMP_SEARCH_FLAG_ANSWERED ?>]" value="0" <?php if (!empty($search['flag']) && $search['flag'][IMP_SEARCH_FLAG_ANSWERED] == 0) echo 'checked="checked" '; ?>/>
      <em><?php echo Horde::label('flag_answered_0', _("Answered messages")) ?></em>
     </td>
     <td class="item0 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_answered_1" name="search_flag[<?php echo IMP_SEARCH_FLAG_ANSWERED ?>]" value="1" <?php if (!empty($search['flag']) && $search['flag'][IMP_SEARCH_FLAG_ANSWERED] == 1) echo 'checked="checked" '; ?>/>
      <em><?php echo Horde::label('flag_answered_1', _("Unanswered messages")) ?></em>
     </td>
     <td class="item0 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_answered_2" name="search_flag[<?php echo IMP_SEARCH_FLAG_ANSWERED ?>]" value="2" <?php if (empty($search['flag']) || $search['flag'][IMP_SEARCH_FLAG_ANSWERED] == 2) echo 'checked="checked" '; ?>/>
      <em><?php echo _("Both") ?></em>
     </td>
     <td class="item0" width="100%">&nbsp;</td>
    </tr>

    <tr>
     <td class="item1 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_flagged_0" name="search_flag[<?php echo IMP_SEARCH_FLAG_FLAGGED ?>]" value="0" <?php if (!empty($search['flag']) && $search['flag'][IMP_SEARCH_FLAG_FLAGGED] == 0) echo 'checked="checked" '; ?>/>
      <em><?php echo Horde::label('flag_flagged_0', _("Important messages")) ?></em>
     </td>
     <td class="item1 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_flagged_1" name="search_flag[<?php echo IMP_SEARCH_FLAG_FLAGGED ?>]" value="1" <?php if (!empty($search['flag']) && $search['flag'][IMP_SEARCH_FLAG_FLAGGED] == 1) echo 'checked="checked" '; ?>/>
            <em><?php echo Horde::label('flag_flagged_1', _("Not important messages")) ?></em>
      </td>
     <td class="item1 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_flagged_2" name="search_flag[<?php echo IMP_SEARCH_FLAG_FLAGGED ?>]" value="2" <?php if (empty($search['flag']) || $search['flag'][IMP_SEARCH_FLAG_FLAGGED] == 2) echo 'checked="checked" '; ?>/>
      <em><?php echo _("Both") ?></em>
     </td>
     <td class="item1" width="100%">&nbsp;</td>
    </tr>

    <tr>
     <td class="item0 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_deleted_0" name="search_flag[<?php echo IMP_SEARCH_FLAG_DELETED ?>]" value="0" <?php if (!empty($search['flag']) && $search['flag'][IMP_SEARCH_FLAG_DELETED] == 0)echo 'checked="checked" '; ?>/>
      <em><?php echo Horde::label('flag_deleted_0', _("Deleted messages")) ?></em>
     </td>
     <td class="item0 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_deleted_1" name="search_flag[<?php echo IMP_SEARCH_FLAG_DELETED ?>]" value="1" <?php if (!empty($search['flag']) && $search['flag'][IMP_SEARCH_FLAG_DELETED] == 1) echo 'checked="checked" '; ?>/>
      <em><?php echo Horde::label('flag_deleted_1', _("Not deleted messages")) ?></em>
     </td>
     <td class="item0 leftAlign" nowrap="nowrap">
      <input type="radio" id="flag_deleted_2" name="search_flag[<?php echo IMP_SEARCH_FLAG_DELETED ?>]" value="2" <?php if (empty($search['flag']) || $search['flag'][IMP_SEARCH_FLAG_DELETED] == 2) echo 'checked="checked" '; ?>/>
      <em><?php echo _("Both") ?></em>
     </td>
     <td class="item0" width="100%">&nbsp;</td>
    </tr>
   </table>
  </td>
 </tr>

<?php if (!empty($search['mbox'])): ?>
 <input id="preselected_folders" type="hidden" name="search_folders[]" value="<?php echo htmlspecialchars($search['mbox']) ?>" />
<?php else: ?>
 <tr>
  <td class="smallheader leftAlign" colspan="2"><?php echo _("Message folders") ?></td>
 </tr>

 <tr>
  <td class="item" colspan="2">
   <a href="#" onclick="toggleAll(true); return false;" onmouseover="status='<?php echo _("Select all") ?>'; return true;"><?php echo _("Select all") ?></a> |
   <a href="#" onclick="toggleAll(false); return false;" onmouseover="status='<?php echo _("Select none") ?>'; return true;"><?php echo _("Select none") ?></a>
<?php if ($subscribe): ?>
<?php $inverse_subscribe = ($shown == IMP_SEARCH_SHOW_UNSUBSCRIBED) ? IMP_SEARCH_SHOW_SUBSCRIBED_ONLY : IMP_SEARCH_SHOW_UNSUBSCRIBED; ?>
 | <a href="#" onclick="document.search.show_subscribed_only.value='<?php echo $inverse_subscribe ?>';document.search.submit(); return false;" onmouseover="status='<?php echo ($inverse_subscribe) ? _("Show Only Subscribed Folders") : _("Show All Folders") ?>';return true;"><?php echo ($inverse_subscribe) ? _("Show Only Subscribed Folders") : _("Show All Folders") ?></a>
<?php endif; ?>
  </td>
 </tr>

 <tr>
  <td class="control" valign="top" align="center" colspan="2">
   <table cellspacing="0" cellpadding="2" width="100%">
    <tr>
     <td class="item leftAlign" valign="top">
      <?php echo $folderlist ?>
     </td>
    </tr>
   </table>
  </td>
 </tr>
<?php endif; ?>

<?php if ($_SESSION['imp']['base_protocol'] != 'pop3'): ?>
 <tr>
  <td class="smallheader leftAlign" colspan="2"><?php echo _("Virtual Folders") ?></td>
 </tr>

 <tr>
  <td class="item" colspan="2">
   <table cellspacing="0" cellpadding="0" width="100%">
<?php if ($edit_query_vfolder): ?>
    <input type="hidden" name="save_vfolder" value="1" />
<?php else: ?>
    <tr>
     <td class="item0 leftAlign" nowrap="nowrap">
      <em><?php echo _("Save search as a virtual folder?") ?></em>&nbsp;<input type="checkbox" id="save_vfolder" name="save_vfolder" <?php if (!empty($search['save_vfolder'])) echo 'checked="checked" '; ?>/>
     </td>
    </tr>
<?php endif; ?>
    <tr>
     <td class="item1 leftAlign" nowrap="nowrap">
      <em><?php echo _("Virtual folder label") ?>:</em>&nbsp;<input type="text" id="vfolder_label" name="vfolder_label" <?php if (!empty($search['vfolder_label'])) echo 'value="' . htmlspecialchars($search['vfolder_label']) . '" '; ?>/>
     </td>
    </tr>
   </table>
  </td>
 </tr>
<?php endif; ?>

 <tr>
  <td valign="top" colspan="2">
   <input type="submit" class="button" value="<?php echo $submit_label ?>" onclick="return formCheck();" />
   <input type="button" class="button" value="<?php echo _("Reset") ?>" onclick="document.search.actionID.value = 'reset_search';document.search.submit();return true;" />
  </td>
 </tr>
</table>
</div>
</form>
