<?php
/**
 * Turba external API interface.
 *
 * $Horde: turba/lib/api.php,v 1.120.2.38.2.3 2008/01/09 22:15:04 chuck Exp $
 *
 * This file defines Turba's external API interface. Other applications can
 * interact with Turba through this API.
 *
 * @package Turba
 */

$_services['perms'] = array(
    'args' => array(),
    'type' => '{urn:horde}hashHash'
);

$_services['show'] = array(
    'link' => '%application%/display.php?source=|source|&key=|key|&uid=|uid|',
);

$_services['browse'] = array(
    'args' => array('path' => 'string', 'properties' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}hashHash',
);

$_services['sources'] = array(
    'args' => array('writeable' => 'boolean'),
    'type' => '{urn:horde}stringArray',
);

$_services['fields'] = array(
    'args' => array('source' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray',
);

$_services['list'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray',
);

$_services['listBy'] = array(
    'args' => array('action' => 'string', 'timestamp' => 'int'),
    'type' => '{urn:horde}stringArray',
);

$_services['getActionTimestamp'] = array(
    'args' => array('uid' => 'string', 'timestamp' => 'int'),
    'type' => 'int',
);

$_services['import'] = array(
    'args' => array('content' => 'string', 'contentType' => 'string', 'source' => 'string'),
    'type' => 'string',
);

$_services['export'] = array(
    'args' => array('uid' => 'string', 'contentType' => 'string'),
    'type' => 'string',
);

$_services['delete'] = array(
    'args' => array('uid' => 'string'),
    'type' => 'boolean',
);

$_services['replace'] = array(
    'args' => array('uid' => 'string', 'content' => 'string', 'contentType' => 'string'),
    'type' => 'boolean',
);

$_services['search'] = array(
    'args' => array('names' => '{urn:horde}stringArray',
                    'sources' => '{urn:horde}stringArray',
                    'fields' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray',
);

$_services['getContact'] = array(
    'args' => array('source' => 'string', 'objectId' => 'string'),
    'type' => '{urn:horde}stringArray',
);

$_services['getContacts'] = array(
    'args' => array('source' => 'string', 'objectIds' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray',
);

$_services['addField'] = array(
    'args' => array('address' => 'string', 'name' => 'string', 'field' => 'string', 'value' => 'string', 'source' => 'string'),
    'type' => '{urn:horde}stringArray',
);

$_services['deleteField'] = array(
    'args' => array('address' => 'string', 'field' => 'string', 'sources' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray',
);

$_services['getField'] = array(
    'args' => array('address' => 'string', 'field' => 'string', 'sources' => '{urn:horde}stringArray', 'strict' => 'boolean', 'multiple' => 'boolean'),
    'type' => '{urn:horde}stringArray',
);

$_services['getAllAttributeValues'] = array(
    'args' => array('field' => 'string', 'sources' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray',
);

$_services['getClientSource'] = array(
    'checkperms' => false,
    'args' => array(),
    'type' => 'string',
);

$_services['getClient'] = array(
    'checkperms' => false,
    'args' => array('objectId' => 'string'),
    'type' => '{urn:horde}stringArray',
);

$_services['getClients'] = array(
    'checkperms' => false,
    'args' => array('objectIds' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray',
);

$_services['addClient'] = array(
    'args' => array('attributes' => '{urn:horde}stringArray'),
    'type' => 'string',
);

$_services['updateClient'] = array(
    'args' => array('objectId' => 'string', 'attributes' => '{urn:horde}stringArray'),
    'type' => 'string',
);

$_services['deleteClient'] = array(
    'args' => array('objectId' => 'string'),
    'type' => '{urn:horde}stringArray',
);

$_services['searchClients'] = array(
    'checkperms' => false,
    'args' => array('names' => '{urn:horde}stringArray', 'fields' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray',
);

$_services['commentCallback'] = array(
    'args' => array('id' => 'string'),
    'type' => 'string'
);

$_services['hasComments'] = array(
    'args' => array(),
    'type' => 'boolean'
);

function _turba_commentCallback($id)
{
    if (!$GLOBALS['conf']['comments']['allow']) {
        return false;
    }

    @define('TURBA_BASE', dirname(__FILE__) . '/..');
    require_once TURBA_BASE . '/lib/base.php';
    global $cfgSources;

    list($source, $key) = explode('.', $id, 2);
    if (isset($cfgSources[$source])) {
        $driver = &Turba_Driver::singleton($source);
        if (!is_a($driver, 'PEAR_Error')) {
            $object = $driver->getObject($key);
            if (!is_a($object, 'PEAR_Error')) {
                return $object->getValue('name');
            }
        }
    }

    return false;
}

function _turba_hasComments()
{
    return $GLOBALS['conf']['comments']['allow'];
}

function _turba_perms()
{
    static $perms = array();
    if (!empty($perms)) {
        return $perms;
    }

    @define('TURBA_BASE', dirname(__FILE__) . '/..');
    require_once TURBA_BASE . '/lib/base.php';
    require TURBA_BASE . '/config/sources.php';

    $perms['tree']['turba']['sources'] = false;
    $perms['title']['turba:sources'] = _("Sources");

    // Run through every contact source.
    foreach ($cfgSources as $source => $curSource) {
        $perms['tree']['turba']['sources'][$source] = false;
        $perms['title']['turba:sources:' . $source] = $curSource['title'];
        $perms['tree']['turba']['sources'][$source]['max_contacts'] = false;
        $perms['title']['turba:sources:' . $source . ':max_contacts'] = _("Maximum Number of Contacts");
        $perms['type']['turba:sources:' . $source . ':max_contacts'] = 'int';
    }

    return $perms;
}

function _turba_sources($writeable = false)
{
    require_once dirname(__FILE__) . '/base.php';

    $addressbooks = Turba::getAddressBooks($writeable ? PERMS_EDIT : PERMS_READ);
    foreach ($addressbooks as $addressbook => $config) {
        $addressbooks[$addressbook] = $config['title'];
    }

    return $addressbooks;
}

function _turba_fields($source = '')
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources, $attributes;

    if (empty($source) || !isset($cfgSources[$source])) {
        return PEAR::raiseError(_("Invalid address book"), 'horde.error', null, null, $source);
    }

    $fields = array();
    foreach ($cfgSources[$source]['map'] as $field_name => $null) {
        if (substr($field_name, 0, 2) != '__') {
            $fields[$field_name] = array('name' => $field_name,
                                         'type' => $attributes[$field_name]['type'],
                                         'label' => $attributes[$field_name]['label'],
                                         'search' => in_array($field_name, $cfgSources[$source]['search']));
        }
    }

    return $fields;
}

/**
 * Browses through Turba's object tree.
 *
 * @param string $path       The path of the tree to browse.
 * @param array $properties  The item properties to return. Defaults to 'name',
 *                           'icon', and 'browseable'.
 *
 * @return array  Content of the specified path.
 */
function _turba_browse($path = '', $properties = array())
{
    function _modified($uid)
    {
        $modified = _turba_getActionTimestamp($uid, 'modify');
        if (empty($modified)) {
            $modified = _turba_getActionTimestamp($uid, 'add');
        }
        return $modified;
    }

    require_once dirname(__FILE__) . '/base.php';
    global $registry, $cfgSources;

    // Default properties.
    if (!$properties) {
        $properties = array('name', 'icon', 'browseable');
    }

    // Massage the path into the form we want.
    if (substr($path, 0, 5) == 'turba') {
        $path = substr($path, 5);
    }
    if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1);
    }
    if (substr($path, -1) == '/') {
        $path = substr($path, 0, -1);
    }

    // Get a list of address books.
    $addressbooks = Turba::getAddressBooks();

    if (empty($path)) {
        $results = array();
        foreach ($addressbooks as $addressbook => $info) {
            if (in_array('name', $properties)) {
                $results['turba/' . $addressbook]['name'] = $info['title'];
            }
            if (in_array('icon', $properties)) {
                $results['turba/' . $addressbook]['icon'] = $registry->getImageDir() . '/turba.png';
            }
            if (in_array('browseable', $properties)) {
                $results['turba/' . $addressbook]['browseable'] = !empty($cfgSources[$addressbook]['browse']);
            }
            if (in_array('contenttype', $properties)) {
                $results['turba/' . $addressbook]['contenttype'] = 'httpd/unix-directory';
            }
            if (in_array('contentlength', $properties)) {
                $results['turba/' . $addressbook]['contentlength'] = 0;
            }
            if (in_array('modified', $properties)) {
                $results['turba/' . $addressbook]['modified'] = time();
            }
            if (in_array('created', $properties)) {
                $results['turba/' . $addressbook]['created'] = 0;
            }
        }
        return $results;
    } elseif (isset($addressbooks[$path])) {
        // Load the Turba driver.
        $driver = &Turba_Driver::singleton($path);
        if (is_a($driver, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $cfgSources[$path]);
        }

        $contacts = $driver->search(array());
        if (is_a($contacts, 'PEAR_Error')) {
            return $contacts;
        }

        $results = array();
        $contacts->reset();
        while ($contact = $contacts->next()) {
            $key = 'turba/' . $contact->getSource() . '/' . $contact->getValue('__key');
            if (in_array('name', $properties)) {
                $results[$key]['name'] = Turba::formatName($contact);
            }
            if (in_array('icon', $properties)) {
                $results[$key]['icon'] = $registry->getImageDir('horde') . '/mime/vcard.png';
            }
            if (in_array('browseable', $properties)) {
                $results[$key]['browseable'] = false;
            }
            if (in_array('contenttype', $properties)) {
                $results[$key]['contenttype'] = 'text/x-vcard';
            }
            if (in_array('contentlength', $properties)) {
                $data = _turba_export($contact->getValue('__uid'), 'text/x-vcard', $contact->getSource());
                if (is_a($data, 'PEAR_Error')) {
                    $data = '';
                }
                $results[$key]['contentlength'] = strlen($data);
            }
            if (in_array('modified', $properties)) {
                $results[$key]['modified'] = _modified($contact->getValue('__uid'));
            }
            if (in_array('created', $properties)) {
                $results[$key]['created'] = _turba_getActionTimestamp($contact->getValue('__uid'), 'add');
            }
        }
        return $results;
    } else {
        $parts = explode('/', $path);
        if (count($parts) == 2 && isset($addressbooks[$parts[0]])) {
            // Load the Turba driver.
            $driver = &Turba_Driver::singleton($parts[0]);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $cfgSources[$parts]);
            }

            $contact = &$driver->getObject($parts[1]);
            if (is_a($contact, 'PEAR_Error')) {
                return $contact;
            }

            $result = array('data' => _turba_export($contact->getValue('__uid'), 'text/x-vcard', $contact->getSource()),
                            'mimetype' => 'text/x-vcard');
            $modified = _modified($contact->getValue('__uid'));
            if (!empty($modified)) {
                $result['mtime'] = $modified;
            }
            return $result;
        }
    }

    return PEAR::raiseError($path . ' does not exist or permission denied');
}

/**
 * Returns an array of UIDs for all contacts that the current user is
 * authorized to see.
 *
 * @return array  An array of UIDs for all contacts the user can access.
 */
function _turba_list($source = null)
{
    require_once dirname(__FILE__) . '/base.php';

    global $cfgSources, $prefs;

    /* Get default address book from user preferences. */
    if (empty($source)) {
        $source = $prefs->getValue('default_dir');
        /* On new installations default_dir is not set. */
        /* Use first source instead. */
        if (empty($source)) {
            $source = array_keys($cfgSources);
            $source = $source[0];
        }
    }

    if (empty($source) || !isset($cfgSources[$source])) {
        return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
    }

    $storage = &Turba_Driver::singleton($source);
    if (is_a($storage, 'PEAR_Error')) {
        return PEAR::raiseError(sprintf(_("Connection failed: %s"), $storage->getMessage()), 'horde.error', null, null, $source);
    }

    $results = $storage->search(array());

    if (is_a($results, 'PEAR_Error')) {
        return PEAR::raiseError(sprintf(_("Error searching the address book: %s"), $results->getMessage()), 'horde.error', null, null, $source);
    }

    $r = array();
    foreach ($results->objects as $o) {
        $r[] = $o->getValue('__uid');
    }

    return $r;
}

/**
 * Returns an array of UIDs for contacts that have had $action happen
 * since $timestamp.
 *
 * @param string  $action     The action to check for - add, modify, or delete.
 * @param integer $timestamp  The time to start the search.
 * @param string  $source     The source for which to retrieve the history.
 *
 * @return array  An array of UIDs matching the action and time criteria.
 */
function _turba_listBy($action, $timestamp, $source = null)
{
    global $prefs, $cfgSources;
    require_once dirname(__FILE__) . '/base.php';

    // FIXME! Turba stores username or owner in history, not the address book source
    // So we have to search for username rather than source.
    $source = Auth::getAuth();
    /*
    // Get default address book from user preferences.
    if (empty($source)) {
        $source = $prefs->getValue('default_dir');
        // On new installations default_dir is not set.
        // Use first source instead.
        if (empty($source)) {
            $source = array_keys($cfgSources);
            $source = $source[0];
        }
    }

    if (empty($source) || !isset($cfgSources[$source])) {
        return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
    }
    */
    $history = &Horde_History::singleton();
    $histories = $history->getByTimestamp('>', $timestamp, array(array('op' => '=', 'field' => 'action', 'value' => $action)), 'turba:' . $source);
    if (is_a($histories, 'PEAR_Error')) {
        return $histories;
    }

    // Strip leading turba:username:.
    return preg_replace('/^([^:]*:){2}/', '', array_keys($histories));
}

/**
 * Returns the timestamp of an operation for a given uid an action.
 *
 * @param string $uid     The uid to look for.
 * @param string $action  The action to check for - add, modify, or delete.
 * @param string $source  The source for which to retrieve the history.
 *
 * @return integer  The timestamp for this action.
 */
function _turba_getActionTimestamp($uid, $action, $source = null)
{
    global $prefs;
    require_once dirname(__FILE__) . '/base.php';
    $history = &Horde_History::singleton();

    // FIXME! Turba stores username or owner in history, not the address book source
    // So we have to search for username rather than adre
    $source = Auth::getAuth();
    /*
    // Get default address book from user preferences.
    if (empty($source)) {
        $source = $prefs->getValue('default_dir');
        // On new installations default_dir is not set.
        // Use first source instead.
        if (empty($source)) {
            $source = array_keys($cfgSources);
            $source = $source[0];
        }
    }

    if (empty($source) || !isset($cfgSources[$source])) {
        return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
    }
    */
    return $history->getActionTimestamp('turba:' . $source . ':' . $uid, $action);
}

/**
 * Import a contact represented in the specified contentType.
 *
 * @param string $content      The content of the contact.
 * @param string $contentType  What format is the data in? Currently supports
 *                             array and text/x-vcard.
 * @param string $source       The source into which the contact will be
 *                             imported.
 *
 * @return string  The new UID, or false on failure.
 */
function _turba_import($content, $contentType = 'array', $source = null)
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources, $prefs;

    /* Get default address book from user preferences. */
    if (empty($source)) {
        $source = $prefs->getValue('default_dir');
        /* On new installations default_dir is not set. */
        /* Use first source instead. */
        if (empty($source)) {
            $source = array_keys($cfgSources);
            $source = $source[0];
        }
    }

    // Check existance of and permissions on the specified source.
    if (!isset($cfgSources[$source])) {
        return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.warning');
    }

    $driver = &Turba_Driver::singleton($source);
    if (is_a($driver, 'PEAR_Error')) {
        return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
    }

    if (!$driver->hasPermission(PERMS_EDIT)) {
        return PEAR::raiseError(_("Permission Denied"), 'horde.error', null, null, $source);
    }

    if (!is_a($content, 'Horde_iCalendar_vcard')) {
        switch ($contentType) {
        case 'array':
            break;

        case 'text/x-vcard':
            require_once 'Horde/iCalendar.php';
            $iCal = new Horde_iCalendar();
            if (!$iCal->parsevCalendar($content)) {
                return PEAR::raiseError(_("There was an error importing the iCalendar data."));
            }
            switch ($iCal->getComponentCount()) {
            case 0:
                return PEAR::raiseError(_("No vCard data was found."));

            case 1:
                $content = $iCal->getComponent(0);
                break;

            default:
                $ids = array();
                foreach ($iCal->getComponents() as $c) {
                    if (is_a($c, 'Horde_iCalendar_vcard')) {
                        $content = $driver->toHash($c);
                        $result = $driver->search($content);
                        if (is_a($result, 'PEAR_Error')) {
                            return $result;
                        } elseif ($result->count() > 0) {
                            continue;
                        }
                        $result = $driver->add($content);
                        if (is_a($result, 'PEAR_Error')) {
                            return $result;
                        }
                        $ids[] = $result;
                    }
                }
                return $ids;
            }
            break;

        default:
            return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));
        }
    }

    if (is_a($content, 'Horde_iCalendar_vcard')) {
        $content = $driver->toHash($content);
    }

    $result = $driver->search($content);
    if (is_a($result, 'PEAR_Error')) {
        return $result;
    } elseif ($result->count() > 0) {
        return PEAR::raiseError(_("Already Exists"), 'horde.message', null, null, $source);
    }

    $result = $driver->add($content);
    if (is_a($result, 'PEAR_Error')) {
        return $result;
    }

    $object = &$driver->getObject($result);
    return is_a($object, 'PEAR_Error') ? $object : $object->getValue('__uid');
}

