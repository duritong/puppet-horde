<?php

require_once 'Horde/DataTree.php';

/**
 * Horde_Share:: provides an interface to all shares a user might have.  Its
 * methods take care of any site-specific restrictions configured in in the
 * application's prefs.php and conf.php files.
 *
 * $Horde: framework/Share/Share.php,v 1.111.2.23 2007/01/14 17:04:56 jan Exp $
 *
 * Copyright 2002-2007 Joel Vandal <jvandal@infoteck.qc.ca>
 * Copyright 2002-2007 Infoteck Internet <webmaster@infoteck.qc.ca>
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Joel Vandal <jvandal@infoteck.qc.ca>
 * @author  Mike Cochrame <mike@graftonhall.co.nz>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 3.0
 * @package Horde_Share
 */
class Horde_Share {

    /**
     * Pointer to a DataTree instance to manage/store shares
     *
     * @var DataTree
     */
    var $_datatree;

    /**
     * The application we're managing shares for.
     *
     * @var string
     */
    var $_app;

    /**
     * The subclass of DataTreeObject to instantiate shares as.
     *
     * @var string
     */
    var $_shareObject = 'DataTreeObject_Share';

    /**
     * A cache of all shares that have been retrieved, so we don't hit the
     * backend again and again for them.
     *
     * @var array
     */
    var $_cache = array();

    /**
     * Cache used for listShares().
     *
     * @var array
     */
    var $_listcache = array();

    /**
     * A list of objects that we're currently sorting, for reference during the
     * sorting algorithm.
     *
     * @var array
     */
    var $_sortList;

    /**
     * Attempts to return a reference to a concrete Horde_Share instance. It
     * will only create a new instance if no Horde_Share instance currently
     * exists.
     *
     * This method must be invoked as:
     *   <code>$var = &Horde_Share::singleton($app);</code>
     *
     * @param string $app  The applications that the shares relates to.
     *
     * @return Horde_Share  The concrete Share reference, or false on an error.
     */
    function &singleton($app)
    {
        static $shares;

        if (!isset($shares[$app])) {
            $shares[$app] = new Horde_Share($app);
        }

        return $shares[$app];
    }

    /**
     * Constructor.
     *
     * @param string $app  The application that the shares belong to.
     */
    function Horde_Share($app)
    {
        global $conf, $registry;

        if (empty($conf['datatree']['driver'])) {
            Horde::fatal('You must configure a DataTree backend to use Shares.', __FILE__, __LINE__);
        }

        $driver = $conf['datatree']['driver'];
        $this->_app = $app;
        $this->_datatree = &DataTree::singleton(
            $driver,
            array_merge(Horde::getDriverConfig('datatree', $driver), array('group' => 'horde.shares.' . $app))
        );

        Horde::callHook('_horde_hook_share_init', array($this, $app));
    }

    /**
     * Returns the DataTree instance used to manage this share.
     *
     * @return DataTree  This share's DataTree instance.
     */
    function &getDataTree()
    {
        return $this->_datatree;
    }

    /**
     * Returns a DataTreeObject_Share object corresponding to the given share
     * name, with the details retrieved appropriately.
     *
     * @param string $name  The name of the share to retrieve.
     *
     * @return DataTreeObject_Share  The requested share.
     */
    function &getShare($name)
    {
        if (isset($this->_cache[$name])) {
            return $this->_cache[$name];
        }

        $this->_cache[$name] = &$this->_datatree->getObject($name, $this->_shareObject);
        if (!is_a($this->_cache[$name], 'PEAR_Error')) {
            $this->_cache[$name]->setShareOb($this);
        }

        return $this->_cache[$name];
    }

    /**
     * Returns a DataTreeObject_Share object corresponding to the given unique
     * ID, with the details retrieved appropriately.
     *
     * @param string $cid  The id of the share to retrieve.
     *
     * @return DataTreeObject_Share  The requested share.
     */
    function &getShareById($cid)
    {
        $share = $this->_datatree->getObjectById($cid, $this->_shareObject);
        if (!is_a($share, 'PEAR_Error')) {
            $share->setShareOb($this);
        }
        return $share;
    }

