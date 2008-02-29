<?php
/**
 * $Horde: turba/browse.php,v 1.76.2.25.2.1 2008/02/15 16:44:11 chuck Exp $
 *
 * Turba: Copyright 2000-2005 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you did
 * not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('TURBA_BASE', dirname(__FILE__));
require_once TURBA_BASE . '/lib/base.php';
require_once TURBA_BASE . '/lib/Object.php';
require_once TURBA_BASE . '/lib/List.php';
require_once TURBA_BASE . '/lib/ListView.php';

// Sort out the sorting values.
if (($sortby = Util::getFormData('sortby')) !== null) {
    if ($sortby == 'name' && $prefs->getValue('name_format') == 'last_first') {
        $sortby = 'lastname';
    }
    $prefs->setValue('sortby', $sortby);
}
if (($sortdir = Util::getFormData('sortdir')) !== null) {
    $prefs->setValue('sortdir', $sortdir);
}

$title = _("Address Book Listing");

if (!$browse_source_count && Util::getFormData('key') != '**search') {
    $notification->push(_("There are no browseable address books."), 'horde.warning');
} else {
    $driver = &Turba_Driver::singleton($default_source);
    if (is_a($driver, 'PEAR_Error')) {
        $notification->push(sprintf(_("Failed to access the address book: %s"), $driver->getMessage()), 'horde.error');
        unset($driver);
    }
}

if (isset($driver)) {
    $actionID = Util::getFormData('actionID');

    // Run through the action handlers.
    switch ($actionID) {
    case 'delete':
        $keys = Util::getFormData('objectkeys');
        if (is_array($keys)) {
            $key = Util::getFormData('key', false);
            if ($key && $key != '**search') {
                // We are removing a contact from a list.
                $errorCount = 0;
                $list = &$driver->getObject($key);
                foreach ($keys as $sourceKey) {
                    list($objectSource, $objectKey) = explode(':', $sourceKey, 2);
                    if (strpos($objectKey, ':')) {
                        list($objectOwner, $objectKey) = explode(':', $objectKey, 2);
                        $objectSource .= ':' . $objectOwner;
                    }
                    if (!$list->removeMember($objectKey, $objectSource)) {
                        $errorCount++;
                    }
                }
                if (!$errorCount) {
                    $notification->push(sprintf(_("Successfully removed %d contact(s) from list."), count($keys)), 'horde.success');
                } elseif (count($keys) == $errorCount) {
                    $notification->push(sprintf(_("Error removing %d contact(s) from list."), count($keys)), 'horde.error');
                } else {
                    $notification->push(sprintf(_("Error removing %d of %d requested contact(s) from list."), $errorCount, count($keys)), 'horde.error');
                }
                $list->store();
            } else {
                // We are deleting an object.
                $errorCount = 0;
                foreach ($keys as $sourceKey) {
                    list($objectSource, $objectKey) = explode(':', $sourceKey, 2);
                    if (strpos($objectKey, ':')) {
                        list($objectOwner, $objectKey) = explode(':', $objectKey, 2);
                    }
                    if (is_a($driver->delete($objectKey), 'PEAR_Error')) {
                        $errorCount++;
                    }
                }
                if (!$errorCount) {
                    $notification->push(sprintf(_("Successfully deleted %d contact(s)."), count($keys)), 'horde.success');
                } elseif (count($keys) == $errorCount) {
                    $notification->push(sprintf(_("Error deleting %d contact(s)."), count($keys)), 'horde.error');
                } else {
                    $notification->push(sprintf(_("Error deleting %d of %d requested contacts(s)."), $errorCount, count($keys)), 'horde.error');
                }
            }
        }
        break;

    case 'move':
    case 'copy':
        $keys = Util::getFormData('objectkeys');
        if (is_array($keys) && $keys) {
            // If we have data, try loading the target address book driver.
            $targetSource = Util::getFormData('targetAddressbook');
            $targetDriver = &Turba_Driver::singleton($targetSource);
            $max_contacts = Turba::hasPermission($targetSource . ':max_contacts', 'source');
            if (is_a($targetDriver, 'PEAR_Error')) {
                $notification->push(sprintf(_("Failed to access the address book: %s"), $targetDriver->getMessage()), 'horde.error');
            } elseif ($max_contacts !== true &&
                      $max_contacts <= $targetDriver->countContacts()) {
                $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d contacts in \"%s\"."), $max_contacts, $cfgSources[$targetSource]['title']), ENT_COMPAT, NLS::getCharset());
                if (!empty($conf['hooks']['permsdenied'])) {
                    $message = Horde::callHook('_perms_hook_denied', array('turba:max_contacts'), 'horde', $message);
                }
                $notification->push($message, 'horde.error', array('content.raw'));
            } else {
                foreach ($keys as $sourceKey) {
                    // Split up the key into source and object ids.
                    list($objectSource, $objectKey) = explode(':', $sourceKey, 2);
                    if (strpos($objectKey, ':')) {
                        list($objectOwner, $objectKey) = explode(':', $objectKey, 2);
                        $objectSource .= ':' . $objectOwner;
                    }

                    // Ignore this entry if the target is the same as the
                    // source.
                    if ($objectSource == $targetDriver->name &&
                        !$targetDriver->usingShares) {
                        continue;
                    }

                    // Try and load the driver for the source.
                    $sourceDriver = &Turba_Driver::singleton($objectSource);
                    if (is_a($sourceDriver, 'PEAR_Error')) {
                        $notification->push(sprintf(_("Failed to access the address book: %s"), $sourceDriver->getMessage()), 'horde.error');
                    } else {
                        $object = &$sourceDriver->getObject($objectKey);
                        if (is_a($object, 'PEAR_Error')) {
                            $notification->push(sprintf(_("Failed to find object to be added: %s"), $object->getMessage()), 'horde.error');
                            continue;
                        } elseif ($object->isGroup()) {
                            if ($actionID == 'move') {
                                $notification->push(sprintf(_("\"%s\" was not moved because it is a list."), $object->getValue('name')), 'horde.warning');
                            } else {
                                $notification->push(sprintf(_("\"%s\" was not copied because it is a list."), $object->getValue('name')), 'horde.warning');
                            }
                            continue;
                        }

                        // Try adding to the target.
                        $objAttributes = array();
                        // Get the values through the Turba_Object class.
                        foreach ($targetDriver->getCriteria() as $info_key => $info_val) {
                            $objAttributes[$info_key] = $object->getValue($info_key);
                        }

                        if ($targetDriver->usingShares) {
                            $objAttributes['__owner'] = $targetDriver->share->get('uid');
                        }
                        $result = $targetDriver->add($objAttributes);

                        if (is_a($result, 'PEAR_Error')) {
                            $notification->push(sprintf(_("Failed to add %s to %s: %s"), $object->getValue('name'), $targetDriver->title, $result->getMessage()), 'horde.error');
                        } else {
                            $notification->push(sprintf(_("Successfully added %s to %s"), $object->getValue('name'), $targetDriver->title), 'horde.success');

                            // If we're moving objects, and we succeeded,
                            // delete them from the original source now.
                            if ($actionID == 'move') {
                                if (is_a($sourceDriver->delete($objectKey), 'PEAR_Error')) {
                                    $notification->push(sprintf(_("There was an error deleting \"%s\" from the source address book."), $object->getValue('name')), 'horde.error');
                                }
                            }
                        }
                    }
                }
            }
        }
        break;

    case 'add':
        // Add a contact to a list.
        $keys = Util::getFormData('objectkeys');
        $targetKey = Util::getFormData('targetList');
        if (empty($targetKey)) {
            break;
        }
        if (!Util::getFormData('targetNew')) {
            list($targetSource, $targetKey) = explode(':', $targetKey, 2);
            if (strpos($targetKey, ':')) {
                list($objectOwner, $targetKey) = explode(':', $targetKey);
                $targetSource = $targetSource . ':' . $objectOwner;
            }
            if (!isset($cfgSources[$targetSource])) {
                break;
            }
            $targetDriver = &Turba_Driver::singleton($targetSource);
            $target = &$targetDriver->getObject($targetKey);
            if (is_a($target, 'PEAR_Error')) {
                $notification->push($target, 'horde.error');
                break;
            }
        } else {
            $targetSource = Util::getFormData('targetAddressbook');
            $targetDriver = &Turba_Driver::singleton($targetSource);
        }

        if (!empty($target) && $target->isGroup()) {
            // Adding contact to an existing list.
            if (is_array($keys)) {
                $errorCount = 0;
                foreach ($keys as $sourceKey) {
                    list($objectSource, $objectKey) = explode(':', $sourceKey, 2);
                    if (strpos($objectKey, ':')) {
                        list($objectOwner, $objectKey) = explode(':', $objectKey, 2);
                        $objectSource .= ':' . $objectOwner;
                    }
                    if (!$target->addMember($objectKey, $objectSource)) {
                        $errorCount++;
                    }
                }
                if (!$errorCount) {
                    $notification->push(sprintf(_("Successfully added %d contact(s) to list."), count($keys)), 'horde.success');
                } elseif($errorCount == count($keys)) {
                    $notification->push(sprintf(_("Error adding %d contact(s) to list."), count($keys)), 'horde.error');
                } else {
                    $notification->push(sprintf(_("Error adding %d of %d requested contact(s) to list."), $errorCount, count($keys)), 'horde.error');
                }
                $target->store();
            }
        } else {
            // Check permissions.
            $max_contacts = Turba::hasPermission($default_source . ':max_contacts', 'source');
            if ($max_contacts !== true &&
                $max_contacts <= $driver->countContacts()) {
                $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d contacts in \"%s\"."), $max_contacts, $cfgSources[$default_source]['title']), ENT_COMPAT, NLS::getCharset());
                if (!empty($conf['hooks']['permsdenied'])) {
                    $message = Horde::callHook('_perms_hook_denied', array('turba:max_contacts'), 'horde', $message);
                }

                $notification->push($message, 'horde.error', array('content.raw'));
                break;
            }
            // Adding contact to a new list.
            $newList = array();
            if ($targetDriver->usingShares) {
                $newList['__owner'] = $targetDriver->share->get('uid');
            } else {
                $newList['__owner'] = Auth::getAuth();
            }
            $newList['__type'] = 'Group';
            $newList['name'] = $targetKey;

            $targetKey = $targetDriver->add($newList);
            $target = &$targetDriver->getObject($targetKey);
            if (!is_a($target, 'PEAR_Error') && $target->isGroup()) {
                $notification->push(sprintf(_("Successfully created the contact list \"%s\"."), $newList['name']), 'horde.success');
                if (is_array($keys)) {
                    $errorCount = 0;
                    foreach ($keys as $sourceKey) {
                        list($objectSource, $objectKey) = explode(':', $sourceKey, 2);
                        if (strpos($objectKey, ':')) {
                            list($objectOwner, $objectKey) = explode(':', $objectKey, 2);
                            $objectSource .= ':' . $objectOwner;
                        }
                        if (!$target->addMember($objectKey, $objectSource)) {
                            $errorCount++;
                        }
                    }
                    if (!$errorCount) {
                        $notification->push(sprintf(_("Successfully added %d contact(s) to list."), count($keys)), 'horde.success');
                    } elseif ($errorCount == count($keys)) {
                        $notification->push(sprintf(_("Error adding %d contact(s) to list."), count($keys)), 'horde.error');
                    } else {
                        $notification->push(sprintf(_("Error adding %d of %d requested contact(s) to list."), $errorCount, count($keys)), 'horde.error');
                    }
                    $target->store();
                }
            } else {
                $notification->push(_("There was an error creating a new list."), 'horde.error');
            }
        }
        break;
    }

    // We might get here from the search page but are not allowed to browse
    // the current address book.
    if ($actionID && empty($cfgSources[$default_source]['browse'])) {
        header('Location: ' . Horde::applicationUrl($prefs->getValue('initial_page'), true));
        exit;
    }
}

$templates = array();
if (isset($driver)) {
    $templates[] = '/browse/javascript.inc';

    // Read the columns to display from the preferences.
    $sources = Turba::getColumns();
    $columns = isset($sources[$default_source]) ? $sources[$default_source] : array();

    // Determine the name of the column to sort by.
    $sortcolumn = ($prefs->getValue('sortby') == 0 ||
                   !isset($columns[$prefs->getValue('sortby') - 1]))
        ? (($prefs->getValue('name_format') == 'first_last')
           ? 'name'
           : 'lastname')
        : $columns[$prefs->getValue('sortby') - 1];

    if (Util::getFormData('key')) {
        // We are displaying a list.
        $list = &$driver->getObject(Util::getFormData('key'));
        if (isset($list) && is_object($list) &&
            !is_a($list, 'PEAR_Error') && $list->isGroup()) {
            $title = sprintf(_("Contacts in list: %s"),
                             $list->getValue('name'));
            $templates[] = '/browse/header.inc';

            // Show List Members.
            if (!is_object($results = $list->listMembers($sortcolumn, $prefs->getValue('sortdir')))) {
                $notification->push(_("Failed to browse list"), 'horde.error');
            } else {
                if ($results->count() != $list->count()) {
                    $notification->push(sprintf(_("There are %d contact(s) in this list that are not viewable to you"),
                                                ($list->count() - $results->count())), 'horde.message');
                }
                $view = &new Turba_ListView($results);
                $view->setType('list');
            }
        } else {
            $notification->push(_("There was an error displaying the list"), 'horde.error');
        }
    } else {
        // We are displaying an address book.
        $title = $cfgSources[$default_source]['title'];
        $templates[] = '/browse/header.inc';
        if (empty($cfgSources[$default_source]['browse'])) {
            $notification->push(_("Your default address book is not browseable."), 'horde.warning');
        } else {
            if (Util::getFormData('show', 'all') == 'contacts') {
                // Show Contacts.
                $results = $driver->search(array('__type' => 'Object'),
                                           $sortcolumn,
                                           'AND',
                                           $prefs->getValue('sortdir'),
                                           $columns);
            } elseif (Util::getFormData('show', 'all') == 'lists') {
                // Show Lists.
                $results = $driver->search(array('__type' => 'Group'),
                                           $sortcolumn,
                                           'AND',
                                           $prefs->getValue('sortdir'),
                                           $columns);
            } else {
                // Show All.
                $results = $driver->search(array(),
                                           $sortcolumn,
                                           'AND',
                                           $prefs->getValue('sortdir'),
                                           $columns);
            }
            if (!is_object($results)) {
                $notification->push(_("Failed to browse the directory"), 'horde.error');
            } elseif (is_a($results, 'PEAR_Error')) {
                $notification->push($results, 'horde.error');
            } else {
                $view = &new Turba_ListView($results);
                $view->setType('directory');
            }
        }
    }
} else {
    $templates[] = '/browse/header.inc';
}

require TURBA_TEMPLATES . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';
foreach ($templates as $template) {
    require TURBA_TEMPLATES . $template;
}

if (isset($view) && is_object($view)) {
    $view->display();
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
