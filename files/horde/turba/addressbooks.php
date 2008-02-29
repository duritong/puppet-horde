<?php
/**
 * Turba script to manage the user's addressbook shares.
 *
 * Copyright 2005-2007 Michael J. Rubinsky <mrubinsk@horde.org>
 *
 * $Horde: turba/addressbooks.php,v 1.3.2.7 2007/01/02 13:55:18 jan Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you did
 * not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 */

@define('TURBA_BASE', dirname(__FILE__));
require_once TURBA_BASE . '/lib/base.php';

// Exit if this isn't an authenticated user, or if there's no source
// configured for shares.
if (!Auth::getAuth() || !$haveShare) {
    require TURBA_BASE . '/' . ($browse_source_count
                                ? basename($prefs->getValue('initial_page'))
                                : 'search.php');
    exit;
}

// Figure out why we are here.
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'add':
    $params = array(
        'sourceType' => $conf['shares']['source'],
        'shareName' => Util::getFormData('sharename')
    );

    $share = &Turba::createShare($params, false);
    if (is_a($share, 'PEAR_Error')) {
        $notification->push(sprintf(_("There was an error creating this address book: %s"), $share->getMessage()), 'horde.error');
    } else {
        // Created the share, now add it to the address books pref it we need
        // to.
        $addressbooks = $prefs->getValue('addressbooks');
        $shareKey = $conf['shares']['source'] . ':' . $share->get('uid');
        if ($addressbooks) {
            $addressbooks .= "\n" . $shareKey;
            $prefs->setValue('addressbooks', $addressbooks);
        }
        $notification->push(sprintf(_("The address book \"%s\" was created successfully."), $share->get('name')), 'horde.success');
    }
    header('Location: ' . Horde::applicationUrl('addressbooks.php', true));
    exit;

case 'delete':
    // Get the source type and uid
    list($type, $uid) = explode(':', Util::getFormData('deleteshare'), 2);
    $driver = &Turba_Driver::singleton($type);
    if (is_a($driver, 'PEAR_Error')) {
        $notification->push(sprintf(_("There was an error removing this address book: %s"), $driver->getMessage()), 'horde.error');
    } else {
        // We have a Turba_Driver, try to delete the address book.
        $result = $driver->deleteAll($uid);
        if (is_a($result, 'PEAR_Error')) {
            $notification->push(sprintf(_("There was an error removing this address book: %s"), $result->getMessage()), 'horde.error');
        } else {
            // Address book successfully deleted from backend, remove the
            // share.
            $result = Turba::deleteShare(Util::getFormData('deleteshare'));
            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("There was an error removing this address book: %s"), $result->getMessage()), 'horde.error');
            } else {
                $notification->push(sprintf(_("The address book \"%s\" was removed successfully."), $result), 'horde.success');
            }
        }
    }
    header('Location: ' . Horde::applicationUrl('addressbooks.php', true));
    exit;

case 'update':
    // Updating some info on an existing share.
    $shareName = Util::getFormData('editshare');
    $params = array(
        'name' => Util::getFormData('sharetitle'),
        'description' => Util::getFormData('description')
    );
    $result = Turba::updateShare($shareName, $params);
    if (is_a($result, 'PEAR_Error')) {
        $notification->push(sprintf(_("There was an error updating this address book: %s"), $result->getMessage()), 'horde.error');
    } else {
        $notification->push(sprintf(_("The address book \"%s\" was successfully updated."), $result), 'horde.success');
    }
    header('Location: ' . Horde::applicationUrl('addressbooks.php', true));
    exit;
}

// Get all the info we will need to display the page.
$mySources = array();
$myRemovable = array();

// Get the shares owned by the current user, and figure out what we will
// display the share name as to the user.
$shares = Turba::listShares(true);
foreach ($shares as $key => $value) {
    list($src, $user) = explode(':', $key, 2);
    if ($user == Auth::getAuth()) {
        $cfgSrcKey = $src;
    } else {
        $myRemovable[] = $key;
        $cfgSrcKey = $key;
    }
    // This is the 'display name' of the share.  This is so we can call the
    // user's own default share "My Address Book" (or whatever this source is
    // configured to be called in $cfgSources) but display the same share as
    // "Username's Address Book" (or similar) if the current user is not the
    // owner.
    $mySources[$key] = $cfgSources[$cfgSrcKey]['title'];
}

Horde::addScriptFile('popup.js', 'horde', true);
$title = _("My Address Books");
require TURBA_TEMPLATES . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';
require TURBA_TEMPLATES . '/addressbooks.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
