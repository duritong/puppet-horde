<?php

require_once 'Horde/MIME/Headers.php';
require_once IMP_BASE . '/lib/version.php';

/**
 * The description of the IMP program to use in the 'User-Agent:' header.
 */
define('IMP_AGENT_HEADER', 'Internet Messaging Program (IMP) ' . IMP_VERSION);

/**
 * The IMP_Headers:: class contains all functions related to handling
 * the headers of mail messages in IMP.
 *
 * $Horde: imp/lib/MIME/Headers.php,v 1.92.2.22 2007/01/02 13:54:59 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   IMP 4.0
 * @package IMP
 */
class IMP_Headers extends MIME_Headers {

    /**
     * The User-Agent string to use.
     *
     * @var string
     */
    var $_agent = IMP_AGENT_HEADER;

    /**
     * Returns a reference to a currently open IMAP stream.
     *
     * @see MIME_Headers::_getStream()
     */
    function &_getStream()
    {
        return $GLOBALS['imp']['stream'];
    }

    /**
     * Parse all of the available mailing list headers.
     */
    function parseAllListHeaders()
    {
        foreach ($this->listHeaders() as $val => $str) {
            $this->parseListHeaders($val);
        }
    }

    /**
     * Parse the information in the mailing list headers.
     *
     * @param string $header  The header to process.
     * @param boolean $raw    Should the raw URL be returned instead of setting
     *                        the header value?
     *
     * @return string  The header value (if $raw == true).
     */
    function parseListHeaders($header, $raw = false)
    {
        if (!($data = $this->getValue($header))) {
            return;
        }

        $output = '';

        require_once 'Horde/Text.php';

        /* Split the incoming data by the ',' character. */
        foreach (preg_split("/,/", $data) as $entry) {
            /* Get the data inside of the brackets. If there is no brackets,
               then return the raw text. */
            if (!preg_match("/\<([^\>]+)\>/", $entry, $matches)) {
                return trim($entry);
            }

            /* Remove all whitespace from between brackets (RFC 2369 [2]). */
            $match = preg_replace("/\s+/", '', $matches[1]);

            /* Determine if there is any comments. */
            preg_match("/(\(.+\))/", $entry, $comments);

            /* RFC 2369 [2] states that we should only show the *FIRST* URL
               that appears in a header that we can adequately handle. */
            if (stristr($match, 'mailto:') !== false) {
                $match = substr($match, strpos($match, ':') + 1);
                if ($raw) {
                    return $match;
                }
                $output = Horde::link(IMP::composeLink($match)) . $match . '</a>';
                if (!empty($comments[1])) {
                    $output .= '&nbsp;' . $comments[1];
                }
                break;
            } else {
                require_once 'Horde/Text/Filter.php';
                if ($url = Text_Filter::filter($match, 'linkurls', array('callback' => 'Horde::externalUrl'))) {
                    if ($raw) {
                        return $match;
                    }
                    $output = $url;
                    if (!empty($comments[1])) {
                        $output .= '&nbsp;' . $comments[1];
                    }
                    break;
                } else {
                    /* Use this entry unless we can find a better one. */
                    $output = $match;
                }
            }
        }

        $this->setValue($header, $output);
    }

    /**
     * Add any site-specific headers defined in config/header.php to
     * the internal header array.
     */
    function addSiteHeaders()
    {
        /* Add the 'User-Agent' header. */
        $this->addAgentHeader();

        /* Tack on any site-specific headers. */
        if (!empty($GLOBALS['conf']['msg']['prepend_header']) &&
            @is_readable(IMP_BASE . '/config/header.php')) {
            require_once IMP_BASE . '/config/header.php';
            foreach ($_header as $key => $val) {
                $this->addHeader(trim($key), trim($val));
            }
        }
    }

