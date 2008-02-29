<?php
/**
 * The Group_ldap class provides an LDAP backend for the Horde groups
 * system.
 *
 * $Horde: framework/Group/Group/ldap.php,v 1.18.2.5 2007/01/02 13:54:20 jan Exp $
 *
 * Copyright 2005-2007 Ben Chavet <ben@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Ben Chavet <ben@horde.org>
 * @since   Horde 3.1
 * @package Horde_Group
 */
class Group_ldap extends Group {

    /**
     * LDAP connection handle
     */
    var $_ds;

    /**
     * Local copy of the global $conf['group']['params'] array.  Simply
     * for coding convenience.
     */
    var $_params;

    /**
     * Generated LDAP filter based on the config parameters
     */
    var $_filter;

    /**
     * Constructor.
     */
    function Group_ldap()
    {
        global $conf;
        $this->_params = $conf['group']['params'];

        $this->_params['gid'] = strtolower($this->_params['gid']);
        $this->_params['memberuid'] = strtolower($this->_params['memberuid']);
        foreach ($this->_params['newgroup_objectclass'] as $key => $val) {
            $this->_params['newgroup_objectclass'][$key] = strtolower($val);
        }

        /* Generate LDAP search filter. */
        if (!empty($this->_params['filter'])) {
            $this->_filter = $this->_params['filter'];
        } elseif (!is_array($this->_params['objectclass'])) {
            $this->_filter = 'objectclass=' . $this->_params['objectclass'];
        } else {
            $this->_filter = '';
            foreach ($this->_params['objectclass'] as $objectclass) {
                $this->_filter = '(&' . $this->_filter;
                $this->_filter .= '(objectclass=' . $objectclass . '))';
            }
        }

        $this->_filter = strtolower($this->_filter);
    }

    /**
     * Connects to the LDAP server.
     *
     * @return boolean  True or False based on success of connect and bind.
     */
    function _connect()
    {
        /* Connect to the LDAP server. */
        $this->_ds = @ldap_connect($this->_params['hostspec']);
        if (!$this->_ds) {
            return PEAR::raiseError(_("Failed to connect to LDAP server."));
        }

        if (!ldap_set_option($this->_ds, LDAP_OPT_PROTOCOL_VERSION,
                             $this->_params['version'])) {
            Horde::logMessage(
                sprintf('Set LDAP protocol version to %d failed: [%d] %s',
                        $this->_params['version'],
                        ldap_errno($conn),
                        ldap_error($conn),
                        __FILE__, __LINE__));
        }

        if (isset($this->_params['binddn'])) {
            $bind = @ldap_bind($this->_ds, $this->_params['binddn'],
                               $this->_params['password']);
        } else {
            $bind = @ldap_bind($this->_ds);
        }

        if (!$bind) {
            return PEAR::raiseError(_("Could not bind to LDAP server."));
        }

        return true;
    }

    /**
     * Recursively deletes $dn. $this->_ds MUST already be connected.
     *
     * @return mixed  True if delete was successful, PEAR_Error otherwise.
     */
    function _recursive_delete($dn)
    {
        $search = @ldap_list($this->_ds, $dn, 'objectclass=*', array(''));
        if (!$search) {
            return PEAR::raiseError(_("Could not reach the LDAP server."));
        }

        $children = @ldap_get_entries($this->_ds, $search);
        for ($i = 0; $i < $children['count']; $i++) {
            $result = $this->_recursive_delete($children[$i]['dn']);
            if (!$result) {
                return PEAR::raiseError(sprintf(_("Group_ldap: Unable to delete group %s"), $this->getName($children[$i]['dn'])));
            }
        }

        $result = @ldap_delete($this->_ds, $dn);
        if (!$result) {
            return PEAR::raiseError(sprintf(_("Group_ldap: Unable to delete group %s"), $dn));
        }

        return $result;
    }

