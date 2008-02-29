<?php
/**
 * Horde external API interface.
 *
 * This file defines Horde's external API interface. Other
 * applications can interact with Horde through this API.
 *
 * $Horde: horde/lib/api.php,v 1.43.2.7 2007/12/20 09:57:43 jan Exp $
 *
 * @package Horde
 */

/* Complex types. */
$_types = array(
    'stringArray' => array(array('item' => 'string')),
    'hashItem' => array('key' => 'string', 'value' => 'string'),
    'hash' => array(array('item' => '{urn:horde}hashItem')),
    'hashHashItem' => array('key' => 'string', 'value' => '{urn:horde}hash'),
    'hashHash' => array(array('item' => '{urn:horde}hashHashItem')));

/* Listings. */
$_services['perms'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray');

$_services['admin_list'] = array(true);

$_services['listApps'] = array(
    'args' => array('filter' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray'
);

$_services['listAPIs'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray'
);

/* Blocks. */
$_services['blockTitle'] = array(
    'args' => array('app' => 'string', 'name' => 'string', 'params' => '{urn:horde}hash'),
    'type' => 'string'
);

$_services['blockContent'] = array(
    'args' => array('app' => 'string', 'name' => 'string', 'params' => '{urn:horde}hash'),
    'type' => 'string'
);

$_services['blocks'] = array(
    'args' => array(),
    'type' => '{urn:horde}hash'
);

/* User data. */
$_services['getPreference'] = array(
    'args' => array('app' => 'string', 'pref' => 'string'),
    'type' => 'string'
);

$_services['setPreference'] = array(
    'args' => array('app' => 'string', 'pref' => 'string', 'value' => 'string'),
    'type' => 'boolean'
);

$_services['removeUserData'] = array(
    'args' => array('user' => 'string'),
    'type' => 'boolean'
);

/* Groups. */
$_services['addGroup'] = array(
    'args' => array('name' => 'string', 'parent' => 'string'),
    'type' => 'boolean'
);

$_services['removeGroup'] = array(
    'args' => array('name' => 'string'),
    'type' => 'boolean'
);

$_services['addUserToGroup'] = array(
    'args' => array('name' => 'string', 'user' => 'string'),
    'type' => 'boolean'
);

$_services['addUsersToGroup'] = array(
    'args' => array('name' => 'string', 'users' => '{urn:horde}stringArray'),
    'type' => 'boolean'
);

$_services['removeUserFromGroup'] = array(
    'args' => array('name' => 'string', 'user' => 'string'),
    'type' => 'boolean'
);

$_services['removeUsersFromGroup'] = array(
    'args' => array('name' => 'string', 'users' => '{urn:horde}stringArray'),
    'type' => 'boolean'
);

$_services['listUsersOfGroup'] = array(
    'args' => array('name' => 'string'),
    'type' => '{urn:horde}stringArray'
);

/* Shares. */
$_services['addShare'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'shareTitle' => 'string', 'userName' => 'string'),
    'type' => 'boolean'
);

$_services['removeShare'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string'),
    'type' => 'boolean'
);

$_services['listSharesOfOwner'] = array(
    'args' => array('shareRoot' => 'string', 'userName' => 'string'),
    'type' => '{urn:horde}stringArray'
);

$_services['addUserPermissions'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'userName' => 'string', 'permissions' => '{urn:horde}stringArray'),
    'type' => 'boolean'
);

$_services['addGroupPermissions'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'groupName' => 'string', 'permissions' => '{urn:horde}stringArray'),
    'type' => 'boolean'
);

$_services['removeUserPermissions'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'userName' => 'string'),
    'type' => 'boolean'
);

$_services['removeGroupPermissions'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'groupName' => 'string'),
    'type' => 'boolean'
);

$_services['listUserPermissions'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'userName' => 'string'),
    'type' => '{urn:horde}stringArray'
);

$_services['listGroupPermissions'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'groupName' => 'string'),
    'type' => '{urn:horde}stringArray'
);

$_services['listUsersOfShare'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'permissions' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray'
);

$_services['listGroupsOfShare'] = array(
    'args' => array('shareRoot' => 'string', 'shareName' => 'string', 'permissions' => '{urn:horde}stringArray'),
    'type' => '{urn:horde}stringArray'
);


/* Listings. */
function _horde_perms()
{
    $perms = array();

    $perms['tree']['horde']['max_blocks'] = false;
    $perms['title']['horde:max_blocks'] = _("Maximum Number of Portal Blocks");
    $perms['type']['horde:max_blocks'] = 'int';

    return $perms;
}

