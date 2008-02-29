<?php

/**
 * Sort by memo description.
 */
define('MNEMO_SORT_DESC', 0);

/**
 * Sort by memo category.
 */
define('MNEMO_SORT_CATEGORY', 1);

/**
 * Sort in ascending order.
 */
define('MNEMO_SORT_ASCEND', 0);

/**
 * Sort in descending order.
 */
define('MNEMO_SORT_DESCEND', 1);

/**
 * Mnemo Base Class.
 *
 * $Horde: mnemo/lib/Mnemo.php,v 1.52.2.9 2007/01/02 13:55:11 jan Exp $
 *
 * Copyright 2001-2007 Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jon Parise <jon@horde.org>
 * @since   Mnemo 1.0
 * @package Mnemo
 */
class Mnemo {

    /**
     * Retrieves the current user's note list from storage. This function will
     * also sort the resulting list, if requested.
     *
     * @param constant $sortby   The field by which to sort. (MNEMO_SORT_DESC,
     *                           MNEMO_SORT_CATEGORY)
     * @param constant $sortdir  The direction by which to sort.
     *                           (MNEMO_SORT_ASC, MNEMO_SORT_DESC)
     *
     * @return array  A list of the requested notes.
     *
     * @see Mnemo_Driver::listMemos()
     */
    function listMemos($sortby = MNEMO_SORT_DESC,
                       $sortdir = MNEMO_SORT_ASCEND)
    {
        global $conf, $display_notepads;
        $memos = array();

        /* Sort the memo list. */
        $sort_functions = array(
            MNEMO_SORT_DESC => 'ByDesc',
            MNEMO_SORT_CATEGORY => 'ByCategory'
        );

        foreach ($display_notepads as $notepad) {
            /* Create a Mnemo storage instance. */
            $storage = &Mnemo_Driver::singleton($notepad);
            $storage->retrieve();

            /* Retrieve the memo list from storage. */
            $newmemos = $storage->listMemos();
            $memos = array_merge($memos, $newmemos);
        }

        /* Sort the array if we have a sort function defined for this
         * field. */
        if (isset($sort_functions[$sortby])) {
            $prefix = ($sortdir == MNEMO_SORT_DESCEND) ? '_rsort' : '_sort';
            uasort($memos, array('Mnemo', $prefix . $sort_functions[$sortby]));
        }

        return $memos;
    }

    /**
     * Returns the number of notes in notepads that the current user owns.
     *
     * @return integer  The number of notes that the user owns.
     */
    function countMemos()
    {
        static $count;
        if (isset($count)) {
            return $count;
        }

        $notepads = Mnemo::listNotepads(true, PERMS_ALL);

        $count = 0;
        foreach (array_keys($notepads) as $notepad) {
            /* Create a Mnemo storage instance. */
            $storage = &Mnemo_Driver::singleton($notepad);
            $storage->retrieve();

            /* Retrieve the memo list from storage. */
            $count += count($storage->listMemos());
        }

        return $count;
    }

    /**
     * Retrieves a specific note from storage.
     *
     * @param string $memolist  The notepad to retrieve the note from.
     * @param string $memo      The Id of the note to retrieve.
     *
     * @return array  The note.
     */
    function getMemo($notepad, $memo)
    {
        $storage = &Mnemo_Driver::singleton($notepad);
        return $storage->get($memo);
    }

    /**
     * Lists all notepads a user has access to.
     *
     * @param boolean $owneronly   Only return memo lists that this user owns?
     *                             Defaults to false.
     * @param integer $permission  The permission to filter notepads by.
     *
     * @return array  The memo lists.
     */
    function listNotepads($owneronly = false, $permission = PERMS_SHOW)
    {
        $notepads = $GLOBALS['mnemo_shares']->listShares(Auth::getAuth(), $permission, $owneronly ? Auth::getAuth() : null);
        if (is_a($notepads, 'PEAR_Error')) {
            Horde::logMessage($notepads, __FILE__, __LINE__, PEAR_LOG_ERR);
            return array();
        }

        return $notepads;
    }

    /**
     * Returns the default notepad for the current user at the specified
     * permissions level.
     */
    function getDefaultNotepad($permission = PERMS_SHOW)
    {
        global $prefs;

        $default_notepad = $prefs->getValue('default_notepad');
        $notepads = Mnemo::listNotepads(false, $permission);

        if (isset($notepads[$default_notepad])) {
            return $default_notepad;
        } elseif ($prefs->isLocked('default_notepad')) {
            return '';
        } elseif (count($notepads)) {
            return key($notepads);
        }

        return false;
    }

