<?php

/**
 * Sort By Thread.
 */
@define('SORTTHREAD', 161);

/**
 * MIMP internal indexing strings.
 */
define('MIMP_MSG_SEP', "\0");

/**
 * MIMP Base Class.
 *
 * $Horde: mimp/lib/MIMP.php,v 1.69.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package MIMP
 */
class MIMP {

    /**
     * Take a Horde_Mobile_card and add global MIMP menu items.
     *
     * @param Horde_Mobile_linkset &$menu  The menu linkset, with page-specific
     *                                     options already filled in.
     */
    function addMIMPMenu(&$menu)
    {
        $items = array();
        if ($_SESSION['mimp']['mailbox'] != 'INBOX' ||
            (strpos($_SERVER['PHP_SELF'], 'mailbox.php') === false &&
             strpos($_SERVER['PHP_SELF'], 'message.php') === false)) {
            $items[Util::addParameter(Horde::applicationUrl('mailbox.php'), 'mailbox', 'INBOX')] = _("Inbox");
        }
        if (strpos($_SERVER['PHP_SELF'], 'compose.php') === false) {
            $items[Util::addParameter(Horde::applicationUrl('compose.php'), 'u', base_convert(microtime() . mt_rand(), 10, 36))] = _("Compose");
        }
        if (strpos($_SERVER['PHP_SELF'], 'folders.php') === false) {
            $items[Horde::applicationUrl('folders.php')] = _("Folders");
        }
        // @TODO
        // if ($options_link = Horde::getServiceLink('options', 'mimp')) {
        //     $items[Util::addParameter($options_link, 'mobile', 1, false)] = _("Options");
        // }
        $items[Auth::addLogoutParameters(Horde::applicationUrl('login.php'), AUTH_REASON_LOGOUT)] = _("Log out");

        foreach ($items as $link => $label) {
            $menu->add(new Horde_Mobile_link($label, $link));
        }
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
            return $key == $urlServer;
        }