function _horde_admin_list()
{
    return array('configuration' => array(
                     'link' => '%application%/admin/setup/',
                     'name' => _("_Setup"),
                     'icon' => 'config.png'),
                 'users' => array(
                     'link' => '%application%/admin/user.php',
                     'name' => _("_Users"),
                     'icon' => 'user.png'),
                 'groups' => array(
                     'link' => '%application%/admin/groups.php',
                     'name' => _("_Groups"),
                     'icon' => 'group.png'),
                 'perms' => array(
                     'link' => '%application%/admin/perms/index.php',
                     'name' => _("_Permissions"),
                     'icon' => 'perms.png'),
                 'datatree' => array(
                     'link' => '%application%/admin/datatree.php',
                     'name' => _("_DataTree"),
                     'icon' => 'datatree.png'),
                 'sessions' => array(
                     'link' => '%application%/admin/sessions.php',
                     'name' => _("Sessions"),
                     'icon' => 'user.png'),
                 'phpshell' => array(
                     'link' => '%application%/admin/phpshell.php',
                     'name' => _("P_HP Shell"),
                     'icon' => 'mime/php.png'),
                 'sqlshell' => array(
                     'link' => '%application%/admin/sqlshell.php',
                     'name' => _("S_QL Shell"),
                     'icon' => 'sql.png'),
                 'cmdshell' => array(
                     'link' => '%application%/admin/cmdshell.php',
                     'name' => _("_CLI"),
                     'icon' => 'shell.png'),
                 );
}

function _horde_listApps($filter = null)
{
    return $GLOBALS['registry']->listApps($filter);
}

function _horde_listAPIs()
{
    return $GLOBALS['registry']->listAPIs();
}

/* Blocks. */
function &_horde_block($app, $name, $params = array())
{
    global $registry;

    require_once 'Horde/Block.php';
    require_once 'Horde/Block/Collection.php';

    $result = $registry->pushApp($app);
    if (!is_a($result, 'PEAR_Error')) {
        $result = Horde_Block_Collection::getBlock($app, $name, $params);
        $registry->popApp($app);
    }
    return $result;
}

function _horde_blockTitle($app, $name, $params = array())
{
    $block = &_horde_block($app, $name, $params);
    if (is_a($block, 'PEAR_Error')) {
        return $block->getMessage();
    }
    return $block->getTitle();
}

function _horde_blockContent($app, $name, $params = array())
{
    $block = &_horde_block($app, $name, $params);
    if (is_a($block, 'PEAR_Error')) {
        return $block->getMessage();
    }
    return $block->getContent();
}

function _horde_blocks()
{
    require_once 'Horde/Block/Collection.php';
    $collection = &Horde_Block_Collection::singleton();
    if (is_a($collection, 'PEAR_Error')) {
        return $collection;
    } else {
        return $collection->getBlocksList();
    }
}

/* User data. */
function _horde_getPreference($app, $pref)
{
    $GLOBALS['registry']->loadPrefs($app);
    return $GLOBALS['prefs']->getValue($pref);
}

function _horde_setPreference($app, $pref, $value)
{
    $GLOBALS['registry']->loadPrefs($app);
    return $GLOBALS['prefs']->setValue($pref, $value);
}

function _horde_removeUserData($user)
{
    if (!Auth::isAdmin() && $user != Auth::getAuth()) {
        return PEAR::raiseError(_("You are not allowed to remove user data."));
    }

    global $conf;

    require_once 'Horde/Prefs.php';
    $prefs = &Prefs::singleton($conf['prefs']['driver'], null, $user);
    if (is_a($result = $prefs->clear(), 'PEAR_Error')) {
        return $result;
    }
}

/* Groups. */

/**
 * Adds a group to the groups system.
 *
 * @param string $name    The group's name.
 * @param string $parent  The group's parent's name.
 */
function _horde_addGroup($name, $parent = null)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to add groups."));
    }

    if (empty($parent)) {
        $parent = DATATREE_ROOT;
    }

    require_once 'Horde/Group.php';
    $groups = &Group::singleton();

    if (is_a($group = &$groups->newGroup($name, $parent), 'PEAR_Error')) {
        return $group;
    }

    return $groups->addGroup($group);
}

/**
 * Removes a group from the groups system.
 *
 * @param string $name  The group's name.
 */
function _horde_removeGroup($name)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to delete groups."));
    }

    require_once 'Horde/Group.php';
    $groups = &Group::singleton();

    if (is_a($group = &$groups->getGroup($name), 'PEAR_Error')) {
        return $group;
    }

    return $groups->removeGroup($group, true);
}