    /**
     * Comparison function for sorting notes by description.
     *
     * @param array $a  Note one.
     * @param array $b  Note two.
     *
     * @return integer  1 if memo one is greater, -1 if memo two is greater; 0
     *                  if they are equal.
     */
    function _sortByDesc($a, $b)
    {
        return strcasecmp($a['desc'], $b['desc']);
    }

    /**
     * Comparison function for reverse sorting notes by description.
     *
     * @param array $a  Note one.
     * @param array $b  Note two.
     *
     * @return integer  -1 if note one is greater, 1 if note two is greater; 0
     *                  if they are equal.
     */
    function _rsortByDesc($a, $b)
    {
        return strcasecmp($b['desc'], $a['desc']);
    }

    /**
     * Comparison function for sorting notes by category.
     *
     * @param array $a  Note one.
     * @param array $b  Note two.
     *
     * @return integer  1 if note one is greater, -1 if note two is greater; 0
     *                  if they are equal.
     */
    function _sortByCategory($a, $b)
    {
        return strcasecmp($a['category'] ? $a['category'] : _("Unfiled"),
                          $b['category'] ? $b['category'] : _("Unfiled"));
    }

    /**
     * Comparison function for reverse sorting notes by category.
     *
     * @param array $a  Note one.
     * @param array $b  Note two.
     *
     * @return integer  -1 if note one is greater, 1 if note two is greater; 0
     *                  if they are equal.
     */
    function _rsortByCategory($a, $b)
    {
        return strcasecmp($b['category'] ? $b['category'] : _("Unfiled"),
                          $a['category'] ? $a['category'] : _("Unfiled"));
    }

    /**
     * Returns the specified permission for the current user.
     *
     * @since Mnemo 2.1
     *
     * @param string $permission  A permission, currently only 'max_notes'.
     *
     * @return mixed  The value of the specified permission.
     */
    function hasPermission($permission)
    {
        global $perms;

        if (!$perms->exists('mnemo:' . $permission)) {
            return true;
        }

        $allowed = $perms->getPermissions('mnemo:' . $permission);
        if (is_array($allowed)) {
            switch ($permission) {
            case 'max_notes':
                $allowed = array_reduce($allowed, create_function('$a, $b', 'return max($a, $b);'), 0);
                break;
            }
        }

        return $allowed;
    }

    /**
     * Builds Mnemo's list of menu items.
     */
    function getMenu($returnType = 'object')
    {
        global $conf, $registry, $print_link;

        require_once 'Horde/Menu.php';

        $menu = &new Menu();
        $menu->add(Horde::applicationUrl('list.php'), _("_List Notes"), 'mnemo.png', null, null, null, basename($_SERVER['PHP_SELF']) == 'index.php' ? 'current' : null);
        if (Mnemo::getDefaultNotepad(PERMS_EDIT) &&
            (!empty($conf['hooks']['permsdenied']) ||
             Mnemo::hasPermission('max_notes') === true ||
             Mnemo::hasPermission('max_notes') > Mnemo::countMemos())) {
            $menu->add(Horde::applicationUrl('memo.php?actionID=add_memo'), _("_New Note"), 'add.png', null, null, null, Util::getFormData('memo') ? '__noselection' : null);
        }
        if (Mnemo::getDefaultNotepad(PERMS_READ)) {
            $menu->add(Horde::applicationUrl('search.php'), _("_Search"), 'search.png', $registry->getImageDir('horde'));
        }

        if (Auth::getAuth()) {
            $menu->add(Horde::applicationUrl('notepads.php'), _("_My Notepads"), 'notepads.png');
        }

        /* Import/Export */
        if ($conf['menu']['import_export']) {
            $menu->add(Horde::applicationUrl('data.php'), _("_Import/Export"), 'data.png', $registry->getImageDir('horde'));
        }

        /* Print */
        if ($conf['menu']['print'] && isset($print_link)) {
            $menu->add($print_link, _("_Print"), 'print.png', $registry->getImageDir('horde'), '_blank', 'popup(this.href); return false;');
        }

        if ($returnType == 'object') {
            return $menu;
        } else {
            return $menu->render();
        }
    }

}
