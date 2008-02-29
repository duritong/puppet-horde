<?php
/**
 * $Horde: nag/tasklists.php,v 1.38.2.5 2007/12/19 18:26:23 chuck Exp $
 *
 * Copyright 2002-2007 Joel Vandal <jvandal@infoteck.qc.ca>
 * Copyright 2002-2007 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('NAG_BASE', dirname(__FILE__));
require_once NAG_BASE . '/lib/base.php';

/* Exit if this isn't an authenticated user. */
if (!Auth::getAuth()) {
    require NAG_BASE . '/list.php';
    exit;
}

/* Run through the action handlers. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'save':
    $to_edit = Util::getFormData('edit_share');
    $id = Util::getFormData('id');
    if (empty($id)) {
        $notification->push(_("Task lists must have a name."), 'horde.error');
        break;
    }

    $tasklist = '';
    if (!isset($to_edit) || $to_edit == '') {
        /* Create New Share. */
        $tasklist = $GLOBALS['nag_shares']->newShare(md5(microtime()));
        if (is_a($tasklist, 'PEAR_Error')) {
            $result = $tasklist;
        } else {
            $tasklist->set('name', $id);
            $tasklist->set('desc', Util::getFormData('description', ''));
            $result = $GLOBALS['nag_shares']->addShare($tasklist);
        }

        if (is_a($result, 'PEAR_Error')) {
            $notification->push(sprintf(_("The task list \"%s\" couldn't be created: %s"), $id, $result->getMessage()), 'horde.error');
        } else {
            $notification->push(sprintf(_("The task list \"%s\" has been created."), $id), 'horde.success');
        }
    } else {
        $tasklist = &$GLOBALS['nag_shares']->getShare($to_edit);
        if (is_a($tasklist, 'PEAR_Error')) {
            $notification->push($tasklist, 'horde.error');
        } elseif ($tasklist->get('owner') != Auth::getAuth()) {
            $notification->push(_("You are not allowed to change this task list."), 'horde.error');
        } else {
            $tasklist->set('name', $id);
            $tasklist->set('desc', Util::getFormData('description', ''));
            $result = $tasklist->save();
            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("The task list \"%s\" couldn't be saved: %s"), $id, $result->getMessage()), 'horde.error');
            } else {
                $notification->push(sprintf(_("The task list \"%s\" has been saved."), $id), 'horde.success');
            }
        }
    }

    unset($to_edit);
    break;

case 'delete':
    $to_delete = Util::getFormData('edit_share');
    $id = Util::getFormData('id');

    if ((isset($to_delete)) && (!empty($to_delete)) && ($to_delete != -1)) {
        $tasklist = $GLOBALS['nag_shares']->getShare($to_delete);
        if (is_a($tasklist, 'PEAR_Error')) {
            $notification->push($tasklist, 'horde.error');
            break;
        }
        if ($tasklist->get('owner') != Auth::getAuth()) {
            $notification->push(_("You are not allowed to delete this task list."), 'horde.error');
            break;
        }

        /* Delete the tasklist. */
        $storage = &Nag_Driver::singleton($to_delete);
        $result = $storage->deleteAll();
        if (is_a($result, 'PEAR_Error')) {
            $notification->push(sprintf(_("Unable to delete \"%s\": %s"), $tasklist->get('name'), $result->getMessage()), 'horde.error');
        } else {
            /* Remove share and all groups/permissions. */
            $GLOBALS['nag_shares']->removeShare($tasklist);
            $notification->push(sprintf(_("The task list \"%s\" has been deleted."), $tasklist->get('name')), 'horde.success');
        }
    } else {
        $notification->push(_("You must select a task list to be deleted."), 'horde.warning');
    }

    /* Make sure we still have at least one task list. */
    if (count(Nag::listTasklists(true)) == 0) {
        /* Create the share if it doesn't exist, on a best-effort
         * basis. */
        if (!$GLOBALS['nag_shares']->exists(Auth::getAuth())) {
            require_once 'Horde/Identity.php';

            $identity = &Identity::singleton();
            $name = $identity->getValue('fullname');
            if (trim($name) == '') {
                $name = Auth::removeHook(Auth::getAuth());
            }
            $tasklist = $GLOBALS['nag_shares']->newShare(Auth::getAuth());
            $tasklist->set('name', sprintf(_("%s's Task List"), $name));
            $GLOBALS['nag_shares']->addShare($tasklist);
        }
    }
    break;
}

/* Personal Task Lists. */
$personal_tasklists = Nag::listTasklists(true);

Horde::addScriptFile('popup.js', 'horde', true);
$title = _("Task Lists");
require NAG_TEMPLATES . '/common-header.inc';
require NAG_TEMPLATES . '/menu.inc';
require NAG_TEMPLATES . '/tasklists/tasklists.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
