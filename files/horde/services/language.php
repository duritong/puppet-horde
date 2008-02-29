<?php
/**
 * Script to set the new language.
 *
 * $Horde: horde/services/language.php,v 1.5.12.4 2007/01/02 13:55:15 jan Exp $
 *
 * Copyright 2003-2007 Marko Djukic <marko@oblo.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';

/* Set the language. */
$_SESSION['horde_language'] = NLS::select();
$prefs->setValue('language', $_SESSION['horde_language']);

/* Redirect to the url or login page if none given. */
$url = Util::getFormData('url');
if (empty($url)) {
    $url = Horde::applicationUrl('index.php', true);
}
header('Location: ' . $url);