    /**
     * Returns an array of DataTreeObject_Share objects corresponding to the
     * given set of unique IDs, with the details retrieved appropriately.
     *
     * @param array $cids  The array of ids to retrieve.
     *
     * @return array  The requested shares.
     */
    function &getShares($cids)
    {
        $shares = $this->_datatree->getObjects($cids, $this->_shareObject);
        if (is_a($shares, 'PEAR_Error')) {
            return $shares;
        }

        $keys = array_keys($shares);
        foreach ($keys as $key) {
            if (is_a($shares[$key], 'PEAR_Error')) {
                return $shares[$key];
            }

            $this->_cache[$key] = &$shares[$key];
            $shares[$key]->setShareOb($this);
        }

        return $shares;
    }

    /**
     * Returns a new share object.
     *
     * @param string $name  The share's name.
     *
     * @return DataTreeObject_Share  A new share object.
     */
    function &newShare($name)
    {
        if (empty($name)) {
            return PEAR::raiseError('Share names must be non-empty');
        }
        $share = &new $this->_shareObject($name);
        $share->setDataTree($this->_datatree);
        $share->setShareOb($this);
        $share->set('owner', Auth::getAuth());

        return $share;
    }

    /**
     * Adds a share to the shares system. The share must first be created with
     * Horde_Share::newShare(), and have any initial details added to it,
     * before this function is called.
     *
     * @param DataTreeObject_Share $share  The new share object.
     *
     * @return boolean|PEAR_Error  PEAR_Error on failure.
     */
    function addShare($share)
    {
        if (!is_a($share, 'DataTreeObject_Share')) {
            return PEAR::raiseError('Shares must be DataTreeObject_Share objects or extend that class.');
        }

        $perm = &$GLOBALS['perms']->newPermission($share->getName());
        if (is_a($perm, 'PEAR_Error')) {
            return $perm;
        }

        /* Give the owner full access */
        $perm->addUserPermission($share->get('owner'), PERMS_SHOW, false);
        $perm->addUserPermission($share->get('owner'), PERMS_READ, false);
        $perm->addUserPermission($share->get('owner'), PERMS_EDIT, false);
        $perm->addUserPermission($share->get('owner'), PERMS_DELETE, false);

        $share->setPermission($perm, false);

        $result = Horde::callHook('_horde_hook_share_add', array($share),
                                  'horde', false);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_datatree->add($share);
    }

    /**
     * Removes a share from the shares system permanently.
     *
     * @param DataTreeObject_Share $share  The share to remove.
     *
     * @return boolean|PEAR_Error  PEAR_Error on failure.
     */
    function removeShare($share)
    {
        if (!is_a($share, 'DataTreeObject_Share')) {
            return PEAR::raiseError('Shares must be DataTreeObject_Share objects or extend that class.');
        }

        $result = Horde::callHook('_horde_hook_share_remove', array($share),
                                  'horde', false);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_datatree->remove($share);
    }

    /**
     * Checks to see if a share has any child shares.
     *
     * @param DataTreeObject_Share $share  The share to check for children.
     *
     * @return boolean  True if the specified share has child shares.
     */
    function hasChildren($share)
    {
        if (!is_a($share, 'DataTreeObject_Share')) {
            return PEAR::raiseError('Shares must be DataTreeObject_Share objects or extend that class.');
        }

        return (bool)$this->_datatree->getNumberOfChildren($share);
    }

    /**
     * Returns a share's direct parent object.
     *
     * @param string $share  Get the parent of this share.
     *
     * @return DataTreeObject_Share  The parent share, if it exists.
     */
    function &getParent($child)
    {
        $id = $this->_datatree->getParent($child);
        if (is_a($id, 'PEAR_Error')) {
            return $id;
        }

        if (!$id || ($id == DATATREE_ROOT)) {
            $error = PEAR::raiseError('Parent does not exist.');
            return $error;
        }

        return $this->getShareById($id);
    }

    /**
     * Returns the ID of a share.
     *
     * @param DataTreeObject_Share  The share to return the ID of.
     *
     * @return integer  The share's ID or PEAR_Error on failure.
     */
    function getShareId($share)
    {
        return $this->_datatree->getId($share->getName());
    }

    /**
     * Utility function to be used with uasort() for sorting arrays of
     * Horde_Share objects.
     * Example:<code>
     * uasort($list, array('Horde_Share', '_sortShares'));
     * </code>
     *
     * @access private
     */
    function _sortShares($a, $b)
    {
        $aParts = explode(':', $a->getName());
        $bParts = explode(':', $b->getName());

        $min = min(count($aParts), count($bParts));
        $idA = '';
        $idB = '';
        for ($i = 0; $i < $min; $i++) {
            if ($idA) {
                $idA .= ':';
                $idB .= ':';
            }
            $idA .= $aParts[$i];
            $idB .= $bParts[$i];

            if ($idA != $idB) {
                $curA = isset($this->_sortList[$idA]) ? $this->_sortList[$idA]->get('name') : '';
                $curB = isset($this->_sortList[$idB]) ? $this->_sortList[$idB]->get('name') : '';
                return strnatcasecmp($curA, $curB);
            }
        }

        return count($aParts) > count($bParts);
    }

