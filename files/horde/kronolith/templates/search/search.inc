<form method="post" name="event" action="search.php">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="search_calendar" />
<input type="hidden" name="search_mode" value="basic" />
<table cellspacing="0" width="100%">
 <tr>
  <td align="left" class="header">
      <?php echo _("Basic Search") ?>
  </td>
  <td align="right" class="header">
    <?php echo Horde::widget(Util::addParameter(Horde::applicationUrl('search.php'), 'search_mode', 'advanced'), _("Advanced Search"), 'smallheader', '', '', _("Ad_vanced Search")) ?>
  </td>
 </tr>
</table>

<table cellspacing="0" width="100%">
<!-- search pattern -->
<tr>
  <td class="item" align="right" valign="top" nowrap="nowrap">
    <strong><?php echo Horde::label('pattern_title', _("Title")) ?>:</strong>&nbsp;
  </td>
  <td class="item" width="100%">
    <input name="pattern_title" id="pattern_title" type="text" value="<?php echo htmlspecialchars(Util::getFormData('pattern_title')) ?>" size="50" maxlength="100" />
  </td>
</tr>
<tr>
  <td class="item" align="right" valign="top" nowrap="nowrap">
    <strong><?php echo Horde::label('pattern_desc', _("Descri_ption")) ?>:</strong>&nbsp;
  </td>
  <td class="item" width="100%">
    <input name="pattern_desc" id="pattern_desc" type="text" value="<?php echo htmlspecialchars(Util::getFormData('pattern_desc')) ?>" size="50" maxlength="100" />
  </td>
</tr>
<!-- range -->
<tr>
  <td class="item" align="right" valign="top" nowrap="nowrap">
    <strong><?php echo Horde::label('search_pattern', _("Time Sp_an")) ?>:</strong>&nbsp;
  </td>
  <td class="item" width="100%">
    <select name="range">
<?php
function range_option_string($thisrange, $descr)
{
    echo '<option ';
    if (Util::getFormData('range') == $thisrange) {
        echo "value=\"$thisrange\" selected=\"selected\"";
    } else {
        echo "value=\"$thisrange\"";
    }
    echo '>' . $descr . '</option>';
}

range_option_string('-', _("All Past"));
range_option_string('-604800', _("Last week"));
range_option_string('-86400', _("Last 24 hours"));
range_option_string('0', _("Now"));
range_option_string('86400', _("Next 24 hours"));
range_option_string('604800', _("Next week"));
range_option_string('+', _("All Future"));
?>
    </select>
  </td>
</tr>
</table>

<br />
<input type="submit" class="button" value="<?php echo _("Search") ?>" />
</form>
