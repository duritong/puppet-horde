<?php
/**
 * $Horde: vacation/main.php,v 1.55.2.5 2007/01/02 13:55:21 jan Exp $
 *
 * Copyright 2001-2007 Eric Rostetter <eric.rostetter@physics.utexas.edu>
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

@define('VACATION_BASE', dirname(__FILE__));
require_once VACATION_BASE . '/lib/base.php';

// Create a driver.
require_once VACATION_BASE . '/lib/Driver.php';
$driver = &Vacation_Driver::factory();

require_once VACATION_BASE . '/lib/AliasDriver.php';

// Get the current login username and realm.
@list($user, $realm) = explode('@', Auth::getAuth(), 2);
if (empty($realm) || empty($driver->_params[$realm])) {
    $realm = 'default';
}

// Check if hordeauth is set to 'full'
$hordeauth = $driver->getParam('hordeauth', $realm);
if ($hordeauth === 'full') {
    $user = Auth::getAuth();
}

$submit = Util::getFormData('submit', false);

if ($submit) {
    $vacationmode = Util::getFormData('mode', 'error');
    if ($vacationmode === 'error') {
        $notification->push(_("You must specify the mode (set or remove)"),
                            'horde.warning');
    }

    // Check for refused usernames, using current horde username.
    if (in_array($user, $conf['user']['refused'])) {
        $notification->push(sprintf(_("You can't change the vacation notice for %s."), $user), 'horde.error');
        $vacationmode = 'error';
    } else {
        // Check for password
        if (empty($hordeauth)) {
            $password = Util::getFormData('password', false);
            if (empty($password)) {
                $notification->push(_("You must give your password"), 'horde.warning');
                $vacationmode = 'error';
            }
        } else {
            $password = Auth::getCredential('password');
        }
    }

    // Call the requested function.
    switch ($vacationmode) {
    case 'set':
        if ($conf['aliases']['show']) {
            $alias = Util::getFormData('alias', '');
        } else {
            $aliases = &Vacation_AliasDriver::singleton();
            $alias_list = $aliases->getAliasesForUser($user);
            if (count($alias_list) > 0) {
                $alias = join(', ', $alias_list);
            } else {
                $alias = '';
            }
        }
        $vacationmsg = Util::getFormData('mess', false);

        $vacationtxt = '';
        // Include the mail subject if the driver supports it.
        if ($conf['vacation']['subject']) {
            $vacationtxt .= 'Subject: ' . Util::getFormData('subject') . "\n";
        }
        // Include the mail sender if the driver supports it.
        if ($conf['vacation']['from']) {
            $vacationtxt .= 'From: ' . Util::getFormData('from') . "\n";
        }
        $vacationtxt .= $vacationmsg;

        if (!$vacationmsg) {
            $notification->push(_("You must give a vacation message."),
                                'horde.warning');
        } elseif (!empty($conf['vacation']['validation_pattern']) &&
                  !@preg_match($conf['vacation']['validation_pattern'], $vacationtxt)) {
            // Validation is required, and wasn't matched.
            $notification->push(_("Your vacation message is not in the proper format."),
                                'horde.warning');
        } else {
            // Try and make sure to send Unix linefeeds.
            $vacationtxt = str_replace("\r\n", "\n", $vacationtxt);
            $vacationtxt = str_replace("\r", "\n", $vacationtxt);

            // Wrap at 75 characters.
            $vacationtxt = wordwrap($vacationtxt);

            if ($driver->setVacation($user, $realm, $password,
                                     $vacationtxt, $alias)) {
                $notification->push(_("Vacation notice successfully enabled."), 'horde.success');
            } else {
                $notification->push(sprintf(_("Failure in modifying vacation notice: %s"),
                                            $driver->err_str), 'horde.error');
            }
        }
        break;

    case 'unset':
        if ($driver->unsetVacation($user, $realm, $password)) {
            $notification->push(_("Vacation notice successfully removed."), 'horde.success');
        } else {
            $notification->push(sprintf(_("Failure in removing vacation notice: %s"),
                                        $driver->err_str), 'horde.error');
        }
        break;
    }
}

// If we can tell if vacation notices are enabled, then say so. But if this
// fails, it could be because it is disabled, or just because we can't tell,
// so just be quiet about it.
$pass = Auth::getCredential('password');
$status = $driver->isEnabled($user, $realm, $pass);
$onVacation = false;
if ($status == 'Y') {
    $curmessage = $driver->currentMessage($user, $realm, $pass);
    $notification->push(_("Your vacation notice is currently enabled."), 'horde.message');
    $onVacation = true;
} elseif ($status == 'N') {
    $curmessage = $driver->currentMessage($user, $realm, $pass);
    if (empty($curmessage)) {
        $curmessage = $conf['vacation']['default'];
    }
    $notification->push(_("Your vacation notice is currently disabled."), 'horde.message');
} else {
    // If the driver can't tell the difference between "disabled" and
    // "unknown", be inscrutable.
    $curmessage = $conf['vacation']['default'];
}

// Split the vacation text in a subject and a message if the driver supports
// it.
if ($conf['vacation']['subject']) {
    if (preg_match('/^Subject: ([^\n]+)\n(.+)$/s', $curmessage, $matches)) {
        $cursubject = $matches[1];
        $curmessage = $matches[2];
    } else {
        $cursubject = '';
    }
} 

// Split the vacation text in a sender and a message if the driver supports
// it.
if ($conf['vacation']['from']) {
    if (preg_match('/^From: ([^\n]+)\n(.+)$/s', $curmessage, $matches)) {
        $curfrom = $matches[1];
        $curmessage = $matches[2];
    } else {
        require_once 'Horde/Identity.php';
        $identity = &Identity::singleton();
        // Default "From:" from identities, with name (name <address>)
        $curfrom = $identity->getDefaultFromAddress(true);
    }
}

$alias = Util::getFormData('alias');
if (is_null($alias)) {
    $aliases = &Vacation_AliasDriver::singleton();
    $alias_list = $aliases->getAliasesForUser($user);
    if (is_array($alias_list) && count($alias_list) > 0) {
        $alias = join(', ', $alias_list);
    }
}

$title = _("Change Vacation Notices");
require VACATION_TEMPLATES . '/common-header.inc';
require VACATION_TEMPLATES . '/main/main.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