/**
 * Adds a user to a group.
 *
 * @param string $name  The group's name.
 * @param string $user  The user to add.
 */
function _horde_addUserToGroup($name, $user)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to change groups."));
    }

    require_once 'Horde/Group.php';
    $groups = &Group::singleton();

    if (is_a($group = &$groups->getGroup($name), 'PEAR_Error')) {
        return $group;
    }

    return $group->addUser($user);
}

/**
 * Adds multiple users to a group.
 *
 * @param string $name  The group's name.
 * @param array $users  The users to add.
 */
function _horde_addUsersToGroup($name, $users)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to change groups."));
    }

    require_once 'Horde/Group.php';
    $groups = &Group::singleton();

    if (is_a($group = &$groups->getGroup($name), 'PEAR_Error')) {
        return $group;
    }

    foreach ($users as $user) {
        $group->addUser($user, false);
    }

    return $group->save();
}

/**
 * Removes a user from a group.
 *
 * @param string $name  The group's name.
 * @param string $user  The user to add.
 */
function _horde_removeUserFromGroup($name, $user)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to change groups."));
    }

    require_once 'Horde/Group.php';
    $groups = &Group::singleton();

    if (is_a($group = &$groups->getGroup($name), 'PEAR_Error')) {
        return $group;
    }

    return $group->removeUser($user);
}

/**
 * Removes multiple users from a group.
 *
 * @param string $name  The group's name.
 * @param array $users  The users to add.
 */
function _horde_removeUsersFromGroup($name, $users)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to change groups."));
    }

    require_once 'Horde/Group.php';
    $groups = &Group::singleton();

    if (is_a($group = &$groups->getGroup($name), 'PEAR_Error')) {
        return $group;
    }

    foreach ($users as $user) {
        $group->removeUser($user, false);
    }

    return $group->save();
}

/**
 * Returns a list of users that are part of this group (and only this group)
 *
 * @return array  The user list.
 */
function _horde_listUsersOfGroup($name)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to list users of groups."));
    }

    require_once 'Horde/Group.php';
    $groups = &Group::singleton();

    if (is_a($group = &$groups->getGroup($name), 'PEAR_Error')) {
        return $group;
    }

    return $group->listUsers();
}

/* Shares. */

/**
 * Adds a share to the shares system.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param string $shareTitle  The share's human readable title.
 * @param string $userName    The share's owner.
 */
function _horde_addShare($shareRoot, $shareName, $shareTitle, $userName)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to add shares."));
    }

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    if (is_a($share = &$shares->newShare($shareName), 'PEAR_Error')) {
        return $share;
    }
    $share->set('owner', $userName);
    $share->set('name', $shareTitle);

    return $shares->addShare($share);
}

/**
 * Removes a share from the shares system permanently.
 *
 * @param string $shareRoot  The name of the share root, e.g. the
 *                           application that the share belongs to.
 * @param string $shareName  The share's name.
 */
function _horde_removeShare($shareRoot, $shareName)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to delete shares."));
    }

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }

    return $shares->removeShare($share);
}

/**
 * Returns an array of all shares that $userName is the owner of.
 *
 * @param string $shareRoot  The name of the share root, e.g. the
 *                           application that the share belongs to.
 * @param string $userName   The share's owner.
 *
 * @return array  The list of shares.
 */
function _horde_listSharesOfOwner($shareRoot, $userName)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to list shares."));
    }

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    $share_list = &$shares->listShares($userName, PERMS_SHOW, $userName);
    $myshares = array();
    foreach ($share_list as $share) {
        $myshares[] = $share->getName();
    }

    return $myshares;
}

/**
 * Gives a user certain privileges for a share.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param string $userName    The user's name.
 * @param array $permissions  A list of permissions (show, read, edit, delete).
 */
function _horde_addUserPermissions($shareRoot, $shareName, $userName,
                                   $permissions)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to change shares."));
    }

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }

    $perm = &$share->getPermission();
    foreach ($permissions as $permission) {
        $permission = String::upper($permission);
        if (defined('PERMS_' . $permission)) {
            $perm->addUserPermission($userName, constant('PERMS_' . $permission), false);
        }
    }
    return $share->setPermission($perm);
}

/**
 * Gives a group certain privileges for a share.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param string $groupName   The group's name.
 * @param array $permissions  A list of permissions (show, read, edit, delete).
 */
