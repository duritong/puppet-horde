<?php
foreach ($map as $name => $v) {
    if (substr($name, 0, 2) != '__') {
?>
 <tr>
  <td width="1%" nowrap="nowrap" class="light rightAlign" ><strong><?php echo $GLOBALS['attributes'][$name]['label'] ?></strong></td>
  <td class="leftAlign"><input type="text" size="30" name="<?php echo $name ?>" value="<?php echo isset($criteria[$name]) ? htmlspecialchars($criteria[$name]) : '' ?>" /></td>
 </tr>
<?php
    }
}
?>
</table>
</form>
<br />
