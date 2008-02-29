<?php
/**
 * $Horde: horde/admin/groups.php,v 1.50.2.8 2007/01/02 13:54:03 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/Menu.php';
require_once 'Horde/Group.php';
require_once 'Horde/Tree.php';

if (!Auth::isAdmin()) {
    Horde::authenticationFailureRedirect();
}

$groups = &Group::singleton();
$auth = &Auth::singleton($conf['auth']['driver']);

$form = null;
$reload = false;
$actionID = Util::getFormData('actionID');
$cid = Util::getFormData('cid');

switch ($actionID) {
case 'addchild':
    if ($cid == DATATREE_ROOT) {
        $form = 'addchild.inc';
        $gname = _("All Groups");
    } else {
        $group = &$groups->getGroupById($cid);
        if (!is_a($group, 'PEAR_Error')) {
            $gname = $group->getShortName();
            $form = 'addchild.inc';
        }
    }
    break;

case 'addchildform':
    $parent = $cid;
    if ($parent == DATATREE_ROOT) {
        $child = &$groups->newGroup(Util::getFormData('child'));
    } else {
        $child = &$groups->newGroup(Util::getFormData('child'), $parent);
    }
    if (is_a($child, 'PEAR_Error')) {
        Horde::logMessage($child, __FILE__, __LINE__, PEAR_LOG_ERR);
        $notification->push(sprintf(_("Group was not created: %s."), $child->getMessage()), 'horde.error');
        break;
    }

    $result = $groups->addGroup($child);
    if (is_a($result, 'PEAR_Error')) {
        Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
        $notification->push(sprintf(_("\"%s\" was not created: %s."), $child->getShortName(), $result->getMessage()), 'horde.error');
    } else {
        $notification->push(sprintf(_("\"%s\" was added to the groups system."), $child->getShortName()), 'horde.success');
        $group = $child;
        $form = 'edit.inc';
        $reload = true;
    }
    break;

case 'delete':
    $group = &$groups->getGroupById($cid);
    if (!is_a($group, 'PEAR_Error')) {
        $form = 'delete.inc';
    }
    break;

case 'deleteform':
    if (Util::getFormData('confirm') == _("Delete")) {
        $group = &$groups->getGroupById($cid);
        if (is_a($group, 'PEAR_Error')) {
            $notification->push(_("Attempt to delete a non-existent group."), 'horde.error');
        } else {
            $result = $groups->removeGroup($group, true);
            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("Unable to delete \"%s\": %s."), $group->getShortName(), $result->getMessage()), 'horde.error');
             } else {
                $notification->push(sprintf(_("Successfully deleted \"%s\"."), $group->getShortName()), 'horde.success');
                $cid = null;
                $reload = true;
            }
        }
    }
    break;

case 'edit':
    $group = &$groups->getGroupById($cid);
    if (!is_a($group, 'PEAR_Error')) {
        $form = 'edit.inc';
    } elseif (($category = Util::getFormData('category')) !== null) {
        $group = &$groups->getGroup($category);
        if (!is_a($group, 'PEAR_Error')) {
            $form = 'edit.inc';
        } elseif (Util::getFormData('autocreate')) {
            $parent = Util::getFormData('parent');
            $group = &$groups->newGroup($category);
            $result = $groups->addGroup($group, $parent);
            if (!is_a($result, 'PEAR_Error')) {
                $form = 'edit.inc';
            }
        }
    }
    break;

case 'editform':
    $group = &$groups->getGroupById($cid);

    // make a copy of the group so we can restore it if there is an error.
    $restore = $group;

    // Add any new users.
    $newuser = Util::getFormData('new_user');
    if (!empty($newuser)) {
        if (is_array($newuser)) {
            foreach ($newuser as $new) {
                $group->addUser($new, false);
            }
        } else {
            $group->addUser($newuser, false);
        }
    }

    // Remove any users marked for purging.
    $removes = Util::getFormData('remove');
    if (!empty($removes) && is_array($removes)) {
        foreach ($removes as $user => $junk) {
            $group->removeUser($user, false);
        }
    }

    // Set the email address of the group.
    $group->set('email', Util::getFormData('email'));

    // Save the group to the backend.
    $result = $group->save();

    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result->getMessage(), 'horde.error');
        // restore backup copy
        $group = $restore;
    } else {
        $notification->push(sprintf(_("Updated \"%s\"."), $group->getShortName()), 'horde.success');
    }

    $form = 'edit.inc';
    $reload = true;
    break;
}

switch ($form) {
case 'addchild.inc':
    $notification->push('document.add_child.child.focus()', 'javascript');
    break;

case 'edit.inc':
    /* Set up the lists. */
    $users = $group->listUsers();
    if (is_a($users, 'PEAR_Error')) {
        $notification->push($users, 'horde.error');
        $users = array();
    }
    $all_users = $group->listAllUsers();
    if (is_a($all_users, 'PEAR_Error')) {
        $notification->push($all_users, 'horde.error');
        $all_users = array();
    }
    $inherited_users = array_diff($all_users, $users);

    if ($auth->hasCapability('list')) {
        $user_list = $auth->listUsers();
        if (is_a($user_list, 'PEAR_Error')) {
            $notification->push($user_list, 'horde.error');
            $user_list = array();
        }
        sort($user_list);
    } else {
        $user_list = array();
    }
    break;

}

