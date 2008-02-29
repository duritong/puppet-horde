<?php
/**
 * $Horde: turba/search.php,v 1.94.4.13 2007/01/02 13:55:18 jan Exp $
 *
 * Copyright 2000-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('TURBA_BASE', dirname(__FILE__));
require_once TURBA_BASE . '/lib/base.php';
require_once TURBA_BASE . '/lib/List.php';
require_once TURBA_BASE . '/lib/ListView.php';
require_once TURBA_BASE . '/lib/Object.php';

/* Verify if the search mode variable is passed in form or is
 * registered in the session. Always use basic search by default */
if (Util::getFormData('search_mode')) {
    $_SESSION['turba']['search_mode'] = Util::getFormData('search_mode');
}
if (!isset($_SESSION['turba']['search_mode'])) {
    $_SESSION['turba']['search_mode'] = 'basic';
}

/* Run search if there is one. */
$source = Util::getFormData('source', Turba::getDefaultAddressBook());
if (!isset($cfgSources[$source])) {
    reset($cfgSources);
    $source = key($cfgSources);
}

$criteria = Util::getFormData('criteria');
$val = Util::getFormData('val');
$driver = &Turba_Driver::singleton($source);
if (is_a($driver, 'PEAR_Error')) {
    $notification->push(sprintf(_("Failed to access the address book: %s"), $driver->getMessage()), 'horde.error');
    $map = array();
} else {
    $map = $driver->getCriteria();

    if ($_SESSION['turba']['search_mode'] == 'advanced') {
        $criteria = array();
        foreach ($map as $key => $value) {
            if ($key != '__key') {
                $val = Util::getFormData($key);
                if (strlen($val)) {
                    $criteria[$key] = $val;
                }
            }
        }
    }

    if ((is_array($criteria) && count($criteria)) || !empty($val)) {
        if (($_SESSION['turba']['search_mode'] == 'basic' &&
             is_object($results = $driver->search(array($criteria => $val)))) ||
            ($_SESSION['turba']['search_mode'] == 'advanced' &&
             is_object($results = $driver->search($criteria)))) {

            if (is_a($results, 'PEAR_Error')) {
                $notification->push($results, 'horde.error');
            } else {
                // Read the columns to display from the preferences.
                $sources = Turba::getColumns();
                $columns = isset($sources[$source]) ? $sources[$source] : array();
                $sortcolumn = ($prefs->getValue('sortby') == 0 ||
                               !isset($columns[$prefs->getValue('sortby') - 1]))
                    ? (($prefs->getValue('name_format') == 'first_last')
                       ? 'name'
                       : 'lastname')
                    : $columns[$prefs->getValue('sortby') - 1];

                $results->sort($sortcolumn, $prefs->getValue('sortdir'));

                $view = &new Turba_ListView($results);
                $view->setType('search');
            }
        } else {
            $notification->push(_("Failed to search the address book"), 'horde.error');
        }
    }
}

if ($_SESSION['turba']['search_mode'] == 'basic') {
    $title = _("Basic Search");
    $notification->push('document.directory_search.val.focus();', 'javascript');
} else {
    $title = _("Advanced Search");
    $notification->push('document.directory_search.name.focus();', 'javascript');
}

require TURBA_TEMPLATES . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';
require TURBA_TEMPLATES . '/browse/search.inc';
if ($_SESSION['turba']['search_mode'] == 'advanced') {
    require TURBA_TEMPLATES . '/browse/search_criteria.inc';
}
if (isset($view) && is_object($view)) {
    require TURBA_TEMPLATES . '/browse/javascript.inc';
    require TURBA_TEMPLATES . '/browse/header.inc';
    $view->display();
}
require $registry->get('templates', 'horde') . '/common-footer.inc';