/**
 * Export a contact, identified by UID, in the requested contentType.
 *
 * @param string $uid         Identify the contact to export.
 * @param mixed $contentType  What format should the data be in?
 *                            Either a string with one of:
 *                            <pre>
 *                             text/vcard
 *                             text/x-vcard
 *                            </pre>
 *                            The first produces a vcard3.0 (rfc2426),
 *                            the second produces a vcard in old 2.1 format
 *                            defined by imc.org
 * @return string  The requested data.
 */
function _turba_export($uid, $contentType, $source = null)
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources, $prefs;

    /* Get default address book from user preferences. */
    if (empty($source)) {
        $source = $prefs->getValue('default_dir');
        /* On new installations default_dir is not set. */
        /* Use first source instead. */
        if (empty($source)) {
            $source = array_keys($cfgSources);
            $source = $source[0];
        }
    }

    if (empty($source) || !isset($cfgSources[$source])) {
        return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
    }

    if (empty($uid)) {
        return PEAR::raiseError(_("Invalid ID"), 'horde.error', null, null, $source);
    }

    $driver = &Turba_Driver::singleton($source);
    if (is_a($driver, 'PEAR_Error')) {
        return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
    }

    if (!$driver->hasPermission(PERMS_READ)) {
        return PEAR::raiseError(_("Permission Denied"), 'horde.error', null, null, $source);
    }

    $result = $driver->search(array('__uid' => $uid));
    if (is_a($result, 'PEAR_Error')) {
        return $result;
    } elseif ($result->count() == 0) {
        return PEAR::raiseError(_("Object not found"), 'horde.error', null, null, $source);
        return true;
    } elseif ($result->count() > 1) {
        return PEAR::raiseError("Internal Horde Error: multiple turba objects with same objectId.", 'horde.error', null, null, $source);
    }

    $version = '3.0';
    switch ($contentType) {
    case 'text/x-vcard;version=2.1':
    case 'text/x-vcard':
        $version = '2.1';
    case 'text/vcard':
        require_once 'Horde/iCalendar.php';

        $export = '';
        foreach ($result->objects as $obj) {
            $vcard = $driver->tovCard($obj, $version);
            $vcard->setAttribute('VERSION', $version);
            /* vCards are not enclosed in BEGIN:VCALENDAR..END:VCALENDAR. */
            /* Export the individual cards instead. */
            $export .= $vcard->exportvCalendar();
        }
        return $export;
    }

    return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));

}

