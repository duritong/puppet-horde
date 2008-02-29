<?php
/**
 * $Horde: horde/admin/cmdshell.php,v 1.9.10.7 2007/01/02 13:54:03 jan Exp $
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
    Horde::fatal('Forbidden.', __FILE__, __LINE__);
}

$title = _("Command Shell");
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/admin/common-header.inc';

echo '<div style="padding:10px">';
if ($command = trim(Util::getFormData('cmd'))) {
    echo '<h1 class="header">' . _("Command") . ':</h1><br />';
    echo '<p class="text"><code>' . nl2br(htmlspecialchars($command)) . '</code></p>';

    echo '<br /><h1 class="header">' . _("Results") . ':</h1><br />';
    echo '<pre class="text">';

    $cmds = explode("\n", $command);
    foreach ($cmds as $cmd) {
        $cmd = trim($cmd);
        if (strlen($cmd)) {
            unset($results);
            flush();
            echo htmlspecialchars(shell_exec($cmd));
        }
    }

    echo '</pre><br />';
}
?>

<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post">
<?php Util::pformInput() ?>
<textarea class="fixed" name="cmd" rows="10" cols="60">
<?php if (!empty($command)) echo htmlspecialchars($command) ?></textarea>
<br />
<input type="submit" class="button" value="<?php echo _("Execute") ?>" />
<?php echo Help::link('admin', 'admin-cmdshell') ?>

</form>
</div>
<?php

require HORDE_TEMPLATES . '/common-footer.inc';