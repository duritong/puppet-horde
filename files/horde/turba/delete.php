<?php
/**
 * $Horde: turba/delete.php,v 1.29.4.5 2007/01/02 13:55:18 jan Exp $
 *
 * Copyright 2000-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('TURBA_BASE', dirname(__FILE__));
require_once TURBA_BASE . '/lib/base.php';

$source = Util::getFormData('source');
$key = Util::getFormData('key');
$driver = &Turba_Driver::singleton($source);

if ($conf['documents']['type'] != 'none') {
    $object = $driver->getObject($key);
    if (is_a($object, 'PEAR_Error')) {
        $notification->push($object->getMessage(), 'horde.error');
        header('Location: ' . Horde::applicationUrl($prefs->getValue('initial_page'), true));
        exit;
    }
    if (is_a($deleted = $object->deleteFiles(), 'PEAR_Error')) {
        $notification->push($deleted, 'horde.error');
        header('Location: ' . Horde::applicationUrl($prefs->getValue('initial_page'), true));
        exit;
    }
}

if (!is_a($result = $driver->delete($key), 'PEAR_Error')) {
    header('Location: ' . Util::getFormData('url', Horde::url($prefs->getValue('initial_page'), true)));
    exit;
}

$notification->push(sprintf(_("There was an error deleting this contact: %s"), $result->getMessage()), 'horde.error');
$title = _("Deletion failed");
require TURBA_TEMPLATES . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
