<?php

include_once 'Log.php';
include_once 'Horde/Util.php';

/**
 * The Horde:: class provides the functionality shared by all Horde
 * applications.
 *
 * $Horde: framework/Horde/Horde.php,v 1.489.2.66 2007/09/29 15:49:33 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @since   Horde 1.3
 * @package Horde_Framework
 */
class Horde {

    /**
     * Logs a message to the global Horde log backend.
     *
     * @param mixed $message     Either a string or a PEAR_Error object.
     * @param string $file       What file was the log function called from
     *                           (e.g. __FILE__)?
     * @param integer $line      What line was the log function called from
     *                           (e.g. __LINE__)?
     * @param integer $priority  The priority of the message. One of:
     * <pre>
     * PEAR_LOG_EMERG
     * PEAR_LOG_ALERT
     * PEAR_LOG_CRIT
     * PEAR_LOG_ERR
     * PEAR_LOG_WARNING
     * PEAR_LOG_NOTICE
     * PEAR_LOG_INFO
     * PEAR_LOG_DEBUG
     * </pre>
     */
    function logMessage($message, $file, $line, $priority = PEAR_LOG_INFO)
    {
        global $conf;

        if (!$conf['log']['enabled']) {
            return;
        }

        if ($priority > $conf['log']['priority']) {
            return;
        }

        $logger = &Horde::getLogger();
        if (!is_a($logger, 'Log')) {
            Horde::fatal(PEAR::raiseError('An error has occurred. Furthermore, Horde encountered an error attempting to log this error. Please check your Horde logging configuration in horde/config/conf.php.'), __FILE__, __LINE__, false);
        }

        if (is_a($message, 'PEAR_Error')) {
            $userinfo = $message->getUserInfo();
            $message = $message->getMessage();
            if (!empty($userinfo)) {
                if (is_array($userinfo)) {
                    $userinfo = @implode(', ', $userinfo);
                }
                $message .= ': ' . $userinfo;
            }
        } elseif (is_callable(array($message, 'getMessage'))) {
            $message = $message->getMessage();
        }

        $app = isset($GLOBALS['registry']) ? $GLOBALS['registry']->getApp() : 'horde';
        $message = '[' . $app . '] ' . $message . ' [on line ' . $line . ' of "' . $file . '"]';

        /* Make sure to log in the system's locale. */
        $locale = setlocale(LC_TIME, 0);
        setlocale(LC_TIME, 'C');

        $logger->log($message, $priority);

        /* Restore original locale. */
        setlocale(LC_TIME, $locale);

        return true;
    }

    /**
     */
    function &getLogger()
    {
        global $conf;

        if (empty($conf['log']['enabled'])) {
            return false;
        }

        static $logcheck;
        if (!isset($logcheck)) {
            // Try to make sure that we can log messages somehow.
            if (empty($conf['log']) ||
                empty($conf['log']['type']) ||
                empty($conf['log']['name']) ||
                empty($conf['log']['ident']) ||
                !isset($conf['log']['params'])) {
                Horde::fatal(PEAR::raiseError('Horde is not correctly configured to log error messages. You must configure at least a text file log in horde/config/conf.php.'), __FILE__, __LINE__, false);
            }
            $logcheck = true;
        }

        return $logger = &Log::singleton($conf['log']['type'], $conf['log']['name'],
                                         $conf['log']['ident'], $conf['log']['params']);
    }

    /**
     * Destroys any existing session on login and make sure to use a new
     * session ID, to avoid session fixation issues. Should be called before
     * checking a login.
     */
    function getCleanSession()
    {
        // Make sure to force a completely new session ID and clear
        // all session data.
        if (version_compare(phpversion(), '4.3.3') !== -1) {
            session_regenerate_id(true);
            session_unset();
        } else {
            @session_destroy();
            if (Util::extensionExists('posix')) {
                $new_session_id = md5(microtime() . posix_getpid());
            } else {
                $new_session_id = md5(uniqid(mt_rand(), true));
            }
            session_id($new_session_id);

            // Restart the session, including setting up the session
            // handler.
            Horde::setupSessionHandler();
            @session_start();
        }

        /* Reset cookie timeouts, if necessary. */
        if (!empty($GLOBALS['conf']['session']['timeout'])) {
            $app = $GLOBALS['registry']->getApp();
            if (Secret::clearKey($app)) {
                Secret::setKey($app);
            }
            Secret::setKey('auth');
        }
    }

    /**
     * Aborts with a fatal error, displaying debug information to the user.
     *
     * @param mixed $error   A PEAR_Error object with debug information or an
     *                       error message.
     * @param integer $file  The file in which the error occured.
     * @param integer $line  The line on which the error occured.
     * @param boolean $log   Log this message via Horde::logMessage()?
     */
    function fatal($error, $file, $line, $log = true)
    {
        @include_once 'Horde/Auth.php';
        @include_once 'Horde/CLI.php';

        $admin = class_exists('Auth') && Auth::isAdmin();
        $cli = class_exists('Horde_CLI') && Horde_CLI::runningFromCLI();

        $errortext = '<h1>' . _("A fatal error has occurred") . '</h1>';
        if (is_a($error, 'PEAR_Error')) {
            $info = array_merge(array('file' => 'conf.php', 'variable' => '$conf'),
                                array($error->getUserInfo()));

            switch ($error->getCode()) {
            case HORDE_ERROR_DRIVER_CONFIG_MISSING:
                $message = sprintf(_("No configuration information specified for %s."), $info['name']) . '<br />' .
                    sprintf(_("The file %s should contain some %s settings."),
                            $GLOBALS['registry']->get('fileroot') . '/config/' . $info['file'],
                            sprintf("%s['%s']['params']", $info['variable'], $info['driver']));
                break;

            case HORDE_ERROR_DRIVER_CONFIG:
                $message = sprintf(_("Required \"%s\" not specified in %s configuration."), $info['field'], $info['name']) . '<br />' .
                    sprintf(_("The file %s should contain a %s setting."),
                            $GLOBALS['registry']->get('fileroot') . '/config/' . $info['file'],
                            sprintf("%s['%s']['params']['%s']", $info['variable'], $info['driver'], $info['field']));
                break;

            default:
                $message = $error->getMessage();
                break;
            }

            $errortext .= '<h3>' . htmlspecialchars($message) . '</h3>';
        } elseif (is_object($error) && method_exists($error, 'getMessage')) {
            $errortext .= '<h3>' . htmlspecialchars($error->getMessage()) . '</h3>';
        } elseif (is_string($error)) {
            $errortext .= '<h3>' . htmlspecialchars($error) . '</h3>';
        }

        if ($admin) {
            $errortext .= '<p><code>' . sprintf(_("[line %s of %s]"), $line, $file) . '</code></p>';
            if (is_object($error)) {
                $errortext .= '<h3>' . _("Details (also in Horde's logfile):") . '</h3>';
                $errortext .= '<p><pre>' . htmlspecialchars(Util::bufferOutput('var_dump', $error)) . '</pre></p>';
            }
        } elseif ($log) {
            $errortext .= '<h3>' . _("Details have been logged for the administrator.") . '</h3>';
        }

        // Log the error via Horde::logMessage() if requested.
        if ($log) {
            Horde::logMessage($error, $file, $line, PEAR_LOG_EMERG);
        }

        if ($cli) {
            echo strip_tags(str_replace(array('<br />', '<p>', '</p>', '<h1>', '</h1>', '<h3>', '</h3>'), "\n", $errortext));
        } else {
            echo <<< HTML
<html>
<head><title>Horde :: Fatal Error</title></head>
<body style="background:#fff; color:#000">$errortext</body>
</html>
HTML;
        }
        exit;
    }

