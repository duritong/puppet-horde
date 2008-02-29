<?php

require_once 'Horde/iCalendar.php';

/**
 * Horde Kronolith free/busy driver for the Kolab IMAP Server.
 * Copyright 2004-2007 Horde Project (http://horde.org/)
 *
 * $Horde: kronolith/lib/Storage/kolab.php,v 1.4.10.7 2007/01/02 13:55:06 jan Exp $
 *
 * See the enclosed file COPYING for license information (GPL). If you did
 * not receive such a file, see also http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Stuart Binge <omicron@mighty.co.za>
 * @package Kronolith
 */
class Kronolith_Storage_kolab extends Kronolith_Storage {

    var $_params = array();

    function Kronolith_Storage_kolab($user, $params = array())
    {
        $this->_user = $user;
        $this->_params = $params;
    }

    function search($email, $private_only = false)
    {
        global $conf;

        $fb_url = sprintf('%s://%s:%d/freebusy/%s.vfb',
                          $conf['storage']['freebusy']['protocol'],
                          $conf['kolab']['imap']['server'],
                          $conf['storage']['freebusy']['port'],
                          $email);

        require_once 'HTTP/Request.php';
        $http = new HTTP_Request($fb_url,
                                 array('method' => 'GET',
                                       'timeout' => 5,
                                       'allowRedirects' => true));
        @$http->sendRequest();
        if ($http->getResponseCode() != 200) {
            return PEAR::raiseError(sprintf(_("Unable to retrieve free/busy information for %s"),
                                            $email), KRONOLITH_ERROR_FB_NOT_FOUND);
        }
        $vfb_text = $http->getResponseBody();

        $iCal = new Horde_iCalendar;
        $iCal->parsevCalendar($vfb_text);

        $vfb = &$iCal->findComponent('VFREEBUSY');
        if ($vfb === false) {
            return PEAR::raiseError(sprintf(_("No free/busy information is available for %s"),
                                    $email), KRONOLITH_ERROR_FB_NOT_FOUND);
        }

        return $vfb;
    }

    function store($email, $vfb, $public = false)
    {
        // We don't care about storing FB info at the moment; we rather let
        // Kolab's freebusy.php script auto-generate it for us.
        return true;
    }

}
