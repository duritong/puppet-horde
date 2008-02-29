<tr>
<?php if (($i == 0) && !$last_field): ?>
 <td>&nbsp;</td>
<?php elseif ($i > 0): ?>
 <td><strong><?php echo (is_null($search['match']) || ($search['match'] == 'and')) ? _("AND") : _("OR") ?></strong>&nbsp;</td>
<?php endif; ?>
 <td style="padding:2px">
  <select onchange="document.search.submit();return true;" name="field[<?php echo $i ?>]" id="field_<?php echo $i ?>">
<?php if ($last_field): ?>
   <option value=""><?php echo _("Select a field") ?></option>
   <option value=""> --------- </option>
<?php endif; ?>
<?php foreach ($imp_search_fields as $key => $val): ?>
   <option value="<?php echo $key ?>"<?php if ($curr_field == $key) echo ' selected="selected"'; ?>><?php $label = $val['label']; echo _($label) ?></option>
<?php endforeach; ?>
  </select>
 </td>
<?php if (is_null($curr_field)): ?>
 <td>&nbsp;</td>
<?php elseif (($imp_search_fields[$curr_field]['type'] == IMP_SEARCH_HEADER) ||
              ($imp_search_fields[$curr_field]['type'] == IMP_SEARCH_BODY) ||
              ($imp_search_fields[$curr_field]['type'] == IMP_SEARCH_TEXT)): ?>
 <td class="item0 leftAlign">
  <input type="text" name="search_text[<?php echo $i ?>]" size="40" <?php if (!empty($search['text'][$i])) echo 'value="' . htmlspecialchars($search['text'][$i]) . '" '; ?>/>
  <input type="checkbox" name="search_text_not[<?php echo $i ?>]" <?php if (!empty($search['text_not'][$i])) echo 'checked="checked" '; ?>/>
    <em><?php echo _("Do NOT match") ?></em>
 </td>
<?php elseif ($imp_search_fields[$curr_field]['type'] == IMP_SEARCH_DATE): ?>
 <td class="item0 leftAlign" nowrap="nowrap">
  <?php echo buildMonthWidget('search_date[' . $i . '][month]', isset($search['date'][$i]['month']) ? $search['date'][$i]['month'] : $curr_date['mon']) ?>
  <?php echo buildDayWidget('search_date[' . $i . '][day]', isset($search['date'][$i]['day']) ? $search['date'][$i]['day'] : $curr_date['mday'], "onchage=\"dateCheck('on')\"") ?>
  <?php echo buildYearWidget('search_date[' . $i . '][year]', -20, isset($search['date'][$i]['year']) ? $search['date'][$i]['year']: $curr_date['year'], "onchange=\"dateCheck('on')\"") ?>
<?php if ($GLOBALS['browser']->hasFeature('javascript')) {
    Horde::addScriptFile('open_calendar.js', 'horde');
    echo '<div id="goto" class="control" style="position:absolute;visibility:hidden;padding:1px"></div>';
    echo Horde::link('#', _("Select a date"), '', '', 'openCalendar(\'dateimg\', \'search_date[' . $i . ']\'); return false;') . Horde::img('calendar.png', _("Calendar"), 'align="top" id="dateimg"', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
} ?>
 </td>
<?php endif; ?>
<?php if (!$last_field): ?>
 <td>&nbsp;<input type="image" src="<?php echo $GLOBALS['registry']->getImageDir('horde') . '/close.png' ?>" onclick="document.search.delete_field_id.value=<?php echo $i ?>;document.search.actionID.value='delete_field';document.search.submit();return true;" value="<?php echo _("Remove Field From Search") ?>" /></td>
<?php elseif ($last_field && ($i != 0)): ?>
 <td>&nbsp;</td>
<?php endif; ?>
</tr>