    /**
     * Searches existing groups for the highest gidnumber, and returns
     * one higher.
     */
    function _nextGid()
    {
        /* Connect to the LDAP server. */
        $success = $this->_connect();
        if (is_a($success, 'PEAR_Error')) {
            return PEAR::raiseError($success->getMessage());
        }

        $search = @ldap_search($this->_ds, $this->_params['basedn'], $this->_filter);
        if (!$search) {
            return PEAR::raiseError(_("Could not reach the LDAP server."));
        }

        @ldap_sort($this->_ds, $search, 'gidnumber');
        $result = @ldap_get_entries($this->_ds, $search);
        @ldap_close($this->_ds);

        if (!is_array($result) || (count($result) <= 1)) {
            return 1;
        }

        return $result[$result['count'] - 1]['gidnumber'][0] + 1;
    }

    /**
     * Return a new group object.
     *
     * @param string $name    The group's name.
     * @param string $parent  The group's parent's name.
     *
     * @return LDAP_Group  A new group object.
     */
    function &newGroup($name, $parent = null)
    {
        if (empty($name)) {
            return PEAR::raiseError(_("Group names must be non-empty"));
        }

        global $conf;
        if (!empty($conf['hooks']['groupldap'])) {
            @include HORDE_BASE . '/config/hooks.php';
            if (function_exists('_horde_hook_groupldap')) {
                $entry = call_user_func('_horde_hook_groupldap', $name, $parent);
            }
        } else {
            // Try this simple default and hope it works.
            $entry[$this->_params['gid']] = $name;
            $entry['objectclass'] = $this->_params['newgroup_objectclass'];
            $entry['gidnumber'] = $this->_nextGid();
        }

        $group = &new LDAP_Group($name, $parent);
        $group->_fromAttributes($entry);
        $group->setGroupOb($this);
        return $group;
    }

    /**
     * Return an LDAP_Group object corresponding to the named group, with the
     * users and other data retrieved appropriately.
     *
     * NOTE: getGroupById() should be used where possible instead of
     * getGroup() because group names are potentially non-unique values.
     *
     * @param string $name  The name of the group to retrieve.
     *
     * @return LDAP_Group  The requested group.
     */
    function &getGroup($name)
    {
        $dn = $this->getGroupId($name);
        if (is_a($dn, 'PEAR_Error')) {
            return PEAR::raiseError($dn->getMessage());
        }
        $group = &$this->getGroupById($dn);
        return $group;
    }

    /**
     * Return an LDAP_Object object corresponding to the given dn, with the
     * users and other data retrieved appropriately.
     *
     * @param string $dn  The dn of the group to retrieve.
     *
     * @return LDAP_Object  The requested group.
     */
    function &getGroupById($dn)
    {
        static $cache;

        if (!is_array($cache)) {
            $cache = array();
        }

        if (!isset($cache[$dn])) {
            /* Connect to the LDAP server. */
            $success = $this->_connect();
            if (is_a($success, 'PEAR_Error')) {
                return PEAR::raiseError($success->getMessage());
            }

            $search = @ldap_search($this->_ds, $dn, $this->_filter);
            if (!$search) {
                return PEAR::raiseError(_("Could not reach the LDAP server."));
            }

            $result = @ldap_get_entries($this->_ds, $search);
            @ldap_close($this->_ds);
            if (!is_array($result) || (count($result) <= 1)) {
                return PEAR::raiseError(_("Empty result."));
            }

            $attributes = array();
            for ($i = 0; $i < $result[0]['count']; $i++) {
                if ($result[0][$result[0][$i]]['count'] > 1) {
                    $attributes[$result[0][$i]] = array();
                    for ($j = 0; $j < $result[0][$result[0][$i]]['count']; $j++) {
                        $attributes[$result[0][$i]][] = $result[0][$result[0][$i]][$j];
                    }
                } else {
                    $attributes[$result[0][$i]] = $result[0][$result[0][$i]][0];
                }
            }
            $attributes['dn'] = $result[0]['dn'];

            $group = new LDAP_Group($this->getGroupName($dn));
            $group->_fromAttributes($attributes);
            $group->setGroupOb($this);
            $cache[$dn] = $group;
        }
        return $cache[$dn];
    }

