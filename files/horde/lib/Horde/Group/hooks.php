<?php
/**
 * The Group_hooks:: class provides the Horde groups system with the
 * addition of adding support for hook functions to define if a user
 * is in a group.
 *
 * $Horde: framework/Group/Group/hooks.php,v 1.7.2.11 2007/01/02 13:54:20 jan Exp $
 *
 * Copyright 2003-2007 Jason Rust <jrust@rustyparts.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jason Rust <jrust@rustyparts.com>
 * @since   Horde 3.0
 * @package Horde_Group
 */
class Group_hooks extends Group {

    /**
     * Constructor.
     */
    function Group_hooks()
    {
        parent::Group();
        require $GLOBALS['registry']->get('fileroot', 'horde') . '/config/hooks.php';
    }

    /**
     * Get a list of every group that $user is in.
     *
     * @param string  $user          The user to get groups for.
     * @param boolean $parentGroups  Also return the parents of any groups?
     *
     * @return array  An array of all groups the user is in.
     */
    function getGroupMemberships($user, $parentGroups = false)
    {
        $memberships = parent::getGroupMemberships($user, $parentGroups);
        $groups = $this->listGroups();
        foreach ($groups as $gid => $group) {
            if ($this->hasHook($group) &&
                call_user_func($this->_getGroupHookName($group), $user)) {
                $memberships += array($gid => $group);
            }
            if ($parentGroups) {
                $parents = $this->getGroupParentList($gid);
                foreach ($parents as $pid => $parent) {
                    if ($this->hasHook($parent) &&
                        call_user_func($this->_getGroupHookName($group), $user)) {
                        $memberships += array($pid => $parent);
                    }
                }
            }
        }

        return $memberships;
    }

    /**
     * Say if a user is a member of a group or not.
     *
     * @param string  $user       The name of the user.
     * @param integer $gid        The ID of the group.
     * @param boolean $subgroups  Return true if the user is in any subgroups
     *                            of $group, also.
     *
     * @return boolean
     */
    function userIsInGroup($user, $gid, $subgroups = true)
    {
        $group = $this->getGroupName($gid);
        if ($this->hasHook($group)) {
            if (call_user_func($this->_getGroupHookName($group), $user)) {
                $inGroup = true;
            } else {
                $inGroup = false;
            }
        } else {
            $inGroup = false;
        }

        if ($inGroup || parent::userIsInGroup($user, $gid, $subgroups)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Determines if a group has a hook associated with it.
     *
     * @param string $name  The group name.
     *
     * @return boolean  True if the group has a hook, false otherwise
     */
    function hasHook($name)
    {
        return function_exists($this->_getGroupHookName($name));
    }

    /**
     * Returns the name of the hook function.
     *
     * @param string $name  The group name.
     *
     * @return string  The function name for the hook for this group
     */
    function _getGroupHookName($name)
    {
        return '_group_hook_' . str_replace(':', '__', $name);
    }

}
