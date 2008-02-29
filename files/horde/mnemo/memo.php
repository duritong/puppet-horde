<?php
/**
 * $Horde: mnemo/memo.php,v 1.42.2.6 2007/01/02 13:55:10 jan Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Mnemo 1.0
 * @package Mnemo
 */

@define('MNEMO_BASE', dirname(__FILE__));
require_once MNEMO_BASE . '/lib/base.php';
require_once 'Horde/Prefs/CategoryManager.php';
$cManager = &new Prefs_CategoryManager();

/* Redirect to the notepad view if no action has been requested. */
$memo_id = Util::getFormData('memo');
$memolist_id = Util::getFormData('memolist');
$actionID = Util::getFormData('actionID');
if (is_null($actionID)) {
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
}

/* Run through the action handlers. */
switch ($actionID) {
case 'add_memo':
    /* Check permissions. */
    if (Mnemo::hasPermission('max_notes') !== true &&
        Mnemo::hasPermission('max_notes') <= Mnemo::countMemos()) {
        $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d notes."), Mnemo::hasPermission('max_notes')), ENT_COMPAT, NLS::getCharset());
        if (!empty($conf['hooks']['permsdenied'])) {
            $message = Horde::callHook('_perms_hook_denied', array('mnemo:max_notes'), 'horde', $message);
        }
        $notification->push($message, 'horde.error', array('content.raw'));
        header('Location: ' . Horde::applicationUrl('list.php', true));
        exit;
    }
    /* Set up the note attributes. */
    $memolist_id = Mnemo::getDefaultNotepad();
    if (is_a($memolist_id, 'PEAR_Error')) {
        $notification->push($memolist_id, 'horde.error');
    }
    $memo_id = null;
    $memo_body = '';
    $memo_category = '';

    $title = _("Adding A New Note");
    $notification->push('document.memo.memo_body.focus();', 'javascript');
    break;

case 'modify_memo':
    /* Get the current note. */
    $memo = Mnemo::getMemo($memolist_id, $memo_id);
    if (!$memo || !isset($memo['memo_id'])) {
        $notification->push(_("Note not found."), 'horde.error');
        header('Location: ' . Horde::applicationUrl('list.php', true));
        exit;
    }

    /* Set up the note attributes. */
    $memo_body = $memo['body'];
    $memo_category = $memo['category'];
    $title = sprintf(_("Modifying %s"), $memo['desc']);
    $notification->push('document.memo.memo_body.focus();', 'javascript');
    break;

case 'save_memo':
    /* Get the form values. */
    $memo_id = Util::getFormData('memo');
    $memo_body = Util::getFormData('memo_body');
    $memo_category = Util::getFormData('memo_category');
    $memolist_original = Util::getFormData('memolist_original');
    $notepad_target = Util::getFormData('notepad_target');

    $share = $GLOBALS['mnemo_shares']->getShare($notepad_target);
    if (is_a($share, 'PEAR_Error')) {
        $notification->push(sprintf(_("Access denied saving note: %s"), $share->getMessage()), 'horde.error');
    } elseif (!$share->hasPermission(Auth::getAuth(), PERMS_EDIT)) {
        $notification->push(sprintf(_("Access denied saving note to %s."), $share->get('name')), 'horde.error');
    } else {
        if ($new_category = Util::getFormData('new_category')) {
            $new_category = $cManager->add($new_category);
            if ($new_category) {
                $memo_category = $new_category;
            }
        }

        /* If $memo_id is set, we're modifying an existing note.  Otherwise,
         * we're adding a new note with the provided attributes. */
        if (!empty($memo_id) &&
            !is_a(Mnemo::getMemo($memolist_original, $memo_id), 'PEAR_Error')) {
            $storage = &Mnemo_Driver::singleton($memolist_original);
            $memo_desc = $storage->getMemoDescription($memo_body);
            $result = $storage->modify($memo_id, $memo_desc, $memo_body, $memo_category);

            if (!is_a($result, 'PEAR_Error') &&
                $memolist_original != $notepad_target) {
                /* Moving the note to another notepad. */
                $share = $GLOBALS['mnemo_shares']->getShare($memolist_original);
                if (!is_a($share, 'PEAR_Error') &&
                    $share->hasPermission(Auth::getAuth(), PERMS_DELETE)) {
                    $share = $GLOBALS['mnemo_shares']->getShare($notepad_target);
                    if (!is_a($share, 'PEAR_Error') &&
                        $share->hasPermission(Auth::getAuth(), PERMS_EDIT)) {
                        $result = $storage->move($memo_id, $notepad_target);
                    } else {
                        $notification->push(_("Access denied moving the note."), 'horde.error');
                    }
                } else {
                    $notification->push(_("Access denied moving the note."), 'horde.error');
                }
            }
        } else {
            /* Check permissions. */
            if (Mnemo::hasPermission('max_notes') !== true &&
                Mnemo::hasPermission('max_notes') <= Mnemo::countMemos()) {
                header('Location: ' . Horde::applicationUrl('list.php', true));
                exit;
            }
            /* Creating a new note. */
            $storage = &Mnemo_Driver::singleton($notepad_target);
            $memo_desc = $storage->getMemoDescription($memo_body);
            $result = $memo_id = $storage->add($memo_desc, $memo_body, $memo_category);
        }

        /* Check our results. */
        if (is_a($result, 'PEAR_Error')) {
            $notification->push(sprintf(_("There was an error saving the note: %s"), $result->getMessage()), 'horde.warning');
        } else {
            $notification->push(sprintf(_("Successfully saved \"%s\"."), $memo_desc), 'horde.success');
        }
    }

    /* Return to the notepad view. */
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
    break;

case 'delete_memos':
    /* Delete the note if we're provided with a valid note ID. */
    $memo_id = Util::getFormData('memo');
    $memolist_id = Util::getFormData('memolist');

    if (!is_null($memo_id) && Mnemo::getMemo($memolist_id, $memo_id)) {
        $share = $GLOBALS['mnemo_shares']->getShare($memolist_id);
        if (!is_a($share, 'PEAR_Error') &&
            $share->hasPermission(Auth::getAuth(), PERMS_DELETE)) {
            $storage = &Mnemo_Driver::singleton($memolist_id);
            $result = $storage->delete($memo_id);

            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("There was an error removing the note: %s"), $result->getMessage()), 'horde.warning');
            } else {
                $notification->push(_("The note was deleted."), 'horde.success');
            }
        } else {
            $notification->push(_("Access denied deleting note."), 'horde.warning');
        }
    }

    /* Return to the notepad. */
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
    break;

default:
    header('Location: ' . Horde::applicationUrl('list.php', true));
    exit;
}

$notification->push('document.memo.memo_body.focus()', 'javascript');
$notepads = Mnemo::listNotepads(false, PERMS_EDIT);
require MNEMO_TEMPLATES . '/common-header.inc';
require MNEMO_TEMPLATES . '/menu.inc';
require MNEMO_TEMPLATES . '/memo/memo.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