    /**
     * Get a globally unique ID for a group.  This really just returns the dn
     * for the group, but is included for compatibility with the Group class.
     *
     * @param LDAP_Object $group  The group.
     *
     * @return string  a GUID referring to $group.
     */
    function getGUID($group)
    {
        return $group->get('dn');
    }

    /**
     * Add a group to the groups system.  The group must first be created with
     * Group_ldap::newGroup(), and have any initial users added to it, before
     * this function is called.
     *
     * @param LDAP_Group $group  The new group object.
     *
     * @return mixed  True if successful, PEAR_Error otherwise.
     */
    function addGroup($group)
    {
        if (!is_a($group, 'DataTreeObject_Group')) {
            return PEAR::raiseError('Groups must be DataTreeObject_Group objects or extend that class.');
        }

        /* Connect to the LDAP server. */
        $success = $this->_connect();
        if (is_a($success, 'PEAR_Error')) {
            return PEAR::raiseError($success->getMessage());
        }

        $dn = $group->get('dn');

        $entry = $group->_toAttributes();
        $success = @ldap_add($this->_ds, $dn, $entry);

        if (!$success) {
            return PEAR::raiseError(sprintf(_("Group_ldap: Unable to add group %s. This is what the server said: "), $group->getName()) . @ldap_error($this->_ds));
        }

        @ldap_close($this->_ds);

        return true;
    }

    /**
     * Store updated data - users, etc. - of a group to the backend system.
     *
     * @param LDAP_Object $group  The group to update
     *
     * @return mixed  True on success, PEAR_Error otherwise.
     */
    function updateGroup($group)
    {
        if (!is_a($group, 'DataTreeObject_Group')) {
            return PEAR::raiseError('Groups must be DataTreeObject_Group objects or extend that class.');
        }

        $entry = $group->_toAttributes();

        /* Connect to the LDAP server. */
        $success = $this->_connect();
        if (is_a($success, 'PEAR_Error')) {
            return PEAR::raiseError($success->getMessage());
        }

        $result = @ldap_modify($this->_ds, $group->getId(), $entry);
        if (!$result) {
            return PEAR::raiseError(sprintf(_("Group_ldap: Unable to update group %s"), $group->getName()));
        }

        @ldap_close($this->_ds);

        /* Log the update of the group users on the history log. */
        $history = &Horde_History::singleton();
        $guid = $this->getGUID($group);
        foreach ($group->getAuditLog() as $userId => $action) {
            $history->log($guid, array('action' => $action, 'user' => $userId), true);
        }
        $group->clearAuditLog();

        /* Log the group modification. */
        $history->log($guid, array('action' => 'modify'), true);
        return $result;
    }

    /**
     * Change the name of a group.
     *
     * @param DataTreeObject_Group $group  The group to rename.
     * @param string $newName              The group's new name.
     */
    function renameGroup($group, $newName)
    {
        if (!is_a($group, 'DataTreeObject_Group')) {
            return PEAR::raiseError('Groups must be DataTreeObject_Group objects or extend that class.');
        }

        $group->set($this->_params['gid'], $newName);
        $entry = $group->_toAttributes();

        /* Connect to the LDAP server. */
        $success = $this->_connect();
        if (is_a($success, 'PEAR_Error')) {
            return PEAR::raiseError($success->getMessage());
        }

        $result = @ldap_modify($this->_ds, $group->getId(), $entry);
        if (!$result) {
            return PEAR::raiseError(sprintf(_("Group_ldap: Unable to update group %s"), $group->getName()));
        }

        @ldap_close($this->_ds);

        /* History Log the name change of the group. */
        $history = &Horde_History::singleton();
        $history->log($group->get('dn'), array('action' => 'rename'), true);
        return $result;

    }

