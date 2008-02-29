 <tr>
  <td class="control" colspan="6">
   <table width="100%">
    <tr>
     <td class="rightAlign">
      <?php echo Horde::widget(Util::addParameter($refresh_url, 'actionID', 'hide_deleted'), $deleted_prompt, 'widget', '', '', $deleted_prompt); ?> |
      <?php echo Horde::widget(Util::addParameter($refresh_url, 'actionID', 'expunge_mailbox'), _("Purge Deleted"), 'widget', '', '', _("Pur_ge Deleted")); ?>
     </td>
    </tr>
   </table>
  </td>
 </tr>
