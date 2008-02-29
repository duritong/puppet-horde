<?php
/**
 * $Horde: mnemo/search.php,v 1.9.8.5 2007/01/02 13:55:11 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jon Parise <jon@horde.org>
 * @since   Mnemo 1.0
 * @package Mnemo
 */

@define('MNEMO_BASE', dirname(__FILE__));
require_once MNEMO_BASE . '/lib/base.php';
$title = _("Search");
require MNEMO_TEMPLATES . '/common-header.inc';
require MNEMO_TEMPLATES . '/menu.inc';
require MNEMO_TEMPLATES . '/search/search.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