    /**
     * Remove a group from the groups system permanently.
     *
     * @param LDAP_Group $group  The group to remove.
     * @param boolean $force     Recursively delete children groups if true.
     *
     * @return mixed  True on success, PEAR_Error otherwise.
     */
    function removeGroup($group, $force = false)
    {
        if (!is_a($group, 'DataTreeObject_Group')) {
            return PEAR::raiseError('Groups must be DataTreeObject_Group objects or extend that class.');
        }

        $dn = $group->getId();

        /* Connect to the LDAP server. */
        $success = $this->_connect();
        if (is_a($success, 'PEAR_Error')) {
            return PEAR::raiseError($success->getMessage());
        }

        if ($force) {
            return $this->_recursive_delete($dn);
        } else {
            $result = @ldap_delete($this->_ds, $dn);
            if (!$result) {
                return PEAR::raiseError(sprintf(_("Group_ldap: Unable to delete group %s"), $dn));
            }
        }
    }

    /**
     * Retrieve the name of a group.
     *
     * @param string $dn  The dn of the group to retrieve the name for.
     *
     * @return string  The group's name.
     */
    function getGroupName($dn)
    {
        $result = ldap_explode_dn($dn, 1);
        return $result[0];
    }

    /**
     * DataTreeObject full names include references to parents, but LDAP does
     * not have this concept.  This function simply returns the $group
     * parameter and is included for compatibility with the Group class.
     *
     * @param string $group  Group name.
     *
     * @return string  $group.
     */
    function getGroupShortName($group)
    {
        return $group;
    }

    /**
     * Retrieve the ID of the given group.
     *
     * NOTE: If given a group name, this function can be unreliable if more
     * than one group exists with the same name.
     *
     * @param mixed $group   LDAP_Group object, or a group name (string)
     *
     * @return string  The group's ID.
     */
    function getGroupId($group)
    {
        if (is_a($group, 'LDAP_Group')) {
            return $group->get('dn');
        }

        static $cache;

        if (!is_array($cache)) {
            $cache = array();
        }

        if (!isset($cache[$group])) {
            $this->_connect();
            $search = @ldap_search($this->_ds, $this->_params['basedn'],
                                   $this->_params['gid'] . '=' . $group,
                                   array($this->_params['gid']));
            if (!$search) {
                return PEAR::raiseError(_("Could not reach the LDAP server."));
            }

            $result = @ldap_get_entries($this->_ds, $search);
            @ldap_close($this->_ds);
            if (!is_array($result) || (count($result) <= 1)) {
                return PEAR::raiseError(_("Empty result."));
            }
            $cache[$group] = $result[0]['dn'];
        }

        return $cache[$group];
    }

    /**
     * Check if a group exists in the system.
     *
     * @param string $group  The group name to check for.
     *
     * @return boolean  True if the group exists, False otherwise.
     */
    function exists($group)
    {
        static $cache;

        if (!is_array($cache)) {
            $cache = array();
        }

        if (!isset($cache[$group])) {
            /* Connect to the LDAP server. */
            $success = $this->_connect();
            if (is_a($success, 'PEAR_Error')) {
                return PEAR::raiseError($success->getMessage());
            }

            $search = @ldap_search($this->_ds, $this->_params['basedn'], '(&' . $this->_filter . '(' . $this->_params['gid'] . '=' . $group . '))');
            if (!$search) {
                return PEAR::raiseError(_("Could not reach the LDAP server."));
            }

            $result = @ldap_get_entries($this->_ds, $search);
            @ldap_close($this->_ds);
            $cache[$group] = (is_array($result) && (count($result > 1)));
        }

        return $cache[$group];
    }

    /**
     * Get a list of the parents of a child group.
     *
     * @param string $dn  The dn of the child group.
     *
     * @return array  The dn and name of the parent group.
     */
    function getGroupParents($dn)
    {
        $parent = $this->getGroupParent($dn);
        return array($parent => $this->getGroupName($parent));
    }

    /**
     * Get the parent of the given group.
     *
     * @param string $dn  The dn of the child group.
     *
     * @return string  The dn of the parent group.
     */
    function getGroupParent($dn)
    {
        $result = ldap_explode_dn($dn, 0);
        unset($result['count']);
        unset($result[0]);
        $parent_dn = implode(',', $result);

        if ($parent_dn == $GLOBALS['conf']['group']['params']['basedn']) {
            return DATATREE_ROOT;
        } else {
            return $parent_dn;
        }
    }