    /**
     * Adds the javascript code to the output (if output has already started)
     * or to the list of script files to include via includeScriptFiles().
     *
     * @param string $file     The full javascript file name.
     * @param string $app      The application name. Defaults to the current
     *                         application.
     * @param boolean $direct  Include the file directly without passing it
     *                         through javascript.php?
     */
    function addScriptFile($file, $app = null, $direct = false)
    {
        global $registry;
        static $included = array();

        if (empty($app)) {
            $app = $registry->getApp();
        }

        // Skip any js files that have since been deprecated.
        $ignored_files = array('horde' => array('tooltip.js' => 1));
        if (!empty($ignored_files[$app][$file])) {
            return;
        }

        // Don't include scripts multiple times.
        if (!empty($included[$app][$file])) {
            return;
        }
        $included[$app][$file] = true;

        // Explicitly check for a non-PHP version of the script.
        if (!$direct && file_exists($file{0} == '/' ? $registry->get('fileroot', $app) . $file : $registry->get('jsfs', $app) . '/' . $file)) {
            $direct = true;
        }

        // If headers have already been sent, we need to output a
        // <script> tag directly.
        if (ob_get_length() || headers_sent()) {
            if ($direct) {
                $url = Horde::url($file{0} == '/' ? $registry->get('webroot', $app) . $file : $registry->get('jsuri', $app) . '/' . $file);
            } else {
                $url = Horde::url($registry->get('webroot', 'horde') . '/services/javascript.php');
                $url = Util::addParameter($url, array('file' => $file,
                                                      'app'  => $app));
            }
            echo '<script type="text/javascript" src="' . $url . '"></script>' . "\n";
        } else {
            global $_horde_script_files;
            $_horde_script_files[$app][] = array($file, $direct);
        }
    }

    /**
     * Includes javascript files that were needed before any headers were sent.
     */
    function includeScriptFiles()
    {
        global $_horde_script_files, $registry;

        /* If there is no javascript available, there's no point in including
         * the rest of the files. */
        if (!$GLOBALS['browser']->hasFeature('javascript')) {
            return;
        }

        /* Add general UI js library. */
        Horde::addScriptFile('horde.js', 'horde', true);
        if ($GLOBALS['browser']->hasQuirk('windowed_controls')) {
            /* Fixes for IE that can't easily be done without browser
             * detection. */
            Horde::addScriptFile('horde.ie.js', 'horde', true);
        }

        if (!empty($_horde_script_files)) {
            $base_url = Horde::url($registry->get('webroot', 'horde') . '/services/javascript.php');
            foreach ($_horde_script_files as $app => $files) {
                foreach ($files as $file) {
                    if (!empty($file[1])) {
                        $url = $file[0]{0} == '/' ? $registry->get('webroot', $app) . $file[0] : $registry->get('jsuri', $app) . '/' . $file[0];
                        echo '<script type="text/javascript" src="' . Horde::url($url) . "\"></script>\n";
                    } else {
                        $url = Util::addParameter($base_url, array('file' => $file[0],
                                                                   'app'  => $app));
                        echo '<script type="text/javascript" src="' . $url . "\"></script>\n";
                    }
                }
            }
        }
    }

    /**
     * Includes javascript files that were needed before any headers were sent.
     */
    function inlineScriptFiles()
    {
        global $_horde_script_files, $registry;

        if (!empty($_horde_script_files)) {
            $jsWrapper = $registry->get('fileroot', 'horde') . '/services/javascript.php';
            foreach ($_horde_script_files as $app => $files) {
                foreach ($files as $file) {
                    if (!empty($file[1])) {
                        @readfile($file[0]{0} == '/' ? $registry->get('fileroot', $app) . $file[0] : $registry->get('jsfs', $app) . '/' . $file[0]);
                    } else {
                        $file = $file[0];
                        require $jsWrapper;
                    }
                }
            }
        }
    }

    /**
     * Checks if link should be shown and return the nescessary code.
     *
     * @param string  $type      Type of link to display
     * @param string  $app       The name of the current Horde application.
     * @param boolean $override  Override Horde settings?
     * @param boolean $referrer  Include the current page as the referrer (url=)?
     *
     * @return string  The HTML to create the link.
     */
    function getServiceLink($type, $app, $override = false, $referrer = true)
    {
        if (!Horde::showService($type) && !$override) {
            return false;
        }

        switch ($type) {
        case 'help':
            if ($GLOBALS['browser']->hasFeature('javascript')) {
                Horde::addScriptFile('popup.js', 'horde', true);
            }
            $url = Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/help/', true);
            return Util::addParameter($url, 'module', $app);

        case 'problem':
            return Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/problem.php?return_url=' . urlencode(Horde::selfUrl(true, true, true)));

        case 'logout':
            return Horde::url(Auth::addLogoutParameters($GLOBALS['registry']->get('webroot', 'horde') . '/login.php', AUTH_REASON_LOGOUT));

        case 'login':
            return Auth::getLoginScreen('', $referrer ? Horde::selfUrl(true) : null);

        case 'options':
            global $conf;
            if (($conf['prefs']['driver'] != '') && ($conf['prefs']['driver'] != 'none')) {
                return Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/prefs.php?app=' . $app);
            }
            break;
        }

        return false;
    }

