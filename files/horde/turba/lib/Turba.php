<?php

/**
 * The virtual path to use for VFS data.
 */
define('TURBA_VFS_PATH', '.horde/turba/documents');

/**
 * Turba Base Class.
 *
 * $Horde: turba/lib/Turba.php,v 1.59.4.29 2007/07/02 14:25:27 jan Exp $
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @package Turba
 */
class Turba {

    function formatEmailAddresses($data, $name)
    {
        global $registry;
        static $batchCompose;

        if (!isset($batchCompose)) {
            $batchCompose = $registry->hasMethod('mail/batchCompose');
        }

        require_once 'Horde/MIME.php';

        $array = is_array($data);
        if (!$array) {
            $data = array($data);
        }

        $addresses = array();
        foreach ($data as $i => $email_vals) {
            $email_vals = explode(',', $email_vals);
            foreach ($email_vals as $j => $email_val) {
                $email_val = trim($email_val);

                // Format the address according to RFC822.
                $mailbox_host = explode('@', $email_val);
                if (!isset($mailbox_host[1])) {
                    $mailbox_host[1] = '';
                }

                $address = MIME::rfc822WriteAddress($mailbox_host[0], $mailbox_host[1], $name);

                // Get rid of the trailing @ (when no host is included in
                // the email address).
                $addresses[$i . ':' . $j] = array('to' => addslashes(str_replace('@>', '>', $address)));
                if (!$batchCompose) {
                    $addresses[$i . ':' . $j] = $GLOBALS['registry']->call('mail/compose', $addresses[$i . ':' . $j]);
                }
            }
        }

        if ($batchCompose) {
            $addresses = $GLOBALS['registry']->call('mail/batchCompose', array($addresses));
        }

        foreach ($data as $i => $email_vals) {
            $email_vals = explode(',', $email_vals);
            $email_values = false;
            foreach ($email_vals as $j => $email_val) {
                if (!is_a($addresses, 'PEAR_Error')) {
                    $mail_link = $addresses[$i . ':' . $j];
                    if (is_a($mail_link, 'PEAR_Error')) {
                        $mail_link = 'mailto:' . urlencode($email_val);
                    }
                } else {
                    $mail_link = 'mailto:' . urlencode($email_val);
                }

                $email_value = Horde::link($mail_link, $email_val) . htmlspecialchars($email_val) . '</a>';
                if ($email_values) {
                    $email_values .= ', ' . $email_value;
                } else {
                    $email_values = $email_value;
                }
            }
        }

        if ($array) {
            return $email_values[0];
        } else {
            return $email_values;
        }
    }

    /**
     * Get all the address books the user has the requested permissions to and
     * return them in the user's preferred order.
     *
     * @param integer $permission  The PERMS_* constant to filter on.
     *
     * @return array  The filtered, ordered $cfgSources entries.
     */
    function getAddressBooks($permission = PERMS_READ)
    {
        $addressbooks = array();
        foreach (array_keys(Turba::getAddressBookOrder()) as $addressbook) {
            $addressbooks[$addressbook] = $GLOBALS['cfgSources'][$addressbook];
        }

        if (!$addressbooks) {
            $addressbooks = $GLOBALS['cfgSources'];
        }

        return Turba::permissionsFilter($addressbooks, 'source', $permission);
    }

