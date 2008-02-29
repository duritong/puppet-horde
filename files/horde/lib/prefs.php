<?php
/**
 * $Horde: horde/lib/prefs.php,v 1.19.4.13 2007/02/09 14:05:40 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

function handle_showsummaryselect($updated)
{
    global $prefs;

    $show_summaries = Util::getFormData('show_summaries');
    if (!is_null($show_summaries)) {
        $prefs->setValue('show_summaries', $show_summaries);
        $updated = true;
    }

    return $updated;
}

function handle_themeselect($updated)
{
    global $prefs;

    $theme = Util::getFormData('theme');
    if (!is_null($theme)) {
        $prefs->setValue('theme', $theme);
        $updated = true;
    }

    return $updated;
}

function handle_categorymanagement($updated)
{
    require_once 'Horde/Prefs/CategoryManager.php';
    $cManager = &new Prefs_CategoryManager();

    /* Always save colors of all categories. */
    $colors = array();
    $categories = $cManager->get();
    foreach ($categories as $category) {
        if ($color = Util::getFormData('color_' . base64_encode($category))) {
            $colors[$category] = $color;
        }
    }
    if ($color = Util::getFormData('color_' . base64_encode('_default_'))) {
        $colors['_default_'] = $color;
    }
    if ($color = Util::getFormData('color_' . base64_encode('_unfiled_'))) {
        $colors['_unfiled_'] = $color;
    }
    $cManager->setColors($colors);

    $action = Util::getFormData('cAction');
    $category = Util::getFormData('category');

    switch ($action) {
    case 'add':
        $cManager->add($category);
        break;

    case 'remove':
        $cManager->remove($category);
        break;

    default:
        /* Save button. */
        $updated = true;
    }

    return $updated;
}

/**
 * Do anything that we need to do as a result of certain preferences
 * changing.
 */
function prefs_callback()
{
    global $prefs, $registry, $notification, $nls;

    if ($prefs->isDirty('language') ||
        $prefs->isDirty('show_sidebar')) {
        if ($prefs->isDirty('language')) {
            NLS::setLang($prefs->getValue('language'));
            NLS::setTextdomain($registry->getApp(), $registry->get('fileroot') . '/locale', NLS::getCharset());
            String::setDefaultCharset(NLS::getCharset());
        }

        $url = $registry->get('webroot', 'horde');
        if (substr($url, -1) != '/') {
            $url .= '/';
        }
        $url = Util::addParameter($url . 'index.php', 'url', Horde::selfUrl(true, false, true));
        if ($prefs->isDirty('show_sidebar') &&
            $prefs->getValue('show_sidebar')) {
            $notification->push('window.location = \'' . addslashes(Horde::url($url, true)) . '\';', 'javascript');
        } else {
            $notification->push('if (window.parent.frames && window.parent.frames.horde_menu) window.parent.frames.location = \'' . addslashes(Horde::url($url, true)) . '\';', 'javascript');
        }
    }

    if ($prefs->isDirty('sidebar_width')) {
        $notification->push('if (window.parent && window.parent.document.getElementById(\'hf\') && window.parent.horde_menu && window.parent.horde_menu.expandedSidebar.shown()) window.parent.document.getElementById(\'hf\').cols = window.parent.horde_menu.rtl ? \'*,' . $prefs->getValue('sidebar_width') . '\' : \'' . $prefs->getValue('sidebar_width') . ',*\';', 'javascript');
    }

    if ($prefs->isDirty('theme') ||
        $prefs->isDirty('menu_view') ||
        $prefs->isDirty('menu_refresh_time')) {
        $notification->push('if (window.parent.frames.horde_menu) window.parent.frames.horde_menu.location.reload();', 'javascript');
    }
}

/* Assign variables for select lists. */
if (!$prefs->isLocked('timezone')) {
    $timezone_options = $tz;
    array_unshift($timezone_options, _("Default"));
}
if (!$prefs->isLocked('initial_application')) {
    global $perms;

    $initial_application_options = array();
    $apps = $registry->listApps(array('active'));
    foreach ($apps as $a) {
        if (file_exists($registry->get('fileroot', $a)) &&
            (($perms->exists($a) && ($perms->hasPermission($a, Auth::getAuth(), PERMS_READ) || Auth::isAdmin())) ||
             !$perms->exists($a))) {
            $initial_application_options[$a] = $registry->get('name', $a);
        }
    }
    asort($initial_application_options);
}
if (!$prefs->isLocked('theme')) {
    $theme_options = array();
    $theme_base = $registry->get('themesfs', 'horde');
    $dh = @opendir($theme_base);
    if (!$dh) {
        $notification->push("Theme directory can't be opened", 'horde.error');
    } else {
        while (($dir = readdir($dh)) !== false) {
            if ($dir == '.' || $dir == '..') {
                continue;
            }

            $theme_name = null;
            @include $theme_base . '/' . $dir . '/info.php';
            if (!empty($theme_name)) {
                $theme_options[$dir] = $theme_name;
            }
        }
    }

    asort($theme_options);
}