    /**
     * @param string $type  The type of link.
     *
     * @return boolean  True if the link is to be shown.
     */
    function showService($type)
    {
        global $conf;

        if (empty($conf['menu']['links'][$type])) {
            return false;
        }

        switch ($conf['menu']['links'][$type]) {
        case 'all':
            return true;

        case 'never':
            return false;

        case 'authenticated':
            return (bool)Auth::getAuth();

        default:
            return false;
        }
    }

    /**
     * Returns the driver parameters for the specified backend.
     *
     * @param mixed $backend  The backend system (e.g. 'prefs', 'categories',
     *                        'contacts') being used.
     *                        The used configuration array will be
     *                        $conf[$backend]. If an array gets passed, it will
     *                        be $conf[$key1][$key2].
     * @param string $type    The type of driver.
     *
     * @return array  The connection parameters.
     */
    function getDriverConfig($backend, $type = 'sql')
    {
        global $conf;

        $c = null;
        if (is_array($backend)) {
            require_once 'Horde/Array.php';
            $c = Horde_Array::getElement($conf, $backend);
        } elseif (isset($conf[$backend])) {
            $c = $conf[$backend];
        }
        if (!is_null($c) && isset($c['params'])) {
            if (isset($conf[$type])) {
                return array_merge($conf[$type], $c['params']);
            } else {
                return $c['params'];
            }
        }

        return isset($conf[$type]) ? $conf[$type] : array();
    }


    /**
     * Returns the VFS driver parameters for the specified backend.
     *
     * @param string $name  The VFS system name (e.g. 'images', 'documents')
     *                      being used.
     *
     * @return array  A hash with the VFS parameters; the VFS driver in 'type'
     *                and the connection parameters in 'params'.
     */
    function getVFSConfig($name)
    {
        global $conf;

        if (!isset($conf[$name]['type'])) {
            return PEAR::raiseError(_("You must configure a VFS backend."));
        }

        if ($conf[$name]['type'] == 'horde') {
            $vfs = $conf['vfs'];
        } else {
            $vfs = $conf[$name];
        }

        if ($vfs['type'] == 'sql') {
            $vfs['params'] = Horde::getDriverConfig($name, 'sql');
        }

        return $vfs;
    }

    /**
     * Checks if all necessary parameters for a driver configuration
     * are set and throws a fatal error with a detailed explaination
     * how to fix this, if something is missing.
     *
     * @param array $params     The configuration array with all parameters.
     * @param string $driver    The key name (in the configuration array) of
     *                          the driver.
     * @param array $fields     An array with mandatory parameter names for
     *                          this driver.
     * @param string $name      The clear text name of the driver. If not
     *                          specified, the application name will be used.
     * @param string $file      The configuration file that should contain
     *                          these settings.
     * @param string $variable  The name of the configuration variable.
     */
    function assertDriverConfig($params, $driver, $fields, $name = null,
                                $file = 'conf.php', $variable = '$conf')
    {
        global $registry;

        // Don't generate a fatal error if we fail during or before
        // Registry instantiation.
        if (is_null($name)) {
            $name = isset($registry) ? $registry->getApp() : '[unknown]';
        }
        $fileroot = isset($registry) ? $registry->get('fileroot') : '';

        if (!is_array($params) || !count($params)) {
            Horde::fatal(PEAR::raiseError(
                sprintf(_("No configuration information specified for %s."), $name) . "\n\n" .
                sprintf(_("The file %s should contain some %s settings."),
                    $fileroot . '/config/' . $file,
                    sprintf("%s['%s']['params']", $variable, $driver))),
                __FILE__, __LINE__);
        }

        foreach ($fields as $field) {
            if (!isset($params[$field])) {
                Horde::fatal(PEAR::raiseError(
                    sprintf(_("Required \"%s\" not specified in %s configuration."), $field, $name) . "\n\n" .
                    sprintf(_("The file %s should contain a %s setting."),
                        $fileroot . '/config/' . $file,
                        sprintf("%s['%s']['params']['%s']", $variable, $driver, $field))),
                    __FILE__, __LINE__);
            }
        }
    }

    /**
     * Returns a session-id-ified version of $uri.
     * If a full URL is requested, all parameter separators get converted to
     * "&", otherwise to "&amp;".
     *
     * @param string $uri              The URI to be modified.
     * @param boolean $full            Generate a full (http://server/path/)
     *                                 URL.
     * @param integer $append_session  0 = only if needed, 1 = always, -1 =
     *                                 never.
     *
     * @return string  The URL with the session id appended (if needed).
     */
    function url($uri, $full = false, $append_session = 0, $force_ssl = false)
    {
        if ($force_ssl) {
            $full = true;
        }

        if ($full) {
            global $conf, $registry, $browser;

            /* Store connection parameters in local variables. */
            $server_name = $conf['server']['name'];
            $server_port = $conf['server']['port'];

            $protocol = 'http';
            if ($conf['use_ssl'] == 1) {
                $protocol = 'https';
            } elseif ($conf['use_ssl'] == 2 &&
                      $browser->usingSSLConnection()) {
                $protocol = 'https';
            } elseif ($conf['use_ssl'] == 3) {
                $server_port = '';
                if ($force_ssl) {
                    $protocol = 'https';
                }
            }

            /* If using non-standard ports, add the port to the URL. */
            if (!empty($server_port) &&
                ((($protocol == 'http') && ($server_port != 80)) ||
                 (($protocol == 'https') && ($server_port != 443)))) {
                $server_name .= ':' . $server_port;
            }

            /* Store the webroot in a local variable. */
            $webroot = $registry->get('webroot');

            $url = $protocol . '://' . $server_name;
            if (substr($uri, 0, 1) != '/') {
                if (substr($webroot, -1) == '/') {
                    $url .= $webroot . $uri;
                } else {
                    $url .= $webroot . '/' . $uri;
                }
            } else {
                $url .= $uri;
            }
        } else {
            $url = $uri;
        }

        if (empty($GLOBALS['conf']['session']['use_only_cookies']) &&
            (($append_session == 1) ||
             (($append_session == 0) &&
              !isset($_COOKIE[session_name()])))) {
            $url = Util::addParameter($url, session_name(), session_id());
        }

        if ($full) {
            /* We need to run the replace twice, because we only catch every
             * second match. */
            return preg_replace(array('/(=?.*?)&amp;(.*?=)/',
                                      '/(=?.*?)&amp;(.*?=)/'),
                                '$1&$2', $url);
        } elseif (preg_match('/=.*&amp;.*=/', $url)) {
            return $url;
        } else {
            return htmlentities($url);
        }
    }

