<?php

/** Horde_String */
require_once 'Horde/String.php';

/**
 * The Browser:: class provides capability information for the current
 * web client.
 *
 * Browser identification is performed by examining the HTTP_USER_AGENT
 * environment variable provided by the web server.
 *
 * $Horde: framework/Browser/Browser.php,v 1.153.2.53 2007/09/27 00:16:27 jan Exp $
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
 * @package Horde_Browser
 */
class Browser {

    /**
     * Major version number.
     *
     * @var integer
     */
    var $_majorVersion = 0;

    /**
     * Minor version number.
     *
     * @var integer
     */
    var $_minorVersion = 0;

    /**
     * Browser name.
     *
     * @var string
     */
    var $_browser = '';

    /**
     * Full user agent string.
     *
     * @var string
     */
    var $_agent = '';

    /**
     * Lower-case user agent string.
     *
     * @var string
     */
    var $_lowerAgent = '';

    /**
     * HTTP_ACCEPT string
     *
     * @var string
     */
    var $_accept = '';

    /**
     * Platform the browser is running on.
     *
     * @var string
     */
    var $_platform = '';

    /**
     * Known robots.
     *
     * @var array
     */
    var $_robots = array(
        /* The most common ones. */
        'Googlebot',
        'msnbot',
        'Slurp',
        'Yahoo',
        /* The rest alphabetically. */
        'appie',
        'Arachnoidea',
        'ArchitextSpider',
        'Ask Jeeves',
        'B-l-i-t-z-Bot',
        'Baiduspider',
        'BecomeBot',
        'cfetch',
        'ConveraCrawler',
        'ExtractorPro',
        'FAST-WebCrawler',
        'FDSE robot',
        'fido',
        'findlinks',
        'Francis',
        'geckobot',
        'Gigabot',
        'Girafabot',
        'grub-client',
        'Gulliver',
        'HTTrack',
        'ia_archiver',
        'iCCrawler',
        'InfoSeek',
        'kinjabot',
        'KIT-Fireball',
        'larbin',
        'LEIA',
        'lmspider',
        'lwp-trivial',
        'Lycos_Spider',
        'Mediapartners-Google',
        'MuscatFerret',
        'NaverBot',
        'OmniExplorer_Bot',
        'polybot',
        'Pompos',
        'RufusBot',
        'Scooter',
        'Seekbot',
        'sogou spider',
        'sproose',
        'Teoma',
        'TheSuBot',
        'TurnitinBot',
        'Ultraseek',
        'ViolaBot',
        'voyager',
        'W3C-checklink',
        'webbandit',
        'www.almaden.ibm.com/cs/crawler',
        'yacy',
        'ZyBorg',
    );

    /**
     * Is this a mobile browser?
     *
     * @var boolean
     */
    var $_mobile = false;

    /**
     * Features.
     *
     * @var array
     */
    var $_features = array(
        'html'       => true,
        'hdml'       => false,
        'wml'        => false,
        'images'     => true,
        'iframes'    => false,
        'frames'     => true,
        'tables'     => true,
        'java'       => true,
        'javascript' => true,
        'dom'        => false,
        'utf'        => false,
        'rte'        => false,
        'homepage'   => false,
        'accesskey'  => false,
        'optgroup'   => false,
        'xmlhttpreq' => false,
        'cite'       => false,
    );

    /**
     * Quirks
     *
     * @var array
     */
    var $_quirks = array(
        'avoid_popup_windows'        => false,
        'break_disposition_header'   => false,
        'break_disposition_filename' => false,
        'broken_multipart_form'      => false,
        'buggy_compression'          => false,
        'cache_same_url'             => false,
        'cache_ssl_downloads'        => false,
        'double_linebreak_textarea'  => false,
        'empty_file_input_value'     => false,
        'must_cache_forms'           => false,
        'no_filename_spaces'         => false,
        'no_hidden_overflow_tables'  => false,
        'ow_gui_1.3'                 => false,
        'png_transparency'           => false,
        'scrollbar_in_way'           => false,
        'scroll_tds'                 => false,
        'windowed_controls'          => false,
    );

