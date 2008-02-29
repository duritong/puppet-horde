<?php
/**
 * $Horde: horde/admin/phpshell.php,v 1.24.10.9 2007/01/02 13:54:03 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/Menu.php';
require_once 'Horde/Help.php';

if (!Auth::isAdmin()) {
    Horde::authenticationFailureRedirect();
}

$title = _("PHP Shell");
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/admin/common-header.inc';

$apps_tmp = $registry->listApps();
$apps = array();
foreach ($apps_tmp as $app) {
    $apps[$app] = $registry->get('name', $app);
}
asort($apps);
$application = Util::getFormData('app', 'horde');
?>
<div style="padding:10px">
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post">
<?php Util::pformInput() ?>

<h1 class="header"><?php echo _("Application") ?></h1><br />
<select name="app">
<?php foreach ($apps as $app => $name): ?>
  <option value="<?php echo $app ?>"<?php if ($application == $app) echo ' selected="selected"' ?>><?php echo $name ?></option>
<?php endforeach; ?>
</select><br /><br />
<?php

if ($command = trim(Util::getFormData('php'))) {
    if (@file_exists($registry->get('fileroot', $application) . '/lib/base.php')) {
        include $registry->get('fileroot', $application) . '/lib/base.php';
    } else {
        $registry->pushApp($application);
    }

    require_once 'Horde/MIME/Viewer.php';
    require_once 'Horde/MIME/Viewer/source.php';
    $pretty = highlight_string('<?php ' . $command, true);
    $pretty = str_replace(array('&lt;?php ',
                                '<font color="#000000"><font color="#007700">&lt;?</font><font color="#0000BB">php </font>',
                                "\r\n",
                                "\r",
                                "\n",
                                '<br />'),
                          array('',
                                '',
                                '',
                                '',
                                '',
                                "\n"),
                          $pretty);
    $pretty = MIME_Viewer_Source::lineNumber(trim($pretty));

    echo '<h1 class="header">' . _("PHP Code") . '</h1><br />';
    echo $pretty;

    echo '<br /><h1 class="header">' . _("Results") . '</h1><br />';
    echo '<pre class="text">';
    eval($command);
    echo '</pre><br />';
}
?>

<textarea class="fixed" name="php" rows="10" cols="60">
<?php if (!empty($command)) echo htmlspecialchars($command) ?></textarea>
<br />
<input type="submit" class="button" value="<?php echo _("Execute") ?>" />
<?php echo Help::link('admin', 'admin-phpshell') ?>

</form>
</div>
<?php

require HORDE_TEMPLATES . '/common-footer.inc';