    /**
     * Checks if a share exists in the system.
     *
     * @param string $share  The share to check.
     *
     * @return boolean  True if the share exists, false otherwise.
     */
    function exists($share)
    {
        return $this->_datatree->exists($share);
    }

    /**
     * Returns the count of all shares that $userid has access to.
     *
     * @param string  $userid      The userid of the user to check access for.
     * @param integer $perm        The level of permissions required.
     * @param mixed   $attributes  Restrict the shares counted to those
     *                             matching $attributes. An array of
     *                             attribute/values pairs or a share owner
     *                             username.
     * @param string  $parent      The parent share to start searching at.
     * @param boolean $allLevels   Return all levels, or just the direct
     *                             children of $parent? Defaults to all levels.
     *
     * @return integer  Number of shares the user has access to.
     */
    function countShares($userid, $perm = PERMS_SHOW, $attributes = null,
                         $parent = DATATREE_ROOT, $allLevels = true)
    {
        $key = serialize(array($this->_app, $userid, $perm, $attributes, $parent, $allLevels, 'count'));
        if (empty($this->_listcache[$key])) {
            $criteria = $this->getShareCriteria($userid, $perm, $attributes);
            $this->_listcache[$key] = $this->_datatree->countByAttributes($criteria, $parent, $allLevels, 'id');
        }

        return $this->_listcache[$key];
    }

    /**
     * Returns an array of all shares that $userid has access to.
     *
     * @param string  $userid       The userid of the user to check access for.
     * @param integer $perm         The level of permissions required.
     * @param mixed   $attributes   Restrict the shares counted to those
     *                              matching $attributes. An array of
     *                              attribute/values pairs or a share owner
     *                              username.
     * @param string  $parent       The parent share to start searching at.
     * @param boolean $allLevels    Return all levels, or just the direct
     *                              children of $parent? Defaults to all
     *                              levels.
     * @param integer $from         The share to start listing at.
     * @param integer $count        The number of shares to return.
     * @param string  $sortby_name  Attribute name to use for sorting.
     * @param string  $sortby_key   Attribute key to use for sorting.
     * @param integer $direction    Sort direction:
     *                                0 - ascending
     *                                1 - descending
     *
     * @return array  The shares the user has access to.
     */
    function &listShares($userid, $perm = PERMS_SHOW, $attributes = null,
                         $parent = DATATREE_ROOT, $allLevels = true, $from = 0,
                         $count = 0, $sortby_name = null, $sortby_key = null,
                         $direction = 0)
    {
        $key = serialize(array($this->_app, $userid, $perm, $attributes,
                               $parent, $allLevels, $from, $count,
                               $sortby_name, $sortby_key, $direction));
        if (empty($this->_listcache[$key])) {
            $criteria = $this->getShareCriteria($userid, $perm, $attributes);
            $sharelist = $this->_datatree->getByAttributes(
                $criteria, $parent, $allLevels, 'id', $from, $count,
                $sortby_name, $sortby_key, $direction);
            if (is_a($sharelist, 'PEAR_Error') || !count($sharelist)) {
                /* If we got back an error or an empty array, pass it back to
                 * the caller. */
                return $sharelist;
            }

            /* Make sure getShares() didn't return an error. */
            $shares = &$this->getShares(array_keys($sharelist));
            if (is_a($shares, 'PEAR_Error')) {
                return $shares;
            }

            $this->_listcache[$key] = &$shares;
            $this->_sortList = $this->_listcache[$key];
            uasort($this->_listcache[$key], array($this, '_sortShares'));
            $this->_sortList = null;
        }

        $result = Horde::callHook('_horde_hook_share_list', array($userid,
                                  $perm, $attributes, $this->_listcache[$key]),
                                  'horde', false);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_listcache[$key];
    }