    /**
     * Returns a session-id-ified version of $uri, using the current
     * application's webroot setting.
     *
     * @param string $uri              The URI to be modified.
     * @param boolean $full            Generate a full (http://server/path/)
     *                                 URL.
     * @param integer $append_session  0 = only if needed, 1 = always, -1 =
     *                                 never.
     *
     * @return string  The url with the session id appended.
     */
    function applicationUrl($uri, $full = false, $append_session = 0)
    {
        if ($full) {
            return Horde::url($uri, $full, $append_session);
        }

        if (substr($uri, 0, 1) != '/') {
            $webroot = $GLOBALS['registry']->get('webroot');
            if (substr($webroot, -1) != '/') {
                $webroot .= '/';
            }
            $uri = $webroot . $uri;
        }

        return Horde::url($uri, $full, $append_session);
    }

    /**
     * Returns an external link passed through the dereferrer to strip session
     * IDs from the referrer.
     *
     * @param string $url   The external URL to link to.
     * @param boolean $tag  If true, a complete <a> tag is returned, only the
     *                      url otherwise.
     *
     * @return string  The link to the dereferrer script.
     */
    function externalUrl($url, $tag = false)
    {
        if (isset($_COOKIE[session_name()]) ||
            String::substr($url, 0, 1) == '#' ||
            String::substr($url, 0, 7) == 'mailto:') {
            $ext = $url;
        } else {
            $ext = Horde::url($GLOBALS['registry']->get('webroot', 'horde') .
                              '/services/go.php', true, -1);

            /* We must make sure there are no &amp's in the URL. */
            $url = preg_replace(array('/(=?.*?)&amp;(.*?=)/', '/(=?.*?)&amp;(.*?=)/'), '$1&$2', $url);
            $ext = Util::addParameter($ext, 'url', $url);
        }

        if ($tag) {
            $ext = Horde::link($ext, $url, '', '_blank');
        }

        return $ext;
    }

    /**
     * Returns a URL to be used for downloading, that takes into account any
     * special browser quirks (i.e. IE's broken filename handling).
     *
     * @param string $filename  The filename of the download data.
     * @param array $params     Any additional parameters needed.
     * @param string $url       The URL to alter. If none passed in, will use
     *                          the file 'view.php' located in the current
     *                          module's base directory.
     *
     * @return string  The download URL.
     */
    function downloadUrl($filename, $params = array(), $url = null)
    {
        global $browser;

        $horde_url = false;

        if (is_null($url)) {
            global $registry;
            $url = Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/services/download/'), 'module', $registry->getApp());
            $horde_url = true;
        }

        /* Add parameters. */
        if (!is_null($params)) {
            $url = Util::addParameter($url, $params);
        }

        /* If we are using the default Horde download link, add the
         * filename to the end of the URL. Although not necessary for
         * many browsers, this should allow every browser to download
         * correctly. */
        if ($horde_url) {
            $url = Util::addParameter($url, 'fn', '/' . rawurlencode($filename));
        } elseif ($browser->hasQuirk('break_disposition_filename')) {
            /* Some browsers will only obtain the filename correctly
             * if the extension is the last argument in the query
             * string and rest of the filename appears in the
             * PATH_INFO element. */
            $filename = rawurlencode($filename);

            /* Get the webserver ID. */
            $server = Horde::webServerID();

            /* Get the name and extension of the file.  Apache 2 does
             * NOT support PATH_INFO information being passed to the
             * PHP module by default, so disable that
             * functionality. */
            if (($server != 'apache2')) {
                if (($pos = strrpos($filename, '.'))) {
                    $name = '/' . preg_replace('/\./', '%2E', substr($filename, 0, $pos));
                    $ext = substr($filename, $pos);
                } else {
                    $name = '/' . $filename;
                    $ext = '';
                }

                /* Enter the PATH_INFO information. */
                if (($pos = strpos($url, '?'))) {
                    $url = substr($url, 0, $pos) . $name . substr($url, $pos);
                } else {
                    $url .= $name;
                }
            }

            /* Append the extension, if it exists. */
            if (($server == 'apache2') || !empty($ext)) {
                $url = Util::addParameter($url, 'fn_ext', '/' . $filename);
            }
        }

        return $url;
    }

    /**
     * Returns an anchor tag with the relevant parameters
     *
     * @param string $url        The full URL to be linked to.
     * @param string $title      The link title/description.
     * @param string $class      The CSS class of the link.
     * @param string $target     The window target to point to.
     * @param string $onclick    JavaScript action for the 'onclick' event.
     * @param string $title2     The link title (tooltip) (deprecated - just
     *                           use $title).
     * @param string $accesskey  The access key to use.
     * @param array $attributes  Any other name/value pairs to add to the <a>
     *                           tag.
     * @param boolean $escape    Whether to escape special characters in the
     *                           title attribute.
     *
     * @return string  The full <a href> tag.
     */
    function link($url, $title = '', $class = '', $target = '', $onclick = '',
                  $title2 = '', $accesskey = '', $attributes = array(),
                  $escape = true)
    {
        static $charset;
        if (!isset($charset)) {
            $charset = NLS::getCharset();
        }

        if (!empty($title2)) {
            $title = $title2;
        }

        $ret = "<a href=\"$url\"";
        if (!empty($onclick)) {
            $ret .= " onclick=\"$onclick\"";
        }
        if (!empty($class)) {
            $ret .= " class=\"$class\"";
        }
        if (!empty($target)) {
            $ret .= " target=\"$target\"";
        }
        if (!empty($title)) {
            if ($escape) {
                $title = nl2br(@htmlspecialchars(@htmlspecialchars($title, ENT_QUOTES, $charset), ENT_QUOTES, $charset));
            }
            $ret .= ' title="' . $title . '"';
        }
        if (!empty($accesskey)) {
            $ret .= ' accesskey="' . htmlspecialchars($accesskey) . '"';
        }

        foreach ($attributes as $name => $value) {
            $ret .= ' ' . htmlspecialchars($name) . '="' . htmlspecialchars($value) . '"';
        }

        return "$ret>";
    }

