<?php
/**
 * $Horde: horde/admin/perms/index.php,v 1.10.10.4 2007/01/02 13:54:04 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2005-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/../..');
require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/Menu.php';

if (!Auth::isAdmin()) {
    Horde::authenticationFailureRedirect();
}

$perm_id = Util::getFormData('perm_id');

$title = _("Permissions Administration");
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/admin/common-header.inc';
$notification->notify(array('listeners' => 'status'));

require_once 'Horde/Perms/UI.php';
$ui = &new Perms_UI($perms);
$ui->renderTree($perm_id);

require HORDE_TEMPLATES . '/common-footer.inc';