    /**
     * Lists *all* shares for the current app/share, regardless of
     * permissions. This is for admin functionality and scripting tools, and
     * shouldn't be called from user-level code!
     *
     * @param boolean $parent  Start the listing at a certain point in the
     *                         tree. Defaults to DATATREE_ROOT, the root.
     *
     * @return array  All shares for the current app/share.
     */
    function listAllShares($parent = DATATREE_ROOT)
    {
        $sharelist = $this->_datatree->get(DATATREE_FORMAT_FLAT, $parent, true);
        if (is_a($sharelist, 'PEAR_Error') || !count($sharelist)) {
            // If we got back an error or an empty array, just return it.
            return $sharelist;
        }
        unset($sharelist[$parent]);

        $shares = &$this->getShares(array_keys($sharelist));
        if (is_a($shares, 'PEAR_Error')) {
            return $shares;
        }

        $this->_sortList = $shares;
        uasort($shares, array($this, '_sortShares'));
        $this->_sortList = null;

        return $shares;
    }

    /**
     * Returns an array of criteria for querying shares.
     *
     * @param string  $userid      The userid of the user to check access for.
     * @param integer $perm        The level of permissions required.
     * @param mixed   $attributes  Restrict the shares returned to those who
     *                             have these attribute values.
     *
     * @return array  The criteria tree for fetching this user's shares.
     */
    function getShareCriteria($userid, $perm = PERMS_SHOW, $attributes = null)
    {
        if (!empty($userid)) {
            $criteria = array(
                'OR' => array(
                    // (owner == $userid)
                    array(
                        'AND' => array(
                            array('field' => 'name', 'op' => '=', 'test' => 'owner'),
                            array('field' => 'value', 'op' => '=', 'test' => $userid))),

                    // (name == perm_users and key == $userid and val & $perm)
                    array(
                        'AND' => array(
                            array('field' => 'name', 'op' => '=', 'test' => 'perm_users'),
                            array('field' => 'key', 'op' => '=', 'test' => $userid),
                            array('field' => 'value', 'op' => '&', 'test' => $perm))),

                    // (name == perm_creator and val & $perm)
                    array(
                        'AND' => array(
                            array('field' => 'name', 'op' => '=', 'test' => 'perm_creator'),
                            array('field' => 'value', 'op' => '&', 'test' => $perm))),

                    // (name == perm_default and val & $perm)
                    array(
                        'AND' => array(
                            array('field' => 'name', 'op' => '=', 'test' => 'perm_default'),
                            array('field' => 'value', 'op' => '&', 'test' => $perm)))));

            // If the user has any group memberships, check for those also.
            require_once 'Horde/Group.php';
            $group = &Group::singleton();
            $groups = $group->getGroupMemberships($userid, true);
            if (is_array($groups) && $groups) {
                // (name == perm_groups and key in ($groups) and val & $perm)
                $criteria['OR'][] = array(
                    'AND' => array(
                        array('field' => 'name', 'op' => '=', 'test' => 'perm_groups'),
                        array('field' => 'key', 'op' => 'IN', 'test' => array_keys($groups)),
                        array('field' => 'value', 'op' => '&', 'test' => $perm)));
            }
        } else {
            $criteria = array(
                'AND' => array(
                     array('field' => 'name', 'op' => '=', 'test' => 'perm_guest'),
                     array('field' => 'value', 'op' => '&', 'test' => $perm)));
        }

        if (is_array($attributes)) {
            // Build attribute/key filter.
            foreach ($attributes as $key => $value) {
                $criteria = array(
                    'AND' => array(
                        $criteria,
                        array(
                            'JOIN' => array(
                                'AND' => array(
                                    array('field' => 'name', 'op' => '=', 'test' => $key),
                                    array('field' => 'value', 'op' => '=', 'test' => $value))))));
            }
        } elseif (!empty($attributes)) {
            // Restrict to shares owned by the user specified in the
            // $attributes string.
            $criteria = array(
                'AND' => array(
                    $criteria,
                    array(
                        'JOIN' => array(
                            array('field' => 'name', 'op' => '=', 'test' => 'owner'),
                            array('field' => 'value', 'op' => '=', 'test' => $attributes)))));
        }

        return $criteria;
    }

    /**
     * TODO
     *
     * @see Perms::getPermissions
     *
     * @param TODO
     * @param TODO
     *
     * @return TODO
     */
    function getPermissions($share, $user = null)
    {
        if (!is_a($share, 'DataTreeObject_Share')) {
            $share = &$this->getShare($share);
        }

        $perm = &$share->getPermission();
        return $GLOBALS['perms']->getPermissions($perm, $user);
    }

