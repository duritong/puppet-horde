<form method="get" name="messages" action="<?php echo Horde::applicationUrl('message.php') ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="targetMbox" value="" />
<input type="hidden" name="actionID" value="" />
<input type="hidden" name="thismailbox" value="<?php echo htmlspecialchars($mailbox_name) ?>" />
<input type="hidden" name="start" value="<?php echo htmlspecialchars($msgindex) ?>" />
<input type="hidden" name="index" value="<?php echo htmlspecialchars($index) ?>" />
<input type="hidden" name="newMbox" value="0" />

<table border="0" cellpadding="0" cellspacing="0" align="center" width="100%">
 <tr>
  <td class="header leftAlign">
<?php
    /* If this is a search mailbox, display a link to the parent mailbox of the
     * message in the header. */
    $header_label = htmlspecialchars($page_label);
    if (isset($imp_search) && $imp_search->isVINBOXFolder()) {
        $header_label .= ' [' . Horde::link(Util::addParameter(Horde::applicationUrl('mailbox.php'), 'mailbox', $mailbox_name)) . IMP::displayFolder($mailbox_name) . '</a>]';
    }
    printf(_("%s: %s (%d&nbsp;of&nbsp;%d)"), $header_label, $shortsub, $msgindex, $msgcount); echo '&nbsp;&nbsp;' . $status
?>
  </td>
 </tr>
</table>
<table border="0" align="center" width="100%" cellspacing="0" cellpadding="2">
