<?php
    if (empty($encryptOpt)) {
        $encryptOpt = $prefs->getValue('default_encrypt');
    }
    $enc_options = array(IMP_ENCRYPT_NONE => _("No Encryption"));
    if (!empty($conf['utils']['gnupg']) && $prefs->getValue('use_pgp')) {
        $enc_options[IMP_PGP_ENCRYPT] = _("PGP Encrypt Message");
        $enc_options[IMP_PGP_SIGN] = _("PGP Sign Message");
        $enc_options[IMP_PGP_SIGNENC] = _("PGP Sign/Encrypt Message");
    }
    if ($prefs->getValue('use_smime')) {
        $enc_options[IMP_SMIME_ENCRYPT] = _("S/MIME Encrypt Message");
        $enc_options[IMP_SMIME_SIGN] = _("S/MIME Sign Message");
        $enc_options[IMP_SMIME_SIGNENC] = _("S/MIME Sign/Encrypt Message");
    }
    foreach ($enc_options as $key => $val) {
        echo '<option value="' . $key . '"' . (($encryptOpt == $key) ? ' selected="selected"' : '') . '>' . $val . '</option>' . "\n";
    }
?>
