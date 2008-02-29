<?php
if (isset($language)) {
    header('Content-type: text/html; charset=' . NLS::getCharset());
    header('Vary: Accept-Language');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<!-- Nag: Copyright 2001-2006, The Horde Project. Nag is under the GPL. -->
<!--    Horde Project: http://horde.org/ | Nag: http://horde.org/nag/   -->
<!--       GNU Public License: http://www.fsf.org/copyleft/gpl.html     -->
<?php echo !empty($language) ? '<html lang="' . strtr($language, '_', '-') . '">' : '<html>' ?>
<head>
<?php

$page_title = $registry->get('name');
if (!empty($title)) $page_title .= ' :: ' . $title;
if (!empty($refresh_time) && ($refresh_time > 0) && !empty($refresh_url)) {
    echo "<meta http-equiv=\"refresh\" content=\"$refresh_time;url=$refresh_url\">\n";
}

if ($GLOBALS['browser']->isBrowser('konqueror')) {
    Horde::addScriptFile('fixUnstyledOptions.js', 'nag', true);
}
Horde::includeScriptFiles();

?>
<title><?php echo htmlspecialchars($page_title) ?></title>
<link href="<?php echo $GLOBALS['registry']->getImageDir()?>/favicon.ico" rel="SHORTCUT ICON" />
<?php echo Horde::stylesheetLink('nag', empty($print_view) ? '' : 'print') ?>
<link href="<?php echo Horde::applicationUrl('themes/categoryCSS.php') ?>" rel="stylesheet" type="text/css" />
</head>

<body<?php if (Util::nonInputVar('bodyClass')) echo ' class="' . $bodyClass . '"' ?>>
