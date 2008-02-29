<?php
// Compose encryption options
/**
 * Send Message w/no encryption.
 */
define('IMP_ENCRYPT_NONE', 1);

/**
 * Send Message - PGP Encrypt.
 */
define('IMP_PGP_ENCRYPT', 2);

/**
 * Send Message - PGP Sign.
 */
define('IMP_PGP_SIGN', 3);

/**
 * Send Message - PGP Sign/Encrypt.
 */
define('IMP_PGP_SIGNENC', 4);

/**
 * Send Message - S/MIME Encrypt.
 */
define('IMP_SMIME_ENCRYPT', 5);

/**
 * Send Message - S/MIME Sign.
 */
define('IMP_SMIME_SIGN', 6);

/**
 * Send Message - S/MIME Sign/Encrypt.
 */
define('IMP_SMIME_SIGNENC', 7);

// IMAP Flags
/**
 * Match all IMAP flags.
 */
define('IMP_ALL', 0);

/**
 * \\UNSEEN flag
.*/
define('IMP_UNSEEN', 1);

/**
 * \\DELETED flag
.*/
define('IMP_DELETED', 2);

/**
 * \\ANSWERED flag.
 */
define('IMP_ANSWERED', 4);

/**
 * \\FLAGGED flag.
 */
define('IMP_FLAGGED', 8);

/**
 * \\DRAFT flag.
 */
define('IMP_DRAFT', 16);

/**
 * An email is personal.
 */
define('IMP_PERSONAL', 32);

// IMAP Sorting Constant
/**
 * Sort By Thread.
 */
@define('SORTTHREAD', 161);

// IMP Mailbox view constants
/**
 * Start on the page with the first unseen message.
 */
define('IMP_MAILBOXSTART_FIRSTUNSEEN', 1);

/**
 * Start on the page with the last unseen message.
 */
define('IMP_MAILBOXSTART_LASTUNSEEN', 2);

/**
 * Start on the first page.
 */
define('IMP_MAILBOXSTART_FIRSTPAGE', 3);

/**
 * Start on the last page.
 */
define('IMP_MAILBOXSTART_LASTPAGE', 4);

// IMP mailbox labels
/**
 * The mailbox name to use for search results.
 */
define('IMP_SEARCH_MBOX', '**search_');

// IMP internal indexing strings
/**
 * String used to separate messages.
 */
define('IMP_MSG_SEP', "\0");

/**
 * String used to separate indexes.
 */
define('IMP_IDX_SEP', "\1");

/**
 * IMP Base Class.
 *
 * $Horde: imp/lib/IMP.php,v 1.449.4.71 2007/05/04 16:36:38 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 2.3.5
 * @package IMP
 */
class IMP {

    /**
     * Returns the AutoLogin server key.
     *
     * @param boolean $first  Return the first value?
     *
     * @return string  The server key.
     */
    function getAutoLoginServer($first = false)
    {
        require IMP_BASE . '/config/servers.php';
        foreach ($servers as $key => $curServer) {
            if (empty($server_key) && substr($key, 0, 1) != '_') {
                $server_key = $key;
            }
            if (IMP::isPreferredServer($curServer, ($first) ? $key : null)) {
                $server_key = $key;
                if ($first) {
                    break;
                }
            }
        }

        return $server_key;
    }

    /**
     * Returns whether we can log in without a login screen for $server_key.
     *
     * @param string $server_key  The server to check. Defaults to
     *                            IMP::getCurrentServer().
     * @param boolean $force      If true, check $server_key even if there is
     *                            more than one server available.
     *
     * @return boolean  True or false.
     */
    function canAutoLogin($server_key = null, $force = false)
    {
        require IMP_BASE . '/config/servers.php';

        $auto_server = IMP::getAutoLoginServer();
        if (is_null($server_key)) {
            $server_key = $auto_server;
        }

        return (((count($auto_server) == 1) || $force) &&
                Auth::getAuth() &&
                !empty($servers[$server_key]['hordeauth']));
    }

    /**
     * Makes sure the user has been authenticated to view the page.
     *
     * @param mixed $flags     Any flags to pass to imap_open(). See
     *                         Auth_imp::authenticate(). However, if this is
     *                         the string 'horde', we just check for Horde auth
     *                         and don't bother the IMAP server.
     * @param boolean $return  If this is true, return false instead of
     *                         exiting/redirecting if authentication fails.
     *
     * @return boolean  True on success, false on error.
     */
    function checkAuthentication($flags = 0, $return = false)
    {
        if ($flags === 'horde') {
            $reason = Auth::isAuthenticated();
        } else {
            $auth_imp = &Auth::singleton(array('imp', 'imp'));
            $auth_imp->authenticateOptions(array('flags' => $flags));
            $reason = $auth_imp->authenticate();
        }

        if ($reason !== true) {
            if ($return) {
                return false;
            }

            if (Util::getFormData('popup')) {
                Util::closeWindowJS();
            } else {
                $url = Auth::addLogoutParameters(IMP::logoutUrl());
                $url = Util::addParameter($url, 'url', Horde::selfUrl(true));
                header('Location: ' . $url);
            }
            exit;
        }

        return true;
    }

