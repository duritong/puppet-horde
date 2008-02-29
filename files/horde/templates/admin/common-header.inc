<div id="menu">
 <span class="leftFloat">
<?php
$menu = &new Menu();
$menu->add(Horde::applicationUrl('services/portal/'), _("Ho_me"), 'horde.png');
foreach ($registry->listApps() as $app) {
    if ($registry->hasMethod('admin_list', $app)) {
        $list = $registry->callByPackage($app, 'admin_list');
        if (is_a($list, 'PEAR_Error')) {
            continue;
        }

        foreach ($list as $vals) {
            if ($app != 'horde') {
                $name = $registry->get('name', $app);
                if (!empty($vals['name'])) {
                    $name .= ' ' . $vals['name'];
                }
            } else {
                $name = $vals['name'];
            }
            $img = isset($vals['icon']) ? $registry->getImageDir($app) . '/' . $vals['icon'] : $registry->get('icon', $app);
            $menu->add(Horde::url($registry->applicationWebPath($vals['link'], $app)), $name, $img, '');
        }
    }
}
echo $menu->render();
?>
 </span>
</div>
