<?php
/**
 * $Horde: turba/lib/prefs.php,v 1.2.10.6 2007/01/02 13:55:19 jan Exp $
 *
 * Copyright 2001-2007 Jon Parise <jon@horde.org>
 * Copyright 2002-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

function handle_columnselect($updated)
{
    $columns = Util::getFormData('columns');
    if (!empty($columns)) {
        $GLOBALS['prefs']->setValue('columns', $columns);
        return true;
    }

    return false;
}

function handle_addressbookselect($updated)
{
    $addressbooks = Util::getFormData('addressbooks');
    if (!empty($addressbooks)) {
        $GLOBALS['prefs']->setValue('addressbooks', $addressbooks);
        return true;
    }

    return false;
}

function handle_imsp_opt($updated)
{
    global $cfgSources;
    $name = Util::getFormData('imsp_books_delete');
    if ($name != 'none') {
        require_once 'Net/IMSP/Utils.php';
        $result = Net_IMSP_Utils::deleteBook($cfgSources[$name]);
        if (is_a($result, 'PEAR_Error')) {
            return false;
        }
    }
    $name = Util::getFormData('imsp_name');
    if ($name != '') {
        // Get user's default IMSP $cfgSource entry
        foreach ($cfgSources as $key => $params) {
            if ($params['type'] == 'imsp' && $params['params']['is_root']) {
                $default = $key;
                break;
            }
        }
        require_once 'Net/IMSP/Utils.php';
        $result = Net_IMSP_Utils::createBook($cfgSources[$default], $name);
        if (is_a($result, 'PEAR_Error')) {
            return false;
        }
    }
    return true;
}

/* Assign variables for select lists. */
if (!$prefs->isLocked('default_dir')) {
    $default_dir_options = array();
    foreach ($cfgSources as $key => $info) {
        $default_dir_options[$key] = $info['title'];
    }
}
