<input type="hidden" name="remote_action" value="add" />

<script type="text/javascript">
<!--
function deleteCal(id)
{
    document.prefs.remote_action.value = 'delete';
    document.prefs.remote_url.value = id;
    document.prefs.submit();
}
-->
</script>

<table cellspacing="2">
<tr>
  <td>&nbsp;</td>
  <td><strong><?php echo _("Name") ?></strong></td>
  <td><strong><?php echo _("Location") ?></strong></td>
</tr>

<?php $cals = unserialize($prefs->getValue('remote_cals')); foreach ($cals as $key => $cal): ?>
<tr>
  <td class="text"><a href="#" onclick="deleteCal(decodeURIComponent('<?php echo rawurlencode($cal['url']) ?>')); return false;"><?php echo Horde::img('delete.png', _("Delete"), '', $registry->getImageDir('horde')) ?></a></td>
  <td class="text"><?php echo htmlspecialchars($cal['name']) ?></td>
  <td class="text"><?php echo htmlspecialchars($cal['url']) ?></td>
</tr>
<?php endforeach; ?>

<tr>
 <td>&nbsp;</td>
</tr>

<tr>
 <td colspan="3">
  <strong><?php echo _("Add a new Remote Calendar") ?></strong>
 </td>
</tr>

<tr>
 <td colspan="3">
  <table cellspacing="1">
   <tr>
    <td align="right">
     <strong><?php echo _("Name") ?></strong>&nbsp;&nbsp;
    </td>
    <td>
     <input type="text" name="remote_name" value="" />
    </td>
   </tr>
   <tr>
    <td align="right">
     <strong><?php echo _("Location") ?></strong>&nbsp;&nbsp;
    </td>
    <td>
     <input size="50" type="text" name="remote_url" value="" />
    </td>
   </tr>
  </table>
 </td>
</tr>

</table>
