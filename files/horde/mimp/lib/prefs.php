<?php
/**
 * $Horde: mimp/lib/prefs.php,v 1.6.2.1 2007/01/02 13:55:09 jan Exp $
 *
 * Copyright 2005-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

function handle_sentmailselect($updated)
{
    if ($GLOBALS['conf']['user']['allow_folders'] &&
        !$GLOBALS['prefs']->isLocked('sent_mail_folder')) {
        $sent_mail_folder = Util::getFormData('sent_mail');
        $sent_mail_new = String::convertCharset(Util::getFormData('sent_mail_new'), NLS::getCharset(), 'UTF7-IMAP');
        $sent_mail_default = $GLOBALS['prefs']->getValue('sent_mail_folder');
        if (empty($sent_mail_folder) && !empty($send_mail_new)) {
            $sent_mail_folder = MIMP::appendNamespace($sent_mail_new);
        } elseif (($sent_mail_folder == '-1') && !empty($sent_mail_default)) {
            $sent_mail_folder = $sent_mail_default;
        }
        $sent_mail_folder = MIMP::folderPref($sent_mail_folder, true);
        if (!empty($sent_mail_folder)) {
            include_once MIMP_BASE . '/lib/Folder.php';
            $mimp_folder = &MIMP_Folder::singleton();
            if (!$mimp_folder->exists($sent_mail_folder)) {
                $mimp_folder->create($sent_mail_folder, $GLOBALS['prefs']->getValue('subscribe'));
            }
        }
        $GLOBALS['identity']->setValue('sent_mail_folder', MIMP::folderPref($sent_mail_folder, false));
        return true;
    }

    return false;
}

/* Make sure we have an active IMAP stream. */
if (!$GLOBALS['registry']->callByPackage('mimp', 'getStream')) {
    header('Location: ' . Util::addParameter(Horde::applicationUrl('redirect.php'), 'url', Horde::selfUrl(true)));
    exit;
}

