<?php
/**
 * $Horde: kronolith/themes/categoryCSS.php,v 1.4.2.3 2007/01/02 13:55:07 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('AUTH_HANDLER', true);
@define('KRONOLITH_BASE', dirname(__FILE__) . '/..');
require_once KRONOLITH_BASE . '/lib/base.php';
require_once 'Horde/Image.php';

header('Content-Type: text/css');

$colors = $cManager->colors();
$fgColors = $cManager->fgColors();
foreach ($colors as $category => $color) {
    if ($category == '_unfiled_') {
        continue;
    } elseif ($category == '_default_') {
        echo '.month-eventBox, .week-eventBox, .day-eventBox, .block-eventBox, .legend-eventBox, ',
            '.month-eventBox a, .week-eventBox a, .day-eventBox a, .block-eventBox a, .legend-eventBox a, ',
            '.month-eventBox a:hover, .week-eventBox a:hover, .day-eventBox a:hover, .block-eventBox a:hover, .legend-eventBox a:hover { ';
    } else {
        $class = '.category' . md5($category);
        echo "$class, $class a, $class a:hover { ";
    }

    echo 'border-color: ' . Horde_Image::modifyColor($color, -0x44) . '; ',
        'color: ' . (isset($fgColors[$category]) ? $fgColors[$category] : $fgColors['_default_']) . '; ',
        'background: ' . $color . "; }\n";
}
