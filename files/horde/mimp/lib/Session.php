<?php
/**
 * Functions required to start an MIMP session.
 *
 * $Horde: mimp/lib/Session.php,v 1.31.2.1 2007/01/02 13:55:09 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 * Copyright 2004-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package MIMP
 */
class MIMP_Session {

    /**
     * Take information posted from a login attempt and try setting up
     * an initial MIMP session. Handle Horde authentication, if
     * required, and only do enough work to see if the user can log
     * in. This function should only be called once, when the user
     * first logs in.
     *
     * @param string $imapuser  The username of the user.
     * @param string $password  The password of the user.
     * @param string $server    The server to use (see config/servers.php).
     * @param array $args       The necessary server information.
     *
     * @return mixed  True on success; false on failure.
     */
    function createSession($imapuser, $password, $server, $args = array())
    {
        global $conf;

        if (empty($imapuser) || empty($password)) {
            return false;
        }

        /* Make sure all necessary parameters are set. */
        $default_args = array(
            'realm'        => '',
            'port'         => '',
            'protocol'     => '',
            'maildomain'   => '',
            'imapchildren' => false,
        );

        /* Merge with the passed-in parameters. */
        $args = array_merge($default_args, $args);

        /* Create the $mimp session variable. */
        $_SESSION['mimp'] = array();
        $_SESSION['mimp']['pass'] = Secret::write(Secret::getKey('mimp'), $password);

        /* Run the username through virtualhost expansion functions if
         * necessary. */
        $_SESSION['mimp']['user'] = $imapuser;
        if (!empty($conf['hooks']['vinfo'])) {
            require_once HORDE_BASE . '/config/hooks.php';
            if (function_exists('_mimp_hook_vinfo')) {
                $_SESSION['mimp']['user'] = call_user_func('_mimp_hook_vinfo');
            }
        }

        /* We might need to override some of the defaults with
         * environment-wide settings. Do NOT use the global $servers
         * variable as it may not exist. */
        require MIMP_BASE . '/config/servers.php';

        /* Determine the unique user name. */
        if (Auth::isAuthenticated()) {
            $_SESSION['mimp']['uniquser'] = Auth::getAuth();
        } else {
            $_SESSION['mimp']['uniquser'] = $_SESSION['mimp']['user'];

            if (($conf['server']['server_list'] != 'none') &&
                !empty($servers[$server]['realm'])) {
                $_SESSION['mimp']['uniquser'] .= '@' . $servers[$server]['realm'];
            } elseif (!empty($args['realm'])) {
                $_SESSION['mimp']['uniquser'] .= '@' . $args['realm'];
            }
        }

       if (($conf['server']['server_list'] != 'none') &&
            !empty($servers[$server]) &&
            is_array($servers[$server])) {
            $fields = array('server', 'protocol', 'port', 'maildomain');
            $ptr = &$servers[$server];

            foreach ($fields as $val) {
                $_SESSION['mimp'][$val] = isset($ptr[$val]) ? $ptr[$val] : null;
            }
        } else {
            $server_key = null;
            foreach ($servers as $key => $val) {
                if (is_null($server_key) && (substr($key, 0, 1) != '_')) {
                    $server_key = $key;
                }
                if (MIMP::isPreferredServer($val, $key)) {
                    $server_key = $key;
                    break;
                }
            }
            $ptr = &$servers[$server_key];

            if ($conf['server']['change_server']) {
                $_SESSION['mimp']['server'] = $server;
            } else {
                $_SESSION['mimp']['server'] = $ptr['server'];
            }

            foreach (array('port', 'protocol', 'smtphost', 'smtpport') as $param) {
                if (!empty($conf['server']['change_' . $param])) {
                    $_SESSION['mimp'][$param] = isset($args[$param]) ? $args[$param] : null;
                } else {
                    $_SESSION['mimp'][$param] = isset($ptr[$param]) ? $ptr[$param] : null;
                }
            }

            $_SESSION['mimp']['maildomain'] = $args['maildomain'];
        }

        /* Determine the base protocol. */
        if (($pos = strpos($_SESSION['mimp']['protocol'], '/'))) {
            $_SESSION['mimp']['base_protocol'] = substr($_SESSION['mimp']['protocol'], 0, $pos);
        } else {
            $_SESSION['mimp']['base_protocol'] = $_SESSION['mimp']['protocol'];
        }

        /* Set the initial mailbox to blank. */
        $_SESSION['mimp']['mailbox'] = '';

        /* Try to authenticate with the given information. */
        $auth_mimp = &Auth::singleton(array('mimp', 'mimp'));
        $auth_mimp->authenticateOptions(array('flags' => OP_HALFOPEN));
        if ($auth_mimp->authenticate(null, null, true) !== true) {
            return false;
        }

        /* Set the session variables. These are cached. */
        $_SESSION['mimp']['mailbox'] = $GLOBALS['prefs']->getValue('mailbox');

        /* IMAP specific variables. */
        if ($_SESSION['mimp']['base_protocol'] == 'imap') {
            /* Check for timeouts. */
            if (!empty($ptr['timeout'])) {
                 $_SESSION['mimp']['timeout'] = $ptr['timeout'];
            }

            /* Check for manual configuration. */
            if (!empty($ptr['imap_config'])) {
                $_SESSION['mimp']['imapchildren'] = $ptr['imap_config']['children'];
                $_SESSION['mimp']['namespace'] = $ptr['imap_config']['namespace'];
            } else {
                require_once MIMP_BASE . '/lib/IMAP/Client.php';
                $imapclient = &new MIMP_IMAPClient($_SESSION['mimp']['server'], $_SESSION['mimp']['port'], $_SESSION['mimp']['protocol']);

                $_SESSION['mimp']['namespace'] = array();

                $use_tls = $imapclient->useTLS();
                $user_namespace = (isset($ptr['namespace']) && is_array($ptr['namespace'])) ? $ptr['namespace'] : array();
                if (is_a($use_tls, 'PEAR_Error')) {
                    if (!empty($user_namespace)) {
                        foreach ($ptr as $val) {
                            /* This is a correct TLS configuration - we only
                             * need to determine the delimiters for each
                             * namespace. Get the default delimiter value
                             * (per RFC 3501 [6.3.8]). */
                            $box = @imap_getmailboxes($_SESSION['mimp']['stream'], MIMP::serverString(), $val);
                            if (!empty($box[0]->delimiter)) {
                                $_SESSION['mimp']['namespace'][$val] = array('name' => $val, 'delimiter' => $box[0]->delimiter);
                            }
                        }
                    } else {
                        $auth_mimp->MIMPsetAuthErrorMsg($use_tls->getMessage());
                        $auth_mimp->clearAuth();
                        return false;
                    }
                } else {
                    /* Auto-detect namespace parameters from IMAP server. */
                    $res = $imapclient->login($_SESSION['mimp']['user'], $password);
                    if (is_a($res, 'PEAR_Error')) {
                        $auth_mimp->MIMPsetAuthErrorMsg($res->getMessage());
                        return false;
                    }
                    $_SESSION['mimp']['namespace'] = $imapclient->namespace($user_namespace);
                    if (!is_array($_SESSION['mimp']['namespace'])) {
                        $auth_mimp->MIMPsetAuthErrorMsg(_("Could not retrieve namespace information from IMAP server."));
                        return false;
                    }
                    $_SESSION['mimp']['imapchildren'] = $imapclient->queryCapability('CHILDREN');
                    $imapclient->logout();
                }
            }

            /* Initialize the 'showunsub' value. */
            $_SESSION['mimp']['showunsub'] = false;
        } else {
            $_SESSION['mimp']['namespace'] = null;
        }

        return true;
    }

}
