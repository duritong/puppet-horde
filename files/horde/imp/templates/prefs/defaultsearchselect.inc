<?php if (!$prefs->isLocked('default_search')): ?>
<?php
    if (!isset($imp_search)) {
        require_once IMP_BASE . '/lib/Search.php';
        $imp_search = new IMP_Search();
    }
    $curr_default = $prefs->getValue('default_search');
    $search_fields = array_merge(array('' => array('label' => _("NONE"))), $imp_search->searchFields());
    $def_select = '';
    foreach ($search_fields as $key => $val) {
        $def_select .= '<option value="' . $key . '"';
        if ($curr_default == $key) {
            $def_select .= ' selected="selected"';
        }
        $def_select .= '>' . $val['label'] . '</option>';
    }
?>
<table border="0">
 <tr>
  <td>
   <?php echo _("Default Search Field") ?>:
   <br />
   <select name="default_search"><?php echo $def_select ?></select>
  </td>
 </tr>
</table>
<?php endif; ?>
