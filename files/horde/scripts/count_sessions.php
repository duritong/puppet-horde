#!/usr/bin/php
<?php
/**
 * $Horde: horde/scripts/count_sessions.php,v 1.5.2.3 2006/04/18 16:24:58 jan Exp $
 *
 * This script counts the number of active authenticated user sessions.
 *
 * Command line options:
 *   '-l'   List the username of active authenticated users
 *   '-ll'  List the username and login time of active authenticated users
 */

// No auth.
@define('AUTH_HANDLER', true);

// Find the base file path of Horde.
@define('HORDE_BASE', dirname(__FILE__) . '/..');

// Do CLI checks and environment setup first.
require_once HORDE_BASE . '/lib/core.php';
require_once 'Horde/CLI.php';

// Make sure no one runs this from the web.
if (!Horde_CLI::runningFromCLI()) {
    exit("Must be run from the command line\n");
}

// Load the CLI environment - make sure there's no time limit, init
// some variables, etc.
Horde_CLI::init();
$cli = &new Horde_CLI();

require_once HORDE_BASE . '/lib/base.php';

/* Make sure there's no compression. */
@ob_end_clean();

$type = !empty($conf['sessionhandler']['type']) ?
    $conf['sessionhandler']['type'] : 'builtin';

if ($type == 'external') {
    Horde::fatal(PEAR::raiseError('Session counting is not supported in the \'external\' SessionHandler at this time.'), __FILE__, __LINE__, false);
} else {
    require_once 'Horde/SessionHandler.php';
    $_session_handler = &SessionHandler::singleton($conf['sessionhandler']['type']);
    if (is_a($_session_handler, 'PEAR_Error')) {
        Horde::fatal(PEAR::raiseError(sprintf("Horde is unable to load session handler of type \"%s\".", $type)), __FILE__, __LINE__, false);
    }

    if ($argc < 2 || ($argv[1] != '-l' && $argv[1] != '-ll')) {
        $count = $_session_handler->countAuthenticatedUsers();
        if (is_a($count, 'PEAR_Error')) {
            Horde::fatal($count, __FILE__, __LINE__, false);
        }

        $cli->writeln($count);
    } else {
        $users = $_session_handler->listAuthenticatedUsers($argv[1] == '-ll');
        if (is_a($users, 'PEAR_Error')) {
            Horde::fatal($users, __FILE__, __LINE__, false);
        }

        foreach ($users as $user) {
            $cli->writeln($user);
        }
    }
}
