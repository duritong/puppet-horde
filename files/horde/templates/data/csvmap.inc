<script type="text/javascript">
<!--
function resetHidden()
{
    var form = document.mapform;
    var tmp = '';
    for (i = 0; i < form.selectData.length; i++) {
        tmp += form.selectData[i].value;
        if (i < form.selectData.length - 1)
            tmp += "\t";
    }
    form.dataKeys.value = tmp;
    tmp = '';
    for (i = 0; i < form.selectApp.length; i++) {
        tmp += form.selectApp[i].value;
        if (i < form.selectApp.length - 1)
            tmp += "\t";
    }
    form.appKeys.value = tmp;
}

function addPair()
{
    var form = document.mapform;
    if (form.select1.selectedIndex >= 0 && form.select2.selectedIndex >= 0) {
        form.selectData[form.selectData.length] = new Option(form.select1[form.select1.selectedIndex].text, form.select1[form.select1.selectedIndex].value);
        form.selectApp[form.selectApp.length] = new Option(form.select2[form.select2.selectedIndex].text, form.select2[form.select2.selectedIndex].value);
        form.select1[form.select1.selectedIndex] = null;
        form.select2[form.select2.selectedIndex] = null;
    }
    resetHidden();
}

function removePair()
{
    var form = document.mapform;
    if (form.selectData.selectedIndex >= 0 || form.selectApp.selectedIndex >= 0) {
        form.select1[form.select1.length] = new Option(form.selectData[form.selectData.selectedIndex].text, form.selectData[form.selectData.selectedIndex].value);
        form.select2[form.select2.length] = new Option(form.selectApp[form.selectApp.selectedIndex].text, form.selectApp[form.selectApp.selectedIndex].value);
        form.selectData[form.selectData.selectedIndex] = null;
        form.selectApp[form.selectApp.selectedIndex] = null;
    }
    resetHidden();
}
//-->
</script>

<form method="post" name="mapform" action="data.php">
<?php echo Util::formInput() ?>
<input type="hidden" name="actionID" value="<?php echo (int)$next_step ?>" />
<input type="hidden" name="import_format" value="<?php echo htmlspecialchars(Util::getFormData('import_format')) ?>" />
<input type="hidden" name="import_step" value="<?php echo (int)$import_step ?>" />
<h1 class="header">
 <?php printf(_("Import, Step %d"), (int)$import_step) ?>
</h1>
<p class="control">
 <?php echo _("Select two matching fields.") ?>
</p>
<table cellspacing="0" width="100%">
 <tr>
  <td class="item">
  <table><tr><td>
    <table cellspacing="0"><tr>
      <td>
        <?php echo _("Imported fields:") ?><br />
        <select name="select1" size="10">
<?php foreach ($_SESSION['import_data']['data'][0] as $key => $value):
      if (!isset($app_fields[$key])): ?>
          <option value="<?php echo htmlspecialchars($key) ?>"><?php echo ($_SESSION['import_data']['header']) ? htmlspecialchars($key) : htmlspecialchars($value) ?></option>
<?php endif;
      endforeach; ?>
        </select>
      </td>
      <td>
        <?php echo _("Available fields:") ?><br />
        <select name="select2" size="10">
<?php foreach ($app_fields as $key => $value):
      if (!isset($_SESSION['import_data']['data'][0][$key])): ?>
      <?php if (($key == 'alarm_time') || ($key == 'alarm_date')): ?>
            <?php if (!isset($_SESSION['import_data']['data'][0]['alarm'])): ?>
            <option value="<?php echo htmlspecialchars($key) ?>"><?php echo htmlspecialchars($value) ?></option>
            <?php endif; ?>
      <?php elseif ($key == 'alarm'): ?>
            <?php if (!(isset($_SESSION['import_data']['data'][0]['alarm_time']) && isset($_SESSION['import_data']['data'][0]['alarm_date']))): ?>
            <option value="<?php echo htmlspecialchars($key) ?>"><?php echo htmlspecialchars($value) ?></option>
            <?php endif; ?>
      <?php else: ?>
      <option value="<?php echo htmlspecialchars($key) ?>"><?php echo htmlspecialchars($value) ?></option>
      <?php endif; ?>
<?php endif;
      endforeach; ?>
        </select>
      </td>
    </tr></table>
    <input type="button" value="<?php echo _("Add pair") ?>" class="button" onclick="addPair()" />
  </td></tr></table>
  </td
</tr>
<tr>
  <td>
    <table cellspacing="0" width="100%">
    <tr><td class="item">&nbsp;</td></tr>
    <tr><td class="smallheader"><?php echo _("Matching fields") ?></td></tr>
    </table>
  </td>
</tr>
<tr>
  <td class="item">
  <table><tr><td>
    <input type="hidden" name="dataKeys" />
    <input type="hidden" name="appKeys" />
    <input type="hidden" name="import_format" value="csv" />
    <select name="selectData" size="10" onchange="document.mapform.selectApp[this.selectedIndex].selected=true">
<?php
$matching = array();
foreach ($_SESSION['import_data']['data'][0] as $key => $value):
    if (isset($app_fields[$key])):
        $matching[] = array($key, $app_fields[$key]);
?>
          <option value="<?php echo htmlspecialchars($key) ?>"><?php echo ($_SESSION['import_data']['header']) ? htmlspecialchars($key) : htmlspecialchars($value) ?></option>
<?php endif; ?>
<?php endforeach; ?>
    </select>
    <select name="selectApp" size="10" onchange="document.mapform.selectData[this.selectedIndex].selected=true">
<?php foreach ($matching as $pair): ?>
          <option value="<?php echo htmlspecialchars($pair[0]) ?>"><?php echo htmlspecialchars($pair[1]) ?></option>
<?php endforeach; ?>
    </select><br />
    <input type="button" value="<?php echo _("Remove pair") ?>" class="button" onclick="removePair()" /><br /><br />
    <input type="submit" value="<?php echo _("Next") ?>" class="button" />
  </td></tr></table>
  </td>
</tr>
</table>
</form>
<script type="text/javascript">
<!--
resetHidden();
//-->
</script>