    /**
     * Uses DOM Tooltips to display the 'title' attribute for
     * Horde::link() calls.
     *
     * @param string $url        The full URL to be linked to
     * @param string $status     The JavaScript mouse-over string
     * @param string $class      The CSS class of the link
     * @param string $target     The window target to point to.
     * @param string $onclick    JavaScript action for the 'onclick' event.
     * @param string $title      The link title (tooltip).
     * @param string $accesskey  The access key to use.
     * @param array  $attributes Any other name/value pairs to add to the <a>
     *                           tag.
     *
     * @return string  The full <a href> tag.
     */
    function linkTooltip($url, $status = '', $class = '', $target = '',
                         $onclick = '', $title = '', $accesskey = '',
                         $attributes = array())
    {
        static $charset;
        if (!isset($charset)) {
            $charset = NLS::getCharset();
        }

        if (!empty($title)) {
            $title = '&lt;pre&gt;' . preg_replace(array('/\n/', '/((?<!<br)\s{1,}(?<!\/>))/em', '/<br \/><br \/>/', '/<br \/>/'), array('', 'str_repeat("&nbsp;", strlen("$1"))', '&lt;br /&gt; &lt;br /&gt;', '&lt;br /&gt;'), nl2br(@htmlspecialchars(@htmlspecialchars($title, ENT_QUOTES, $charset), ENT_QUOTES, $charset))) . '&lt;/pre&gt;';
        }
        return Horde::link($url, $title, $class, $target, $onclick, null, $accesskey, $attributes, false);
    }

    /**
     * Returns an anchor sequence with the relevant parameters for a widget
     * with accesskey and text.
     *
     * @access public
     *
     * @param string  $url      The full URL to be linked to.
     * @param string  $title    The link title/description.
     * @param string  $class    The CSS class of the link
     * @param string  $target   The window target to point to.
     * @param string  $onclick  JavaScript action for the 'onclick' event.
     * @param string  $title2   The link title (tooltip) (deprecated - just use
     *                          $title).
     * @param boolean $nocheck  Don't check if the access key already has been
     *                          used. Defaults to false (= check).
     *
     * @return string  The full <a href>Title</a> sequence.
     */
    function widget($url, $title = '', $class = 'widget', $target = '',
                    $onclick = '', $title2 = '', $nocheck = false)
    {
        if (!empty($title2)) {
            $title = $title2;
        }

        $ak = Horde::getAccessKey($title, $nocheck);

        return Horde::link($url, '', $class, $target, $onclick, '', $ak) . Horde::highlightAccessKey($title, $ak) . '</a>';
    }