    /**
     * Get a list of parents all the way up to the root object for the given
     * group.
     *
     * @param string $dn  The dn of the group.
     *
     * @return array  A flat list of all of the parents of the given group,
     *                hashed in $dn => $name format.
     */
    function getGroupParentList($dn)
    {
        $result = ldap_explode_dn($dn, 0);
        $num = $result['count'];

        unset($result['count']);
        unset($result[0]);

        $count = 0;
        $parents = array();
        $parent_dn = implode(',', $result);
        while ($parent_dn != $this->_params['basedn'] && $count++ != $num) {
            $parents[$parent_dn] = $this->getGroupName($parent_dn);
            unset($result[$count]);
            $parent_dn = implode(',', $result);
        }
        $parents[DATATREE_ROOT] = DATATREE_ROOT;

        return $parents;
    }

    /**
     * Get a list of every group, in the format dn => groupname.
     *
     * @param boolean $refresh  If true, the cached value is ignored and the
     *                          group list is refreshed from the group backend.
     *
     * @return array  dn => groupname hash.
     */
    function listGroups($refresh = false)
    {
        static $groups;

        if ($refresh || is_null($groups)) {
            /* Connect to the LDAP server. */
            $success = $this->_connect();
            if (is_a($success, 'PEAR_Error')) {
                return PEAR::raiseError($success->getMessage());
            }

            $search = @ldap_search($this->_ds, $this->_params['basedn'], $this->_filter, array($this->_params['gid']));
            if (!$search) {
                return PEAR::raiseError(_("Could not reach the LDAP server."));
            }

            @ldap_sort($this->_ds, $search, $this->_params['gid']);

            $result = @ldap_get_entries($this->_ds, $search);
            @ldap_close($this->_ds);
            if (!is_array($result) || (count($result) <= 1)) {
                return array();
            }

            $groups = array();
            for ($i = 0; $i < $result['count']; $i++) {
                $groups[$result[$i]['dn']] = $result[$i][$this->_params['gid']][0];
            }
        }

        return $groups;
    }

    /**
     * Get a list of every user that is part of the specified group and any
     * of its subgroups.
     *
     * @param string $dn  The dn of the parent group.
     *
     * @return array  The complete user list.
     */
    function listAllUsers($dn)
    {
        static $cache;

        if (!is_array($cache)) {
            $cache = array();
        }

        if (!isset($cache[$dn])) {
            $success = $this->_connect();
            if (is_a($success, 'PEAR_Error')) {
                return PEAR::raiseError($success->getMessage());
            }

            $search = @ldap_search($this->_ds, $dn, $this->_filter);
            if (!$search) {
                return PEAR::raiseError(sprintf(_("Could not reach the LDAP server: %s"), @ldap_error($this->_ds)));
            }

            $result = @ldap_get_entries($this->_ds, $search);
            @ldap_close($this->_ds);
            if (!is_array($result) || (count($result) <= 1)) {
                // Not an error, we just don't have any users in this group.
                return array();
            }

            $users = array();
            for ($i = 0; $i < $result['count']; $i++) {
                $users = array_merge($users, $this->listUsers($result[$i]['dn']));
            }

            $cache[$dn] = array_unique($users);
        }

        return $cache[$dn];
    }

