<?php
/**
 * Replaces occurences of %VAR% with VAR, if VAR exists in the webserver's
 * environment.  Ignores all text after a '#' character (shell-style
 * comments).
 *
 * $Horde: framework/Text_Filter/Filter/environment.php,v 1.3.10.5 2007/01/02 13:54:43 jan Exp $
 *
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 3.0
 * @package Horde_Text
 */
class Text_Filter_environment extends Text_Filter {

    /**
     * Returns a hash with replace patterns.
     *
     * @return array  Patterns hash.
     */
    function getPatterns()
    {
        $regexp = array('/^#.*$\n/m' => '',
                        '/^([^#]*)#.*$/m' => '$1',
                        '/%([A-Za-z_]+)%/e' => 'getenv("$1")');
        return array('regexp' => $regexp);
    }

}