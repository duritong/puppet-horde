<script type="text/javascript">
<!--
function remove(category)
{
    document.prefs.cAction.value = 'remove';
    document.prefs.category.value = category;
    document.prefs.submit();
}

function add()
{
    var category = window.prompt('<?php echo addslashes(_("Please enter a name for the new category:")) ?>', '');
    if (category != null && category != '') {
        document.prefs.cAction.value = 'add';
        document.prefs.category.value = category;
        document.prefs.submit();
    }
}
//-->
</script>
<input name="cAction" value="" type="hidden" />
<input name="category" value="" type="hidden" />
<table>
 <tr>
  <td colspan="2">
   <input type="button" class="button" value="<?php echo _("New Category") ?>" onclick="add();" />
  </td>
 </tr>
<?php

require_once 'Horde/Prefs/CategoryManager.php';
$cManager = &new Prefs_CategoryManager();
$categories = $cManager->get();
$colors = $cManager->colors();
$fgcolors = $cManager->fgColors();
$url = Horde::url($registry->get('webroot', 'horde') . '/services/images/colorpicker.php');
$url = Util::addParameter($url, 'form', 'prefs');

// Default Color
$color = isset($colors['_default_']) ? $colors['_default_'] : '#FFFFFF';
$fgcolor = isset($fgcolors['_default_']) ? $fgcolors['_default_'] : '#000000';
$c_url = Util::addParameter($url, 'target', 'color_' . base64_encode('_default_'));
?>
 <tr>
  <td style="background:<?php echo htmlspecialchars($color) ?>; color:<?php echo $fgcolor ?>"><strong><?php echo _("Default Color") ?></strong></td>
  <td>
   <?php if (!$prefs->isLocked('category_colors')): ?>
    <input class="fixed" size="7" style="background:<?php echo htmlspecialchars($color) ?>; color:<?php echo $fgcolor ?>" name="color_<?php echo base64_encode('_default_') ?>" value="<?php echo htmlspecialchars($color) ?>" />
    <a href="<?php echo $c_url ?>" onclick="window.open('<?php echo $c_url ?>', 'colorpicker', 'toolbar=no,location=no,status=no,scrollbars=no,resizable=no,width=120,height=250,left=100,top=100'); return false;" onmouseout="window.status='';" onmouseover="window.status='<?php echo _("Color Picker") ?>'; return true;" class="widget" target="colorpicker">
    <?php echo Horde::img('colorpicker.png', _("Color Picker"), 'height="16"', $registry->getImageDir('horde')) ?></a>
   <?php endif; ?>
  </td>
 </tr>
<?php
// Unfiled Color
$color = isset($colors['_unfiled_']) ? $colors['_unfiled_'] : '#FFFFFF';
$fgcolor = isset($fgcolors['_unfiled_']) ? $fgcolors['_unfiled_'] : '#000000';
$c_url = Util::addParameter($url, 'target', 'color_' . base64_encode('_unfiled_'));
?>
 <tr>
  <td style="background:<?php echo htmlspecialchars($color) ?>; color:<?php echo $fgcolor ?>"><strong><?php echo _("Unfiled") ?></strong></td>
  <td>
   <?php if (!$prefs->isLocked('category_colors')): ?>
    <input class="fixed" size="7" style="background:<?php echo htmlspecialchars($color) ?>; color:<?php echo $fgcolor ?>" name="color_<?php echo base64_encode('_unfiled_') ?>" value="<?php echo htmlspecialchars($color) ?>" />
    <a href="<?php echo $c_url ?>" onclick="window.open('<?php echo $c_url ?>', 'colorpicker', 'toolbar=no,location=no,status=no,scrollbars=no,resizable=no,width=120,height=250,left=100,top=100'); return false;" onmouseout="window.status='';" onmouseover="window.status='<?php echo _("Color Picker") ?>'; return true;" class="widget" target="colorpicker">
    <?php echo Horde::img('colorpicker.png', _("Color Picker"), 'height="16"', $registry->getImageDir('horde')) ?></a>
   <?php endif; ?>
  </td>
 </tr>
<?php
foreach ($categories as $name):
    $color = isset($colors[$name]) ? $colors[$name] : '#FFFFFF';
    $fgcolor = isset($fgcolors[$name]) ? $fgcolors[$name] : '#000000';
    $c_url = Util::addParameter($url, 'target', 'color_' . base64_encode($name));
?>
 <tr>
  <td style="background:<?php echo htmlspecialchars($color) ?>; color:<?php echo $fgcolor ?>"><strong><?php echo ($name == '_default_' ? _("Default Color") : htmlspecialchars($name)) ?></strong></td>
  <td>
   <?php if (!$prefs->isLocked('category_colors')): ?>
    <input class="fixed" size="7" style="background:<?php echo htmlspecialchars($color) ?>; color:<?php echo $fgcolor ?>" name="color_<?php echo base64_encode($name) ?>" value="<?php echo htmlspecialchars($color) ?>" />
    <a href="<?php echo $c_url ?>" onclick="window.open('<?php echo $c_url ?>', 'colorpicker', 'toolbar=no,location=no,status=no,scrollbars=no,resizable=no,width=120,height=250,left=100,top=100'); return false;" onmouseout="window.status='';" onmouseover="window.status='<?php echo _("Color Picker") ?>'; return true;" class="widget" target="colorpicker">
    <?php echo Horde::img('colorpicker.png', _("Color Picker"), 'height="16"', $registry->getImageDir('horde')) ?></a>
    <?php endif; ?>
    <a href="#" onclick="remove(decodeURIComponent('<?php echo rawurlencode(String::convertCharset($name, NLS::getCharset(), 'UTF-8')) ?>')); return false;"><?php echo Horde::img('delete.png') ?></a>
  </td>
 </tr>
<?php endforeach; ?>
</table>
