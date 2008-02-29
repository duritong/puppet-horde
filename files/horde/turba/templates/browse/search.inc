<?php
/* Build the directory sources select widget. */
$source_options = '';
$criteria_options = '';
$js_criteria = "var criteriaOptions = new Array();\n";
$source_count = 0;
foreach (Turba::getAddressBooks() as $key => $entry) {
    $js_criteria .= "criteriaOptions[$source_count] = new Array()\n" .
        "criteriaOptions[$source_count][0] = '$key';\n";

    /* Build the criteria select widget. */
    $field_count = 1;
    foreach ($entry['search'] as $field) {
        $js_criteria .= "criteriaOptions[$source_count][$field_count] = new Array('$field', '" . $GLOBALS['attributes'][$field]['label'] . "');\n";
        if ($key == $default_source) {
            $selected = ($criteria == $field) ? ' selected="selected"' : '';
            $criteria_options .= "<option value=\"$field\"$selected>" .
                htmlspecialchars($GLOBALS['attributes'][$field]['label']) . "</option>\n";
        }
        $field_count++;
    }

    $selected = ($key == $default_source) ? ' selected="selected"' : '';
    $source_options .= "<option value=\"$key\"$selected>" .
        htmlspecialchars($entry['title']) . "</option>\n";

    $unique_source = $key;
    $source_count++;
}

/* Build search mode tabs. */
require_once 'Horde/UI/Tabs.php';
require_once 'Horde/Variables.php';
$sUrl = Horde::applicationUrl('search.php');
$vars = &Variables::getDefaultVariables();
$tabs = &new Horde_UI_Tabs('search_mode', $vars);
$tabs->addTab(_("Basic Search"), $sUrl, 'basic');
$tabs->addTab(_("Advanced Search"), $sUrl, 'advanced');
echo $tabs->render($_SESSION['turba']['search_mode']);

if ($_SESSION['turba']['search_mode'] == 'basic'): ?>
<script type="text/javascript">
<?php echo $js_criteria ?>
function updateCriterias()
{
    var f = document.directory_search;
    while (f.criteria.length > 0) {
        f.criteria.options[f.criteria.length - 1] = null;
    }

    for (var i = 0; i < criteriaOptions.length; i++) {
        if (criteriaOptions[i][0] == f.source.options[f.source.selectedIndex].value) {
            for (var j = 1; j < criteriaOptions[i].length; j++) {
                f.criteria.options[f.criteria.length] = new Option(criteriaOptions[i][j][1], criteriaOptions[i][j][0]);
            }
            f.criteria.selectedIndex = 0;
            return;
        }
    }
}
</script>

<form name="directory_search" action="search.php" method="get">
<?php echo Util::formInput() ?>
<input type="hidden" name="actionID" value="1" />

<p class="text" style="padding:1em">
<?php if (count($cfgSources) > 1): ?>
 <strong><?php echo Horde::label('source', _("From")) ?></strong>
 <select id="source" name="source" onchange="updateCriterias()">
  <?php echo $source_options ?>
 </select>
<?php else: ?>
 <input type="hidden" name="source" value="<?php reset($cfgSources); echo key($cfgSources) ?>" />
<?php endif; ?>

 <strong><?php echo Horde::label('criteria', _("Find")) ?></strong>
 <select id="criteria" name="criteria">
  <?php echo $criteria_options ?>
 </select>

 <strong><?php echo Horde::label('val', _("Matching")) ?></strong>
 <input type="text" size="30" id="val" name="val" value="<?php echo htmlspecialchars($val) ?>" />

 <input class="button" type="submit" name="search" value="<?php echo _("Search") ?>" />
</p>

</form>
<br />

<?php else: ?>

<form name="directory_search" action="search.php" method="get">
<?php echo Util::formInput() ?>

<table cellspacing="5" width="100%">
  <tr>
    <td>&nbsp;</td>
    <td>
      <input type="submit" class="button" name="search" value="<?php echo _("Search") ?>" />
      <input type="reset" class="button" name="reset" value="<?php echo _("Reset to Defaults") ?>" />
    </td>
  </tr>
 <?php if ($source_count > 1): ?>
  <tr>
    <td class="light rightAlign"><strong><?php echo _("Directory") ?></strong></td>
    <td class="leftAlign">
      <select name="source" onchange="directory_search.submit()">
       <?php echo $source_options ?>
      </select>
    </td>
  </tr>
 <?php else: ?>
  <input type="hidden" name="source" value="<?php echo $unique_source ?>" />
 <?php endif; ?>

<?php endif; ?>
