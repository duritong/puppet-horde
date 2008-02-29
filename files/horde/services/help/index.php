<?php
/**
 * $Horde: horde/services/help/index.php,v 2.80.10.10 2007/01/02 13:55:16 jan Exp $
 *
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/../..');
@define('AUTH_HANDLER', true);

require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/Help.php';

$title = _("Help");
$show = String::lower(Util::getFormData('show', 'index'));
$module = String::lower(preg_replace('/\W/', '', Util::getFormData('module', 'horde')));
$topic = Util::getFormData('topic');

if ($module == 'admin') {
    $fileroot = $registry->get('fileroot');
    $help_file = $fileroot . "/admin/locale/$language/help.xml";
    $help_file_fallback = $fileroot . '/admin/locale/en_US/help.xml';
} else {
    $fileroot = $registry->get('fileroot', $module);
    $help_file = $fileroot . "/locale/$language/help.xml";
    $help_file_fallback = $fileroot . '/locale/en_US/help.xml';
}

if ($show == 'index') {
    $base_url = $registry->get('webroot', 'horde') . '/services/help/';
    $main_url = Horde::url($base_url);
    $main_url = Util::addParameter($main_url, array('show' => 'entry',
                                                    'module' => $module,
                                                    'topic' => $topic));
    $menu_url = Horde::url($base_url);
    $menu_url = Util::addParameter($menu_url, array('module' => $module,
                                                    'show' => 'menu'));
    require HORDE_TEMPLATES . '/help/index.inc';
    exit;
}

$bodyClass = 'help';
require HORDE_TEMPLATES . '/common-header.inc';
if ($show == 'menu') {
    /* Set up urls. */
    $url = Horde::url($registry->get('webroot', 'horde') . '/services/help/');
    $url = Util::addParameter($url, 'module', $module);
    $topics_link = Util::addParameter($url, 'show', 'topics');
    $topics_link = Horde::link($topics_link, null, 'header', 'help_main') . _("List Help Topics") . '</a>';
    $about_link = Util::addParameter($url, 'show', 'about');
    $about_link = Horde::link($about_link, null, 'header', 'help_main') . _("About...") . '</a>';
    require HORDE_TEMPLATES . '/help/menu.inc';
} elseif ($show == 'about') {
    require $fileroot . '/lib/version.php';
    $mod_version_constant = String::upper($module) . '_VERSION';
    if (!defined($mod_version_constant)) {
        exit;
    }
    $version = String::ucfirst($module) . ' ' . constant($mod_version_constant);
    $credits = Util::bufferOutput('include', $fileroot . '/docs/CREDITS');
    $credits = String::convertCharset($credits, 'iso-8859-1', NLS::getCharset());
    require HORDE_TEMPLATES . '/help/about.inc';
} else {
    $help = &new Help(HELP_SOURCE_FILE, array($help_file, $help_file_fallback));
    if (($show == 'entry') && !empty($topic)) {
        $help->lookup($topic);
        $help->display();
    } else {
        $topics = $help->topics();
        foreach ($topics as $id => $title) {
            $link = Horde::url($registry->get('webroot', 'horde') . '/services/help/');
            $link = Util::addParameter($link, array('show' => 'entry', 'module' => $module, 'topic' => $id));
            echo Horde::link($link);
            echo $title . "</a><br />\n";
        }
    }
    $help->cleanup();
}

require HORDE_TEMPLATES . '/common-footer.inc';
