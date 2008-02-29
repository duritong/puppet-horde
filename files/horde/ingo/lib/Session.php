<?php
/**
 * Functions required to start a Ingo session.
 *
 * $Horde: ingo/lib/Session.php,v 1.2.10.8 2007/01/02 13:55:03 jan Exp $
 *
 * Copyright 2004-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Session {

    /**
     * Create an ingo session.
     * This function should only be called once, when the user first uses
     * Ingo in a session.
     *
     * Creates the $ingo session variable with the following entries:
     * 'backend' (array) - The backend configuration to use.
     * 'change' (integer) - The timestamp of the last time the rules were
     *                      altered.
     * 'storage' (array) - Used by Ingo_Storage:: for caching data.
     * 'script_categories' (array) - The list of available categories for the
     *                               Ingo_Script driver in use.
     * 'script_generate' (boolean) - Is the Ingo_Script::generate() call
     *                               available?
     *
     * @return boolean  True on success, false on failure.
     */
    function createSession()
    {
        global $prefs;

        $_SESSION['ingo'] = array();
        $_SESSION['ingo']['change'] = 0;
        $_SESSION['ingo']['storage'] = array();

        /* Get the backend. */
        $_SESSION['ingo']['backend'] = Ingo::getBackend();

        /* Determine if the Ingo_Script:: generate() method is available. */
        $ingo_script = &Ingo::loadIngoScript();
        $_SESSION['ingo']['script_generate'] = $ingo_script->generateAvailable();

        /* Get the list of categories this driver supports. */
        $_SESSION['ingo']['script_categories'] = array_merge($ingo_script->availableActions(), $ingo_script->availableCategories());

        /* Disable categories as specified in preferences */
        if ($prefs->isLocked('blacklist') && in_array(INGO_STORAGE_ACTION_BLACKLIST, $_SESSION['ingo']['script_categories'])) {
            $_SESSION['ingo']['script_categories'] = array_diff($_SESSION['ingo']['script_categories'], array(INGO_STORAGE_ACTION_BLACKLIST));
        }
        if ($prefs->isLocked('whitelist') && in_array(INGO_STORAGE_ACTION_WHITELIST, $_SESSION['ingo']['script_categories'])) {
            $_SESSION['ingo']['script_categories'] = array_diff($_SESSION['ingo']['script_categories'], array(INGO_STORAGE_ACTION_WHITELIST));
        }
        if ($prefs->isLocked('vacation') && in_array(INGO_STORAGE_ACTION_VACATION, $_SESSION['ingo']['script_categories'])) {
            $_SESSION['ingo']['script_categories'] = array_diff($_SESSION['ingo']['script_categories'], array(INGO_STORAGE_ACTION_VACATION));
        }
        if ($prefs->isLocked('forward') && in_array(INGO_STORAGE_ACTION_FORWARD, $_SESSION['ingo']['script_categories'])) {
            $_SESSION['ingo']['script_categories'] = array_diff($_SESSION['ingo']['script_categories'], array(INGO_STORAGE_ACTION_FORWARD));
        }
    }

}
