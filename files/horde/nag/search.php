<?php
/**
 * $Horde: nag/search.php,v 1.17.8.4 2007/01/02 13:55:12 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('NAG_BASE', dirname(__FILE__));
require_once NAG_BASE . '/lib/base.php';

$title = _("Search");
$notification->push('document.search.search_pattern.focus()', 'javascript');
require NAG_TEMPLATES . '/common-header.inc';
require NAG_TEMPLATES . '/menu.inc';
require NAG_TEMPLATES . '/search/search.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