$title = _("Group Administration");
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/admin/common-header.inc';
$notification->notify(array('listeners' => 'status'));
if (!empty($form)) {
    require HORDE_TEMPLATES . '/admin/groups/' . $form;
}

/* Get the perms tree. */
$nodes = $groups->listGroups(true);
$nodes[DATATREE_ROOT] = DATATREE_ROOT;

/* Set up some node params. */
$spacer = '&nbsp;&nbsp;&nbsp;&nbsp;';
$icondir = array('icondir' => $registry->getImageDir());
$group_node = $icondir + array('icon' => 'group.png');
$add = Horde::applicationUrl('admin/groups.php?actionID=addchild');
$edit = Horde::applicationUrl('admin/groups.php?actionID=edit');
$delete = Horde::applicationUrl('admin/groups.php?actionID=delete');
$edit_img = Horde::img('edit.png', _("Edit Group"));
$delete_img = Horde::img('delete.png', _("Delete Group"));

/* Set up the tree. */
$tree = Horde_Tree::factory('admin_groups', 'javascript');
$tree->setOption(array('alternate' => true, 'hideHeaders' => true));
$tree->setHeader(array(array('width' => '50%')));

/* Explicitly check for > 0 since we can be called with current = -1
 * for the root node. */
if ($cid > 0) {
    $cid_parents = $groups->getGroupParentList($cid);
    if (is_a($cid_parents, 'PEAR_ERror')) {
        Horde::fatal($cid_parents, __FILE__, __LINE__);
    }
}

foreach ($nodes as $id => $node) {
    $node_params = ($cid == $id) ? array('class' => 'selected') : array();
    if ($id == DATATREE_ROOT) {
        $add_img = Horde::img('group.png', _("Add New Group"));
        $add_link = Horde::link(Util::addParameter($add, 'cid', $id), _("Add New Group")) . $add_img . '</a>';

        $base_node_params = $icondir + array('icon' => 'administration.png');
        $tree->addNode($id, null, _("All Groups"), 0, true, $base_node_params + $node_params, array($spacer, $add_link));
    } else {
        $node_params['url'] = Util::addParameter($edit, 'cid', $id);
        $add_img = Horde::img('group.png', _("Add Child Group"));
        $add_link = Horde::link(Util::addParameter($add, 'cid', $id), _("Add Child Group")) . $add_img . '</a>';
        $delete_link = Horde::link(Util::addParameter($delete, 'cid', $id), _("Delete Group")) . $delete_img . '</a>';

        $parent_id = $groups->getGroupParent($id);
        $group_extra = array($spacer, $add_link, $delete_link);
        $tree->addNode($id, $parent_id, $groups->getGroupShortName($node), $groups->getLevel($id) + 1, (isset($cid_parents[$id])) ? true : false, $group_node + $node_params, $group_extra);
    }
}

echo '<h1 class="header">' . Horde::img('group.png') . ' ' . _("Groups") . '</h1>';
$tree->renderTree();
require HORDE_TEMPLATES . '/common-footer.inc';