    /**
     * Builds a string containing a list of addresses.
     *
     * @param string $field    The address field to parse.
     * @param integer $addURL  The self URL.
     * @param boolean $set     Set the associated header with the return
     *                         string?
     * @param boolean $link    Link each address to the compose screen?
     *
     * @return string  String containing the formatted address list.
     */
    function buildAddressLinks($field, $addURL, $set = false, $link = true)
    {
        global $prefs, $registry;

        $add_link = null;

        /* Make sure this is a valid object address field. */
        $array = $this->myGetOb($field);
        if (empty($array) || !is_array($array)) {
            return null;
        }

        /* Set up the add address icon link if contact manager is
           available. */
        if ($link &&
            $registry->hasMethod('contacts/import') &&
            $prefs->getValue('add_source')) {
            $add_link = Util::addParameter($addURL, 'actionID', 'add_address');
        }

        $addr_array = array();

        foreach ($this->getAddressesFromObject($array) as $ob) {
            if (!empty($ob->address) && !empty($ob->inner)) {
                $ret = '';

                /* If this is an incomplete e-mail address, don't link
                   to anything. */
                if (stristr($ob->host, 'UNKNOWN') !== false) {
                    $ret = $ob->address;
                } else {
                    $ret = htmlspecialchars(str_replace('\"', '"', $ob->address));
                    if ($link) {
                        $ret = Horde::link(IMP::composeLink(array('to' => addslashes($ob->address))), sprintf(_("New Message to %s"), $ob->inner)) . $ret . '</a>';
                    }

                    /* Append the add address icon to every address if
                       contact manager is available. */
                    if ($add_link) {
                        $curr_link = Util::addParameter($add_link, array('name' => $ob->personal,
                                                                         'address' => $ob->inner));
                        $ret .= Horde::link($curr_link, sprintf(_("Add %s to my Address Book"), $ob->inner)) .
                            Horde::img('addressbook_add.png', sprintf(_("Add %s to my Address Book"), $ob->inner)) . '</a>';
                    }
                }

                $addr_array[] = $ret;
            }
        }

        /* If left with an empty address list ($ret), inform the user that
           the recipient list is purposely "undisclosed". */
        if (empty($addr_array)) {
            $ret = _("Undisclosed Recipients");
        } else {
            /* Build the address line. */
            if (count($addr_array) > 20) {
                Horde::addScriptFile('hideable.js', 'horde', true);
                Horde::addScriptFile('addressesBlocks.js', 'imp');
            
                $ret = '<div id="at_' . $field . '">' .
                    Horde::link('#', '', 'widget', '', 'toggleAddressesBlock(\'' . $field . '\', \'' . count($addr_array) . '\'); return false;', '', '') .
                    sprintf(_("[Show addresses - %s recipients]"), count($addr_array)) . '</a></div>' .
                    '<div id="ab_' . $field . '" style="display:none;"><span class="nowrap">' . implode(',</span> <span class="nowrap">', $addr_array) . '</span></div>';
            } else {
                $ret = '<span class="nowrap">' . implode(',</span> <span class="nowrap">', $addr_array) . '</span>';
            }
        }

        /* Set the header value, if requested. */
        if (!empty($set)) {
            $this->setValue($field, $ret);
        }

        return $ret;
    }

    /**
     * Add the local time string to the date header.
     *
     * @param string $date  The date string.
     *
     * @return string  The date string with the local time added on.
     */
    function addLocalTime($date)
    {
        if (empty($date)) {
            $ltime = false;
        } else {
            $date = preg_replace('/\s+\(\w+\)$/', '', $date);
            $ltime = strtotime($date);
        }
        if ($ltime !== false && $ltime !== -1) {
            $date_str = strftime($GLOBALS['prefs']->getValue('date_format'), $ltime);
            $time_str = strftime($GLOBALS['prefs']->getValue('time_format'), $ltime);
            $tz = strftime('%Z');
            if ((date('Y') != @date('Y', $ltime)) ||
                (date('M') != @date('M', $ltime)) ||
                (date('d') != @date('d', $ltime))) {
                /* Not today, use the date. */
                $date .= sprintf(' <small>[%s %s %s]</small>', $date_str, $time_str, $tz);
            } else {
                /* Else, it's today, use the time only. */
                $date .= sprintf(' <small>[%s %s]</small>', $time_str, $tz);
            }
        }

        return $date;
    }

    /**
     * Get a header from the header object.
     *
     * @since IMP 4.1
     *
     * @param string $field  The header to return as an object.
     *
     * @return mixed  The field requested.
     */
    function myGetOb($field)
    {
        if (!isset($this->_obCache[$field])) {
            $value = $this->getValue($field);
            $this->_obCache[$field] = IMP::parseAddressList($value);
        }
        return $this->_obCache[$field];
    }

}