    /**
     * Get a list of every group that the given user is a member of.
     *
     * @param string  $user          The user to get groups for.
     * @param boolean $parentGroups  Also return the parents of any groups?
     *
     * @return array  An array of all groups the user is in.
     */
    function getGroupMemberships($user, $parentGroups = false)
    {
        static $cache;

        if (empty($cache[$user])) {
            /* Connect to the LDAP server. */
            $success = $this->_connect();
            if (is_a($success, 'PEAR_Error')) {
                return PEAR::raiseError($success->getMessage());
            }

            $search = @ldap_search($this->_ds, $this->_params['basedn'], '(' . $this->_params['memberuid'] . '=' . $user . ')');
            if (!$search) {
                return PEAR::raiseError(_("Could not reach the LDAP server."));
            }

            $result = @ldap_get_entries($this->_ds, $search);
            @ldap_close($this->_ds);
            if (!is_array($result) || (count($result) <= 1)) {
                return PEAR::raiseError(_("Empty result."));
            }

            $groups = array();
            for ($i = 0; $i < $result['count']; $i++) {
                $groups[$result[$i]['dn']] = $result[$i][$this->_params['gid']][0];
            }

            $cache[$user] = $groups;
        }

        return $cache[$user];
    }

    /**
     * Returns the tree depth of the given group, relative to the base dn.
     * 0 is returned for any object directly below the base dn.
     *
     * @param string $dn  The dn of the object.
     *
     * @return intenger  The tre depth of the group.
     */
    function getLevel($dn)
    {
        $base = @ldap_explode_dn($this->_params['basedn'], 0);
        $group = @ldap_explode_dn($dn, 0);
        return $group['count'] - $base['count'] - 1;
    }

}

/**
 * Extension of the DataTreeObject_Group class for storing group information
 * in an LDAP directory.
 *
 * @author  Ben Chavet <ben@horde.org>
 * @since   Horde 3.1
 * @package Horde_Group
 */
class LDAP_Group extends DataTreeObject_Group {

    /**
     * Constructor.
     *
     * @param string $name    The name of this group.
     * @param string $parent  The dn of the parent of this group.
     */
    function LDAP_Group($name, $parent = null)
    {
        parent::DataTreeObject_Group($name);
        if ($parent) {
            $this->data['dn'] = strtolower($GLOBALS['conf']['group']['params']['gid']) . '=' . $name . ',' . $parent;
        } else {
            $this->data['dn'] = strtolower($GLOBALS['conf']['group']['params']['gid']) . '=' . $name .
                ',' . strtolower($GLOBALS['conf']['group']['params']['basedn']);
        }
    }

    /**
     * Get a list of every user that is part of this group (and only
     * this group).
     *
     * @return array  The user list.
     */
    function listUsers()
    {
        return $this->_groupOb->listUsers($this->data['dn']);
    }

    /**
     * Get a list of every user that is a member of this group and any of
     * it's subgroups.
     *
     * @return array  The complete user list.
     */
    function listAllUsers()
    {
        return $this->_groupOb->listAllUsers($this->data['dn']);
    }

    /**
     * Take in a list of attributes from the backend and map it to our
     * internal data array.
     *
     * @param array $attributes  The list of attributes from the backend.
     */
    function _fromAttributes($attributes = array())
    {
        $this->data['users'] = array();
        foreach ($attributes as $key => $value) {
            if ($key == strtolower($GLOBALS['conf']['group']['params']['memberuid'])) {
                if (is_array($value)) {
                    foreach ($value as $user) {
                        $this->data['users'][$user] = '1';
                    }
                } else {
                    $this->data['users'][$value] = '1';
                }
            } elseif ($key == 'mail') {
                $this->data['email'] = $value;
            } else {
                $this->data[$key] = $value;
            }
        }
    }

    /**
     * Map this object's attributes from the data array into a format that
     * can be stored in an LDAP entry.
     *
     * @return array  The entry array.
     */
    function _toAttributes()
    {
        $attributes = array();
        foreach ($this->data as $key => $value) {
            if ($key == 'users') {
                foreach ($value as $user => $membership) {
                    $attributes[strtolower($GLOBALS['conf']['group']['params']['memberuid'])][] = $user;
                }
            } elseif ($key == 'email') {
                $attributes['mail'] = !empty($value) ? $value : ' ';
            } elseif ($key != 'dn' && $key != strtolower($GLOBALS['conf']['group']['params']['memberuid'])) {
                $attributes[$key] = !empty($value) ? $value : ' ';
            }
        }

        return $attributes;
    }

}