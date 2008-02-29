<?php
/**
 * $Horde: imp/expand.php,v 1.16.10.6 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2002-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

$no_compress = true;
$authentication = 'horde';

@define('IMP_BASE', dirname(__FILE__));
require_once IMP_BASE . '/lib/base.php';

$actionID = Util::getFormData('actionID');

if ($actionID == 'expand_addresses') {
    $form_name = Util::getFormData('form_name');
    $field_name = Util::getFormData('field_name');
    $field_value = Util::getFormData('field_value');

    $address = IMP::expandAddresses($field_value, true);
    if (is_a($address, 'PEAR_Error') &&
        $conf['compose']['add_maildomain_to_unexpandable']) {
        $addrString = preg_replace('/,\s+/', ',', $field_value);
        $address = MIME::encodeAddress($addrString, null, $_SESSION['imp']['maildomain']);
    }
}

require IMP_TEMPLATES . '/compose/expand.inc';