        if (!empty($server['preferred'])) {
            if (is_array($server['preferred'])) {
                foreach ($server['preferred'] as $preferred) {
                    if ($preferred == $_SERVER['SERVER_NAME'] ||
                        $preferred == $_SERVER['HTTP_HOST']) {
                        return true;
                    }
                }
            } elseif ($server['preferred'] == $_SERVER['SERVER_NAME'] ||
                      $server['preferred'] == $_SERVER['HTTP_HOST']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a full c-client server specification string.
     *
     * @param string $mbox      The mailbox to append to end of the server
     *                          string.
     * @param string $protocol  Override the protocol currently being used.
     *
     * @return string  The full spec string.
     */
    function serverString($mbox = null, $protocol = null)
    {
        $srvstr = '{' . $_SESSION['mimp']['server'];

        /* If port is not specified, don't include it in Server String. */
        if (isset($_SESSION['mimp']['port'])) {
            $srvstr .= ':' . $_SESSION['mimp']['port'];
        }

        if (!isset($protocol)) {
            $protocol = $_SESSION['mimp']['protocol'];
        }

        /* If protocol is not specified, don't include it in Server String. */
        if (!empty($protocol)) {
            $srvstr .= '/' . $protocol;
        }

        return $srvstr . '}' . $mbox;
    }

    /**
     * Returns the plain text label that is displayed for the current mailbox,
     * removing namespace and folder prefix information from what is shown to
     * the user.
     *
     * @return string  The plain text label.
     */
    function getLabel()
    {
        $label = '';

        if ($_SESSION['mimp']['mailbox'] == 'INBOX') {
            $label = _("Inbox");
        } else {
            $label = MIMP::displayFolder($_SESSION['mimp']['mailbox']);
        }

        return $label;
    }

    /**
     * Returns the bare address.
     *
     * @param string $address    The address string.
     * @param boolean $multiple  Should we return multiple results?
     *
     * @return mixed  See MIME::bareAddress().
     */
    function bareAddress($address, $multiple = false)
    {
        require_once 'Horde/MIME.php';
        return MIME::bareAddress($address, $_SESSION['mimp']['maildomain'], $multiple);
    }

    /**
     * Use the Registry to expand names and returning error information for
     * any address that is either not valid or fails to expand.
     *
     * @param string $addrString  The name(s) or address(es) to expand.
     * @param boolean $full       If true generate a full, rfc822-valid
     *                            address list.
     *
     * @return mixed   Either a string containing all expanded addresses or
     *                 an array containing all matching address or an error
     *                 object.
     */
    function expandAddresses($addrString, $full = false)
    {
        if (!preg_match('|[^\s]|', $addrString)) {
            return '';
        }

        global $prefs, $registry;

        require_once 'Mail/RFC822.php';
        require_once 'Horde/MIME.php';

        $addrString = preg_replace('/,\s+/', ',', $addrString);
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
        $results = $registry->call('contacts/search', array($arr, $src, $search_fields));
        if (is_a($results, 'PEAR_Error')) {
            return $results;
        }

        $ambiguous = false;
        $error = false;
        $i = 0;
        $missing = array();

        foreach ($results as $res) {
            $tmp = $arr[$i];
            if ($parser->validateMailbox(MIME::encodeAddress($tmp, null, ''))) {
                // noop
            } elseif (count($res) == 1) {
                if ($full) {
                    if (strpos($res[0]['email'], ',') !== false) {
                        $arr[$i] = $res[0]['name'] . ': ' . $res[0]['email'] . ';';
                    } else {
                        list($mbox, $host) = explode('@', $res[0]['email']);
                        $arr[$i] = MIME::rfc822WriteAddress($mbox, $host, $res[0]['name']);
                    }
                } else {
                    $arr[$i] = $res[0]['name'];
                }
            } elseif (count($res) > 1) {
                /* Handle the multiple case - we return an array with all found
                   addresses. */
                $arr[$i] = array($arr[$i]);
                foreach ($res as $one_res) {
                    if ($full) {
                        if (strpos($one_res['email'], ',') !== false) {
                            $arr[$i][] = $one_res['name'] . ': ' . $one_res['email'] . ';';
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
            } else {
                /* Handle the missing/invalid case - we should return error info
                   on each address that couldn't be expanded/validated. */
                $error = true;
                if (!$ambiguous) {
                    $arr[$i] = PEAR::raiseError(null, null, null, null, $arr[$i]);
                    $missing[$i] = $arr[$i];
                }
            }
            $i++;
        }

        if ($ambiguous) {
            foreach ($missing as $i => $addr) {
                $arr[$i] = $addr->getUserInfo();
            }
            return $arr;
        } elseif ($error) {
            return PEAR::raiseError(_("Please resolve ambiguous or invalid addresses."), null, null, null, $res);
        } else {
            return implode(', ', $arr);
        }
    }

    /**
     * Returns the appropriate link to call the message composition
     * screen.
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
        $args = array_merge($args, $extra);

        /* Convert the $args hash into proper URL parameters. */
        $args_list = array();
        foreach ($args as $key => $val) {
            if (!empty($val) || is_int($val)) {
                $args_list[$key] = $val;
            }
        }

        return Util::addParameter(Horde::applicationUrl('compose.php'), $args_list);
    }

    /**
     * Generate an URL to the logout screen that includes any known
     * information, such as username, server, etc., that can be filled
     * in on the login form.
     *
     * @return string  Logout URL with logout parameters added.
     */
    function logoutUrl($uri = 'login.php', $reason = null)
    {
        $params = array(
            'imapuser' => isset($_SESSION['mimp']['user']) ? $_SESSION['mimp']['user'] :
                                                             Util::getFormData('imapuser'),
            'server'   => isset($_SESSION['mimp']['server']) ? $_SESSION['mimp']['server'] :
                                                               Util::getFormData('server'),
            'port'     => isset($_SESSION['mimp']['port']) ? $_SESSION['mimp']['port'] :
                                                             Util::getFormData('port'),
            'protocol' => isset($_SESSION['mimp']['protocol']) ? $_SESSION['mimp']['protocol'] :
                                                                 Util::getFormData('protocol'),
            'folders'  => isset($_SESSION['mimp']['folders']) ? $_SESSION['mimp']['folders'] :
                                                                Util::getFormData('folders'),
            'language' => isset($_SESSION['mimp']['language']) ? $_SESSION['mimp']['language'] :
                                                                 Util::getFormData('language')
        );

        foreach ($params as $key => $val) {
            if (!empty($val)) {
                $uri = Util::addParameter($uri, $key, $val);
            }
        }

        return Horde::applicationUrl($uri, true);
    }

    /**
     * If there is information available to tell us about a prefix in front of
     * mailbox names that shouldn't be displayed to the user, then use it to
     * strip that prefix out.
     *
     * @param string $mailbox  The folder name to display.
     *
     * @return string  The folder, with any prefix gone.
     */
    function displayFolder($mailbox)
    {
        static $cache = array();

        if (isset($cache[$mailbox])) {
            return $cache[$mailbox];
        }

        if ($mailbox == 'INBOX') {
            $cache[$mailbox] = _("INBOX");
        } else {
            $namespace_info = MIMP::getNamespace($mailbox);
            if (!is_null($namespace_info) &&
                !empty($namespace_info['name']) &&
                ($namespace_info['type'] == 'personal') &&
                substr($mailbox, 0, strlen($namespace_info['name'])) == $namespace_info['name']) {
                $cache[$mailbox] = substr($mailbox, strlen($namespace_info['name']));
            } else {
                $cache[$mailbox] = $mailbox;
            }

            $cache[$mailbox] = String::convertCharset($cache[$mailbox], 'UTF7-IMAP');
        }

        return $cache[$mailbox];
    }

    /**
     * Make sure the user has been authenticated to view the page.
     *
     * @param mixed $flags     Any flags to pass to imap_open(). See
     *                         Auth_mimp::authenticate(). However, if this is
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
            $auth_mimp = &Auth::singleton(array('mimp', 'mimp'));
            $auth_mimp->authenticateOptions(array('flags' => $flags));
            $reason = $auth_mimp->authenticate();
        }

        if ($reason !== true) {
            if ($return) {
                return false;
            }

            if (Util::getFormData('popup')) {
                Util::closeWindowJS();
            } else {
                $url = Auth::addLogoutParameters(MIMP::logoutUrl());
                $url = Util::addParameter($url, 'url', Horde::selfUrl(true), false);
                header('Location: ' . $url);
            }
            exit;
        }

        return true;
    }

    /**
     * Filter a string, if requested.
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
            $text = Text_Filter::filter($text, 'words', array('words_file' => $conf['msg']['filtering']['words'], 'replacement' => $conf['msg']['filtering']['replacement']));
        }

        return $text;
    }

    /**
     * Get the initial URL.
     *
     * @param string $actionID  The action ID to perform on the initial page.
     *
     * @return string  The initial URL.
     */
    function getInitialUrl($actionID = null)
    {
        $init_url = $GLOBALS['prefs']->getValue('initial_page');
        if ($init_url == 'folders.php') {
            $url = Horde::applicationUrl($init_url, true);
        } else {
            $url = Horde::applicationUrl('mailbox.php', true);
            $url = Util::addParameter($url, 'mailbox', $init_url, false);
        }

        if (!empty($actionID)) {
            $url = Util::addParameter($url, 'actionID', $actionID, false);
        }

        return $url;
    }

    /**
     * Wrapper around MIMP_Folder::flist() which generates the body of a
     * &lt;select&gt; form input from the generated folder list. The
     * &lt;select&gt; and &lt;/select&gt; tags are NOT included in the output
     * of this function.
     *
     * @param string $heading   An optional string to use as the label for
     *                          an empty-value option at the top of the list
     * @param boolean $abbrev   If true, abbreviate long mailbox names by
     *                          replacing the middle of the name with '...'.
     * @param array $filter     An array of mailboxes to ignore. If the
     *                          first element in the array is null,
     *                          then the mailbox will be shown in the
     *                          resulting list, but there will be an empty
     *                          value argument (i.e. non-selectable).
     * @param string $selected  The mailbox to have selected by default.
     *
     * @return string  A string containg <option> elements for each mailbox in
     *                 the list.
     */
    function flistSelect($heading = '', $abbrev = true, $filter = array(),
                         $selected = null)
    {
        require_once 'Horde/Text.php';
        require_once MIMP_BASE . '/lib/Folder.php';

        $mimp_folder = &MIMP_Folder::singleton();

        /* Don't filter here - since we are going to parse through every
         * member of the folder list below anyway, we can filter at that time.
         * This allows us the have a single cached value for the folder list
         * rather than a cached value for each different mailbox we may
         * visit. */
        $mailboxes = $mimp_folder->flist_MIMP();
        $text = '';

        if (strlen($heading) > 0) {
            $text .= '<option value="">' . $heading . "</option>\n";
        }

        /* Add the list of mailboxes to the lists. */
        $showmbox = false;
        if (!empty($filter) && is_null($filter[0])) {
            $showmbox = true;
            array_shift($filter);
        }

        $filter = array_flip($filter);
        foreach ($mailboxes as $mbox) {
            if (isset($filter[$mbox['val']]) && !$showmbox) {
                continue;
            }

            $val = isset($filter[$mbox['val']]) ? '' : htmlspecialchars($mbox['val']);
            $sel = ($mbox['val'] && ($mbox['val'] === $selected)) ? ' selected="selected"' : '';
            $label = ($abbrev) ? $mbox['abbrev'] : $mbox['label'];
            $text .= sprintf('<option value="%s"%s>%s</option>%s', $val, $sel, Text::htmlSpaces($label), "\n");
        }

        return $text;
    }

    /**
     * Get namespace info for a full folder path.
     *
     * @param string $mailbox  The folder path.
     * @param boolean $empty   If true and no matching namespace is found,
     *                         return the empty namespace, if it exists.
     *
     * @return mixed  The namespace info for the folder path or null if the
     *                path doesn't exist.
     */
    function getNamespace($mailbox, $empty = true)
    {
        static $cache = array();

        if ($_SESSION['mimp']['base_protocol'] == 'pop3') {
            return null;
        }

        $key = (int)$empty;
        if (isset($cache[$key][$mailbox])) {
            return $cache[$key][$mailbox];
        }

        foreach ($_SESSION['mimp']['namespace'] as $key => $val) {
            if (!empty($key) && (strpos($mailbox, $key) === 0)) {
                $cache[$key][$mailbox] = $val;
                return $val;
            }
        }

        if ($empty && isset($_SESSION['mimp']['namespace'][''])) {
            $cache[$key][$mailbox] = $_SESSION['mimp']['namespace'][''];
        } else {
            $cache[$key][$mailbox] = null;
        }

        return $cache[$key][$mailbox];
    }

    /**
     * Get the default personal namespace.
     *
     * @return mixed  The default personal namespace info.
     */
    function defaultNamespace()
    {
        static $default = null;

        if ($_SESSION['mimp']['base_protocol'] == 'pop3') {
            return null;
        }

        if (!$default) {
            foreach ($_SESSION['mimp']['namespace'] as $val) {
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
     *   trash_folder, sent_mail_folder
     * To allow folders from the personal namespace to be stored without this
     * prefix for portability, we strip the personal namespace. To tell apart
     * folders from the personal and any empty namespace, we prefix folders
     * from the empty namespace with the delimiter.
     *
     *
     * @param string $mailbox  The folder path.
     * @param boolean $append  True - convert from preference value.
     *                         False - convert to preference value.
     *
     * @return string  The folder name.
     */
    function folderPref($folder, $append)
    {
        $def_ns = MIMP::defaultNamespace();
        $empty_ns = MIMP::getNamespace('');
        if ($append) {
            /* Converting from preference value. */
            if (!is_null($empty_ns) &&
                strpos($folder, $empty_ns['delimiter']) === 0) {
                /* Prefixed with delimiter => from empty namespace. */
                $folder = substr($folder, strlen($empty_ns['delimiter']));
            } elseif (is_null($ns = MIMP::getNamespace($folder, false))) {
                /* No namespace prefix => from personal namespace. */
                $folder = $def_ns['name'] . $folder;
            }
        } elseif (!$append && !is_null($ns = MIMP::getNamespace($folder))) {
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
     * @param string $mbox  The user-entered mailbox string.
     *
     * @return string  The mailbox string with any necessary namespace info
     *                 added.
     */
    function appendNamespace($mbox)
    {
        $ns_info = MIMP::getNamespace($mbox, false);
        if (is_null($ns_info)) {
            $ns_info = MIMP::defaultNamespace();
        }
        return $ns_info['name'] . $mbox;
    }

}