/**
 * Deletes a contact identified by UID.
 *
 * @param string|array $uid  Identify the contact to delete, either a single
 *                           UID or an array.
 *
 * @return boolean  Success or failure.
 */
function _turba_delete($uid, $source = null)
{
    // Handle an array of UIDs for convenience of deleting multiple contacts
    // at once.
    if (is_array($uid)) {
        foreach ($uid as $g) {
            $result = _turba_delete($uid, $source);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        return true;
    }

    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources, $prefs;

    /* Get default address book from user preferences. */
    if (empty($source)) {
        $source = $prefs->getValue('default_dir');
        /* On new installations default_dir is not set. */
        /* Use first source instead. */
        if (empty($source)) {
            $source = array_keys($cfgSources);
            $source = $source[0];
        }
    }

    if (empty($source) || !isset($cfgSources[$source])) {
        return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
    }

    if (empty($uid)) {
        return PEAR::raiseError(_("Invalid ID"), 'horde.error', null, null, $source);
    }

    $driver = &Turba_Driver::singleton($source);
    if (is_a($driver, 'PEAR_Error')) {
        return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
    }

    if (!$driver->hasPermission(PERMS_EDIT)) {
        return PEAR::raiseError(_("Permission Denied"), 'horde.error', null, null, $source);
    }

    // If the objectId isn't in $source in the first place, just return
    // true. Otherwise, try to delete it and return success or failure.
    $result = $driver->search(array('__uid' => $uid));
    if (is_a($result, 'PEAR_Error')) {
        return $result;
    } elseif ($result->count() == 0) {
        return true;
    } else {
        $r = $result->objects[0];
        return $driver->delete($r->getValue('__key'));
    }
}

/**
 * Replaces the contact identified by UID with the content represented in the
 * specified contentType.
 *
 * @param string $uid          Idenfity the contact to replace.
 * @param string $content      The content of the contact.
 * @param string $contentType  What format is the data in? Currently supports
 *                             array and text/x-vcard.
 *
 * @return boolean  Success or failure.
 */
function _turba_replace($uid, $content, $contentType, $source = null)
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources, $prefs;

    /* Get default address book from user preferences. */
    if (empty($source)) {
        $source = $prefs->getValue('default_dir');
        /* On new installations default_dir is not set. */
        /* Use first source instead. */
        if (empty($source)) {
            $source = array_keys($cfgSources);
            $source = $source[0];
        }
    }

    if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources) || !isset($cfgSources[$source])) {
        return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.warning');
    }

    if (empty($uid)) {
        return PEAR::raiseError(_("Invalid objectId"), 'horde.error', null, null, $source);
    }

    // Check permissions.
    $driver = &Turba_Driver::singleton($source);
    if (is_a($driver, 'PEAR_Error')) {
        return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
    }
    if (!$driver->hasPermission(PERMS_EDIT)) {
        return PEAR::raiseError(_("Permission denied"));
    }
    $result = $driver->search(array('__uid' => $uid));
    if (is_a($result, 'PEAR_Error')) {
        return $result;
    } elseif (!$result->count()) {
        return PEAR::raiseError(_("Object not found"), 'horde.error', null, null, $source);
    } elseif ($result->count() > 1) {
        return PEAR::raiseError("Internal Horde Error: multiple turba objects with same objectId.", 'horde.error', null, null, $source);
    }
    $object = $result->objects[0];

    switch ($contentType) {
    case 'array':
        break;

    case 'text/x-vcard':
        require_once 'Horde/iCalendar.php';
        $iCal = new Horde_iCalendar();
        if (!$iCal->parsevCalendar($content)) {
            return PEAR::raiseError(_("There was an error importing the iCalendar data."));
        }

        switch ($iCal->getComponentCount()) {
        case 0:
            return PEAR::raiseError(_("No vCard data was found."));

        case 1:
            $content = $iCal->getComponent(0);
            $content = $driver->toHash($content);
            break;

        default:
            return PEAR::raiseError(_("Only one vcard supported."));
        }
        break;

    default:
        return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));
    }

    foreach ($content as $attribute => $value) {
        if ($attribute != '__key') {
            $object->setValue($attribute, $value);
        }
    }

    return $object->store();
}