    /**
     * Determines if the given mail server is the "preferred" mail server for
     * this web server.  This decision is based on the global 'SERVER_NAME'
     * and 'HTTP_HOST' server variables and the contents of the 'preferred'
     * either field in the server's definition.  The 'preferred' field may
     * take a single value or an array of multiple values.
     *
     * @param string $server  A complete server entry from the $servers hash.
     * @param TODO $key       TODO
     *
     * @return boolean  True if this entry is "preferred".
     */
    function isPreferredServer($server, $key = null)
    {
        static $urlServer;

        if (!isset($urlServer)) {
            $urlServer = Util::getFormData('server');
        }

        if (!empty($urlServer)) {
            return ($key == $urlServer);
        }

        if (!empty($server['preferred'])) {
            if (is_array($server['preferred'])) {
                if (in_array($_SERVER['SERVER_NAME'], $server['preferred']) ||
                    in_array($_SERVER['HTTP_HOST'], $server['preferred'])) {
                    return true;
                }
            } elseif (($server['preferred'] == $_SERVER['SERVER_NAME']) ||
                      ($server['preferred'] == $_SERVER['HTTP_HOST'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates a full c-client server specification string.
     *
     * @param string $mbox      The mailbox to append to end of the server
     *                          string.
     * @param string $protocol  Override the protocol currently being used.
     *
     * @return string  The full spec string.
     */
    function serverString($mbox = null, $protocol = null)
    {
        $srvstr = '{' . $_SESSION['imp']['server'];

        /* If port is not specified, don't include it in the string. */
        if (!empty($_SESSION['imp']['port'])) {
            $srvstr .= ':' . $_SESSION['imp']['port'];
        }

        if (is_null($protocol)) {
            $protocol = $_SESSION['imp']['protocol'];
        }

        /* If protocol is not specified, don't include it in the string. */
        if (!empty($protocol)) {
            $srvstr .= '/' . $protocol;
        }

        return $srvstr . '}' . $mbox;
    }

    /**
     * Returns the plain text label that is displayed for the current mailbox,
     * replacing IMP_SEARCH_MBOX with an appropriate string and removing
     * namespace and folder prefix information from what is shown to the user.
     *
     * @return string  The plain text label.
     */
    function getLabel()
    {
        $label = '';

        if ($GLOBALS['imp_search']->searchMboxID()) {
            $label = $GLOBALS['imp_search']->getLabel();
        } else {
            $label = IMP::displayFolder($_SESSION['imp']['mailbox']);
        }

        return $label;
    }

    /**
     * Returns the bare address.
     *
     * @param string $address    The address string.
     * @param boolean $multiple  Should we return multiple results?
     *
     * @return mixed  See {@link MIME::bareAddress}.
     */
    function bareAddress($address, $multiple = false)
    {
        static $addresses;

        if (!isset($addresses[(string)$multiple][$address])) {
            require_once 'Horde/MIME.php';
            $addresses[(string)$multiple][$address] = MIME::bareAddress($address, $_SESSION['imp']['maildomain'], $multiple);
        }

        return $addresses[(string)$multiple][$address];
    }

    /**
     * Uses the Registry to expand names and returning error information for
     * any address that is either not valid or fails to expand.
     *
     * @param string $addrString  The name(s) or address(es) to expand.
     * @param boolean $full       If true generate a full, rfc822-valid address
     *                            list.
     *
     * @return mixed   Either a string containing all expanded addresses or an
     *                 array containing all matching address or an error
     *                 object.
     */
    function expandAddresses($addrString, $full = false)
    {
        if (!preg_match('|[^\s]|', $addrString)) {
            return '';
        }

        global $prefs;

        require_once 'Mail/RFC822.php';
        require_once 'Horde/MIME.php';

        $parser = &new Mail_RFC822(null, '@INVALID');
        $search_fields = array();

        $src = explode("\t", $prefs->getValue('search_sources'));
        if ((count($src) == 1) && empty($src[0])) {
            $src = array();
        }

        if (($val = $prefs->getValue('search_fields'))) {
            $field_arr = explode("\n", $val);
            foreach ($field_arr as $field) {
                $field = trim($field);
                if (!empty($field)) {
                    $tmp = explode("\t", $field);
                    if (count($tmp) > 1) {
                        $source = array_splice($tmp, 0, 1);
                        $search_fields[$source[0]] = $tmp;
                    }
                }
            }
        }

        $arr = MIME::rfc822Explode($addrString, ',');
        $arr = array_map('trim', $arr);
        $arr = array_filter($arr);

        $results = $GLOBALS['registry']->call('contacts/search', array($arr, $src, $search_fields));
        if (is_a($results, 'PEAR_Error')) {
            return $results;
        }

        /* Remove any results with empty email addresses. */
        $keys = array_keys($results);
        foreach ($keys as $key) {
            $subTotal = count($results[$key]);
            for ($i = 0; $i < $subTotal; ++$i) {
                if (empty($results[$key][$i]['email'])) {
                    unset($results[$key][$i]);
                }
            }
        }

        $ambiguous = false;
        $error = false;
        $missing = array();

        foreach ($arr as $i => $tmp) {
            $address = MIME::encodeAddress($tmp, null, '');
            if (!is_a($address, 'PEAR_Error') &&
                ($parser->validateMailbox($address) ||
                 $parser->_isGroup($address))) {
                // noop
            } elseif (!isset($results[$tmp]) || !count($results[$tmp])) {
                /* Handle the missing/invalid case - we should return error
                 * info on each address that couldn't be
                 * expanded/validated. */
                $error = true;
                if (!$ambiguous) {
                    $arr[$i] = PEAR::raiseError(null, null, null, null, $arr[$i]);
                    $missing[$i] = $arr[$i];
                }
            } else {
                $res = $results[$tmp];
                if (count($res) == 1) {
                    if ($full) {
                        if (strpos($res[0]['email'], ',') !== false) {
                            $vars = get_class_vars('MIME');
                            $arr[$i] = MIME::_rfc822Encode($res[0]['name'], $vars['rfc822_filter'] . '.') . ': ' . $res[0]['email'] . ';';
                        } else {
                            list($mbox, $host) = explode('@', $res[0]['email']);
                            $arr[$i] = MIME::rfc822WriteAddress($mbox, $host, $res[0]['name']);
                        }
                    } else {
                        $arr[$i] = $res[0]['name'];
                    }
                } else {
                    /* Handle the multiple case - we return an array
                     * with all found addresses. */
                    $arr[$i] = array($arr[$i]);
                    foreach ($res as $one_res) {
                        if ($full) {
                            if (strpos($one_res['email'], ',') !== false) {
                                $vars = get_class_vars('MIME');
                                $arr[$i][] = MIME::_rfc822Encode($one_res['name'], $vars['rfc822_filter'] . '.') . ': ' . $one_res['email'] . ';';
                            } else {
                                $mbox_host = explode('@', $one_res['email']);
                                if (isset($mbox_host[1])) {
                                    $arr[$i][] = MIME::rfc822WriteAddress($mbox_host[0], $mbox_host[1], $one_res['name']);
                                }
                            }
                        } else {
                            $arr[$i][] = $one_res['name'];
                        }
                    }
                    $ambiguous = true;
                }
            }
        }

        if ($ambiguous) {
            foreach ($missing as $i => $addr) {
                $arr[$i] = $addr->getUserInfo();
            }
            return $arr;
        } elseif ($error) {
            return PEAR::raiseError(_("Please resolve ambiguous or invalid addresses."), null, null, null, $arr);
        } else {
            $list = '';
            foreach ($arr as $elm) {
                if (substr($list, -1) == ';') {
                    $list .= ' ';
                } elseif (!empty($list)) {
                    $list .= ', ';
                }
                $list .= $elm;
            }
            return $list;
        }
    }

    /**
     * Adds a contact to the user defined address book.
     *
     * @param string $newAddress  The contact's email address.
     * @param string $newName     The contact's name.
     *
     * @return string  A link or message to show in the notification area.
     */
    function addAddress($newAddress, $newName)
    {
        global $registry, $prefs;

        if (empty($newName)) {
            $newName = $newAddress;
        }

        $result = $registry->call('contacts/import',
                                  array(array('name' => $newName, 'email' => $newAddress),
                                        'array', $prefs->getValue('add_source')));
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        } else {
            $contact_link = $registry->link('contacts/show', array('uid' => $result, 'source' => $prefs->getValue('add_source')));
            if (!empty($contact_link) && !is_a($contact_link, 'PEAR_Error')) {
                $contact_link = Horde::link(Horde::url($contact_link), sprintf(_("Go to address book entry of \"%s\""), $newName)) . @htmlspecialchars($newName, ENT_COMPAT, NLS::getCharset()) . '</a>';
            } else {
                $contact_link = @htmlspecialchars($newName, ENT_COMPAT, NLS::getCharset());
            }
            return $contact_link;
        }
    }

    /**
     * Parses an address or address list into the address components,
     * automatically choosing the best suited parsing method.
     *
     * @param string $address  An address or address list.
     *
     * @return array  A list of address objects.
     */
    function parseAddressList($address)
    {
        static $parser;

        if (Util::extensionExists('imap') &&
            strpos($address, ':') === false) {
            $result = @imap_rfc822_parse_adrlist($address, '');
            if (is_array($result)) {
                return $result;
            }
        }

        if (!isset($parser)) {
            require_once 'Mail/RFC822.php';
            $parser = &new Mail_RFC822();
        }

        return $parser->parseAddressList($address, '', false, false);
    }

    /**
     * Wrapper around IMP_Folder::flist() which generates the body of a
     * &lt;select&gt; form input from the generated folder list. The
     * &lt;select&gt; and &lt;/select&gt; tags are NOT included in the output
     * of this function.
     *
     * @param string $heading         The label for an empty-value option at
     *                                the top of the list.
     * @param boolean $abbrev         If true, abbreviate long mailbox names
     *                                by replacing the middle of the name with
     *                                '...'.
     * @param array $filter           An array of mailboxes to ignore.
     * @param string $selected        The mailbox to have selected by default.
     * @param boolean $new_folder     If true, display an option to create a
     *                                new folder.
     * @param boolean $inc_tasklists  Should the user's editable tasklists be
     *                                included in the list?
     * @param boolean $inc_vfolder    Should the user's virtual folders be
     *                                included in the list?
     * @param boolean $inc_tasklists  Should the user's editable notepads be
     *                                included in the list?
     *
     * @return string  A string containing <option> elements for each mailbox
     *                 in the list.
     */
    function flistSelect($heading = '', $abbrev = true, $filter = array(),
                         $selected = null, $new_folder = false,
                         $inc_tasklists = false, $inc_vfolder = false,
                         $inc_notepads = false)
    {
        require_once 'Horde/Text.php';
        require_once IMP_BASE . '/lib/Folder.php';

        $imp_folder = &IMP_Folder::singleton();

        /* Don't filter here - since we are going to parse through every
         * member of the folder list below anyway, we can filter at that time.
         * This allows us the have a single cached value for the folder list
         * rather than a cached value for each different mailbox we may
         * visit. */
        $mailboxes = $imp_folder->flist_IMP();
        $text = '';

        if (strlen($heading) > 0) {
            $text .= '<option value="">' . $heading . "</option>\n";
        }

        if ($new_folder &&
            (!empty($GLOBALS['conf']['hooks']['permsdenied']) ||
             (IMP::hasPermission('create_folders') &&
              IMP::hasPermission('max_folders')))) {
            $text .= '<option value="">----</option>' . "\n";
            $text .= '<option value="*new*">' . _("New Folder") . "</option>\n";
            $text .= '<option value="">----</option>' . "\n";
        }

        /* Add the list of mailboxes to the lists. */
        $filter = array_flip($filter);
        foreach ($mailboxes as $mbox) {
            if (isset($filter[$mbox['val']])) {
                continue;
            }

            $val = isset($filter[$mbox['val']]) ? '' : htmlspecialchars($mbox['val']);
            $sel = ($mbox['val'] && ($mbox['val'] === $selected)) ? ' selected="selected"' : '';
            $label = ($abbrev) ? $mbox['abbrev'] : $mbox['label'];
            $text .= sprintf('<option value="%s"%s>%s</option>%s', $val, $sel, Text::htmlSpaces($label), "\n");
        }

        /* Add the list of virtual folders to the list. */
        if ($inc_vfolder) {
            $vfolders = $GLOBALS['imp_search']->listQueries(true);
            if (!empty($vfolders)) {
                $vfolder_sel = $GLOBALS['imp_search']->searchMboxID();
                $text .= '<option value="">----</option>' . "\n";
                foreach ($vfolders as $id => $val) {
                    $text .= sprintf('<option value="%s"%s>%s</option>%s', $GLOBALS['imp_search']->createSearchID($id), ($vfolder_sel == $id) ? ' selected="selected"' : '', Text::htmlSpaces($val), "\n");
                }
            }
        }

        /* Add the list of editable tasklists to the list. */
        if ($inc_tasklists && $_SESSION['imp']['tasklistavail']) {
            $tasklists = $GLOBALS['registry']->call('tasks/listTasklists',
                                                    array(false, PERMS_EDIT));

            if (!is_a($tasklists, 'PEAR_Error') && count($tasklists)) {
                $text .= '<option value="">----</option>' . "\n";

                foreach ($tasklists as $id => $tasklist) {
                    $text .= sprintf('<option value="%s">%s</option>%s',
                                     '_tasklist_' . $id,
                                     Text::htmlSpaces($tasklist->get('name')),
                                     "\n");
                }
            }
        }

        /* Add the list of editable notepads to the list. */
        if ($inc_notepads && $_SESSION['imp']['notepadavail']) {
            $notepads = $GLOBALS['registry']->call('notes/listNotepads',
                                                    array(false, PERMS_EDIT));

            if (!is_a($notepads, 'PEAR_Error') && count($notepads)) {
                $text .= '<option value="">----</option>' . "\n";

                foreach ($notepads as $id => $notepad) {
                    $text .= sprintf('<option value="%s">%s</option>%s',
                                     '_notepad_' . $id,
                                     Text::htmlSpaces($notepad->get('name')),
                                     "\n");
                }
            }
        }

        return $text;
    }

    /**
     * Checks for To:, Subject:, Cc:, and other compose window arguments and
     * pass back either a URI fragment or an associative array with any of
     * them which are present.
     *
     * @param string $format  Either 'uri' or 'array'.
     *
     * @return string  A URI fragment or an associative array with any compose
     *                 arguments present.
     */
    function getComposeArgs()
    {
        $args = array();
        $fields = array('to', 'cc', 'bcc', 'message', 'subject');

        foreach ($fields as $val) {
            if (($$val = Util::getFormData($val))) {
                $args[$val] = $$val;
            }
        }

        /* Decode mailto: URLs. */
        if (isset($args['to']) && (strpos($args['to'], 'mailto:') === 0)) {
            $mailto = @parse_url($args['to']);
            if (is_array($mailto)) {
                $args['to'] = $mailto['path'];
                if (!empty($mailto['query'])) {
                    parse_str($mailto['query'], $vals);
                    foreach ($fields as $val) {
                        if (isset($vals[$val])) {
                            $args[$val] = $vals[$val];
                        }
                    }
                }
            }
        }

        return $args;
    }

    /**
     * Open a compose window.
     */
    function openComposeWin($options = array())
    {
        global $prefs;

        if ($prefs->getValue('compose_popup')) {
            return true;
        } else {
            $options += IMP::getComposeArgs();
            $url = Util::addParameter(Horde::applicationUrl('compose.php', true),
                                      $options, null, false);
            header('Location: ' . $url);
            return false;
        }
    }

    /**
     * Returns the appropriate link to call the message composition screen.
     *
     * @param mixed $args   List of arguments to pass to compose.php. If this
     *                      is passed in as a string, it will be parsed as a
     *                      toaddress?subject=foo&cc=ccaddress (mailto-style)
     *                      string.
     * @param array $extra  Hash of extra, non-standard arguments to pass to
     *                      compose.php.
     *
     * @return string  The link to the message composition screen.
     */
    function composeLink($args = array(), $extra = array())
    {
        global $prefs, $browser;

        /* Make sure the compose window always knows which mailbox it's in,
           for replying, forwarding, marking as answered, etc. */
        /* $_SESSION['imp'] may not be available here. */
        if (!isset($extra['thismailbox']) &&
            isset($_SESSION['imp']['thismailbox'])) {
            $extra['thismailbox'] = $_SESSION['imp']['thismailbox'];
        }

        if (is_string($args)) {
            $string = $args;
            $args = array();
            if (($pos = strpos($string, '?')) !== false) {
                parse_str(substr($string, $pos + 1), $args);
                $args['to'] = substr($string, 0, $pos);
            } else {
                $args['to'] = $string;
            }
        }

        /* Merge the two argument arrays. */
        if (is_array($extra)) {
            $args = array_merge($args, $extra);
        }

        /* Convert the $args hash into proper URL parameters. */
        $url = substr(Util::addParameter('', array_diff($args, array('')), null, false), 1);

        if ($prefs->getValue('compose_popup') &&
            $browser->hasFeature('javascript')) {
            Horde::addScriptFile('popup.js');
            return "javascript:popup_imp('" . Horde::applicationUrl('compose.php') . "',800,650,'" . htmlspecialchars($browser->escapeJSCode(addslashes($url))) . "');";
        } else {
            return Horde::applicationUrl(empty($url) ? 'compose.php' : 'compose.php?' . $url);
        }
    }

    /**
     * Generates an URL to the logout screen that includes any known
     * information, such as username, server, etc., that can be filled in on
     * the login form.
     *
     * @return string  Logout URL with logout parameters added.
     */
    function logoutUrl()
    {
        $params = array(
            'imapuser' => isset($_SESSION['imp']['user']) ?
                          $_SESSION['imp']['user'] :
                          Util::getFormData('imapuser'),
            'server'   => isset($_SESSION['imp']['server']) ?
                          $_SESSION['imp']['server'] :
                          Util::getFormData('server'),
            'port'     => isset($_SESSION['imp']['port']) ?
                          $_SESSION['imp']['port'] :
                          Util::getFormData('port'),
            'protocol' => isset($_SESSION['imp']['protocol']) ?
                          $_SESSION['imp']['protocol'] :
                          Util::getFormData('protocol'),
            'language' => isset($_SESSION['imp']['language']) ?
                          $_SESSION['imp']['language'] :
                          Util::getFormData('language'),
            'smtphost' => isset($_SESSION['imp']['smtphost']) ?
                          $_SESSION['imp']['smtphost'] :
                          Util::getFormData('smtphost'),
            'smtpport' => isset($_SESSION['imp']['smtpport']) ?
                          $_SESSION['imp']['smtpport'] :
                          Util::getFormData('smtpport'),
        );

        $url = Util::addParameter('login.php', array_diff($params, array('')), null, false);
        return Horde::applicationUrl($url, true);
    }

    /**
     * If there is information available to tell us about a prefix in front of
     * mailbox names that shouldn't be displayed to the user, then use it to
     * strip that prefix out.
     *
     * @param string $folder  The folder name to display.
     *
     * @return string  The folder, with any prefix gone.
     */
    function displayFolder($folder)
    {
        static $cache = array();

        if (isset($cache[$folder])) {
            return $cache[$folder];
        }

        if ($folder == 'INBOX') {
            $cache[$folder] = _("Inbox");
        } else {
            $namespace_info = IMP::getNamespace($folder);
            if (!is_null($namespace_info) &&
                !empty($namespace_info['name']) &&
                ($namespace_info['type'] == 'personal') &&
                substr($folder, 0, strlen($namespace_info['name'])) == $namespace_info['name']) {
                $cache[$folder] = substr($folder, strlen($namespace_info['name']));
            } else {
                $cache[$folder] = $folder;
            }

            $cache[$folder] = String::convertCharset($cache[$folder], 'UTF7-IMAP');
        }

        return $cache[$folder];
    }

    /**
     * Filters a string, if requested.
     *
     * @param string $text  The text to filter.
     *
     * @return string  The filtered text (if requested).
     */
    function filterText($text)
    {
        global $conf, $prefs;

        if ($prefs->getValue('filtering')) {
            require_once 'Horde/Text/Filter.php';
            $text = Text_Filter::filter($text, 'words', array('words_file' => $conf['msgsettings']['filtering']['words'], 'replacement' => $conf['msgsettings']['filtering']['replacement']));
        }

        return $text;
    }

    /**
     * Returns the specified permission for the current user.
     *
     * @since IMP 4.1
     *
     * @param string $permission  A permission, either 'create_folders' or
     *                            'max_folders'.
     * @param boolean $value      If true, the method returns the value of a
     *                            scalar permission, otherwise whether the
     *                            permission limit has been hit already.
     *
     * @return mixed  The value of the specified permission.
     */
    function hasPermission($permission, $value = false)
    {
        global $perms;

        if (!$perms->exists('imp:' . $permission)) {
            return true;
        }

        $allowed = $perms->getPermissions('imp:' . $permission);
        if (is_array($allowed)) {
            switch ($permission) {
            case 'create_folders':
                $allowed = array_reduce($allowed, create_function('$a, $b', 'return $a | $b;'), false);
                break;
            case 'max_folders':
                $allowed = array_reduce($allowed, create_function('$a, $b', 'return max($a, $b);'), 0);
                break;
            }
        }
        if ($permission == 'max_folders' && !$value) {
            $folder = &IMP_Folder::singleton();
            $allowed = $allowed > count($folder->flist_IMP(array(), false));
        }

        return $allowed;
    }

    /**
     * Build IMP's list of menu items.
     */
    function getMenu($returnType = 'object')
    {
        global $conf, $prefs, $registry;

        require_once 'Horde/Menu.php';

        $menu_search_url = Horde::applicationUrl('search.php');
        $menu_mailbox_url = Horde::applicationUrl('mailbox.php');

        $spam_folder = IMP::folderPref($prefs->getValue('spam_folder'), true);

        $menu = new Menu(HORDE_MENU_MASK_ALL & ~HORDE_MENU_MASK_LOGIN);

        $menu->add(Util::addParameter($menu_mailbox_url, 'mailbox', 'INBOX'), _("_Inbox"), 'folders/inbox.png');
        if (($_SESSION['imp']['base_protocol'] != 'pop3') &&
            ($prefs->getValue('use_trash') || $prefs->getValue('use_vtrash')) &&
            $prefs->getValue('empty_trash_menu')) {
            $mailbox = null;
            if ($prefs->getValue('use_vtrash')) {
                $mailbox = $GLOBALS['imp_search']->createSearchID($prefs->getValue('vtrash_id'));
            } else {
                $trash_folder = IMP::folderPref($prefs->getValue('trash_folder'), true);
                if (!is_null($trash_folder)) {
                    $mailbox = $trash_folder;
                }
            }

            if (!empty($mailbox)) {
                $menu_trash_url = Util::addParameter($menu_mailbox_url,
                                                     array('mailbox' => $mailbox,
                                                           'actionID' => 'empty_mailbox'));
                $menu->add($menu_trash_url, _("Empty _Trash"), 'empty_trash.png', null, null, "return window.confirm('" . addslashes(_("Are you sure you wish to empty your trash folder?")) . "');", '__noselection');
            }
        }

        if (($_SESSION['imp']['base_protocol'] != 'pop3') &&
            !empty($spam_folder) &&
            $prefs->getValue('empty_spam_menu')) {
            $menu_spam_url = Util::addParameter($menu_mailbox_url, array('mailbox' => $spam_folder,
                                                                         'actionID' => 'empty_mailbox'));
            $menu->add($menu_spam_url, _("Empty _Spam"), 'empty_spam.png', null, null, "return window.confirm('" . addslashes(_("Are you sure you wish to empty your spam folder?")) . "');", '__noselection');
        }

        $menu->add(IMP::composeLink(), _("_New Message"), 'compose.png');

        if ($conf['user']['allow_folders']) {
            $menu->add(Util::nocacheUrl(Horde::applicationUrl('folders.php')), _("_Folders"), 'folders/folder.png');
        }
        $menu->add($menu_search_url, _("_Search"), 'search.png', $registry->getImageDir('horde'));
        if (($_SESSION['imp']['base_protocol'] != 'pop3') && $prefs->getValue('fetchmail_menu')) {
            if ($prefs->getValue('fetchmail_popup')) {
                $menu->add(Horde::applicationUrl('fetchmail.php'), _("F_etch Mail"), 'fetchmail.png', null, 'fetchmail', 'window.open(this.href, \'fetchmail\', \'toolbar=no,location=no,status=yes,scrollbars=yes,resizable=yes,width=300,height=450,left=100,top=100\'); return false;');
            } else {
                $menu->add(Horde::applicationUrl('fetchmail.php'), _("F_etch Mail"), 'fetchmail.png');
            }
        }
        if ($prefs->getValue('filter_menuitem')) {
            $menu->add(Horde::applicationUrl('filterprefs.php'), _("Fi_lters"), 'filters.png');
        }

        /* Logout. If IMP can auto login or IMP is providing
         * authentication, then we only show the logout link if the
         * sidebar isn't shown or if the configuration says to always
         * show the current user a logout link. */
        $impAuth = Auth::getProvider() == 'imp';
        $impAutoLogin = IMP::canAutoLogin();
        if (!($impAuth || $impAutoLogin) ||
            !$prefs->getValue('show_sidebar') ||
            Horde::showService('logout')) {

            /* If IMP provides authentication and the sidebar isn't
             * always on, target the main frame for logout to hide the
             * sidebar while logged out. */
            $logout_target = null;
            if ($impAuth || $impAutoLogin) {
                $logout_target = '_parent';
            }

            /* If IMP doesn't provide Horde authentication then we
             * need to use IMP's logout screen since logging out
             * should *not* end a Horde session. */
            if ($impAuth || $impAutoLogin) {
                $logout_url = Horde::getServiceLink('logout', 'horde', true);
            } else {
                $logout_url = Auth::addLogoutParameters(Horde::applicationUrl('login.php'), AUTH_REASON_LOGOUT);
            }

            $id = $menu->add($logout_url, _("_Log out"), 'logout.png', $registry->getImageDir('horde'), $logout_target);
            $menu->setPosition($id, HORDE_MENU_POS_LAST);
        }

        if ($returnType == 'object') {
            return $menu;
        } else {
            return $menu->render();
        }
    }

    /**
     * Outputs IMP's status/notification bar.
     */
    function status()
    {
        global $notification;

        if (isset($_SESSION['imp']['stream'])) {
            $alerts = imap_alerts();
            if (is_array($alerts)) {
                $alerts = str_replace('[ALERT] ', '', $alerts);
                foreach ($alerts as $alert) {
                    $notification->push($alert, 'horde.warning');
                }
            }
        }

        /* BC check. */
        if (class_exists('Notification_Listener_audio')) {
            $notification->notify(array('listeners' => array('status', 'audio')));
        }
    }

    /**
     * Returns the javascript for a new message notification popup.
     *
     * @param array $newmsgs  Associative array with mailbox names as the keys
     *                        and the message count as the values
     *
     * @return string  The javascript for the popup message
     */
    function getNewMessagePopup($newmsgs)
    {
        $_alert = '';
        $count = 0;
        foreach ($newmsgs as $mb => $nm) {
            $count++;
            $_mailbox_message = $mb;
            $_alert .= IMP::displayFolder($_mailbox_message) .
                ($nm > 1 ? _(" - $nm new messages") : _(" - $nm new message")) . '\n';
        }
        if (!empty($_alert)) {
            if ($count == 1) {
                $mailboxOpenUrl = Horde::applicationUrl('mailbox.php', true);
                $mailboxOpenUrl = Util::addParameter($mailboxOpenUrl, array('no_newmail_popup' => 1, 'mailbox' => $_mailbox_message));

                return "if (confirm('" . addslashes(_("You have new mail in the following folder:")) . '\n' .
                    $_alert . addslashes(_("Do you want to open that folder?")) .
                    "')) { window.location.href = '" . str_replace('&amp;', '&', $mailboxOpenUrl) .
                    "'; window.focus(); }";
            } else {
                return "alert('" . addslashes(_("You have new mail in the following folders:")) .
                    '\n' . $_alert ."');";
            }
        }
    }

    /**
     * Generates the URL to the prefs page.
     *
     * @param boolean $full  Generate full URL?
     *
     * @return string  The URL to the IMP prefs page.
     */
    function prefsURL($full = false)
    {
        return Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/prefs.php?app=imp', $full);
    }

    /**
     * Are we currently in "print" mode?
     *
     * @param boolean $mode  True if in print mode, false if not.
     *
     * @return boolean  Returns true if in "print" mode.
     */
    function printMode($mode = null)
    {
        static $print = false;
        if (!is_null($mode)) {
            $print = $mode;
        }
        return $print;
    }

    /**
     * Get message indices list.
     *
     * @param mixed $indices  The following inputs are allowed:
     * <pre>
     * 1. An array of messages indices in the following format:
     *    msg_id IMP_IDX_SEP msg_folder
     *    msg_id = Message index of the message
     *    IMP_IDX_SEP = IMP constant used to separate index/folder
     *    msg_folder = The full folder name containing the message index
     * 2. An array with the full folder name as keys and an array of message
     *    indices as the values.
     * 3. An IMP_Mailbox object, which will use the current index/folder
     *    as determined by the object. If an IMP_Mailbox object is used, it
     *    will be updated after the action is performed.
     * </pre>
     *
     * @return mixed  Returns an array with the folder as key and an array
     *                of message indices as the value (See #2 above).
     *                Else, returns false.
     */
    function parseIndicesList($indices)
    {
        $msgList = array();

        if (is_a($indices, 'IMP_Mailbox')) {
            $msgIdx = $indices->getIMAPIndex();
            if (empty($msgIdx)) {
                return false;
            }
            $msgList[$msgIdx['mailbox']][] = $msgIdx['index'];
            return $msgList;
        }

        if (!is_array($indices)) {
            return false;
        }
        if (!count($indices)) {
            return array();
        }

        reset($indices);
        if (!is_array(current($indices))) {
            /* Build the list of indices/mailboxes to delete if input
               is of format #1. */
            foreach ($indices as $msgIndex) {
                if (strpos($msgIndex, IMP_IDX_SEP) === false) {
                    return false;
                } else {
                    list($val, $key) = explode(IMP_IDX_SEP, $msgIndex);
                    $msgList[$key][] = $val;
                }
            }
        } else {
            /* We are dealing with format #2. */
            foreach ($indices as $key => $val) {
                if ($GLOBALS['imp_search']->isSearchMbox($key)) {
                    $msgList += IMP::parseIndicesList($val);
                } else {
                    /* Make sure we don't have any duplicate keys. */
                    $msgList[$key] = is_array($val) ? array_unique($val) : array($val);
                }
            }
        }

        return $msgList;
    }

    /**
     * Either sets or checks the value of the logintasks flag.
     *
     * @param integer $set  The value of the flag.
     *
     * @return integer  The value of the flag.
     *                  0 = No login tasks pending
     *                  1 = Login tasks pending
     *                  2 = Login tasks pending, previous tasks interrupted
     */
    function loginTasksFlag($set = null)
    {
        if (!is_null($set)) {
            $_SESSION['imp']['_logintasks'] = $set;
        }

        return isset($_SESSION['imp']['_logintasks']) ? $_SESSION['imp']['_logintasks'] : 0;
    }

    /**
     * Get namespace info for a full folder path.
     *
     * @since IMP 4.1
     *
     * @param string $mailbox  The folder path. If empty, will return info
     *                         on the default namespace (i.e. the first
     *                         personal namespace).
     * @param boolean $empty   If true and no matching namespace is found,
     *                         return the empty namespace, if it exists.
     *
     * @return mixed  The namespace info for the folder path or null if the
     *                path doesn't exist.
     */
    function getNamespace($mailbox = null, $empty = true)
    {
        static $cache = array();

        if ($_SESSION['imp']['base_protocol'] == 'pop3') {
            return null;
        }

        if ($mailbox === null) {
            reset($_SESSION['imp']['namespace']);
            $mailbox = key($_SESSION['imp']['namespace']);
        }

        $key = (int)$empty;
        if (isset($cache[$key][$mailbox])) {
            return $cache[$key][$mailbox];
        }

        foreach ($_SESSION['imp']['namespace'] as $key => $val) {
            if (!empty($key) && (strpos($mailbox, $key) === 0)) {
                $cache[$key][$mailbox] = $val;
                return $val;
            }
        }

        if ($empty && isset($_SESSION['imp']['namespace'][''])) {
            $cache[$key][$mailbox] = $_SESSION['imp']['namespace'][''];
        } else {
            $cache[$key][$mailbox] = null;
        }

        return $cache[$key][$mailbox];
    }

    /**
     * Get the default personal namespace.
     *
     * @since IMP 4.1
     *
     * @return mixed  The default personal namespace info.
     */
    function defaultNamespace()
    {
        static $default = null;

        if ($_SESSION['imp']['base_protocol'] == 'pop3') {
            return null;
        }

        if (!$default) {
            foreach ($_SESSION['imp']['namespace'] as $val) {
                if ($val['type'] == 'personal') {
                    $default = $val;
                    break;
                }
            }
        }

        return $default;
    }

    /**
     * Convert a preference value to/from the value stored in the preferences.
     *
     * Preferences that need to call this function before storing/retrieving:
     *   trash_folder, spam_folder, drafts_folder, sent_mail_folder
     * To allow folders from the personal namespace to be stored without this
     * prefix for portability, we strip the personal namespace. To tell apart
     * folders from the personal and any empty namespace, we prefix folders
     * from the empty namespace with the delimiter.
     *
     * @since IMP 4.1
     *
     * @param string $mailbox  The folder path.
     * @param boolean $append  True - convert from preference value.
     *                         False - convert to preference value.
     *
     * @return string  The folder name.
     */
    function folderPref($folder, $append)
    {
        $def_ns = IMP::defaultNamespace();
        $empty_ns = IMP::getNamespace('');
        if ($append) {
            /* Converting from preference value. */
            if (!is_null($empty_ns) &&
                strpos($folder, $empty_ns['delimiter']) === 0) {
                /* Prefixed with delimiter => from empty namespace. */
                $folder = substr($folder, strlen($empty_ns['delimiter']));
            } elseif (is_null($ns = IMP::getNamespace($folder, false))) {
                /* No namespace prefix => from personal namespace. */
                $folder = $def_ns['name'] . $folder;
            }
        } elseif (!$append && !is_null($ns = IMP::getNamespace($folder))) {
            /* Converting to preference value. */
            if ($ns['name'] == $def_ns['name']) {
                /* From personal namespace => strip namespace. */
                $folder = substr($folder, strlen($def_ns['name']));
            } elseif ($ns['name'] == $empty_ns['name']) {
                /* From empty namespace => prefix with delimiter. */
                $folder = $empty_ns['delimiter'] . $folder;
            }
        }
        return $folder;
    }

    /**
     * Make sure a user-entered mailbox contains namespace information.
     *
     * @since IMP 4.1
     *
     * @param string $mbox  The user-entered mailbox string.
     *
     * @return string  The mailbox string with any necessary namespace info
     *                 added.
     */
    function appendNamespace($mbox)
    {
        $ns_info = IMP::getNamespace($mbox, false);
        if (is_null($ns_info)) {
            $ns_info = IMP::defaultNamespace();
        }
        return $ns_info['name'] . $mbox;
    }

    /**
     * Generates a URL with any necessary information required for handling a
     * search mailbox added to the parameters.
     *
     * @since IMP 4.1
     *
     * @param string $page     Page name to link to.
     * @param string $mailbox  The mailbox to use on the linked page.
     * @param string $index    The index to use on the linked page.
     *
     * @return string  URL to $page with any necessary search information
     *                 added to the parameter list of the URL.
     */
    function generateSearchUrl($page, $mailbox, $index = null)
    {
        $link = Horde::applicationUrl($page);

        foreach (IMP::getSearchParameters($mailbox, $index) as $key => $val) {
            $link = Util::addParameter($link, $key, $val);
        }

        return $link;
    }

    /**
     * Returns a list of parameters necessary for handling a search mailbox.
     *
     * @since IMP 4.1
     *
     * @param string $mailbox  The mailbox to use on the linked page.
     * @param string $index    The index to use on the linked page.
     *
     * @return array  The list of parameters needed for handling a search
     *                mailbox (may be empty if not currently in a search
     *                mailbox).
     */
    function getSearchParameters($mailbox, $index = null)
    {
        $params = array();

        if ($GLOBALS['imp_search']->searchMboxID()) {
            $params['thismailbox'] = $mailbox;
            $params['mailbox'] = $GLOBALS['imp']['mailbox'];
        }
        if (!is_null($index)) {
            $params['index'] = $index;
        }

        return $params;
    }

}
