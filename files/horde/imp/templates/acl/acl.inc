<script type="text/javascript">
<!--
function selectRule(num)
{
<?php if (isset($js_right)): ?>
    var users = new Array(<?php echo $js_user ?>);

<?php foreach ($js_right as $key => $val): ?>
    var <?php echo $key ?> = new Array(<?php echo $val ?>);
<?php endforeach; ?>
<?php endif; ?>

<?php if ($canEdit): ?>
    if (num == <?php echo count($curr_acl) ?>) {
        resetForm();
    } else {
        document.acl.share_user.value = users[num];
        document.acl.share_user.disabled = true;
<?php if (!empty($js_right)): ?>
<?php foreach ($js_right as $key => $val): ?>
        if (<?php echo $key ?>[num] > 0) {
            document.acl.elements['acl[<?php echo $key ?>]'].checked = true;
        } else {
            document.acl.elements['acl[<?php echo $key ?>]'].checked = false;
        }
<?php endforeach; ?>
<?php endif; ?>
        document.acl.actionID.value = 'imp_acl_edit';
        document.acl.fbutton.value = '<?php echo addslashes(_("Modify")) ?>';
    }
<?php endif; ?>
}

function resetForm()
{
    document.acl.reset();
    document.acl.share_user.disabled = false;
    document.acl.actionID.value = 'imp_acl_set';
    document.acl.fbutton.value = '<?php echo addslashes(_("Create")) ?>';
    if (document.acl.number) {
        for (var i = 0; i < document.acl.number.length -1 ; i++) {
            document.acl.number[i].checked = false;
        }
    }
}

var loading;
function ACLFolderChange(clear)
{
    if (document.folders.folder[document.folders.folder.selectedIndex].value != '') {
        if (loading == null || clear != null) {
            loading = true;
            disableButtons();
            document.folders.submit();
        }
    }
}

function disableButtons() {
    if (document.acl.fbutton) {
        document.acl.fbutton.disabled = true;
    }
    if (document.acl.resetbut) {
        document.acl.resetbut.disabled = true;
    }
    if (document.acl.back) {
        document.acl.back.disabled = true;
    }
    if (document.acl.number) {
        for (var i = 0; i < document.acl.number.length; i++) {
            document.acl.number[i].disabled = true;
        }
    }
    return true;
}


// -->
</script>

<form method="post" name="folders" action="<?php echo Horde::applicationUrl('acl.php') ?>">
<input type="hidden" name="actionID" value="" />
<?php Util::pformInput() ?>
<div class="header">
  <?php Prefs_UI::generateNavigationCell('acl') ?>
  <?php echo _("Share mail folders") ?>
</div>

<div class="header">
  <ul>
    <li>
      <?php echo Horde::link('#', _("Change Folder"), 'smallheader', '', 'ACLFolderChange(true); return false;') ?><?php echo Horde::img('shared.png', _("Change Folder")) ?></a><select name="folder" onchange="ACLFolderChange()"><?php echo $options ?></select>&nbsp;
    </li>
  </ul>
  <?php printf(_("Current access to %s"), String::convertCharset($folder, 'UTF7-IMAP')) ?>
</div>
</form>

<form method="post" name="acl" action="<?php echo Horde::applicationUrl('acl.php') ?>">
  <input type="hidden" name="actionID" value="imp_acl_set" />
  <input type="hidden" name="folder" value="<?php echo $folder ?>" />
  <?php Util::pformInput() ?>
<table cellspacing="0" width="100%">
<?php if (!count($curr_acl)): ?>
  <tr>
    <td colspan="2" class="item leftAlign">
      <em><strong>&nbsp;<?php echo _("The current list of users with access to this folder could not be retrieved") ?></strong></em>
    </td>
  </tr>
<?php else: ?>
<?php $i = 1; foreach ($curr_acl as $index => $rule): ?>
  <tr>
    <td class="light rightAlign">
      <strong><?php echo $i++ ?>&nbsp;</strong>
    </td><td class="item leftAlign">
      <input class="item" type="radio" name="number" value="<?php echo $i-2 ?>" <?php if (in_array($index, $protected)) echo 'disabled ' ?>onclick="selectRule(<?php echo $i - 2 ?>)" />&nbsp;&nbsp;
      <strong><?php echo $index ?></strong>&nbsp;&nbsp;
    <?php foreach ($rule as $key => $val): ?><?php echo $key ?>,&nbsp;<?php endforeach; ?>
    </td>
  </tr>
<?php endforeach; ?>
  <tr>
    <td class="light rightAlign">
      <strong><?php echo _("New") ?>&nbsp;</strong>
    </td><td class="item leftAlign">
      <input class="item" type="radio" checked name="number" value="<?php echo $i - 1 ?>" onclick="selectRule(<?php echo $i - 1 ?>)" />&nbsp;&nbsp;
    </td>
  </tr>
<?php endif; ?>

  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td colspan="2" class="header">
      <span class="smallheader"><?php echo _("Grant access") ?></span>
    </td>
  </tr>

<?php if ($canEdit): ?>
  <tr>
    <td class="light rightAlign">
      <strong><?php echo _("User") ?>&nbsp;</strong>
    </td><td class="item leftAlign">
      &nbsp;<input class="fixed" type="text" name="share_user" />
    </td>
  </tr>
<?php foreach ($rights as $right => $desc): ?>
  <tr>
    <td class="light rightAlign">
      <strong><?php echo $right ?>&nbsp;:&nbsp;</strong>
    </td><td class="item leftAlign">
      <input type="checkbox" name="acl[<?php echo $right ?>]" />
    &nbsp;<?php echo $desc ?>
    </td>
  </tr>
<?php endforeach; ?>
  <tr>
    <td>&nbsp;</td>
    <td class="header" valign="middle">
      <input type="button" class="button" name="fbutton" onclick="disableButtons(); document.acl.share_user.disabled = false; document.acl.submit();" value="<?php echo _("Create") ?>" />
      <input type="button" class="button" name="resetbut" onclick="resetForm()" value="<?php echo _("Reset") ?>" />
      <input type="button" class="button" name="back" onclick="disableButtons(); document.location.href='<?php echo $prefs_url ?>'" value="<?php echo _("Return to Options") ?>" />
    </td>
  </tr>

<?php else: ?>
  <tr>
    <td colspan="2" class="item leftAlign">
      <em><strong>&nbsp;<?php echo _("You do not have permission to change access to this folder") ?></strong></em>
    </td>
  </tr>
  <tr>
    <td colspan="2" class="header" valign="middle">
      <input type="button" class="button" name="back" onclick="disableButtons(); document.location.href='<?php echo $prefs_url ?>'" value="<?php echo _("Return to Options") ?>" />
    </td>
  </tr>
<?php endif; ?>
</table>
</form>