function _turba_search($names = array(), $sources = array(), $fields = array())
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources, $attributes, $prefs;

    if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
        return array();
    }

    if (!count($sources)) {
        $sources = array(key($cfgSources));
    }

    // Read the columns to display from the preferences.
    $sort_columns = Turba::getColumns();

    $results = array();
    $seen = array();
    foreach ($sources as $source) {
        // Skip invalid sources.
        if (!isset($cfgSources[$source])) {
            continue;
        }

        // Skip sources that aren't browseable if the search is empty.
        if (!count($names) && empty($cfgSources[$source]['browse'])) {
            continue;
        }

        $driver = &Turba_Driver::singleton($source);
        if (is_a($driver, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
        }

        // Determine the name of the column to sort by.
        $columns = isset($sort_columns[$source])
            ? $sort_columns[$source] : array();
        $sortcolumn = ($prefs->getValue('sortby') == 0 ||
                       !isset($sort_columns[$prefs->getValue('sortby') - 1]))
            ? (($prefs->getValue('name_format') == 'first_last')
               ? 'name'
               : 'lastname')
            : $sort_columns[$prefs->getValue('sortby') - 1];

        foreach ($names as $name) {
            $criteria = array();
            if (isset($fields[$source])) {
                foreach ($fields[$source] as $field) {
                    $criteria[$field] = trim($name);
                }
            }
            if (count($criteria) == 0) {
                $criteria['name'] = trim($name);
            }
            $res = $driver->search($criteria, $sortcolumn, 'OR');

            if (!is_a($res, 'Turba_List')) {
                continue;
            }

            while ($ob = $res->next()) {
                if (!$ob->isGroup()) {
                    /* Not a group. */
                    $att = array('__key' => $ob->getValue('__key'));
                    foreach ($ob->driver->getCriteria() as $info_key => $info_val) {
                        $att[$info_key] = $ob->getValue($info_key);
                    }
                    $email = array();
                    foreach (array_keys($att) as $key) {
                        if ($ob->getValue($key) && isset($attributes[$key]) &&
                            $attributes[$key]['type'] == 'email') {
                            $email[] = $ob->getValue($key);
                        }
                    }
                    if (count($email)) {
                        for ($i = 0; $i < count($email); $i++) {
                            $seen_key = trim(String::lower($ob->getValue('name'))) . '/' . trim(String::lower($email[$i]));
                            if (!empty($seen[$seen_key])) {
                                continue;
                            }
                            $seen[$seen_key] = true;
                            if (!isset($results[$name])) {
                                $results[$name] = array();
                            }
                            $results[$name][] = array_merge($att,
                                                    array('id' => $att['__key'],
                                                          'name' => Turba::formatName($ob),
                                                          'email' => $email[$i],
                                                          '__type' => 'Object',
                                                          'source' => $source));
                        }
                    } else {
                        if (!isset($results[$name])) {
                            $results[$name] = array();
                        }
                        $results[$name][] = array_merge($att,
                                                array('id' => $att['__key'],
                                                      'name' => Turba::formatName($ob),
                                                      'email' => null,
                                                      '__type' => 'Object',
                                                      'source' => $source));
                    }
                } else {
                    /* Is a distribution list. */
                    $listatt = $ob->getAttributes();
                    $seeninlist = array();
                    $members = $ob->listMembers();
                    $listName = $ob->getValue('name');

                    if (is_a($members, 'Turba_List')) {
                        if ($members->count() > 0) {
                            if (!isset($results[$name])) {
                                $results[$name] = array();
                            }
                            $emails = array();
                            while ($ob = $members->next()) {
                                $att = $ob->getAttributes();
                                foreach ($att as $key => $value) {
                                    if (!empty($value) && isset($attributes[$key]) &&
                                        $attributes[$key]['type'] == 'email' &&
                                        empty($seeninlist[trim(String::lower($att['name'])) . trim(String::lower($value))])) {

                                        $emails[] = $value;
                                        $seeninlist[trim(String::lower($att['name'])) . trim(String::lower($value))] = true;
                                    }
                                }
                            }
                            $results[$name][] = array('name' => $listName, 'email' => implode(', ', $emails), 'id' => $listatt['__key'], 'source' => $source);
                        }
                    }
                }
            }
        }
    }

    return $results;
}