    /**
     * Get the order the user selected for displaying address books.
     *
     * @return array  An array describing the order to display the address books.
     */
    function getAddressBookOrder()
    {
        $i = 0;
        $lines = explode("\n", $GLOBALS['prefs']->getValue('addressbooks'));
        $temp = $lines;
        $addressbooks = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && isset($GLOBALS['cfgSources'][$line])) {
                $addressbooks[$line] = $i++;
            } else {
                // If the address book does not exist in cfgSources,
                // see if it would have represented a Horde_Share.  If so,
                // assume the share is no longer available and prune the
                // setting.
                if (strpos($line, ':')) {
                    $pos = array_search($line, $temp);
                    unset($temp[$pos]);
                }
            }
        }
        $GLOBALS['prefs']->setValue('addressbooks', implode("\n", $temp));
        return $addressbooks;
    }

    /**
     * Returns the current user's default address book.
     *
     * @return string  The default address book name.
     */
    function getDefaultAddressBook()
    {
        $lines = explode("\n", $GLOBALS['prefs']->getValue('addressbooks'));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && isset($GLOBALS['cfgSources'][$line])) {
                return $line;
            }
        }

        return null;
    }


    /**
     */
    function getColumns()
    {
        $columns = array();
        $lines = explode("\n", $GLOBALS['prefs']->getValue('columns'));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line) {
                $cols = explode("\t", $line);
                if (count($cols) > 1) {
                    $source = array_splice($cols, 0, 1);
                    $columns[$source[0]] = $cols;
                }
            }
        }

        return $columns;
    }

    /**
     * Returns a best guess at the lastname in a string.
     *
     * @param string $name  String contain the full name.
     *
     * @return string  String containing the last name.
     */
    function guessLastname($name)
    {
        global $prefs;

        $name = trim(preg_replace('|\s|', ' ', $name));
        if (!empty($name)) {
            /* Assume that last names are always before any commas. */
            if (is_int(strpos($name, ','))) {
                $name = String::substr($name, 0, strpos($name, ','));
            }

            /* Take out anything in parentheses. */
            $name = trim(preg_replace('|\(.*\)|', '', $name));

            $namelist = explode(' ', $name);
            $name = $namelist[($nameindex = (count($namelist) - 1))];

            while (!empty($name) && String::length($name) < 5 &&
                   strspn($name[(String::length($name) - 1)], '.:-') &&
                   !empty($namelist[($nameindex - 1)])) {
                $nameindex--;
                $name = $namelist[$nameindex];
            }
        }
        return $name;
    }

    /**
     * Formats the name according to the user's preference.
     *
     * @param Turba_Object $ob  The object to get a name from.
     *
     * @return string  The formatted name, either "Firstname Lastname"
     *                 or "Lastname, Firstname" depending on the user's
     *                 preference.
     */
    function formatName($ob)
    {
        global $prefs;
        static $name_format;

        if (!isset($name_format)) {
            $name_format = $prefs->getValue('name_format');
        }

        /* if no formatting, return original name */
        if ($name_format != 'first_last' && $name_format != 'last_first') {
            return $ob->getValue('name');
        }

        /* See if we have the name fields split out explicitly. */
        if ($ob->hasValue('firstname') && $ob->hasValue('lastname')) {
            if ($name_format == 'last_first') {
                return $ob->getValue('lastname') . ', ' . $ob->getValue('firstname');
            } else {
                return $ob->getValue('firstname') . ' ' . $ob->getValue('lastname');
            }
        } else {
            /* One field, we'll have to guess. */
            $name = $ob->getValue('name');
            $lastname = Turba::guessLastname($name);
            if ($name_format == 'last_first' &&
                !is_int(strpos($name, ',')) &&
                String::length($name) > String::length($lastname)) {
                $name = preg_replace('/\s+' . preg_quote($lastname, '/') . '/', '', $name);
                $name = $lastname . ', ' . $name;
            }
            if ($name_format == 'first_last' &&
                is_int(strpos($name, ',')) &&
                String::length($name) > String::length($lastname)) {
                $name = preg_replace('/' . preg_quote($lastname, '/') . ',\s*/', '', $name);
                $name = $name . ' ' . $lastname;
            }
            return $name;
        }
    }

    /**
     * Checks if a user has the specified permissions on the passed-in object.
     *
     * @since Turba 2.1
     *
     * @param mixed $in            The data to check on.
     * @param string $filter       What are we checking for.
     * @param integer $permission  What permission to check for.
     *
     * @return mixed  Either a boolean if checking PERMS_* or a requested
     *                extended permissions value.
     *
     */
    function hasPermission($in, $filter, $permission = PERMS_READ)
    {
        global $perms;

        $userID = Auth::getAuth();

        switch ($filter) {
        case 'object':
            if (!is_a($in, 'Turba_Object')) {
                return false;
            }

            $sourceTag = 'turba:sources:' . $in->driver->name;
            if ($perms->exists($sourceTag)) {
                return $perms->hasPermission($sourceTag, $userID, $permission,
                                             $in->getValue('__owner'));
            }

            // Otherwise, we assume anyone can access their private
            // address books, but not public ones.
            return !$in->driver->public;

        case 'source':
            // Note that if we are using Horde Permissions then $source will
            // (correctly) be pruned here to point to the 'original' entry in
            // $cfgSources. Otherwise, we couldn't enforce the extended
            // permissions like max_contacts on a per source basis.
            if (($pos = strpos($in, ':')) !== false) {
                $source = substr($in, 0, $pos);
            } else {
                $source = $in;
            }
            $srcConfig = $GLOBALS['cfgSources'][$source];
            if (!$perms->exists('turba:sources:' . $in)) {
                // Assume we have permissions if it's not explicitly set.
                // If using Horde_Share, the only perms we'd be checking
                // are the extended permissions.
                return true;
            } elseif ((empty($srcConfig['use_shares'])) &&
                      ($source === $in)) {
                // Using Horde_Perms AND checking source level permsissions
                return $perms->hasPermission('turba:sources:' . $in, $userID,
                                             $permission);
            } else {
                // Checking extended permissions for either Horde_Perms or
                // Horde_Share
                $allowed = $perms->getPermissions('turba:sources:' . $in);
                if (is_array($allowed)) {
                    switch (substr($in, strpos($in, ':'))) {
                    case 'max_contacts':
                        $allowed = array_reduce($allowed, create_function('$a, $b', 'return max($a, $b);'), 0);
                        break;
                    }
                }
                return $allowed;
            }

        default:
            return true;
        }

        return false;
    }

    /**
     * Filters data based on permissions.
     *
     * @param array $in            The data we want filtered.
     * @param string $filter       What type of data we are filtering.
     * @param integer $permission  The PERMS_* constant we will filter on.
     *
     * @return array  The filtered data.
     */
    function permissionsFilter($in, $filter, $permission = PERMS_READ)
    {
        global $perms;

        $out = array();

        switch ($filter) {
        case 'source':
            foreach ($in as $sourceId => $source) {
                $driver = &Turba_Driver::singleton($sourceId);
                if (!is_a($driver, 'PEAR_Error')) {
                    if ($driver->hasPermission($permission)) {
                        $out[$sourceId] = $source;
                    }
                }
            }
            break;

        default:
            $out = $in;
        }

        return $out;
    }

    /**
     * Creates a new $cfgSources entry for each share the current user has
     * access to.  Note that this will only sync shares that are unique to
     * Horde (basically, a SQL driver source for now).  Any backend that
     * supports acls or similar mechanism should be configured from within
     * sources.php or _horde_hook_share_* calls.
     *
     * @param array $sources  The default $cfgSources array.
     *
     * @return array  The $cfgSources array.
     */
    function getConfigFromShares($sources)
    {
        $shares = Turba::listShares();
       // Notify the user if we failed, but still return the $cfgSource array.
       if (is_a($shares, 'PEAR_Error')) {
           $notification->push($shares);
           return $sources;
       }
       $shareNames = array_keys($shares);
       foreach ($shareNames as $name) {
           if (!isset($sources[$name])) {
               if (strpos($name, ':') !== false) {
                   list($srcType, $user) = explode(':', $name, 2);
                   if (($user != Auth::getAuth()) &&
                       (!empty($sources[$srcType]['use_shares']))) {
                       $newSrc = $sources[$srcType];
                       $newSrc['title'] = $shares[$name]->get('name');
                       $sources[$name] = $newSrc;
                   }
               }
           }
       }
       return $sources;
    }

    /**
     * Returns all shares the current user has specified permissions to.
     *
     * @param boolean $owneronly   Only return address books owned by the user?
     *                             Defaults to false.
     * @param integer $permission  Permissions to filter by.
     *
     * @return array  Shares the user has the requested permissions to.
     */
    function listShares($owneronly = false, $permission = PERMS_READ)
    {
        $sources = $GLOBALS['turba_shares']->listShares(Auth::getAuth(), $permission,
                                                      $owneronly ? Auth::getAuth() : null);
        if (is_a($sources, 'PEAR_Error')) {
            Horde::logMessage($sources, __FILE__, __LINE__, PEAR_LOG_ERR);
            return array();
        }
        return $sources;
    }

    /**
     * Create a new Turba share.
     *
     * @param array $params       Parameters for the new share object.
     * @param boolean $isdefault  Are we creating a 'default' share?
     *
     * @return mixed  The new share object or PEAR_Error
     */
    function &createShare($params, $isDefault = false)
    {
        // We need to know what the source type is for this share.
        if (empty($params['sourceType'])) {
            $share = PEAR::raiseError(sprintf('Unable to create a new address book. Enter a source name in Turba\'s configuration at Administration->Setup->Address Book->Shares.'));
            return $share;
        }

        if ($isDefault) {
            // Gather info for user's default share for this source.
            require_once 'Horde/Identity.php';
            $identity = &Identity::singleton();
            if (!isset($params['shareName'])) {
                // Use the shareName if it was passed in, otherwise use
                // a sensible default.
                $name = $identity->getValue('fullname');
                if (trim($name) == '') {
                    $name = Auth::removeHook(Auth::getAuth());
                }
                $name = sprintf(_("%s's Address Book"), $name);
            } else {
                $name = $params['shareName'];
            }
            $uid = Auth::getAuth();
        } else {
            // Not default share, see if we need to generate a uid.
            $name = $params['shareName'];
            if (empty($params['uid'])) {
                $uid = md5(microtime());
            } else {
                $uid = $params['uid'];
            }
        }

        // Generate the new share
        $share = &$GLOBALS['turba_shares']->newShare($params['sourceType'] . ':' . $uid);
        if (is_a($share, 'PEAR_Error')) {
            return $share;
        }

        $share->set('name', $name);
        $share->set('uid', $uid);
        $share->addUserPermission(Auth::getAuth(), PERMS_ALL);
        foreach ($params as $key => $value) {
            if ($key != 'sourceType' && $key != 'shareName' && $key != 'uid') {
                $share->set($key, $value);
            }
        }
        $GLOBALS['turba_shares']->addShare($share);
        $share->save();
        return $share;
    }

    /**
     * Update a Turba share.
     *
     * @param string $name   The name of the share to update.
     * @param array $params  The params to update.
     *
     * @return mixed  The display name of the updated share or PEAR_Error.
     */
    function updateShare($name, $params)
    {
        $share = &$GLOBALS['turba_shares']->getShare($name);
        if (is_a($share, 'PEAR_Error')) {
            return $share;
        }
        $name = $share->get('name');
        foreach ($params as $key => $value) {
            $share->set($key, $value);
        }
        $result = $share->save();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        } else {
            return $name;
        }
    }

    /**
     * Remove a Turba share.
     *
     * @param string $name  The name of the share to remove.
     *
     * @return mixed  The display name of the deleted share or PEAR_Error.
     */
    function deleteShare($name)
    {
        $share = &$GLOBALS['turba_shares']->getShare($name);
        if (is_a($share, 'PEAR_Error')) {
            return $share;
        }

        // Enforce the requirement that only the share's owner can delete it.
        if ($share->get('owner') != Auth::getAuth()) {
            return PEAR::raiseError(_("You do not have permissions to delete this source."));
        }
        $name = $share->get('name');
        $res = $GLOBALS['turba_shares']->removeShare($share);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        } else {
            return $name;
        }
    }

    /**
     * Build Turba's list of menu items.
     */
    function getMenu($returnType = 'object')
    {
        require_once 'Horde/Menu.php';
        $menu = &new Menu();

        if ($GLOBALS['haveShare']) {
            $menu->add(Horde::applicationUrl('addressbooks.php'), _("_My Address Books"), 'turba.png');
        }
        if ($GLOBALS['browse_source_count']) {
            $menu->add(Horde::applicationUrl('browse.php'), _("_Browse"), 'menu/browse.png', null, null, null, (($GLOBALS['prefs']->getValue('initial_page') == 'browse.php' && basename($_SERVER['PHP_SELF']) == 'index.php') || (basename($_SERVER['PHP_SELF']) == 'browse.php' && Util::getFormData('key') != '**search')) ? 'current' : '__noselection');
        }
        if (count($GLOBALS['addSources'])) {
            $menu->add(Horde::applicationUrl('add.php'), _("_New Contact"), 'menu/new.png');
        }
        $menu->add(Horde::applicationUrl('search.php'), _("_Search"), 'search.png', $GLOBALS['registry']->getImageDir('horde'), null, null, (($GLOBALS['prefs']->getValue('initial_page') == 'search.php' && basename($_SERVER['PHP_SELF']) == 'index.php') || (basename($_SERVER['PHP_SELF']) == 'browse.php' && Util::getFormData('key') == '**search')) ? 'current' : null);

        /* Import/Export */
        if ($GLOBALS['conf']['menu']['import_export']) {
            $menu->add(Horde::applicationUrl('data.php'), _("_Import/Export"), 'data.png', $GLOBALS['registry']->getImageDir('horde'));
        }

        if ($returnType == 'object') {
            return $menu;
        } else {
            return $menu->render();
        }
    }
}
