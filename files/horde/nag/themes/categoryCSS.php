<?php
/**
 * $Horde: nag/themes/categoryCSS.php,v 1.1.2.3 2007/01/02 13:55:13 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('AUTH_HANDLER', true);
@define('NAG_BASE', dirname(__FILE__) . '/..');
require_once NAG_BASE . '/lib/base.php';
require_once 'Horde/Image.php';
require_once 'Horde/Prefs/CategoryManager.php';

header('Content-Type: text/css');

$cManager = &new Prefs_CategoryManager();

$colors = $cManager->colors();
$fgColors = $cManager->fgColors();
foreach ($colors as $category => $color) {
    if ($category == '_unfiled_' || $category == '_default_') {
        continue;
    }

    $class = '.category' . md5($category);

    echo "$class, .linedRow td$class, .overdue td$class, .closed td$class { ",
        'color: ' . (isset($fgColors[$category]) ? $fgColors[$category] : $fgColors['_default_']) . '; ',
        'background: ' . $color . "; }\n";
}
