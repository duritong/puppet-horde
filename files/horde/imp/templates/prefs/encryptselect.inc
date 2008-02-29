<?php echo _("Your default encryption method for sending messages:") ?><br />
<select name="default_encrypt">
<?php
    $encryptOpt = $prefs->getValue('default_encrypt');
    require IMP_TEMPLATES . '/compose/encrypt_list.inc';
?>
</select>
<br />
