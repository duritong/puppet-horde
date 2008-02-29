<?php
/**
 * $Horde: horde/js/alphaImageLoader.php,v 1.2.8.5 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 2004-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/core.php';

$registry = &Registry::singleton(HORDE_SESSION_NONE);

/* This should be cached. */
$mod_gmt = gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT';
header('Last-Modified: ' . $mod_gmt);
header('Cache-Control: public, max-age=86400');
header('Content-Type: text/x-component');

?>
<public:component>
<public:attach event="onpropertychange" for="element" onEvent="propertyChanged()" />
<script>

var transparentImage = "<?php echo $registry->getImageDir('horde') ?>/blank.gif";

pngHack();

function propertyChanged()
{
    if (event.propertyName == "src") {
        pngHack();
    }
}

function pngHack()
{
    var w = element.width;
    var h = element.height;
    var src = element.src;
    if (src.indexOf(transparentImage) != -1) {
        // Already fixed.
        return;
    }

    if (src.indexOf("png") == -1) {
        element.runtimeStyle.filter = "";
        return;
    }

    element.src = transparentImage;
    element.width = w;
    element.height = h;
    element.runtimeStyle.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + src + "',sizingMethod='scale')";
}

</script>
</public:component>