function _turba_getContact($source = '', $objectId = '')
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources;

    if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
        return array();
    }

    if (isset($cfgSources[$source])) {
        $driver = &Turba_Driver::singleton($source);
        if (is_a($driver, 'PEAR_Error')) {
            return $driver;
        }

        $object = $driver->getObject($objectId);
        if (is_a($object, 'PEAR_Error')) {
            return $object;
        }

        $attributes = array();
        foreach ($cfgSources[$source]['map'] as $field => $map) {
            $attributes[$field] = $object->getValue($field);
        }
        return $attributes;
    }

    return array();
}

function _turba_getContacts($source = '', $objectIds = array())
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources;
    $results = array();
    if (!is_array($objectIds)) {
        $objectIds = array($objectIds);
    }

    if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
        return array();
    }

    if (isset($cfgSources[$source])) {
        $driver = &Turba_Driver::singleton($source);
        if (is_a($driver, 'PEAR_Error')) {
            return $driver;
        }

        $objects = $driver->getObjects($objectIds);
        if (is_a($objects, 'PEAR_Error')) {
            return $objects;
        }

        foreach ($objects as $object) {
            $attributes = array();
            foreach ($cfgSources[$source]['map'] as $field => $map) {
                $attributes[$field] = $object->getValue($field);
            }
            $results[] = $attributes;
        }
    }

    return $results;
}

