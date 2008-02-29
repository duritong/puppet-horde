<?php
/**
 * $Horde: horde/services/download/index.php,v 1.9.10.4 2007/01/02 13:55:16 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (LGPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/../..');
require_once HORDE_BASE . '/lib/core.php';

$registry = &Registry::singleton(HORDE_SESSION_READONLY);

if (!($module = Util::getFormData('module')) ||
    !file_exists($registry->get('fileroot', $module))) {
    Horde::fatal('Do not call this script directly.', __FILE__, __LINE__);
}
include $registry->get('fileroot', $module) . '/view.php';
