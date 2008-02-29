<?php

$block_name = _("Vacation Summary");

/**
 * $Horde: vacation/lib/Block/summary.php,v 1.7 2005/09/09 04:33:25 chuck Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_Vacation_summary extends Horde_Block {

    var $_app = 'vacation';

    function _title()
    {
        global $registry;
        return Horde::link(Horde::applicationUrl($registry->getInitialPage(), true)) . $registry->get('name') . '</a>';
    }

    function _content()
    {
        global $registry;
        require_once dirname(__FILE__) . '/../base.php';
        require_once VACATION_BASE . '/lib/Driver.php';

        // Get the current login credentials.
        $split = explode('@', Auth::getAuth());
        $user = @$split[0];
        $realm = @$split[1];
        $pass = Auth::getCredential('password');

        // Create the driver.
        $driver = &Vacation_Driver::factory();

        // Find out if vacation is active.
        if (!isset($driver)) {
            return '<p><em>' . _("Failed to create a vacation driver") . '</em></p>';
        }

        return '<p><strong>' . (!$driver->isEnabled($user, $realm, $pass) ?
                                _("Vacation is not active.") :
                                _("Vacation is active.")) . '</strong></p>';
    }

}