function _turba_getAllAttributeValues($field = '', $sources = array())
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources;

    if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
        return array();
    }

    if (!count($sources)) {
        $sources = array(key($cfgSources));
    }

    $results = array();
    foreach ($sources as $source) {
        if (isset($cfgSources[$source])) {
            $driver = &Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
            }

            $res = $driver->search(array());
            if (!is_a($res, 'Turba_List')) {
                return PEAR::raiseError(_("Search failed"), 'horde.error', null, null, $source);
            }

            while ($ob = $res->next()) {
                if ($ob->hasValue($field)) {
                    $results[$ob->getValue('source') . ':' . $ob->getValue('__key')] = array(
                        'name' => $ob->getValue('name'),
                        'email' => $ob->getValue('email'),
                        $field => $ob->getValue($field));
                }
            }
        }
    }

    return $results;
}

function _turba_getClientSource()
{
    return !empty($GLOBALS['conf']['client']['addressbook']) ? $GLOBALS['conf']['client']['addressbook'] : false;
}

function _turba_getClient($objectId = '')
{
    return $GLOBALS['registry']->call('clients/getContact', array('source' => $GLOBALS['conf']['client']['addressbook'],
                                                                  'objectId' => $objectId));
}

function _turba_getClients($objectIds = array())
{
    return $GLOBALS['registry']->call('clients/getContacts', array('source' => $GLOBALS['conf']['client']['addressbook'],
                                                                   'objectIds' => $objectIds));
}

