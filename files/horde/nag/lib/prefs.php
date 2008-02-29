<?php
/**
 * $Horde: nag/lib/prefs.php,v 1.3.10.4 2007/01/02 13:55:12 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

function handle_tasklistselect($updated)
{
    $default_tasklist = Util::getFormData('default_tasklist');
    if (!is_null($default_tasklist)) {
        $tasklists = Nag::listTasklists();
        if (is_array($tasklists) && isset($tasklists[$default_tasklist])) {
            $GLOBALS['prefs']->setValue('default_tasklist', $default_tasklist);
            return true;
        }
    }

    return false;
}

function handle_showsummaryselect($updated)
{
    $summary_categories = Util::getFormData('summary_categories');
    $GLOBAL['prefs']->setValue('summary_categories', $summary_categories);
    return true;
}
