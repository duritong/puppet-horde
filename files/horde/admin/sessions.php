<?php
/**
 * $Horde: horde/admin/sessions.php,v 1.2.2.4 2007/01/02 13:54:03 jan Exp $
 *
 * Copyright 2005-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/Menu.php';
require_once 'Horde/SessionHandler.php';

if (!Auth::isAdmin()) {
    Horde::authenticationFailureRedirect();
}

$type = !empty($conf['sessionhandler']['type']) ? $conf['sessionhandler']['type'] : 'none';
if ($type == 'external') {
    $notification->push(_("Can't administer external session handlers."), 'horde.error');
} else {
    $sh = &SessionHandler::singleton($type);
}

$title = _("Session Admin");
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/admin/common-header.inc';
$notification->notify(array('listeners' => 'status'));

if (!empty($sh)) {
    $users = $sh->listAuthenticatedUsers();
    $count = $sh->countAuthenticatedUsers();
    echo '<h1 class="header">' . _("Current Users");
    if (is_a($users, 'PEAR_Error')) {
        echo '</h1><p class="headerbox"><em>' . sprintf(_("Listing users failed: %s"), $users->getMessage()) . '</em></p>';
    } else {
        echo ' (' . $count . ')</h1>';
        echo '<ul class="headerbox linedRow">';
        foreach ($users as $user) {
            echo '<li>' . htmlspecialchars($user) . '</li>';
        }
        echo '</ul>';
    }

    echo '<br />';

    $ids = $sh->getSessionIDs();
    echo '<h1 class="header">' . _("Current Sessions") . '</h1>';
    if (is_a($ids, 'PEAR_Error')) {
        echo '<p class="headerbox"><em>' . sprintf(_("Listing sessions failed: %s"), $ids->getMessage()) . '</em></p>';
    } else {
        echo '<ul class="headerbox linedRow">';
        foreach ($ids as $user) {
            echo '<li>' . htmlspecialchars($user) . '</li>';
        }
        echo '</ul>';
    }
}

require HORDE_TEMPLATES . '/common-footer.inc';