function _turba_addClient($attributes = array())
{
    return $GLOBALS['registry']->call('clients/import', array('content' => $attributes,
                                                              'contentType' => 'array',
                                                              'source' => $GLOBALS['registry']->call('clients/getClientSource')));
}

function _turba_updateClient($objectId = '', $attributes = array())
{
    return $GLOBALS['registry']->call('clients/replace', array('uid' => $GLOBALS['registry']->call('clients/getClientSource') . ':' . $objectId,
                                                               'content' => $attributes,
                                                               'contentType' => 'array'));
}

function _turba_deleteClient($objectId = '')
{
    return $GLOBALS['registry']->call('clients/delete', array($GLOBALS['registry']->call('clients/getClientSource') . ':' . $objectId));
}

function _turba_searchClients($names = array(), $fields = array())
{
    return $GLOBALS['registry']->call('clients/search',
                                      array('names' => $names,
                                            'sources' => array($GLOBALS['conf']['client']['addressbook']),
                                            'fields' => $fields));
}

function _turba_addField($address = '', $name = '', $field = '', $value = '', $source = '')
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources;

    if (empty($source) || !isset($cfgSources[$source])) {
        return PEAR::raiseError(_("Invalid address book"), 'horde.error', null, null, $source);
    }

    if (empty($address)) {
        return PEAR::raiseError(_("Invalid email"), 'horde.error', null, null, $source);
    }

    if (empty($name)) {
        return PEAR::raiseError(_("Invalid name"), 'horde.error', null, null, $source);
    }

    if (empty($value)) {
        return PEAR::raiseError(_("Invalid entry"), 'horde.error', null, null, $source);
    }

    $driver = &Turba_Driver::singleton($source);
    if (is_a($driver, 'PEAR_Error')) {
        return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
    }

    if (!$driver->hasPermission(PERMS_EDIT)) {
        return PEAR::raiseError(_("Permission Denied"), 'horde.error', null, null, $source);
    }

    $res = $driver->search(array('email' => trim($address)), null, 'AND');
    if (is_a($res, 'PEAR_Error')) {
        return PEAR::raiseError(sprintf(_("Search failed: %s"), $res->getMessage()), 'horde.message', null, null, $source);
    }

    if ($res->count() > 1) {
        $res2 = $driver->search(array('email' => trim($address), 'name' => trim($name)), null, 'AND');
        if (is_a($res2, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Search failed: %s"), $res2->getMessage()), 'horde.message', null, null, $source);
        }

        if (!$res2->count()) {
            return PEAR::raiseError(sprintf(_("Multiple persons with address [%s], but none with name [%s] already exist"), trim($address), trim($name)), 'horde.message', null, null, $source);
        }

        $res3 = $driver->search(array('email' => $address, 'name' => $name, $field => $value));
        if (is_a($res3, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Search failed: %s"), $res3->getMessage()), 'horde.message', null, null, $source);
        }

        if ($res3->count()) {
            return PEAR::raiseError(sprintf(_("This person already has a %s entry in the address book"), $field), 'horde.message', null, null, $source);
        }

        $ob = $res2->next();
        $ob->setValue($field, $value);
        $ob->store();
    } elseif ($res->count() == 1) {
        $res4 = $driver->search(array('email' => $address, $field => $value));
        if (is_a($res4, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Search failed: %s"), $res4->getMessage()), 'horde.message', null, null, $source);
        }

        if ($res4->count()) {
            return PEAR::raiseError(sprintf(_("This person already has a %s entry in the address book"), $field), 'horde.message', null, null, $source);
        }

        $ob = $res->next();
        $ob->setValue($field, $value);
        $ob->store();
    } else {
        return $driver->add(array('email' => $address, 'name' => $name, $field => $value, '__owner' => Auth::getAuth()));
    }

    return;
}

function _turba_getField($address = '', $field = '', $sources = array(),
                         $strict = false, $multiple = false)
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources;

    if (empty($address)) {
        return PEAR::raiseError(_("Invalid email"), 'horde.error');
    }

    if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
        return array();
    }

    if (!count($sources)) {
        if (!count($cfgSources)) {
            return PEAR::raiseError(_("No address books found."));
        }
        reset($cfgSources);
        $sources = array(key($cfgSources));
    }

    $result = array();
    foreach ($sources as $source) {
        if (!isset($cfgSources[$source])) {
            continue;
        }

        $driver = &Turba_Driver::singleton($source);
        if (is_a($driver, 'PEAR_Error')) {
            continue;
        }

        $list = $driver->search(array('email' => $address), null, 'AND', 0, array(), $strict ? array('email') : array());
        if (!is_a($list, 'Turba_List')) {
            continue;
        }

        while ($ob = $list->next()) {
            if ($ob->hasValue($field)) {
                $result[] = $ob->getValue($field);
            }
        }
    }

    if (count($result) > 1) {
        if ($multiple) {
            return $result;
        } else {
            return PEAR::raiseError(_("More than 1 entry found"), 'horde.warning', null, null, $source);
        }
    } elseif (empty($result)) {
        return PEAR::raiseError(sprintf(_("No %s entry found for %s"), $field, $address), 'horde.warning', null, null, $source);
    }

    return reset($result);
}

function _turba_deleteField($address = '', $field = '', $sources = array())
{
    require_once dirname(__FILE__) . '/base.php';
    global $cfgSources;

    if (empty($address)) {
        return PEAR::raiseError(_("Invalid email"), 'horde.error');
    }

    if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
        return array();
    }

    if (count($sources) == 0) {
        $sources = array(key($cfgSources));
    }

    $success = false;

    foreach ($sources as $source) {
        if (isset($cfgSources[$source])) {
            $driver = &Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                continue;
            }
            if (!$driver->hasPermission(PERMS_EDIT)) {
                continue;
            }

            $res = $driver->search(array('email' => $address));
            if (is_a($res, 'Turba_List')) {
                if ($res->count() > 1) {
                    continue;
                }

                $ob = $res->next();
                if (is_object($ob) && $ob->hasValue($field)) {
                    $ob->setValue($field, '');
                    $ob->store();
                    $success = true;
                }
            }
        }
    }

    if (!$success) {
        return PEAR::raiseError(sprintf(_("No %s entry found for %s"), $field, $address), 'horde.error');
    }

    return;
}
