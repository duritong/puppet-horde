<?php
/**
 * $Horde: horde/services/prefs.php,v 1.19.2.11 2007/01/02 13:55:15 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once dirname(__FILE__) . '/../lib/core.php';
require_once 'Horde/Prefs/UI.php';

$registry = &Registry::singleton();

/* Figure out which application we're setting preferences for. */
$app = Util::getFormData('app', Prefs_UI::getDefaultApp());
$appbase = $registry->get('fileroot', $app);

/* See if we have a preferences group set. */
$group = Util::getFormData('group');

/* Load $app's base environment, but don't request that the app perform
 * authentication beyond Horde's. */
$authentication = 'none';
require_once $appbase . '/lib/base.php';

/* Set title. */
$title = sprintf(_("Options for %s"), $registry->get('name'));

/* Special case where we need the Identity object in Horde's prefs.php. */
if ($group == 'identities' && $app == 'horde') {
    require_once 'Horde/Identity.php';
    $identity = &Identity::singleton($app == 'horde' ? null : array($app, $app));
}

/* Load $app's preferences, if any. */
$prefGroups = array();
if (file_exists($appbase . '/config/prefs.php')) {
    require $appbase . '/config/prefs.php';
}

/* Load custom preference handlers for $app, if present. */
if (file_exists($appbase . '/lib/prefs.php')) {
    require_once $appbase . '/lib/prefs.php';
}

/* If there's only one prefGroup, just show it. */
if (empty($group) && count($prefGroups) == 1) {
    $group = array_keys($prefGroups);
    $group = array_pop($group);
}

if ($group == 'identities') {
    require_once 'Horde/Identity.php';
    $identity = &Identity::singleton($app == 'horde' ? null : array($app, $app));
    if ($app != 'horde') {
        if (Util::nonInputVar('prefGroups')) {
            $keepPrefGroups = $prefGroups;
            unset($prefGroups);
        }
        require HORDE_BASE . '/config/prefs.php';
        $horde_members = $prefGroups['identities']['members'];
        if (Util::nonInputVar('keepPrefGroups')) {
            $prefGroups = $keepPrefGroups;
        } else {
            unset($prefGroups);
        }
    }

    if (isset($horde_members)) {
        $prefGroups['identities']['members'] = array_merge($horde_members, $prefGroups['identities']['members']);
    }

    switch (Util::getFormData('actionID')) {
    case 'update_prefs':
        if ($prefs->isLocked('default_identity')) {
            $default = $identity->getDefault();
        } else {
            $default = Util::getPost('default_identity');
            $id = Util::getPost('identity');
            if ($id == -1) {
                $id = $identity->add();
            } elseif ($id == -2) {
                $prefGroups['identities']['members'] = array('default_identity');
            }
            $identity->setDefault($id);
        }
        if (Prefs_UI::handleForm($group, $identity)) {
            $result = $identity->verify();
            $identity->setDefault($default);
            if (is_a($result, 'PEAR_Error')) {
                $notification->push($result, 'horde.error');
            } else {
                $identity->save();
            }
        } else {
            $identity->setDefault($default);
            $identity->save();
        }
        unset($prefGroups);
        require $appbase . '/config/prefs.php';
        break;

    case 'delete_identity':
        $id = (int)Util::getFormData('id');
        $deleted_identity = $identity->delete($id);
        unset($_prefs['default_identity']['enum'][$id]);
        $notification->push(sprintf(_("The identity \"%s\" has been deleted."), $deleted_identity[0]['id']), 'horde.success');
        break;

    case 'change_default_identity':
        $default_identity = $identity->setDefault(Util::getFormData('id'));
        $identity->save();
        $notification->push(_("Your default identity has been changed."),
                            'horde.success');
        break;
    }
} elseif (Prefs_UI::handleForm($group, $prefs)) {
    require $appbase . '/config/prefs.php';
}

/* Show the UI. */
Prefs_UI::generateUI($group);

require $registry->get('templates', 'horde') . '/common-footer.inc';
