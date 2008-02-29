<?php

require_once 'Horde/MIME/Headers.php';
require_once MIMP_BASE . '/lib/version.php';

/**
 * The description of the MIMP program to use in the 'User-Agent:' header.
 */
define('MIMP_AGENT_HEADER', 'Mobile Internet Messaging Program (MIMP) ' . MIMP_VERSION);

/**
 * The MIMP_Headers:: class contains all functions related to handling
 * the headers of mail messages in MIMP.
 *
 * $Horde: mimp/lib/MIME/Headers.php,v 1.14.2.1 2007/01/02 13:55:10 jan Exp $
 *
 * Copyright 2002-2007 Michael Slusarz <slusarz@curecanti.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@curecanti.org>
 * @package MIMP
 */
class MIMP_Headers extends MIME_Headers {

    /**
     * The User-Agent string to use.
     *
     * @var string
     */
    var $_agent = MIMP_AGENT_HEADER;

    /**
     * Returns a reference to a currently open IMAP stream.
     *
     * @see MIME_Headers::_getStream()
     */
    function &_getStream()
    {
        return $_SESSION['mimp']['stream'];
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
                $output = Horde::link(MIMP::composeLink($match)) . $match . '</a>';
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
     * Add any site-specific headers defined in config/header.txt to
     * the internal header array.
     */
    function addSiteHeaders()
    {
        static $_header;

        /* Add the 'User-Agent' header. */
        $this->addAgentHeader();

        /* Tack on any site-specific headers. */
        if (!empty($GLOBALS['conf']['msg']['prepend_header']) &&
            is_readable(MIMP_BASE . '/config/header.php')) {
            require_once MIMP_BASE . '/config/header.php';
            foreach ($_header as $key => $val) {
                $this->addHeader(trim($key), trim($val));
            }
        }
    }

}
