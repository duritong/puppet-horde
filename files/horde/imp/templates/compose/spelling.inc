<form method="post" name="compose" enctype="multipart/form-data" action="<?php echo Util::addParameter(Horde::applicationUrl('compose.php'), 'uniq', base_convert(microtime(), 10, 36)) ?>">
<input type="hidden" name="reloaded" value="1" />
<input type="hidden" name="actionID" value="" />
<input type="hidden" name="spellcheck" value="" />
<input type="hidden" name="done_action" value="<?php echo $f_done_action ?>" />
<input type="hidden" name="message" value="<?php echo htmlspecialchars($f_message) ?>" />
<input type="hidden" name="oldmsg" value="<?php echo htmlspecialchars($f_oldmsg) ?>" />
<input type="hidden" name="currmsg" value="<?php echo htmlspecialchars($f_currmsg) ?>" />
<input type="hidden" name="newmsg" value="<?php echo htmlspecialchars($f_newmsg) ?>" />
<input type="hidden" name="rtemode" value="<?php echo htmlspecialchars($rtemode) ?>" />
<?php if (!empty($ignoreall)): ?>
<input type="hidden" name="ignoreall" value="<?php echo htmlspecialchars(serialize($ignoreall)) ?>" />
<?php endif; ?>

<!-- Maintain state -->
<?php
    foreach ($s_var as $val) {
        $form_val = Util::getFormData($val);
        if (!empty($form_val)) {
            if (is_array($form_val)) {
                foreach ($form_val as $key2 => $val2) {
                    echo '<input type="hidden" name="' . $val . '[' . htmlspecialchars($key2) . ']" value="' . htmlspecialchars($val2) . '" />' . "\n";
                }
            } else {
                echo '<input type="hidden" name="' . $val . '" value="' . htmlspecialchars($form_val) . '" />' . "\n";
            }
        }
    }

    $count = $i = $numerrors = 0;
    if (isset($error) && is_array($error)) {
        $numerrors = count($error);
    }
    $errorscount = $errorsleft = $numerrors;

    if ($numerrors > 0) {
        for ($k = 0; $k < $errorscount; $k++) {
            if (in_array(String::lower($error[$k][0], true), $ignoreall)) {
                $errorsleft--;
            }
        }
    }
?>

<h1 class="header">
<?php if ($errorsleft > 0): ?>
 <?php echo _("Spell Checker") ?> - <em><?php echo $errorsleft ?> <?php echo ($errorsleft > 1) ? _("spelling errors") : _("spelling error"); ?></em>
<?php else: ?>
 <?php echo _("Spell check complete") ?> - <?php echo _("No spelling errors") ?>
<?php endif; ?>
</h1>

<table width="100%" border="0" cellspacing="0" cellpadding="1">
<?php
    while (($count < $prefs->getValue('num_words')) && ($i < $numerrors)) {
        if (!isset($error) || !is_array($error)) {
            echo _("Spell check complete");
            break;
        }

        if (!in_array(String::lower($error[$i][0], true), $ignoreall)) {
?>
 <tr class="item">
  <td class="item"><?php echo _("Correct") ?></td>
  <td colspan="3" nowrap="nowrap">
<?php
            $offset = $error[$i][1];
            $word = $error[$i][0];
            $alt = $error[$i][2];
            echo '<span style="color:#ff0000">"' . htmlspecialchars($word) . '"</span>';
            echo _(" in ");
            $context = _highlightError(htmlspecialchars($word), htmlspecialchars($f_currmsg), htmlspecialchars($offset));

            if (!empty($context)) {
                echo "<span class=\"fixed\">$context</span>";
            } else {
                echo _("none");
            }
?>
  </td>
  <td><strong><em><?php echo _("Change"); ?></em></strong></td>
  <td><strong><em><?php echo _("Change All"); ?></em></strong></td>
  <td><?php echo _("Ignore") ?></td>
  <td><?php echo _("Ignore All") ?></td>
 </tr>
 <tr>
  <td class="item"><strong><?php echo _("Suggestions") ?></strong></td>
  <td class="item">
   <input type="hidden" name="oldword[<?php echo $count ?>]" value="<?php echo htmlspecialchars($word) ?>" />
   <input type="hidden" name="wordoffset[<?php echo $count ?>]" value="<?php echo htmlspecialchars($offset) ?>" />
    <select name="subs[<?php echo $count ?>]" onchange="change_selected(<?php echo $count ?>);">
<?php
            if (!empty($alt)) {
                $checkedval = 3;
                $altwords = explode(', ', $alt);
                for ($j = 0; $j < count($altwords); $j++) {
                    echo '<option value="' . htmlspecialchars($altwords[$j]) . '">' . htmlspecialchars($altwords[$j]) . '</option>';
                }
            } else {
                $checkedval = 3;
                echo '<option value="0">' . _("No suggestions") . '</option>';
            }
?>
   </select>
  </td>
  <td class="item"><strong><?php echo (!empty($alt)) ? _("or") : _("Replace with:"); ?></strong></td>
  <td class="item"><input type="text" name="subtext[<?php echo $count ?>]" size="12" onchange="change_selected(<?php echo $count ?>);" /></td>
  <td class="item"><input type="radio" value="1" name="opt[<?php echo $count ?>]" <?php if ($checkedval == 1) echo 'checked="checked"'; ?> /></td>
  <td class="item"><input type="radio" value="2" name="opt[<?php echo $count ?>]" <?php if ($checkedval == 2) echo 'checked="checked"'; ?> /></td>
  <td class="item"><input type="radio" value="3" name="opt[<?php echo $count ?>]" <?php if ($checkedval == 3) echo 'checked="checked"'; ?> /></td>
  <td class="item"><input type="radio" value="4" name="opt[<?php echo $count ?>]" <?php if ($checkedval == 4) echo 'checked="checked"'; ?> /></td>
 </tr>
<?php
            $count++;
        }
        $i++;
    }
?>
 <tr class="item">
  <td colspan="8" class="item">
   <input type="button" class="button" onclick="document.compose.actionID.value='spell_check_cancel'; document.compose.submit();" value="<?php echo _("Cancel") ?>" /><em>&nbsp;<?php echo _("(Discard all changes and return to the compose screen.)") ?></em>
  </td>
 </tr>
<?php if (($actionID == 'spell_check_forward') || ($errorsleft == 0)): ?>
 <tr class="item">
  <td colspan="8" class="item">
   <input type="button" class="button" onclick="document.compose.actionID.value='<?php echo $spell_check_done_action; ?>'; document.compose.submit();" value="<?php echo _("Done") ?>" /><em>&nbsp;<?php echo $spell_check_done_caption; ?></em>
  </td>
 </tr>
<?php endif; ?>
<?php if ($errorsleft > 0): ?>
 <tr class="item">
  <td colspan="8" class="item">
   <input type="button" class="button" onclick="document.compose.actionID.value='spell_check_forward'; document.compose.submit();" value="<?php echo _("Next") ?>" /><em>&nbsp;<?php echo _("(Apply these changes and move to next screen.)") ?></em>
  </td>
 </tr>
<?php endif; ?>
 <tr><td colspan="8">&nbsp;</td></tr>
</table>
<h1 class="header">
 <?php echo _("Corrected Text") ?>
</h1>
<div class="item">
 <span class="fixed" style="white-space:pre">
  <?php echo $display_msg ?>
 </span>
</div>

</form>