function _horde_addGroupPermissions($shareRoot, $shareName, $groupName,
                                    $permissions)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to change shares."));
    }

    require_once 'Horde/Share.php';
    require_once 'Horde/Group.php';
    $shares = &Horde_Share::singleton($shareRoot);
    $groups = &Group::singleton();

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }
    if (is_a($groupId = $groups->getGroupId($groupName), 'PEAR_Error')) {
        return $groupId;
    }

    $perm = &$share->getPermission();
    foreach ($permissions as $permission) {
        $permission = String::upper($permission);
        if (defined('PERMS_' . $permission)) {
            $perm->addGroupPermission($groupId, constant('PERMS_' . $permission), false);
        }
    }
    return $share->setPermission($perm);
}

/**
 * Removes a user from a share.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param string $userName    The user's name.
 */
function _horde_removeUserPermissions($shareRoot, $shareName, $userName)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to change shares."));
    }

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }

    return $share->removeUser($userName);
}

/**
 * Removes a group from a share.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param string $groupName   The group's name.
 */
function _horde_removeGroupPermissions($shareRoot, $shareName, $groupName)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to change shares."));
    }

    require_once 'Horde/Share.php';
    require_once 'Horde/Group.php';
    $shares = &Horde_Share::singleton($shareRoot);
    $groups = &Group::singleton();

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }
    if (is_a($groupId = $groups->getGroupId($groupName), 'PEAR_Error')) {
        return $groupId;
    }

    return $share->removeGroup($groupId);
}

/**
 * Returns an array of all user permissions on a share.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param string $userName    The user's name.
 *
 * @return array  All user permissions for this share.
 */
function _horde_listUserPermissions($shareRoot, $shareName, $userName)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to list share permissions."));
    }

    $perm_map = array(PERMS_SHOW => 'show',
                      PERMS_READ => 'read',
                      PERMS_EDIT => 'edit',
                      PERMS_DELETE => 'delete');

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }

    $perm = &$share->getPermission();
    $permissions = $perm->getUserPermissions();
    if (empty($permissions[$userName])) {
        return array();
    }

    $user_permissions = array();
    foreach (array_keys(Perms::integerToArray($permissions[$userName])) as $permission) {
        $user_permissions[] = $perm_map[$permission];
    }

    return $user_permissions;
}

/**
 * Returns an array of all group permissions on a share.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param string $groupName   The group's name.
 *
 * @return array  All group permissions for this share.
 */
function _horde_listGroupPermissions($shareRoot, $shareName, $groupName)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to list share permissions."));
    }

    $perm_map = array(PERMS_SHOW => 'show',
                      PERMS_READ => 'read',
                      PERMS_EDIT => 'edit',
                      PERMS_DELETE => 'delete');

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }

    $perm = &$share->getPermission();
    $permissions = $perm->getGroupPermissions();
    if (empty($permissions[$groupName])) {
        return array();
    }

    $group_permissions = array();
    foreach (array_keys(Perms::integerToArray($permissions[$groupName])) as $permission) {
        $group_permissions[] = $perm_map[$permission];
    }

    return $group_permissions;
}

/**
 * Returns a list of users which have have certain permissions on a share.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param array $permissions  A list of permissions (show, read, edit, delete).
 *
 * @return array  List of users with the specified permissions.
 */
function _horde_listUsersOfShare($shareRoot, $shareName, $permissions)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to list users of shares."));
    }

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }

    $perm = 0;
    foreach ($permissions as $permission) {
        $permission = String::upper($permission);
        if (defined('PERMS_' . $permission)) {
            $perm &= constant('PERMS_' . $permission);
        }
    }

    return $share->listUsers($perm);
}

/**
 * Returns a list of groups which have have certain permissions on a share.
 *
 * @param string $shareRoot   The name of the share root, e.g. the
 *                            application that the share belongs to.
 * @param string $shareName   The share's name.
 * @param array $permissions  A list of permissions (show, read, edit, delete).
 *
 * @return array  List of groups with the specified permissions.
 */
function _horde_listGroupsOfShare($shareRoot, $shareName, $permissions)
{
    if (!Auth::isAdmin()) {
        return PEAR::raiseError(_("You are not allowed to list groups of shares."));
    }

    require_once 'Horde/Share.php';
    $shares = &Horde_Share::singleton($shareRoot);

    if (is_a($share = &$shares->getShare($shareName), 'PEAR_Error')) {
        return $share;
    }

    $perm = 0;
    foreach ($permissions as $permission) {
        $permission = String::upper($permission);
        if (defined('PERMS_' . $permission)) {
            $perm &= constant('PERMS_' . $permission);
        }
    }

    return $share->listGroups($perm);
}
