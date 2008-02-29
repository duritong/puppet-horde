<script type="text/javascript">
<!--
var editURL = decodeURIComponent('<?php echo rawurlencode(Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/services/shares/edit.php?app=turba', true), 'share', '@ID@', false)) ?>');
var shares = new Array();
<?php foreach ($shares as $key => $value): ?>
shares['<?php echo htmlspecialchars($key) ?>'] = new Array();
shares['<?php echo htmlspecialchars($key) ?>']['name'] = decodeURIComponent("<?php echo rawurlencode(String::convertCharset($shares[$key]->get('name'), NLS::getCharset(), 'UTF-8')) ?>");
shares['<?php echo htmlspecialchars($key) ?>']['description'] = decodeURIComponent("<?php echo rawurlencode(String::convertCharset($shares[$key]->get('description'), NLS::getCharset(), 'UTF-8')) ?>");
<?php endforeach ?>

function newChoice()
{
    var share = document.editshares.share.options[document.editshares.share.selectedIndex].value;
    if (share == -1) {
        document.editshares.editshare.value = '';
        document.editshares.reset();
    } else {
        document.editshares.editshare.value = share;
        document.editshares.sharetitle.value = shares[share]['name'];
        document.editshares.description.value = shares[share]['description'];
    }
}

function checkSubmit()
{
    if (document.editshares.actionID.value == 'add' &&
        (document.editshares.sharename.value == '' ||
         document.editshares.sharename.value == 'none')) {
        return false;
    }

    if (document.editshares.actionID.value == 'update' &&
        document.editshares.editshare.value == '') {
        return false;
    }

    if ((document.editshares.actionID.value == 'delete') &&
        (document.editshares.deleteshare.value == 'none')) {
        return false;
    }
}
// -->
</script>
<form action="addressbooks.php" method="post" name="editshares" onsubmit="return checkSubmit();">
<?php echo Util::formInput() ?>
 <input type="hidden" name="actionID" value="none" />
 <h1 class="header"><?php echo _("Create Address Book") ?></h1>
  <table width = "100%" class="headerbox">
   <tr>
    <td width="15%" class="nowrap" align="right">
     <strong><?php echo _("Choose a name") ?></strong>
    </td>
    <td width="85%">
     <input type="text" style="width:200px" name="sharename" />
    </td>
   </tr>
   <tr>
    <td>&nbsp;</td>
    <td>
     <input type="submit" name="newshare" class="button" onclick="document.editshares.actionID.value='add';" value="<?php echo _("Create") ?>" />
    </td>
   </tr>
  </table>
<?php if (count($myRemovable)): ?>
  <h1 class="header"><?php echo _("Delete Address Book") ?></h1>
  <table width="100%" class="headerbox">
   <tr>
    <td width="15%" class="nowrap" align="right">
     <strong><?php echo _("Address book to delete ") ?></strong>
    </td>
    <td width="85%">
     <select name="deleteshare" style="width:200px">
      <option value="none"><?php echo _("Select an address book to delete") ?></option>
      <?php foreach ($myRemovable as $srcName): ?>
      <option value="<?php echo htmlspecialchars($srcName) ?>"><?php echo htmlspecialchars($mySources[$srcName]) ?></option>
      <?php endforeach; ?>
     </select>
    </td>
   </tr>
   <tr>
    <td>&nbsp;</td>
    <td>
     <input type="submit" name="delete" class="button" onclick="document.editshares.actionID.value='delete';" value="<?php echo _("Delete") ?>" />
    </td>
   </tr>
  </table>
 <?php endif ?>
 <h1 class="header"><?php echo _("Edit Address Books") ?></h1>
 <table class="headerbox" width="100%">
  <tr>
   <td width="15%" class="nowrap" align="right">
    <input type="hidden" name="editshare" value="" />
    <strong><?php echo _("Address Book") ?></strong>
   </td>
   <td width="85%">
    <select name="share" style="width:200px" onchange="newChoice();">
     <option value="none"><?php echo _("Select an address book to edit") ?></option>
     <?php foreach ($mySources as $key => $value): ?>
     <option value="<?php echo htmlspecialchars($key) ?>"><?php echo htmlspecialchars($value) ?></option>
     <?php endforeach; ?>
    </select>
   </td>
  </tr>
  <tr>
   <td class="nowrap" align="right">
    <strong><?php echo _("Permissions") ?></strong>
   </td>
   <td>
    <input type="button" name="perms" class="button" onclick="if (document.editshares.editshare.value) popup(editURL.replace(/%40ID%40/, document.editshares.editshare.value));" value="<?php echo _("Edit") ?>" />
   </td>
  </tr>
  <tr>
   <td class="nowrap" align="right">
    <strong><?php echo _("Name") ?></strong>
   </td>
   <td>
    <input type="text" name="sharetitle" style="width:200px" />
   </td>
  </tr>
  <tr>
   <td class="nowrap" valign="top" align="right">
    <strong><?php echo _("Description") ?></strong>
   </td>
   <td>
    <textarea style="width:200px" name="description" rows="4" cols="60"></textarea>
   </td>
  </tr>
  <tr>
   <td>&nbsp;</td>
   <td>
    <input type="submit" name="save" class="button" onclick="document.editshares.actionID.value='update'" value="<?php echo _("Save") ?>" />
   </td>
  </tr>
 </table>
</form>
