<div id="menu">
 <h1 class="leftFloat"><?php printf(_("Welcome, %s"), htmlspecialchars($fullname)) ?></h1>
 <div class="rightFloat">
<?php
$menu = &new Menu();
$menu->add(Horde::applicationUrl('services/portal/'), _("Ho_me"), 'horde.png');
if (!$prefs->isLocked('portal_layout')) {
    $menu->add(Horde::applicationUrl('services/portal/edit.php'), _("La_yout"), 'layout.png');
}
if (Auth::isAdmin()) {
    $menu->add(Horde::applicationUrl('admin/'), _("_Administration"), 'administration.png');
}
echo $menu->render();
?>
 </div>
</div>
