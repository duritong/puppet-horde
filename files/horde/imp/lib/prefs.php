<?php

define('IMP_PREF_NO_FOLDER', '**nofolder');

/**
 * $Horde: imp/lib/prefs.php,v 1.3.10.22 2007/11/23 17:44:46 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

function handle_sentmailselect($updated)
{
    global $conf, $prefs, $identity;

    if ($conf['user']['allow_folders'] &&
        !$prefs->isLocked('sent_mail_folder')) {
        $sent_mail_folder = Util::getFormData('sent_mail_folder');
        $sent_mail_new = String::convertCharset(Util::getFormData('sent_mail_new'), NLS::getCharset(), 'UTF7-IMAP');
        $sent_mail_default = $prefs->getValue('sent_mail_folder');
        if (empty($sent_mail_folder) && !empty($sent_mail_new)) {
            $sent_mail_folder = IMP::appendNamespace($sent_mail_new);
        } elseif (($sent_mail_folder == '-1') && !empty($sent_mail_default)) {
            $sent_mail_folder = IMP::appendNamespace($sent_mail_default);
        }
        if (!empty($sent_mail_folder)) {
            include_once IMP_BASE . '/lib/Folder.php';
            $imp_folder = &IMP_Folder::singleton();
            if (!$imp_folder->exists($sent_mail_folder)) {
                $imp_folder->create($sent_mail_folder, $prefs->getValue('subscribe'));
            }
        }
        $identity->setValue('sent_mail_folder', IMP::folderPref($sent_mail_folder, false));
        $updated = true;
    }

    return $updated;
}

function handlefolders($updated, $pref, $folder, $new)
{
    global $conf, $prefs;

    if ($conf['user']['allow_folders']) {
        $folder = Util::getFormData($folder);
        if (isset($folder) && !$prefs->isLocked($pref)) {
            $new = String::convertCharset(Util::getFormData($new), NLS::getCharset(), 'UTF7-IMAP');
            if ($folder == IMP_PREF_NO_FOLDER) {
                $prefs->setValue($pref, '');
            } else {
                if (empty($folder) && !empty($new)) {
                    $folder = IMP::appendNamespace($new);
                    include_once IMP_BASE . '/lib/Folder.php';
                    $imp_folder = &IMP_Folder::singleton();
                    if (!$imp_folder->create($folder, $prefs->getValue('subscribe'))) {
                        $folder = null;
                    }
                }
                if (!empty($folder)) {
                    $prefs->setValue($pref, IMP::folderPref($folder, false));
                    $updated = true;
                }
            }
        }
    }

    return $updated;
}

function handle_folderselect($updated)
{
    return $updated | handlefolders($updated, 'drafts_folder', 'drafts', 'drafts_new');
}

function handle_trashselect($updated)
{
    return $updated | handlefolders($updated, 'trash_folder', 'trash', 'trash_new');
}

function handle_sourceselect($updated)
{
    global $prefs;

    $search_sources = Util::getFormData('search_sources');
    if (!is_null($search_sources)) {
        $prefs->setValue('search_sources', $search_sources);
        $updated = true;
    }

    $search_fields_string = Util::getFormData('search_fields_string');
    if (!is_null($search_fields_string)) {
        $prefs->setValue('search_fields', $search_fields_string);
        $updated = true;
    }

    $add_source = Util::getFormData('add_source');
    if (!is_null($add_source)) {
        $prefs->setValue('add_source', $add_source);
        $updated = true;
    }

    return $updated;
}

function handle_initialpageselect($updated)
{
    $initial_page = Util::getFormData('initial_page');
    $GLOBALS['prefs']->setValue('initial_page', $initial_page);
    return true;
}

function handle_encryptselect($updated)
{
    $default_encrypt = Util::getFormData('default_encrypt');
    $GLOBALS['prefs']->setValue('default_encrypt', $default_encrypt);
    return true;
}

function handle_spamselect($updated)
{
    return $updated | handlefolders($updated, 'spam_folder', 'spam', 'spam_new');
}

function handle_defaultsearchselect($updated)
{
    $default_search = Util::getFormData('default_search');
    $GLOBALS['prefs']->setValue('default_search', $default_search);
    return true;
}

function prefs_callback()
{
    global $prefs;

    /* Never allow trash and Virtual Trash to be activated at the same time.
     * If they collide, and a trash folder is defined, always default to trash
     * folder. */
    if ($prefs->isDirty('use_vtrash') || $prefs->isDirty('use_trash')) {
        if ($prefs->getValue('use_vtrash') && $prefs->getValue('use_trash')) {
            if ($prefs->getValue('trash_folder')) {
                $prefs->setValue('use_vtrash', 0);
                $GLOBALS['notification']->push(_("Cannot activate both a Trash folder and Virtual Trash. A Trash folder will be used."), 'horde.error');
            } else {
                $prefs->setValue('use_trash', 0);
                $GLOBALS['notification']->push(_("Cannot activate both a Trash folder and Virtual Trash. A Virtual Trash folder will be used."), 'horde.error');
            }
        }
    }

    /* Always check to make sure we have a valid trash folder if delete to
     * trash is active. */
    if (($prefs->isDirty('use_trash') || $prefs->isDirty('trash_folder')) &&
        $prefs->getValue('use_trash') &&
        !$prefs->getValue('trash_folder')) {
        $prefs->setValue('use_trash', 0);
        $GLOBALS['notification']->push(_("Cannot delete to trash unless a Trash folder is defined."), 'horde.error');
    }

    if (($prefs->isDirty('use_vtrash') && $prefs->getValue('use_vtrash')) ||
        $GLOBALS['prefs']->isDirty('use_vinbox')) {
        require_once IMP_BASE . '/lib/Search.php';
        $imp_search = new IMP_Search();
        $imp_search->sessionSetup();
    }

    if ($GLOBALS['prefs']->isDirty('subscribe')) {
        require_once IMP_BASE . '/lib/IMAP/Tree.php';
        $imptree = &IMP_Tree::singleton();
        $imptree->init();
    }

    /* If a maintenance option has been activated, we need to make sure the
     * global Horde 'do_maintenance' pref is also active. */
    if (!$GLOBALS['prefs']->isLocked('do_maintenance') &&
        !$GLOBALS['prefs']->getValue('do_maintenance')) {
        foreach (array('rename_sentmail_monthly', 'delete_sentmail_monthly', 'purge_sentmail', 'delete_attachments_monthly', 'purge_trash') as $val) {
            if ($GLOBALS['prefs']->getValue($val)) {
                $GLOBALS['prefs']->setValue('do_maintenance', true);
                break;
            }
        }
    }
}

require_once IMP_BASE . '/lib/Maintenance/imp.php';
$maint = new Maintenance_IMP();
foreach (($maint->exportIntervalPrefs()) as $val) {
    $$val = &$intervals;
}

/* Make sure we have an active IMAP stream. */
if (!$GLOBALS['registry']->call('mail/getStream')) {
    header('Location: ' . Util::addParameter(Horde::applicationUrl('redirect.php'), 'url', Horde::selfUrl(true)));
    exit;
}
