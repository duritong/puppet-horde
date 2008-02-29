<?php
/**
 * $Horde: horde/services/cacheview.php,v 1.9.10.4 2007/01/02 13:55:15 jan Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/Cache.php';

$cid = Util::getFormData('cid');
if (empty($cid)) {
    exit;
}

$cache = &Horde_Cache::singleton($conf['cache']['driver'], Horde::getDriverConfig('cache', $conf['cache']['driver']));
$cdata = @unserialize($cache->get($cid, $conf['cache']['default_lifetime']));
if (!$cdata) {
    exit;
}

$browser->downloadHeaders('cacheObject', $cdata['ctype'], true, strlen($cdata['data']));
echo $cdata['data'];