    /**
     * Returns the Identity for a particular share owner.
     *
     * @param mixed $share  The share to fetch the Identity for - either the
     *                      string name, or the DataTreeObject_Share object.
     *
     * @return string  The preference's value.
     */
    function &getIdentityByShare($share)
    {
        if (!is_a($share, 'DataTreeObject_Share')) {
            $share = $this->getShare($share);
            if (is_a($share, 'PEAR_Error')) {
                return null;
            }
        }

        require_once 'Horde/Identity.php';
        $owner = $share->get('owner');
        return $ret = &Identity::singleton('none', $owner);
    }

}

/**
 * Extension of the DataTreeObject class for storing Share information in the
 * DataTree driver. If you want to store specialized Share information, you
 * should extend this class instead of extending DataTreeObject directly.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   Horde 3.0
 * @package Horde_Share
 */
class DataTreeObject_Share extends DataTreeObject {

    /**
     * The Horde_Share object which this share came from - needed for
     * updating data in the backend to make changes stick, etc.
     *
     * @var Horde_Share
     */
    var $_shareOb;

    /**
     * The DataTreeObject_Share constructor. Just makes sure to call the parent
     * constructor so that the share's name is set properly.
     *
     * @param string $id  The id of the share.
     */
    function DataTreeObject_Share($id)
    {
        parent::DataTreeObject($id);
        if (is_null($this->data)) {
            $this->data = array();
        }
    }

    /**
     * Associates a Share object with this share.
     *
     * @param Horde_Share $shareOb  The Share object.
     */
    function setShareOb(&$shareOb)
    {
        $this->_shareOb = &$shareOb;
    }

    /**
     * Returns this share's parent object.
     *
     * @return DataTreeObject_Share  The parent share, if it exists.
     */
    function &getParent()
    {
        $parent = $this->_shareOb->getParent($this);
        return $parent;
    }

    /**
     * Gives a user a certain privilege for this share.
     *
     * @param string $userid       The userid of the user.
     * @param integer $permission  A PERMS_* constant.
     */
    function addUserPermission($userid, $permission)
    {
        $perm = &$this->getPermission();
        $perm->addUserPermission($userid, $permission, false);
        $this->setPermission($perm);
    }

    /**
     * Removes a certain privilege for a user from this share.
     *
     * @param string $userid       The userid of the user.
     * @param integer $permission  A PERMS_* constant.
     */
    function removeUserPermission($userid, $permission)
    {
        $perm = &$this->getPermission();
        $perm->removeUserPermission($userid, $permission, false);
        $this->setPermission($perm);
    }

    /**
     * Gives a group certain privileges for this share.
     *
     * @param string $group        The group to add permissions for.
     * @param integer $permission  A PERMS_* constant.
     */
    function addGroupPermission($group, $permission)
    {
        $perm = &$this->getPermission();
        $perm->addGroupPermission($group, $permission, false);
        $this->setPermission($perm);
    }

    /**
     * Removes a certain privilege from a group.
     *
     * @param string $group         The group to remove permissions from.
     * @param constant $permission  A PERMS_* constant.
     */
    function removeGroupPermission($group, $permission)
    {
        $perm = &$this->getPermission();
        $perm->removeGroupPermission($group, $permission, false);
        $this->setPermission($perm);
    }

    /**
     * Checks to see if a user has a given permission.
     *
     * @param string $userid       The userid of the user.
     * @param integer $permission  A PERMS_* constant to test for.
     * @param string $creator      The creator of the event.
     *
     * @return boolean  Whether or not $userid has $permission.
     */
    function hasPermission($userid, $permission, $creator = null)
    {
        if ($userid == $this->get('owner')) {
            return true;
        }

        return $GLOBALS['perms']->hasPermission($this->getPermission(), $userid, $permission, $creator);
    }

    /**
     * Removes a user from this share.
     *
     * @param string $userid  The userid of the user to remove.
     */
    function removeUser($userid)
    {
        /* Remove all $userid's permissions. */
        $perm = &$this->getPermission();
        $perm->removeUserPermission($userid, PERMS_SHOW, false);
        $perm->removeUserPermission($userid, PERMS_READ, false);
        $perm->removeUserPermission($userid, PERMS_EDIT, false);
        $perm->removeUserPermission($userid, PERMS_DELETE, false);
        return $this->setPermission($perm);
    }