    /**
     * Returns a session-id-ified version of $SCRIPT_NAME resp. $PHP_SELF.
     *
     * @param boolean $script_params Include script parameters like
     *                               QUERY_STRING and PATH_INFO?
     * @param boolean $nocache       Include a nocache parameter in the URL?
     * @param boolean $full          Return a full URL?
     *
     * @return string  The requested URI.
     */
    function selfUrl($script_params = false, $nocache = true, $full = false,
                     $force_ssl = false)
    {
        if (substr(php_sapi_name(), 0, 3) == 'cgi') {
            // When using CGI PHP, SCRIPT_NAME may contain the path to
            // the PHP binary instead of the script being run; use
            // PHP_SELF instead.
            $url = $_SERVER['PHP_SELF'];
        } else {
            $url = isset($_SERVER['SCRIPT_NAME']) ?
                $_SERVER['SCRIPT_NAME'] :
                $_SERVER['PHP_SELF'];
        }

        if ($script_params) {
            if (!empty($_SERVER['PATH_INFO'])) {
                $url .= $_SERVER['PATH_INFO'];
            }
            if (!empty($_SERVER['QUERY_STRING'])) {
                $url .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        $url = Horde::url($url, $full, 0, $force_ssl);

        if ($nocache) {
            return Util::nocacheUrl($url, !$full);
        } else {
            return $url;
        }
    }

    /**
     * Constructs a correctly-pathed link to an image.
     *
     * @param string $src   The image file.
     * @param string $alt   Text describing the image.
     * @param mixed  $attr  Any additional attributes for the image tag. Can be
     *                      a pre-built string or an array of key/value pairs
     *                      that will be assembled and html-encoded.
     * @param string $dir   The root graphics directory.
     *
     * @return string  The full image tag.
     */
    function img($src, $alt = '', $attr = '', $dir = null)
    {
        static $charset;
        if (!isset($charset)) {
            $charset = NLS::getCharset();
        }

        /* If browser does not support images, simply return the ALT text. */
        if (!$GLOBALS['browser']->hasFeature('images')) {
            return @htmlspecialchars($alt, ENT_COMPAT, $charset);
        }

        /* If no directory has been specified, get it from the registry. */
        if ($dir === null) {
            global $registry;
            $dir = $registry->getImageDir();
        }

        /* If a directory has been provided, prepend it to the image source. */
        if (!empty($dir)) {
            $src = $dir . '/' . $src;
        }

        /* Build all of the tag attributes. */
        $attributes = array('src' => $src,
                            'alt' => $alt);
        if (is_array($attr)) {
            $attributes = array_merge($attributes, $attr);
        }
        if (empty($attributes['title'])) {
            $attributes['title'] = '';
        }

        $img = '<img';
        foreach ($attributes as $attribute => $value) {
            $img .= ' ' . $attribute . '="' . ($attribute == 'src' ? $value : @htmlspecialchars($value, ENT_COMPAT, $charset)) . '"';
        }

        /* If the user supplied a pre-built string of attributes, add that. */
        if (is_string($attr) && !empty($attr)) {
            $img .= ' ' . $attr;
        }

        /* Return the closed image tag. */
        return $img . ' />';
    }

    /**
     * Determines the location of the system temporary directory. If a specific
     * setting cannot be found, it defaults to /tmp.
     *
     * @return string  A directory name that can be used for temp files.
     *                 Returns false if one could not be found.
     */
    function getTempDir()
    {
        global $conf;

        /* If one has been specifically set, then use that */
        if (!empty($conf['tmpdir'])) {
            $tmp = $conf['tmpdir'];
        }

        /* Next, try Util::getTempDir(). */
        if (empty($tmp)) {
            $tmp = Util::getTempDir();
        }

        /* If it is still empty, we have failed, so return false;
         * otherwise return the directory determined. */
        return empty($tmp) ? false : $tmp;
    }

    /**
     * Creates a temporary filename for the lifetime of the script, and
     * (optionally) registers it to be deleted at request shutdown.
     *
     * @param string $prefix   Prefix to make the temporary name more
     *                         recognizable.
     * @param boolean $delete  Delete the file at the end of the request?
     * @param string $dir      Directory to create the temporary file in.
     * @param boolean $secure  If deleting file, should we securely delete the
     *                         file?
     *
     * @return string   Returns the full path-name to the temporary file or
     *                  false if a temporary file could not be created.
     */
    function getTempFile($prefix = 'Horde', $delete = true, $dir = '',
                         $secure = false)
    {
        if (empty($dir) || !is_dir($dir)) {
            $dir = Horde::getTempDir();
        }

        return Util::getTempFile($prefix, $delete, $dir, $secure);
    }

    /**
     * Starts output compression, if requested.
     */
    function compressOutput()
    {
        static $started;

        if (isset($started)) {
            return;
        }

        /* Compress output if requested and possible. */
        if ($GLOBALS['conf']['compress_pages'] &&
            !$GLOBALS['browser']->hasQuirk('buggy_compression') &&
            !(bool)ini_get('zlib.output_compression') &&
            !(bool)ini_get('zend_accelerator.compress_all') &&
            ini_get('output_handler') != 'ob_gzhandler') {
            if (ob_get_level()) {
                ob_end_clean();
            }
            ob_start('ob_gzhandler');
        }

        $started = true;
    }

    /**
     * Determines if output compression can be used.
     *
     * @return boolean  True if output compression can be used, false if not.
     */
    function allowOutputCompression()
    {
        require_once 'Horde/Browser.php';
        $browser = &Browser::singleton();

        /* Turn off compression for buggy browsers. */
        if ($browser->hasQuirk('buggy_compression')) {
            return false;
        }

        return (ini_get('zlib.output_compression') == '' &&
                ini_get('zend_accelerator.compress_all') == '' &&
                ini_get('output_handler') != 'ob_gzhandler');
    }

    /**
     * Returns the Web server being used.
     * PHP string list built from the PHP 'configure' script.
     *
     * @return string  A web server identification string.
     * <pre>
     * 'aolserver' = AOL Server
     * 'apache1'   = Apache 1.x
     * 'apache2'   = Apache 2.x
     * 'caudium'   = Caudium
     * 'cgi'       = Unknown server - PHP built as CGI program
     * 'cli'       = Command Line Interface build
     * 'embed'     = Embedded PHP
     * 'isapi'     = Zeus ISAPI
     * 'milter'    = Milter
     * 'nsapi'     = NSAPI
     * 'phttpd'    = PHTTPD
     * 'pi3web'    = Pi3Web
     * 'roxen'     = Roxen/Pike
     * 'servlet'   = Servlet
     * 'thttpd'    = thttpd
     * 'tux'       = Tux
     * 'webjames'  = Webjames
     * </pre>
     */
    function webServerID()
    {
        $server = php_sapi_name();

        if ($server == 'apache') {
            return 'apache1';
        } elseif (($server == 'apache2filter') ||
                  ($server == 'apache2handler')) {
            return 'apache2';
        } else {
            return $server;
        }
    }

    /**
     * Returns the <link> tags for the CSS stylesheets.
     *
     * @param string|array $app  The Horde application(s).
     * @param mixed $theme       The theme to use; specify an empty value to
     *                           retrieve the theme from user preferences, and
     *                           false for no theme.
     * @param boolean $inherit   Inherit Horde-wide CSS?
     *
     * @return string  <link> tags for CSS stylesheets.
     */
    function stylesheetLink($apps = null, $theme = '', $inherit = true)
    {
        if ($theme === '' && isset($GLOBALS['prefs'])) {
            $theme = $GLOBALS['prefs']->getValue('theme');
        }

        $rtl = isset($GLOBALS['nls']['rtl'][$GLOBALS['language']]);
        $css = array();

        $themes_fs = $GLOBALS['registry']->get('themesfs', 'horde');
        if ($inherit) {
            $themes_uri = Horde::url($GLOBALS['registry']->get('themesuri', 'horde'), false, -1);
            $css[] = $themes_uri . '/screen.css';
            if (!empty($theme) &&
                file_exists($themes_fs . '/' . $theme . '/screen.css')) {
                $css[] = $themes_uri . '/' . $theme . '/screen.css';
            }

            if ($rtl) {
                $css[] = $themes_uri . '/rtl.css';
                if (!empty($theme) &&
                    file_exists($themes_fs . '/' . $theme . '/rtl.css')) {
                    $css[] = $themes_uri . '/' . $theme . '/rtl.css';
                }
            }
        }

        if (!empty($apps)) {
            if (!is_array($apps)) {
                $apps = array($apps);
            }

            foreach ($apps as $app) {
                if ($inherit && $app == 'horde') {
                    continue;
                }

                $themes_fs = $GLOBALS['registry']->get('themesfs', $app);
                $themes_uri = Horde::url($GLOBALS['registry']->get('themesuri', $app), false, -1);
                if (file_exists($themes_fs . '/screen.css')) {
                    $css[] = $themes_uri . '/screen.css';
                }
                if (!empty($theme) &&
                    file_exists($themes_fs . '/' . $theme . '/screen.css')) {
                    $css[] = $themes_uri . '/' . $theme . '/screen.css';
                }

                if ($rtl) {
                    if (file_exists($themes_fs . '/rtl.css')) {
                        $css[] = $themes_uri . '/rtl.css';
                    }
                    if (!empty($theme) &&
                        file_exists($themes_fs . '/' . $theme . '/rtl.css')) {
                        $css[] = $themes_uri . '/' . $theme . '/rtl.css';
                    }
                }
            }
        }

        $html = '';
        foreach ($css as $css_link) {
            $html .= '<link href="' . $css_link . '" rel="stylesheet" type="text/css" />' . "\n";
        }

        /* Load IE PNG transparency code if needed. */
        if ($GLOBALS['browser']->hasQuirk('png_transparency') &&
            $GLOBALS['prefs']->getValue('alpha_filter')) {
            $url = Horde::url($GLOBALS['registry']->get('jsuri', 'horde') . '/alphaImageLoader.php', true, -1);
            $html .= '<style type="text/css"> img { behavior: url("' . $url . '"); } </style>';
        }

        /* Load browser specific stylesheets if needed. */
        if ($GLOBALS['browser']->isBrowser('msie') && $GLOBALS['browser']->getMajor() < 7) {
            $html .= '<link href="' . $GLOBALS['registry']->get('themesuri', 'horde') . '/ie6_or_less.css" rel="stylesheet" type="text/css" />' . "\n";
            if ($GLOBALS['browser']->getPlatform() == 'mac') {
                $html .= '<link href="' . $GLOBALS['registry']->get('themesuri', 'horde') . '/ie5mac.css" rel="stylesheet" type="text/css" />' . "\n";
            }
        }
        if ($GLOBALS['browser']->isBrowser('opera')) {
            $html .= '<link href="' . $GLOBALS['registry']->get('themesuri', 'horde') . '/opera.css" rel="stylesheet" type="text/css" />' . "\n";
        }
        if ($GLOBALS['browser']->isBrowser('mozilla') &&
            $GLOBALS['browser']->getMajor() >= 5 &&
            preg_match('/rv:(.*)\)/', $GLOBALS['browser']->getAgentString(), $revision) &&
            $revision[1] <= 1.4) {
            $html .= '<link href="' . $GLOBALS['registry']->get('themesuri', 'horde') . '/moz14.css" rel="stylesheet" type="text/css" />' . "\n";
        }
        if (strpos(strtolower($GLOBALS['browser']->getAgentString()), 'safari') !== false) {
            $html .= '<link href="' . $GLOBALS['registry']->get('themesuri', 'horde') . '/safari.css" rel="stylesheet" type="text/css" />' . "\n";
        }

        return $html;
    }

    /**
     * Sets a custom session handler up, if there is one.
     * If the global variable 'session_cache_limiter' is defined, its value 
     * will override the cache limiter setting found in the configuration
     * file.
     */
    function setupSessionHandler()
    {
        global $conf;

        ini_set('url_rewriter.tags', 0);
        if (!empty($conf['session']['use_only_cookies'])) {
            ini_set('session.use_only_cookies', 1);
            if (!empty($conf['cookie']['domain']) &&
                strpos($conf['server']['name'], '.') === false) {
                Horde::fatal('Session cookies will not work without a FQDN and with a non-empty cookie domain. Either use a fully qualified domain name like "http://www.example.com" instead of "http://example" only, or set the cookie domain in the Horde configuration to an empty value, or enable non-cookie (url-based) sessions in the Horde configuration.', __FILE__, __LINE__);
            }
        }
        session_set_cookie_params($conf['session']['timeout'],
                                  $conf['cookie']['path'], $conf['cookie']['domain'], $conf['use_ssl'] == 1 ? 1 : 0);
        session_cache_limiter(Util::nonInputVar('session_cache_limiter', $conf['session']['cache_limiter']));
        session_name(urlencode($conf['session']['name']));

        $type = !empty($conf['sessionhandler']['type']) ? $conf['sessionhandler']['type'] : 'none';

        if ($type == 'external') {
            $calls = $conf['sessionhandler']['params'];
            session_set_save_handler($calls['open'],
                                     $calls['close'],
                                     $calls['read'],
                                     $calls['write'],
                                     $calls['destroy'],
                                     $calls['gc']);
        } elseif ($type != 'none') {
            global $_session_handler;
            require_once 'Horde/SessionHandler.php';
            $_session_handler = &SessionHandler::singleton($conf['sessionhandler']['type']);
            if (!empty($_session_handler) &&
                !is_a($_session_handler, 'PEAR_Error')) {
                ini_set('session.save_handler', 'user');
                session_set_save_handler(array(&$_session_handler, 'open'),
                                         array(&$_session_handler, 'close'),
                                         array(&$_session_handler, 'read'),
                                         array(&$_session_handler, 'write'),
                                         array(&$_session_handler, 'destroy'),
                                         array(&$_session_handler, 'gc'));
            } else {
                Horde::fatal(PEAR::raiseError('Horde is unable to correctly start the custom session handler.'), __FILE__, __LINE__, false);
            }
        }
    }

    /**
     * Returns an un-used access key from the label given.
     *
     * @param string $label     The label to choose an access key from.
     * @param boolean $nocheck  Don't check if the access key already has been
     *                          used?
     *
     * @return string  A single lower case character access key or empty
     *                 string if none can be found
     */
    function getAccessKey($label, $nocheck = false, $shutdown = false)
    {
        /* The access keys already used in this page */
        static $_used = array();

        /* The labels already used in this page */
        static $_labels = array();

        /* Shutdown call for translators? */
        if ($shutdown) {
            if (!count($_labels)) {
                return;
            }
            $script = basename($_SERVER['PHP_SELF']);
            $labels = array_keys($_labels);
            sort($labels);
            $used = array_keys($_used);
            sort($used);
            $remaining = str_replace($used, array(), 'abcdefghijklmnopqrstuvwxyz');
            Horde::logMessage('Access key information for ' . $script, __FILE__, __LINE__);
            Horde::logMessage('Used labels: ' . implode(',', $labels), __FILE__, __LINE__);
            Horde::logMessage('Used keys: ' . implode('', $used), __FILE__, __LINE__);
            Horde::logMessage('Free keys: ' . $remaining, __FILE__, __LINE__);
            return;
        }

        /* Use access keys at all? */
        static $notsupported;
        if (!isset($notsupported)) {
            $notsupported = !$GLOBALS['browser']->hasFeature('accesskey') ||
                !$GLOBALS['prefs']->getValue('widget_accesskey');
        }

        if ($notsupported || !preg_match('/_([A-Za-z])/', $label, $match)) {
            return '';
        }
        $key = $match[1];

        /* Has this key already been used? */
        if (isset($_used[strtolower($key)]) &&
            !($nocheck && isset($_labels[$label]))) {
            return '';
        }

        /* Save key and label. */
        $_used[strtolower($key)] = true;
        $_labels[$label] = true;

        return $key;
    }

    /**
     * Strips an access key from a label.
     * For multibyte charset strings the access key gets removed completely,
     * otherwise only the underscore gets removed.
     *
     * @param string $label  The label containing an access key.
     *
     * @return string  The label with the access key being stripped.
     */
    function stripAccessKey($label)
    {
        include_once HORDE_BASE . '/config/nls.php';
        $multibyte = isset($GLOBALS['nls']['multibyte'][NLS::getCharset(true)]);

        return preg_replace('/_([A-Za-z])/',
                            $multibyte && preg_match('/[\x80-\xff]/', $label) ? '' : '\1',
                            $label);
    }

    /**
     * Highlights an access key in a label.
     *
     * @param string $label      The label to to highlight the access key in.
     * @param string $accessKey  The access key to highlight.
     *
     * @return string  The HTML version of the label with the access key
     *                 highlighted.
     */
    function highlightAccessKey($label, $accessKey)
    {
        $stripped_label = Horde::stripAccesskey($label);

        if (empty($accessKey)) {
            return $stripped_label;
        }

        if (isset($GLOBALS['nls']['multibyte'][NLS::getCharset(true)])) {
            /* Prefix parenthesis with the UTF-8 representation of the LRO
             * (Left-to-Right-Override) Unicode codepoint U+202D. */
            $prefix = NLS::getCharset() == 'UTF-8' ? "\xe2\x80\xad" : '';
            return $stripped_label . $prefix . '(<span class="accessKey">'
                . strtoupper($accessKey) . '</span>' . ')';
        } else {
            return str_replace('_' . $accessKey, '<span class="accessKey">' . $accessKey . '</span>', $label);
        }
    }

    /**
     * Returns the appropriate "accesskey" and "title" attributes for an HTML
     * tag and the given label.
     *
     * @param string $label     The title of an HTML element
     * @param boolean $nocheck  Don't check if the access key already has been
     *                          used?
     *
     * @return string  The title, and if appropriate, the accesskey attributes
     *                 for the element.
     */
    function getAccessKeyAndTitle($label, $nocheck = false)
    {
        $ak = Horde::getAccessKey($label, $nocheck);
        $attributes = 'title="' . Horde::stripAccessKey($label);
        if (!empty($ak)) {
            $attributes .= sprintf(_(" (Accesskey %s)"), $ak);
            $attributes .= '" accesskey="' . $ak;
        }
        $attributes .= '"';
        return $attributes;
    }

    /**
     * Returns a label element including an access key for usage in conjuction
     * with a form field. User preferences regarding access keys are respected.
     *
     * @param string $for    The form field's id attribute.
     * @param string $label  The label text.
     * @param string $ak     The access key to use. If null a new access key
     *                       will be generated.
     *
     * @return string  The html code for the label element.
     */
    function label($for, $label, $ak = null)
    {
        global $prefs;

        if (is_null($ak)) {
            $ak = Horde::getAccesskey($label, 1);
        }
        $label = Horde::highlightAccessKey($label, $ak);

        return sprintf('<label for="%s"%s>%s</label>',
                       $for,
                       !empty($ak) ? ' accesskey="' . $ak . '"' : '',
                       $label);
    }

    /**
     * Redirects to the main Horde login page on authentication failure.
     */
    function authenticationFailureRedirect()
    {
        require_once 'Horde/CLI.php';
        if (Horde_CLI::runningFromCLI()) {
            $cli = &Horde_CLI::singleton();
            $cli->fatal(_("You are not authenticated."));
        }

        $url = $GLOBALS['registry']->get('webroot', 'horde') . '/login.php';
        $url = Util::addParameter($url, array('url' => Horde::selfUrl(true), 'nosidebar' => 1), null, false);
        $url = Auth::addLogoutParameters($url);
        header('Location: ' . Horde::url($url, true));
        exit;
    }

    /**
     * Provides a standardised function to call a Horde hook, checking whether
     * a hook config file exists and whether the function itself exists. If
     * these two conditions are not satisfied it will return the specified
     * value (by default a PEAR error).
     *
     * @param string $hook  The function to call.
     * @param array  $args  An array of any arguments to pass to the hook
     *                      function.
     * @param string $app   If specified look for hooks in the config directory
     *                      of this app.
     * @param mixed $error  What to return if $app/config/hooks.php or $hook
     *                      does not exist. If this is the string 'PEAR_Error'
     *                      a PEAR error object is returned instead, detailing
     *                      the failure.
     *
     * @return mixed  Either the results of the hook or PEAR error on failure.
     */
    function callHook($hook, $args = array(), $app = 'horde', $error = 'PEAR_Error')
    {
        global $registry;
        static $hooks_loaded;

        if (!isset($hooks_loaded)) {
            if (file_exists($registry->get('fileroot', $app) . '/config/hooks.php')) {
                require_once $registry->get('fileroot', $app) . '/config/hooks.php';
                $hooks_loaded = true;
            } else {
                $hooks_loaded = false;
            }
        }
        if ($hooks_loaded && function_exists($hook)) {
            return call_user_func_array($hook, $args);
        }

        if (is_string($error) && strcmp($error, 'PEAR_Error') == 0) {
            $error = PEAR::raiseError(sprintf('Hook %s in application %s not called.', $hook, $app));
            Horde::logMessage($error, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        }

        return $error;
    }

    /**
     * Returns the specified permission for the current user.
     *
     * @since Horde 3.1
     *
     * @param string $permission  A permission, currently only 'max_blocks'.
     *
     * @return mixed  The value of the specified permission.
     */
    function hasPermission($permission)
    {
        global $perms;

        if (!$perms->exists('horde:' . $permission)) {
            return true;
        }

        $allowed = $perms->getPermissions('horde:' . $permission);
        if (is_array($allowed)) {
            switch ($permission) {
            case 'max_blocks':
                $allowed = array_reduce($allowed, create_function('$a, $b', 'return max($a, $b);'), 0);
                break;
            }
        }

        return $allowed;
    }

}
