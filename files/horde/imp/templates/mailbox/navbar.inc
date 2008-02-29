<tr class="control">
 <td colspan="6">

<table width="100%">
 <tr>
  <td class="leftAlign" nowrap="nowrap" width="20%">
   <form name="select<?php echo $navform ?>" action="javascript:void(0)" onsubmit="return false">
   <select name="filter" onchange="makeSelection(<?php echo $navform ?>);">
    <option value="" selected="selected"><?php echo _("Select") ?>:</option>
    <option value="!<?php echo IMP_ALL ?>"><?php echo _("All") ?></option>
    <option value="<?php echo IMP_ALL ?>"><?php echo _("None") ?></option>
    <option value="+<?php echo IMP_ALL ?>"><?php echo _("Invert") ?></option>
<?php if (!strstr($imp['protocol'], 'pop3')): ?>
    <option value="!<?php echo IMP_UNSEEN ?>"><?php echo _("Seen") ?></option>
    <option value="<?php echo IMP_UNSEEN ?>"><?php echo _("Unseen") ?></option>
    <option value="<?php echo IMP_FLAGGED ?>"><?php echo _("Important") ?></option>
    <option value="!<?php echo IMP_FLAGGED ?>"><?php echo _("Not Important") ?></option>
    <option value="<?php echo IMP_ANSWERED ?>"><?php echo _("Answered") ?></option>
    <option value="!<?php echo IMP_ANSWERED ?>"><?php echo _("Unanswered") ?></option>
    <option value="<?php echo IMP_DELETED ?>"><?php echo _("Deleted") ?></option>
    <option value="!<?php echo IMP_DELETED ?>"><?php echo _("Not Deleted") ?></option>
    <option value="<?php echo IMP_DRAFT ?>"><?php echo _("Draft") ?></option>
    <option value="!<?php echo IMP_DRAFT ?>"><?php echo _("Not Draft") ?></option>
    <option value="<?php echo IMP_PERSONAL ?>"><?php echo _("Personal") ?></option>
    <option value="!<?php echo IMP_PERSONAL ?>"><?php echo _("Not Personal") ?></option>
<?php endif; ?>
   </select>
<?php if ($imp['base_protocol'] != 'pop3'): ?>
   <select name="flag" onchange="flagMessages(<?php echo $navform ?>);">
    <option value="" selected="selected"><?php echo _("Mark as:") ?></option>
    <option value="Seen"><?php echo _("Seen") ?></option>
    <option value="0Seen"><?php echo _("Unseen") ?></option>
    <option value="Flagged"><?php echo _("Important") ?></option>
    <option value="0Flagged"><?php echo _("Not Important") ?></option>
    <option value="Answered"><?php echo _("Answered") ?></option>
    <option value="0Answered"><?php echo _("Unanswered") ?></option>
<?php if (!$use_trash && !$use_vtrash): ?>
    <option value="Deleted"><?php echo _("Deleted") ?></option>
    <option value="0Deleted"><?php echo _("Not Deleted") ?></option>
<?php endif; ?>
    <option value="Draft"><?php echo _("Draft") ?></option>
    <option value="0Draft"><?php echo _("Not Draft") ?></option>
   </select>
<?php endif; ?>
   </form>
  </td>
  <td class="leftAlign" nowrap="nowrap" width="30%">
<?php if ($conf['user']['allow_folders']): ?>
   <form method="post" name="copymove<?php echo $navform ?>" action="<?php echo Horde::applicationUrl('mailbox.php') ?>">
   <?php echo Horde::widget('#', _("Move to folder"), 'widget', '', "Transfer('move_messages', $navform); return false;", _("Move"), true) ?> |
   <?php echo Horde::widget('#', _("Copy to folder"), 'widget', '', "Transfer('copy_messages', $navform); return false;", _("Copy"), true) ?>
   <select name="targetMailbox" onchange="updateFolders(<?php echo $navform ?>);">
    <?php echo $folder_options ?>
   </select>
   </form>
<?php endif; ?>
  </td>

  <td width="50%" class="rightAlign" nowrap="nowrap">
<?php if ($pageOb->pagecount > 1): ?>
   <form method="get" name="pagenav<?php echo $navform ?>" action="<?php echo Horde::applicationUrl('mailbox.php') ?>">
   <?php Util::pformInput() ?>
   <?php echo $pages_first . '&nbsp;' . $pages_prev ?>
   <input type="text" name="page" value="<?php echo htmlspecialchars($pageOb->page) ?>" size="<?php echo String::length($pageOb->pagecount) ?>" />
   <?php echo $pages_next . '&nbsp;' . $pages_last ?>
   </form>
<?php endif; ?>
  </td>
 </tr>
</table>

 </td>
</tr>
