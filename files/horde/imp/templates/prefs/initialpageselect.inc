<?php if (!$prefs->isLocked('initial_page')): ?>
<?php
if (empty($GLOBALS['conf']['user']['allow_folders'])) {
    $mailbox_option = '<option value="INBOX" selected="selected">' . _("Inbox") . '</option>';
} else {
    $mailbox_selected = $prefs->getValue('initial_page');
    $mailbox_option = '<option value="folders.php"';
    if ($mailbox_selected == 'folders.php') {
        $mailbox_option .= ' selected="selected"';
    }
    $mailbox_option .= '>' . _("Folder Navigator") . "</option>\n";
    $mailbox_option .= IMP::flistSelect('', true, array(), $mailbox_selected, false, false, true);
}
?>

<table>
 <tr>
  <td>
   <?php echo _("View or mailbox to display after login:") ?><br />
   <select name="initial_page">
    <?php echo $mailbox_option ?>
   </select>
  </td>
 </tr>
</table>
<?php endif; ?>