    /**
     * List of viewable image MIME subtypes.
     * This list of viewable images works for IE and Netscape/Mozilla.
     *
     * @var array
     */
    var $_images = array('jpeg', 'gif', 'png', 'pjpeg', 'x-png', 'bmp');

    /**

    /**
     * Returns a reference to the global Browser object, only creating it
     * if it doesn't already exist.
     *
     * This method must be invoked as:<pre>
     *   $browser = &Browser::singleton([$userAgent[, $accept]]);</pre>
     *
     * @param string $userAgent  The browser string to parse.
     * @param string $accept     The HTTP_ACCEPT settings to use.
     *
     * @return Browser  The Browser object.
     */
    function &singleton($userAgent = null, $accept = null)
    {
        static $instances;

        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($userAgent, $accept));
        if (empty($instances[$signature])) {
            $instances[$signature] = new Browser($userAgent, $accept);
        }

        return $instances[$signature];
    }

    /**
     * Create a browser instance (Constructor).
     *
     * @param string $userAgent  The browser string to parse.
     * @param string $accept     The HTTP_ACCEPT settings to use.
     */
    function Browser($userAgent = null, $accept = null)
    {
        $this->match($userAgent, $accept);
    }

    /**
     * Parses the user agent string and inititializes the object with
     * all the known features and quirks for the given browser.
     *
     * @param string $userAgent  The browser string to parse.
     * @param string $accept     The HTTP_ACCEPT settings to use.
     */
    function match($userAgent = null, $accept = null)
    {
        // Set our agent string.
        if (is_null($userAgent)) {
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $this->_agent = trim($_SERVER['HTTP_USER_AGENT']);
            }
        } else {
            $this->_agent = $userAgent;
        }
        $this->_lowerAgent = String::lower($this->_agent);

        // Set our accept string.
        if (is_null($accept)) {
            if (isset($_SERVER['HTTP_ACCEPT'])) {
                $this->_accept = String::lower(trim($_SERVER['HTTP_ACCEPT']));
            }
        } else {
            $this->_accept = String::lower($accept);
        }

        // Check for UTF support.
        if (isset($_SERVER['HTTP_ACCEPT_CHARSET'])) {
            $this->setFeature('utf', strpos(String::lower($_SERVER['HTTP_ACCEPT_CHARSET']), 'utf') !== false);
        }

        if (!empty($this->_agent)) {
            $this->_setPlatform();

            if (strpos($this->_lowerAgent, 'iemobile') !== false ||
                strpos($this->_lowerAgent, 'mobileexplorer') !== false ||
                strpos($this->_lowerAgent, 'openwave') !== false ||
                strpos($this->_lowerAgent, 'opera mini') !== false ||
                strpos($this->_lowerAgent, 'operamini') !== false) {
                $this->setFeature('frames', false);
                $this->setFeature('javascript', false);
                $this->setQuirk('avoid_popup_windows');
                $this->_mobile = true;
            } elseif (preg_match('|Opera[/ ]([0-9.]+)|', $this->_agent, $version)) {
                $this->setBrowser('opera');
                list($this->_majorVersion, $this->_minorVersion) = explode('.', $version[1]);
                $this->setFeature('javascript', true);
                $this->setQuirk('no_filename_spaces');

                /* Opera Mobile reports its screen resolution in the
                 * user agent strings. */
                if (preg_match('/; (120x160|240x280|240x320|320x320)\)/', $this->_agent)) {
                    $this->_mobile = true;
                }

                if ($this->_majorVersion >= 7) {
                    $this->setFeature('dom');
                    $this->setFeature('iframes');
                    $this->setFeature('accesskey');
                    $this->setFeature('optgroup');
                    $this->setQuirk('double_linebreak_textarea');
                }
            } elseif (strpos($this->_lowerAgent, 'elaine/') !== false ||
                      strpos($this->_lowerAgent, 'palmsource') !== false ||
                      strpos($this->_lowerAgent, 'digital paths') !== false) {
                $this->setBrowser('palm');
                $this->setFeature('images', false);
                $this->setFeature('frames', false);
                $this->setFeature('javascript', false);
                $this->setQuirk('avoid_popup_windows');
                $this->_mobile = true;
            } elseif ((preg_match('|MSIE ([0-9.]+)|', $this->_agent, $version)) ||
                      (preg_match('|Internet Explorer/([0-9.]+)|', $this->_agent, $version))) {

                $this->setBrowser('msie');
                $this->setQuirk('cache_ssl_downloads');
                $this->setQuirk('cache_same_url');
                $this->setQuirk('break_disposition_filename');

                if (strpos($version[1], '.') !== false) {
                    list($this->_majorVersion, $this->_minorVersion) = explode('.', $version[1]);
                } else {
                    $this->_majorVersion = $version[1];
                    $this->_minorVersion = 0;
                }

                /* IE (< 7) on Windows does not support alpha transparency in
                 * PNG images. */
                if (($this->_majorVersion < 7) &&
                    preg_match('/windows/i', $this->_agent)) {
                    $this->setQuirk('png_transparency');
                }

                /* IE 6 (pre-SP1) and 5.5 (pre-SP1) have buggy compression.
                 * The versions affected are as follows:
                 * 6.00.2462.0000  Internet Explorer 6 Public Preview (Beta)
                 * 6.00.2479.0006  Internet Explorer 6 Public Preview (Beta)
                                    Refresh
                 * 6.00.2600.0000  Internet Explorer 6 (Windows XP)
                 * 5.50.3825.1300  Internet Explorer 5.5 Developer Preview (Beta)
                 * 5.50.4030.2400  Internet Explorer 5.5 & Internet Tools Beta
                 * 5.50.4134.0100  Internet Explorer 5.5 for Windows Me (4.90.3000)
                 * 5.50.4134.0600  Internet Explorer 5.5
                 * 5.50.4308.2900  Internet Explorer 5.5 Advanced Security Privacy Beta
                 *
                 * See:
                 * ====
                 * http://support.microsoft.com/kb/164539;
                 * http://support.microsoft.com/default.aspx?scid=kb;en-us;Q312496)
                 * http://support.microsoft.com/default.aspx?scid=kb;en-us;Q313712
                 */
                $ie_vers = $this->getIEVersion();
                $buggy_list = array(
                    '6,00,2462,0000', '6,00,2479,0006', '6,00,2600,0000',
                    '5,50,3825,1300', '5,50,4030,2400', '5,50,4134,0100',
                    '5,50,4134,0600', '5,50,4308,2900'
                );
                if (!is_null($ie_vers) && in_array($ie_vers, $buggy_list)) {
                    $this->setQuirk('buggy_compression');
                }

                /* Some Handhelds have their screen resolution in the
                 * user agent string, which we can use to look for
                 * mobile agents. */
                if (preg_match('/; (120x160|240x280|240x320|320x320)\)/', $this->_agent)) {
                    $this->_mobile = true;
                }

                switch ($this->_majorVersion) {
                case 7:
                    $this->setFeature('javascript', 1.4);
                    $this->setFeature('dom');
                    $this->setFeature('iframes');
                    $this->setFeature('utf');
                    $this->setFeature('rte');
                    $this->setFeature('homepage');
                    $this->setFeature('accesskey');
                    $this->setFeature('optgroup');
                    $this->setFeature('xmlhttpreq');
                    break;

                case 6:
                    $this->setFeature('javascript', 1.4);
                    $this->setFeature('dom');
                    $this->setFeature('iframes');
                    $this->setFeature('utf');
                    $this->setFeature('rte');
                    $this->setFeature('homepage');
                    $this->setFeature('accesskey');
                    $this->setFeature('optgroup');
                    $this->setFeature('xmlhttpreq');
                    $this->setQuirk('scrollbar_in_way');
                    $this->setQuirk('broken_multipart_form');
                    $this->setQuirk('windowed_controls');
                    break;

                case 5:
                    if ($this->getPlatform() == 'mac') {
                        $this->setFeature('javascript', 1.2);
                        $this->setFeature('optgroup');
                    } else {
                        // MSIE 5 for Windows.
                        $this->setFeature('javascript', 1.4);
                        $this->setFeature('dom');
                        $this->setFeature('xmlhttpreq');
                        if ($this->_minorVersion >= 5) {
                            $this->setFeature('rte');
                            $this->setQuirk('windowed_controls');
                        }
                    }
                    $this->setFeature('iframes');
                    $this->setFeature('utf');
                    $this->setFeature('homepage');
                    $this->setFeature('accesskey');
                    if ($this->_minorVersion == 5) {
                        $this->setQuirk('break_disposition_header');
                        $this->setQuirk('broken_multipart_form');
                    }
                    break;

                case 4:
                    $this->setFeature('javascript', 1.2);
                    $this->setFeature('accesskey');
                    if ($this->_minorVersion > 0) {
                        $this->setFeature('utf');
                    }
                    break;

                case 3:
                    $this->setFeature('javascript', 1.1);
                    $this->setQuirk('avoid_popup_windows');
                    break;
                }
            } elseif (preg_match('|ANTFresco/([0-9]+)|', $this->_agent, $version)) {
                $this->setBrowser('fresco');
                $this->setFeature('javascript', 1.1);
                $this->setQuirk('avoid_popup_windows');
            } elseif (strpos($this->_lowerAgent, 'avantgo') !== false) {
                $this->setBrowser('avantgo');
                $this->_mobile = true;
            } elseif (preg_match('|Konqueror/([0-9]+)|', $this->_agent, $version) ||
                      preg_match('|Safari/([0-9]+)\.?([0-9]+)?|', $this->_agent, $version)) {
                // Konqueror and Apple's Safari both use the KHTML
                // rendering engine.
                $this->setBrowser('konqueror');
                $this->setQuirk('empty_file_input_value');
                $this->setQuirk('no_hidden_overflow_tables');
                $this->_majorVersion = $version[1];
                if (isset($version[2])) {
                    $this->_minorVersion = $version[2];
                }

                if (strpos($this->_agent, 'Safari') !== false &&
                    $this->_majorVersion >= 60) {
                    // Safari.
                    $this->setFeature('utf');
                    $this->setFeature('javascript', 1.4);
                    $this->setFeature('dom');
                    $this->setFeature('iframes');
                    if ($this->_majorVersion > 125 ||
                        ($this->_majorVersion == 125 &&
                         $this->_minorVersion >= 1)) {
                        $this->setFeature('accesskey');
                        $this->setFeature('xmlhttpreq');
                    }
                } else {
                    // Konqueror.
                    $this->setFeature('javascript', 1.1);
                    switch ($this->_majorVersion) {
                    case 3:
                        $this->setFeature('dom');
                        $this->setFeature('iframes');
                        break;
                    }
                }
            } elseif (preg_match('|Mozilla/([0-9.]+)|', $this->_agent, $version)) {
                $this->setBrowser('mozilla');
                $this->setQuirk('must_cache_forms');

                list($this->_majorVersion, $this->_minorVersion) = explode('.', $version[1]);
                switch ($this->_majorVersion) {
                case 5:
                    if ($this->getPlatform() == 'win') {
                        $this->setQuirk('break_disposition_filename');
                    }
                    $this->setFeature('javascript', 1.4);
                    $this->setFeature('dom');
                    $this->setFeature('accesskey');
                    $this->setFeature('optgroup');
                    $this->setFeature('xmlhttpreq');
                    $this->setFeature('cite');
                    if (preg_match('|rv:(.*)\)|', $this->_agent, $revision)) {
                        if ($revision[1] >= 1) {
                            $this->setFeature('iframes');
                        }
                        if ($revision[1] >= 1.3) {
                            $this->setFeature('rte');
                        }
                    }
                    break;

                case 4:
                    $this->setFeature('javascript', 1.3);
                    $this->setQuirk('buggy_compression');
                    break;

                case 3:
                default:
                    $this->setFeature('javascript', 1);
                    $this->setQuirk('buggy_compression');
                    break;
                }
            } elseif (preg_match('|Lynx/([0-9]+)|', $this->_agent, $version)) {
                $this->setBrowser('lynx');
                $this->setFeature('images', false);
                $this->setFeature('frames', false);
                $this->setFeature('javascript', false);
                $this->setQuirk('avoid_popup_windows');
            } elseif (preg_match('|Links \(([0-9]+)|', $this->_agent, $version)) {
                $this->setBrowser('links');
                $this->setFeature('images', false);
                $this->setFeature('frames', false);
                $this->setFeature('javascript', false);
                $this->setQuirk('avoid_popup_windows');
            } elseif (preg_match('|HotJava/([0-9]+)|', $this->_agent, $version)) {
                $this->setBrowser('hotjava');
                $this->setFeature('javascript', false);
            } elseif (strpos($this->_agent, 'UP/') !== false ||
                      strpos($this->_agent, 'UP.B') !== false ||
                      strpos($this->_agent, 'UP.L') !== false) {
                $this->setBrowser('up');
                $this->setFeature('html', false);
                $this->setFeature('javascript', false);
                $this->setFeature('hdml');
                $this->setFeature('wml');

                if (strpos($this->_agent, 'GUI') !== false &&
                    strpos($this->_agent, 'UP.Link') !== false) {
                    /* The device accepts Openwave GUI extensions for
                     * WML 1.3. Non-UP.Link gateways sometimes have
                     * problems, so exclude them. */
                    $this->setQuirk('ow_gui_1.3');
                }
                $this->_mobile = true;
            } elseif (strpos($this->_agent, 'Xiino/') !== false) {
                $this->setBrowser('xiino');
                $this->setFeature('hdml');
                $this->setFeature('wml');
                $this->_mobile = true;
            } elseif (strpos($this->_agent, 'Palmscape/') !== false) {
                $this->setBrowser('palmscape');
                $this->setFeature('javascript', false);
                $this->setFeature('hdml');
                $this->setFeature('wml');
                $this->_mobile = true;
            } elseif (strpos($this->_agent, 'Nokia') !== false) {
                $this->setBrowser('nokia');
                $this->setFeature('html', false);
                $this->setFeature('wml');
                $this->setFeature('xhtml');
                $this->_mobile = true;
            } elseif (strpos($this->_agent, 'Ericsson') !== false) {
                $this->setBrowser('ericsson');
                $this->setFeature('html', false);
                $this->setFeature('wml');
                $this->_mobile = true;
            } elseif (strpos($this->_lowerAgent, 'wap') !== false) {
                $this->setBrowser('wap');
                $this->setFeature('html', false);
                $this->setFeature('javascript', false);
                $this->setFeature('hdml');
                $this->setFeature('wml');
                $this->_mobile = true;
            } elseif (strpos($this->_lowerAgent, 'docomo') !== false ||
                      strpos($this->_lowerAgent, 'portalmmm') !== false) {
                $this->setBrowser('imode');
                $this->setFeature('images', false);
                $this->_mobile = true;
        } elseif (strpos($this->_agent, 'BlackBerry') !== false) {
                $this->setBrowser('blackberry');
                $this->setFeature('html', false);
                $this->setFeature('javascript', false);
                $this->setFeature('hdml');
                $this->setFeature('wml');
                $this->_mobile = true;
            } elseif (strpos($this->_agent, 'MOT-') !== false) {
                $this->setBrowser('motorola');
                $this->setFeature('html', false);
                $this->setFeature('javascript', false);
                $this->setFeature('hdml');
                $this->setFeature('wml');
                $this->_mobile = true;
            } elseif (strpos($this->_lowerAgent, 'j-') !== false) {
                $this->setBrowser('mml');
                $this->_mobile = true;
            }
        }
    }

    /**
     * Match the platform of the browser.
     *
     * This is a pretty simplistic implementation, but it's intended
     * to let us tell what line breaks to send, so it's good enough
     * for its purpose.
     *
     * @since Horde 2.2
     */
    function _setPlatform()
    {
        if (strpos($this->_lowerAgent, 'wind') !== false) {
            $this->_platform = 'win';
        } elseif (strpos($this->_lowerAgent, 'mac') !== false) {
            $this->_platform = 'mac';
        } else {
            $this->_platform = 'unix';
        }
    }

    /**
     * Return the currently matched platform.
     *
     * @return string  The user's platform.
     *
     * @since Horde 2.2
     */
    function getPlatform()
    {
        return $this->_platform;
    }

    /**
     * Sets the current browser.
     *
     * @param string $browser  The browser to set as current.
     */
    function setBrowser($browser)
    {
        $this->_browser = $browser;
    }

    /**
     * Determine if the given browser is the same as the current.
     *
     * @param string $browser  The browser to check.
     *
     * @return boolean  Is the given browser the same as the current?
     */
    function isBrowser($browser)
    {
        return ($this->_browser === $browser);
    }

    /**
     * Do we consider the current browser to be a mobile device?
     *
     * @return boolean  True if we do, false if we don't.
     */
    function isMobile()
    {
        return $this->_mobile;
    }

    /**
     * Determines if the browser is a robot or not.
     *
     * @return boolean  True if browser is a known robot.
     */
    function isRobot()
    {
        foreach ($this->_robots as $robot) {
            if (strpos($this->_agent, $robot) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve the current browser.
     *
     * @return string  The current browser.
     */
    function getBrowser()
    {
        return $this->_browser;
    }

    /**
     * Retrieve the current browser's major version.
     *
     * @return integer  The current browser's major version.
     */
    function getMajor()
    {
        return $this->_majorVersion;
    }

    /**
     * Retrieve the current browser's minor version.
     *
     * @return integer  The current browser's minor version.
     */
    function getMinor()
    {
        return $this->_minorVersion;
    }

    /**
     * Retrieve the current browser's version.
     *
     * @return string  The current browser's version.
     */
    function getVersion()
    {
        return $this->_majorVersion . '.' . $this->_minorVersion;
    }

    /**
     * Return the full browser agent string.
     *
     * @return string  The browser agent string.
     */
    function getAgentString()
    {
        return $this->_agent;
    }

    /**
     * Set unique behavior for the current browser.
     *
     * @param string $quirk  The behavior to set.
     * @param string $value  Special behavior parameter.
     */
    function setQuirk($quirk, $value = true)
    {
        $this->_quirks[$quirk] = $value;
    }

    /**
     * Check unique behavior for the current browser.
     *
     * @param string $quirk  The behavior to check.
     *
     * @return boolean  Does the browser have the behavior set?
     */
    function hasQuirk($quirk)
    {
        return !empty($this->_quirks[$quirk]);
    }

    /**
     * Retrieve unique behavior for the current browser.
     *
     * @param string $quirk  The behavior to retrieve.
     *
     * @return string  The value for the requested behavior.
     */
    function getQuirk($quirk)
    {
        return isset($this->_quirks[$quirk])
               ? $this->_quirks[$quirk]
               : null;
    }

    /**
     * Set capabilities for the current browser.
     *
     * @param string $feature  The capability to set.
     * @param string $value    Special capability parameter.
     */
    function setFeature($feature, $value = true)
    {
        $this->_features[$feature] = $value;
    }

    /**
     * Check the current browser capabilities.
     *
     * @param string $feature  The capability to check.
     *
     * @return boolean  Does the browser have the capability set?
     */
    function hasFeature($feature)
    {
        return !empty($this->_features[$feature]);
    }

    /**
     * Retrieve the current browser capability.
     *
     * @param string $feature  The capability to retrieve.
     *
     * @return string  The value of the requested capability.
     */
    function getFeature($feature)
    {
        return isset($this->_features[$feature])
               ? $this->_features[$feature]
               : null;
    }

    /**
     * Determine if we are using a secure (SSL) connection.
     *
     * @return boolean  True if using SSL, false if not.
     */
    function usingSSLConnection()
    {
        return ((isset($_SERVER['HTTPS']) &&
                 ($_SERVER['HTTPS'] == 'on')) ||
                getenv('SSL_PROTOCOL_VERSION'));
    }

    /**
     * Returns the server protocol in use on the current server.
     *
     * @return string  The HTTP server protocol version.
     */
    function getHTTPProtocol()
    {
        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            if (($pos = strrpos($_SERVER['SERVER_PROTOCOL'], '/'))) {
                return substr($_SERVER['SERVER_PROTOCOL'], $pos + 1);
            }
        }

        return null;
    }

    /**
     * Determine if files can be uploaded to the system.
     *
     * @return integer  If uploads allowed, returns the maximum size of the
     *                  upload in bytes.  Returns 0 if uploads are not
     *                  allowed.
     */
    function allowFileUploads()
    {
        if (ini_get('file_uploads')) {
            if (($dir = ini_get('upload_tmp_dir')) &&
                !is_writable($dir)) {
                return 0;
            }
            $filesize = ini_get('upload_max_filesize');
            switch (strtolower(substr($filesize, -1, 1))) {
            case 'k':
                $filesize = intval(floatval($filesize) * 1024);
                break;

            case 'm':
                $filesize = intval(floatval($filesize) * 1024 * 1024);
                break;

            default:
                $filesize = intval($filesize);
                break;
            }
            $postsize = ini_get('post_max_size');
            switch (strtolower(substr($postsize, -1, 1))) {
            case 'k':
                $postsize = intval(floatval($postsize) * 1024);
                break;

            case 'm':
                $postsize = intval(floatval($postsize) * 1024 * 1024);
                break;

            default:
                $postsize = intval($postsize);
                break;
            }
            return min($filesize, $postsize);
        } else {
            return 0;
        }
    }

    /**
     * Determines if the file was uploaded or not.  If not, will return the
     * appropriate error message.
     *
     * @param string $field  The name of the field containing the uploaded
     *                       file.
     * @param string $name   The file description string to use in the error
     *                       message.  Default: 'file'.
     *
     * @return mixed  True on success, PEAR_Error on error.
     */
    function wasFileUploaded($field, $name = null)
    {
        require_once 'PEAR.php';

        if (is_null($name)) {
            $name = _("file");
        }

        if (!($uploadSize = Browser::allowFileUploads())) {
            return PEAR::raiseError(_("File uploads not supported."));
        }

        /* Get any index on the field name. */
        require_once 'Horde/Array.php';
        $index = Horde_Array::getArrayParts($field, $base, $keys);

        if ($index) {
            /* Index present, fetch the error var to check. */
            $keys_path = array_merge(array($base, 'error'), $keys);
            $error = Horde_Array::getElement($_FILES, $keys_path);

            /* Index present, fetch the tmp_name var to check. */
            $keys_path = array_merge(array($base, 'tmp_name'), $keys);
            $tmp_name = Horde_Array::getElement($_FILES, $keys_path);
        } else {
            /* No index, simple set up of vars to check. */
            if (!isset($_FILES[$field])) {
                return PEAR::raiseError(_("No file uploaded"), UPLOAD_ERR_NO_FILE);
            }
            $error = $_FILES[$field]['error'];
            $tmp_name = $_FILES[$field]['tmp_name'];
        }

        if (!isset($_FILES) || ($error == UPLOAD_ERR_NO_FILE)) {
            return PEAR::raiseError(sprintf(_("There was a problem with the file upload: No %s was uploaded."), $name), UPLOAD_ERR_NO_FILE);
        } elseif (($error == UPLOAD_ERR_OK) && is_uploaded_file($tmp_name)) {
            return true;
        } elseif (($error == UPLOAD_ERR_INI_SIZE) ||
                  ($error == UPLOAD_ERR_FORM_SIZE)) {
            return PEAR::raiseError(sprintf(_("There was a problem with the file upload: The %s was larger than the maximum allowed size (%d bytes)."), $name, $uploadSize), $error);
        } elseif ($error == UPLOAD_ERR_PARTIAL) {
            return PEAR::raiseError(sprintf(_("There was a problem with the file upload: The %s was only partially uploaded."), $name), $error);
        }
    }

    /**
     * Returns the headers for a browser download.
     *
     * @param string $filename  The filename of the download.
     * @param string $cType     The content-type description of the file.
     * @param boolean $inline   True if inline, false if attachment.
     * @param string $cLength   The content-length of this file.
     *
     * @since Horde 2.2
     */
    function downloadHeaders($filename = 'unknown', $cType = null,
                             $inline = false, $cLength = null)
    {
        /* Remove linebreaks from file names. */
        $filename = str_replace(array("\r\n", "\r", "\n"), ' ', $filename);

        /* Some browsers don't like spaces in the filename. */
        if ($this->hasQuirk('no_filename_spaces')) {
            $filename = strtr($filename, ' ', '_');
        }

        /* MSIE doesn't like multiple periods in the file name. Convert
           all periods (except the last one) to underscores. */
        if ($this->isBrowser('msie')) {
            if (($pos = strrpos($filename, '.'))) {
                $filename = strtr(substr($filename, 0, $pos), '.', '_') . substr($filename, $pos);
            }
        }

        /* Content-Type/Content-Disposition Header. */
        if ($inline) {
            if (!is_null($cType)) {
                header('Content-Type: ' . trim($cType));
            } elseif ($this->isBrowser('msie')) {
                header('Content-Type: application/x-msdownload');
            } else {
                header('Content-Type: application/octet-stream');
            }
            header('Content-Disposition: inline; filename="' . $filename . '"');
        } else {
            if ($this->isBrowser('msie')) {
                header('Content-Type: application/x-msdownload');
            } elseif (!is_null($cType)) {
                header('Content-Type: ' . trim($cType));
            } else {
                header('Content-Type: application/octet-stream');
            }

            if ($this->hasQuirk('break_disposition_header')) {
                header('Content-Disposition: filename="' . $filename . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            }
        }

        /* Content-Length Header. */
        if (!is_null($cLength)) {
            header('Content-Length: ' . $cLength);
        }

        /* Overwrite Pragma: and other caching headers for IE. */
        if ($this->hasQuirk('cache_ssl_downloads')) {
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
        }
    }

    /**
     * Determines if a browser can display a given MIME type.
     *
     * @param string $mimetype  The MIME type to check.
     *
     * @return boolean  True if the browser can display the MIME type.
     */
    function isViewable($mimetype)
    {
        $mimetype = String::lower($mimetype);
        list($type, $subtype) = explode('/', $mimetype);

        if (!empty($this->_accept)) {
            $wildcard_match = false;

            if (strpos($this->_accept, $mimetype) !== false) {
                return true;
            }

            if (strpos($this->_accept, '*/*') !== false) {
                $wildcard_match = true;
                if ($type != 'image') {
                    return true;
                }
            }

            /* image/jpeg and image/pjpeg *appear* to be the same
             * entity, but Mozilla doesn't seem to want to accept the
             * latter.  For our purposes, we will treat them the
             * same. */
            if ($this->isBrowser('mozilla') &&
                ($mimetype == 'image/pjpeg') &&
                (strpos($this->_accept, 'image/jpeg') !== false)) {
                return true;
            }

            if (!$wildcard_match) {
                return false;
            }
        }

        if (!$this->hasFeature('images') || ($type != 'image')) {
            return false;
        }

        return in_array($subtype, $this->_images);
    }

    /**
     * Escape characters in javascript code if the browser requires it.
     * %23, %26, and %2B (for some browsers) and %27 need to be escaped or
     * else javascript will interpret it as a single quote, pound sign, or
     * ampersand and refuse to work.
     *
     * @param string $code  The JS code to escape.
     *
     * @return string  The escaped code.
     */
    function escapeJSCode($code)
    {
        $from = $to = array();

        if ($this->isBrowser('msie') ||
            ($this->isBrowser('mozilla') && ($this->getMajor() >= 5)) ||
            $this->isBrowser('konqueror')) {
            $from = array('%23', '%26', '%2B');
            $to = array('%2523', '%2526', '%252B');
        }
        $from[] = '%27';
        $to[] = '\%27';

        return str_replace($from, $to, $code);
    }

    /**
     * Set the IE version in the session.
     *
     * @param string $ver  The IE Version string.
     */
    function setIEVersion($ver)
    {
        $_SESSION['__browser'] = array(
            'ie_version' => $ver
        );
    }

    /**
     * Return the IE version stored in the session, if available.
     *
     * @return mixed  The IE Version string or null if no string is stored.
     */
    function getIEVersion()
    {
        return isset($_SESSION['__browser']['ie_version']) ? $_SESSION['__browser']['ie_version'] : null;
    }

}
