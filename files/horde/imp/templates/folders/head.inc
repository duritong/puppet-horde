<h1 class="header">
 <?php
    $refresh_title = _("Reload View");
    $refresh_ak = Horde::getAccessKey($refresh_title);
    $refresh_title = Horde::stripAccessKey($refresh_title);
    if (!empty($refresh_ak)) {
        $refresh_title .= sprintf(_(" (Accesskey %s)"), $refresh_ak);
    }
    echo _("Folder Navigator") . '&nbsp;' . Horde::link($folders_url, $refresh_title, '', '', '', $refresh_title, $refresh_ak) . Horde::img('reload.png', _("Reload"), null, $registry->getImageDir('horde')) . '</a>';
?>
</h1>
<div class="headerbox folders">
<form name="fmanager" method="post" action="<?php echo $folders_url ?>">