    /**
     * Removes a group from this share.
     *
     * @param integer $groupId  The group to remove.
     */
    function removeGroup($groupId)
    {
        /* Remove all $groupId's permissions. */
        $perm = &$this->getPermission();
        $perm->removeGroupPermission($groupId, PERMS_SHOW, false);
        $perm->removeGroupPermission($groupId, PERMS_READ, false);
        $perm->removeGroupPermission($groupId, PERMS_EDIT, false);
        $perm->removeGroupPermission($groupId, PERMS_DELETE, false);
        return $this->setPermission($perm);
    }

    /**
     * Returns an array containing all the userids of the users with access to
     * this share.
     *
     * @param integer $perm_level  List only users with this permission level.
     *                             Defaults to all users.
     *
     * @return array  The users with access to this share.
     */
    function listUsers($perm_level = null)
    {
        $perm = &$this->getPermission();
        return array_keys($perm->getUserPermissions($perm_level));
    }

    /**
     * Returns an array containing all the groupids of the groups with access
     * to this share.
     *
     * @param integer $perm_level  List only users with this permission level.
     *                             Defaults to all users.
     *
     * @return array  The IDs of the groups with access to this share.
     */
    function listGroups($perm_level = null)
    {
        $perm = &$this->getPermission();
        return array_keys($perm->getGroupPermissions($perm_level));
    }

    /**
     * TODO
     *
     * @param TODO
     * @param boolean $update  TODO
     *
     * @return TODO
     */
    function setPermission(&$perm, $update = true)
    {
        $this->data['perm'] = $perm->getData();
        if ($update) {
            return $this->save();
        }
        return true;
    }

    /**
     * TODO
     *
     * @return TODO
     */
    function &getPermission()
    {
        $perm = &new DataTreeObject_Permission($this->getName());
        $perm->data = isset($this->data['perm']) ? $this->data['perm'] : array();

        return $perm;
    }

    /**
     * Forces all children of this share to inherit the permissions set on this
     * share.
     *
     * @return TODO
     */
    function inheritPermissions()
    {
        $perm = &$this->getPermission();
        $children = $this->_shareOb->listAllShares($this->getName());
        if (is_a($children, 'PEAR_Error')) {
            return $children;
        }

        foreach ($children as $child) {
            $child->setPermission($perm);
        }

        return true;
    }

    /**
     * Saves any changes to this object to the backend permanently. New objects
     * are added instead.
     *
     * @return boolean | PEAR_Error  PEAR_Error on failure.
     */
    function save()
    {
        $result = Horde::callHook('_horde_hook_share_modify', array($this),
                                  'horde', false);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return parent::save();
    }

    /**
     * Maps this object's attributes from the data array into a format that we
     * can store in the attributes storage backend.
     *
     * @access protected
     *
     * @param boolean $permsonly  Only process permissions? Lets subclasses
     *                            override part of this method while handling
     *                            their additional attributes seperately.
     *
     * @return array  The attributes array.
     */
    function _toAttributes($permsonly = false)
    {
        // Default to no attributes.
        $attributes = array();

        foreach ($this->data as $key => $value) {
            if ($key == 'perm') {
                foreach ($value as $type => $perms) {
                    if (is_array($perms)) {
                        foreach ($perms as $member => $perm) {
                            $attributes[] = array('name' => 'perm_' . $type,
                                                  'key' => $member,
                                                  'value' => $perm);
                        }
                    } else {
                        $attributes[] = array('name' => 'perm_' . $type,
                                              'key' => '',
                                              'value' => $perms);
                    }
                }
            } elseif (!$permsonly) {
                $attributes[] = array('name' => $key,
                                      'key' => '',
                                      'value' => $value);
            }
        }

        return $attributes;
    }

    /**
     * Takes in a list of attributes from the backend and maps it to our
     * internal data array.
     *
     * @access protected
     *
     * @param array $attributes   The list of attributes from the backend
     *                            (attribute name, key, and value).
     * @param boolean $permsonly  Only process permissions? Lets subclasses
     *                            override part of this method while handling
     *                            their additional attributes seperately.
     */
    function _fromAttributes($attributes, $permsonly = false)
    {
        // Initialize data array.
        $this->data['perm'] = array();

        foreach ($attributes as $attr) {
            if (substr($attr['name'], 0, 4) == 'perm') {
                if (!empty($attr['key'])) {
                    $this->data['perm'][substr($attr['name'], 5)][$attr['key']] = $attr['value'];
                } else {
                    $this->data['perm'][substr($attr['name'], 5)] = $attr['value'];
                }
            } elseif (!$permsonly) {
                $this->data[$attr['name']] = $attr['value'];
            }
        }
    }

}
