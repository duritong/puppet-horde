<?php

require_once 'Horde/iCalendar.php';

/**
 * Sync4j (www.sync4j.org)
 *
 * The Sync4J outlook converter uses its native SIF format for data
 * exchange. Conversion to text/vcalendar etc. is done by
 * SifConverter.php The connector seems not support DevInf
 * information, so SyncML_Device can only detect it by the decice ID:
 * so in the connector configuration the device ID must be set to
 * 'sc-pim-<type>' which should be the default anyhow.
 *
 * Copyright 2005-2007 Karsten Fourmont <karsten@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * $Horde: framework/SyncML/SyncML/Device/Sync4j.php,v 1.8.2.5 2007/01/02 13:54:42 jan Exp $
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @package SyncML
 */
class SyncML_Device_sync4j extends SyncML_Device {

    /**
     * Convert the content.
     *
     * Currently strips uid (primary key) information as client and
     * server might use different ones.
     *
     * Charset conversions might be added here too.
     */
    function convertClient2Server($content, $contentType)
    {
        list($content, $contentType) =
            parent::convertClient2Server($content, $contentType);

        switch ($contentType) {
        case 'sif/note' :
        case 'text/x-s4j-sifn' :
            $content = $this->sif2vnote(base64_decode($content));
            $contentType = 'text/x-vnote';
            break;

        case 'sif/contact' :
        case 'text/x-s4j-sifc' :
            $content = $this->sif2vcard(base64_decode($content));
            $contentType = 'text/x-vcard';
            break;

        case 'sif/calendar' :
        case 'text/x-s4j-sife' :
            $content = $this->sif2vevent(base64_decode($content));
            $contentType = 'text/x-vevent';
            break;

        case 'sif/task' :
        case 'text/x-s4j-sift' :
            $content = $this->sif2vtodo(base64_decode($content));
            $contentType = 'text/x-vtodo';
            break;
        }

        if (DEBUGLOG_ICALENDARDATA) {
            $fp = @fopen('/tmp/sync/log.txt', 'a');
            if ($fp) {
                @fwrite($fp, "\ninput converted for server: $contentType\n");
                @fwrite($fp,$content . "\n");
                @fclose($fp);
            }
        }

        return array($content, $contentType);
    }

    /**
     * Converts the content from the backend to a format suitable for the
     * client device.
     *
     * Strips the uid (primary key) information as client and server might use
     * different ones.
     *
     * @param string $content       The content to convert
     * @param string $contentType   The contentType of content as returned from
     *                              the backend
     * @return array                array($newcontent, $newcontentType):
     *                              the converted content and the
     *                              (possibly changed) new ContentType.
     */
    function convertServer2Client($content, $contentType)
    {
        global $backend;

        list($content, $contentType) =
            parent::convertServer2Client($content, $contentType);

        switch ($contentType) {
        case 'text/calendar' :
        case 'text/x-icalendar' :
        case 'text/x-vcalendar' :
        case 'text/x-vevent' :
            $content = $this->vevent2sif($content);
            $content = base64_encode($content);
            $contentType = 'sif/calendar';
            break;

        case 'text/x-vtodo' :
            $content = $this->vtodo2sif($content);
            $content = base64_encode($content);
            $contentType = 'sif/task';
            break;

        case 'text/x-vcard' :
            $content = $this->vcard2sif($content);
            $content = base64_encode($content);
            $contentType = 'sif/contact';
            break;

        case 'text/x-vnote':
            $content = $this->vnote2sif($content);
            $content = base64_encode($content);
            $contentType = 'sif/note';
            break;
        }

        if (DEBUGLOG_ICALENDARDATA) {
            $fp = @fopen('/tmp/sync/log.txt', 'a');
            if ($fp) {
                @fwrite($fp, serialize($contentType));
                @fwrite($fp, "\nconverted for sync4j client: $contentType\n");
                @fwrite($fp, base64_decode($content) . "\n");
                @fclose($fp);
            }
        }

        return array($content, $contentType);
    }

    /**
     * Decodes a sif xml string to an associative array.
     *
     * Quick hack to convert from text/vcard and text/vcalendar to
     * Sync4J's proprietery sif datatypes and vice versa.  For details
     * about the sif format see the appendix of the developer guide on
     * www.sync4j.org.
     *
     * @access private
     *
     * @param string $sif  A sif string like <k1>v1&gt;</k1><k2>v2</k2>
     *
     * @return array  Assoc array in utf8 like array ('k1' => 'v1>', 'k2' => 'v2');
     */
    function sif2array($sif)
    {
        $r = array();
        if (preg_match_all('/<([^>]*)>([^<]*)<\/\1>/si', $sif, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $r[$match[1]] = html_entity_decode($match[2]);
            }
        }
        return $r;
    }

    /**
     * Encodes an assoc. array to sif xml
     *
     * Quick hack to convert from text/vcard and text/vcalendar to
     * Sync4J's proprietery sif datatypes and vice versa.  For details
     * about the sif format see the appendix of the developer guide on
     * www.sync4j.org.
     *
     * @access private
     *
     * @param array $array  An assoc array.
     *
     * @return string  The resulting XML string.
     */
    function array2sif($array, $pre='', $post='')
    {
        $search = array('<', '>');
        $replace = array('&lt;', '&gt;');

        $r = $pre;
        foreach ($array as $key => $value) {
            $r .= '<' . $key . '>' .
                str_replace($search, $replace, $value) .
                '</' . $key . '>';
        }

        return $r . $post;
    }

    function sif2vnote($sif)
    {
        $a = $this->sif2array($sif);

        $iCal = &new Horde_iCalendar();
        $iCal->setAttribute('VERSION', '1.1');
        $iCal->setAttribute('PRODID', '-//The Horde Project//Mnemo //EN');
        $iCal->setAttribute('METHOD', 'PUBLISH');

        $vnote = &Horde_iCalendar::newComponent('vnote', $iCal);
        $vnote->setAttribute('BODY', isset($a['Body']) ? $a['Body'] : '');

        return $vnote->exportvCalendar();
    }

    function sif2vcard($sif)
    {
        $a = $this->sif2array($sif);

        $iCal = &new Horde_iCalendar();
        $iCal->setAttribute('VERSION', '2.1');
        $iCal->setAttribute('PRODID', '-//The Horde Project//Mnemo //EN');
        $iCal->setAttribute('METHOD', 'PUBLISH');

        $vcard = &Horde_iCalendar::newComponent('vcard', $iCal);

        $vcard->setAttribute('FN', $a['FileAs']);
        $vcard->setAttribute('NICKNAME', $a['NickName']);
        $vcard->setAttribute('TEL', $a['HomeTelephoneNumber'],
                             array('TYPE'=>'HOME'));
        $vcard->setAttribute('TEL', $a['BusinessTelephoneNumber'],
                             array('TYPE'=>'WORK'));
        $vcard->setAttribute('TEL', $a['MobileTelephoneNumber'],
                             array('TYPE'=>'CELL'));
        $vcard->setAttribute('TEL', $a['BusinessFaxNumber'],
                             array('TYPE'=>'FAX'));
        $vcard->setAttribute('EMAIL', $a['Email1Address']);
        $vcard->setAttribute('TITLE', $a['JobTitle']);
        $vcard->setAttribute('ORG', $a['CompanyName']);
        $vcard->setAttribute('NOTE', $a['Body']);
        $vcard->setAttribute('URL', $a['WebPage']);

        $v = array(
            VCARD_N_FAMILY      => $a['LastName'],
            VCARD_N_GIVEN       => $a['FirstName'],
            VCARD_N_ADDL        => $a['MiddleName'],
            VCARD_N_PREFIX      => $a['Title'],
            VCARD_N_SUFFIX      => $a['Suffix']
        );
        $vcard->setAttribute('N', implode(';', $v), array(), false, $v);

        $v = array(
            VCARD_ADR_POB           => $a['HomeAddressPostOfficeBox'],
            VCARD_ADR_EXTEND        => '',
            VCARD_ADR_STREET        => $a['HomeAddressStreet'],
            VCARD_ADR_LOCALITY      => $a['HomeAddressCity'],
            VCARD_ADR_REGION        => $a['HomeAddressState'],
            VCARD_ADR_POSTCODE      => $a['HomeAddressPostalCode'],
            VCARD_ADR_COUNTRY       => $a['HomeAddressCountry'],
        );
        $vcard->setAttribute('ADR', implode(';', $v), array('TYPE' => 'HOME' ), true, $v);

        $v = array(
            VCARD_ADR_POB           => $a['BusinessAddressPostOfficeBox'],
            VCARD_ADR_EXTEND        => '',
            VCARD_ADR_STREET        => $a['BusinessAddressStreet'],
            VCARD_ADR_LOCALITY      => $a['BusinessAddressCity'],
            VCARD_ADR_REGION        => $a['BusinessAddressState'],
            VCARD_ADR_POSTCODE      => $a['BusinessAddressPostalCode'],
            VCARD_ADR_COUNTRY       => $a['BusinessAddressCountry'],
        );

        $vcard->setAttribute('ADR', implode(';', $v), array('TYPE' => 'WORK' ), true, $v);

        return $vcard->exportvCalendar();
    }

    function sif2vevent($sif)
    {
        $a = $this->sif2array($sif);

        $iCal = &new Horde_iCalendar();
        $iCal->setAttribute('VERSION', '1.0');
        $iCal->setAttribute('PRODID', '-//The Horde Project//Mnemo //EN');
        $iCal->setAttribute('METHOD', 'PUBLISH');

        $vEvent = &Horde_iCalendar::newComponent('vevent', $iCal);

        if ($a['AllDayEvent'] == 'True') {
            $t = $iCal->_parseDateTime($a['Start']);
            $vEvent->setAttribute('DTSTART',
                                  array('year' => date('Y', $t),
                                        'month' => date('m', $t),
                                        'mday' => date('d',$t)),
                                  array('VALUE' => 'DATE'));
            $t = $iCal->_parseDateTime($a['End']);
            $vEvent->setAttribute('DTEND',
                                  array('year' => date('Y', $t),
                                        'month' => date('m', $t),
                                        'mday' => date('d',$t)),
                                  array('VALUE' => 'DATE'));
        } else {
            $vEvent->setAttribute('DTSTART',
                                  $iCal->_parseDateTime($a['Start']));
            $vEvent->setAttribute('DTEND',
                                  $iCal->_parseDateTime($a['End']));
        }

        $vEvent->setAttribute('DTSTAMP', time());
        $vEvent->setAttribute('SUMMARY', $a['Subject']);
        $vEvent->setAttribute('DESCRIPTION', $a['Body']);
        // $vEvent->setAttribute('CATEGORIES', $a['']);
        $vEvent->setAttribute('LOCATION', $a['Location']);

        return $vEvent->exportvCalendar();
    }

    function sif2vtodo($sif)
    {
        $a = $this->sif2array($sif);

        $iCal = &new Horde_iCalendar();
        $iCal->setAttribute('VERSION', '1.0');
        $iCal->setAttribute('PRODID', '-//The Horde Project//Mnemo //EN');
        $iCal->setAttribute('METHOD', 'PUBLISH');

        $vtodo = &Horde_iCalendar::newComponent('vtodo', $iCal);

        $vtodo->setAttribute('SUMMARY', $a['Subject']);
        $vtodo->setAttribute('DESCRIPTION', $a['Body']);
        if ($a['Importance'] == 0) {
            $vtodo->setAttribute('PRIORITY', 5);
        } elseif ($a['Importance'] == 2) {
            $vtodo->setAttribute('PRIORITY', 1);
        } else {
            $vtodo->setAttribute('PRIORITY', 3);
        }
        if ($a['DueDate'] != '45001231T230000Z') {
            $vtodo->setAttribute('DUE', $iCal->_parseDateTime($a['DueDate']));
        }
        $vtodo->setAttribute('COMPLETED', $a['Complete'] == 'True' ? 1 : 0);

        return $vtodo->exportvCalendar();
    }

    function vnote2sif($vnote)
    {
        $iCal = &new Horde_iCalendar();
        if (!$iCal->parsevCalendar($vnote)) {
            die("There was an error importing the data.");
        }

        $components = $iCal->getComponents();

        switch (count($components)) {
        case 0:
            die("No data was found.");

        case 1:
            $content = $components[0];
            break;

        default:
            die("Multiple components found; only one is supported.");
        }

        $a = array('Body' => $content->getAttribute('BODY'));

        return $this->array2sif(
            $a,
            '<?xml version="1.0"?><note>',
            '<Categories></Categories>'
            . '<Subject></Subject><Color></Color><Height></Height><Width></Width><Left></Left><Top></Top>'
            . '</note>');
    }

    function vcard2sif($vcard)
    {
        $iCal = &new Horde_iCalendar();
        if (!$iCal->parsevCalendar($vcard)) {
            die("There was an error importing the data.");
        }

        $components = $iCal->getComponents();

        switch (count($components)) {
        case 0:
            die("No data was found.");

        case 1:
            $content = $components[0];
            break;

        default:
            die("Multiple components found; only one is supported.");
        }

        // from here on, the code is taken from
        // Turba_Driver::toHash, v 1.65 2005/03/12
        // and modified for the Sync4J attribute names.
        $attr = $content->getAllAttributes();
        foreach ($attr as $item) {
            switch ($item['name']) {
            case 'FN':
                $hash['FileAs'] = $item['value'];
                break;

            case 'N':
                $name = $item['values'];
                $hash['LastName'] = $name[VCARD_N_FAMILY];
                $hash['FirstName'] = $name[VCARD_N_GIVEN];
                $hash['MiddleName'] = $name[VCARD_N_ADDL];
                $hash['Title'] = $name[VCARD_N_PREFIX];
                $hash['Suffix'] = $name[VCARD_N_SUFFIX];
                break;

            case 'NICKNAME':
                $hash['NickName'] = $item['value'];
                break;

            // For vCard 3.0.
            case 'ADR':
                if (isset($item['params']['TYPE'])) {
                    if (!is_array($item['params']['TYPE'])) {
                        $item['params']['TYPE'] = array($item['params']['TYPE']);
                    }
                } else {
                    $item['params']['TYPE'] = array();
                    if (isset($item['params']['WORK'])) {
                        $item['params']['TYPE'][] = 'WORK';
                    }
                    if (isset($item['params']['HOME'])) {
                        $item['params']['TYPE'][] = 'HOME';
                    }
                }

                $address = $item['values'];
                foreach ($item['params']['TYPE'] as $adr) {
                    switch (String::upper($adr)) {
                    case 'HOME':
                        $prefix = 'HomeAddress';
                        break;

                    case 'WORK':
                        $prefix = 'WorkAddress';
                        break;

                    default:
                        $prefix = 'HomeAddress';
                    }

                    if ($prefix) {
                        $hash[$prefix . 'Street'] = isset($address[VCARD_ADR_STREET]) ? $address[VCARD_ADR_STREET] : null;
                        $hash[$prefix . 'City'] = isset($address[VCARD_ADR_LOCALITY]) ? $address[VCARD_ADR_LOCALITY] : null;
                        $hash[$prefix . 'State'] = isset($address[VCARD_ADR_REGION]) ? $address[VCARD_ADR_REGION] : null;
                        $hash[$prefix . 'PostalCode'] = isset($address[VCARD_ADR_POSTCODE]) ? $address[VCARD_ADR_POSTCODE] : null;
                        $hash[$prefix . 'Country'] = isset($address[VCARD_ADR_COUNTRY]) ? $address[VCARD_ADR_COUNTRY] : null;
                        $hash[$prefix . 'PostOfficeBox'] = isset($address[VCARD_ADR_POB]) ? $address[VCARD_ADR_POB] : null;
                    }
                }
                break;

            case 'TEL':
                if (isset($item['params']['FAX'])) {
                    $hash['BusinessFaxNumber'] = $item['value'];
                } elseif (isset($item['params']['TYPE'])) {
                    if (!is_array($item['params']['TYPE'])) {
                        $item['params']['TYPE'] = array($item['params']['TYPE']);
                    }
                    // For vCard 3.0.
                    foreach ($item['params']['TYPE'] as $tel) {
                        if (String::upper($tel) == 'WORK') {
                            $hash['BusinessTelephoneNumber'] = $item['value'];
                        } elseif (String::upper($tel) == 'HOME') {
                            $hash['HomeTelephoneNumber'] = $item['value'];
                        } elseif (String::upper($tel) == 'CELL') {
                            $hash['MobileTelephoneNumber'] = $item['value'];
                        } elseif (String::upper($tel) == 'FAX') {
                            $hash['BusinessFaxNumber'] = $item['value'];
                        }
                    }
                } else {
                    if (isset($item['params']['HOME'])) {
                        $hash['HomeTelephoneNumber'] = $item['value'];
                    } elseif (isset($item['params']['WORK'])) {
                        $hash['BusinessTelephoneNumber'] = $item['value'];
                    } elseif (isset($item['params']['CELL'])) {
                        $hash['MobileTelephoneNumber'] = $item['value'];
                    } else {
                        $hash['HomeTelephoneNumber'] = $item['value'];
                    }
                }
                break;

            case 'EMAIL':
                if (isset($item['params']['PREF']) || !isset($hash['email'])) {
                    $hash['Email1Address'] = Horde_iCalendar_vcard::getBareEmail($item['value']);
                    $hash['Email1AddressType'] = 'SMTP';
                }
                break;

            case 'TITLE':
                $hash['JobTitle'] = $item['value'];
                break;

            case 'ORG':
                $hash['CompanyName'] = $item['value'];
                break;

            case 'NOTE':
                $hash['Body'] = $item['value'];
                break;

            case 'URL':
                $hash['WebPage'] = $item['value'];
                break;
            }
        }

        return $this->array2sif(
            $hash,
            '<?xml version="1.0"?><contact>',
            '</contact>');
    }

    function vevent2sif($vcard)
    {
        $iCal = &new Horde_iCalendar();
        if (!$iCal->parsevCalendar($vcard)) {
            die("There was an error importing the data.");
        }

        $components = $iCal->getComponents();

        switch (count($components)) {
        case 0:
            die("No data was found.");

        case 1:
            $content = $components[0];
            break;

        default:
            die("Multiple components found; only one is supported.");
        }

        // Is there a real need to provide the correct value?
        $duration = 0;

        $attr = $content->getAllAttributes();
        foreach ($attr as $item) {
            switch ($item['name']) {
            case 'DTSTART':
                if (!empty($item['params']['VALUE']) && $item['params']['VALUE'] == "DATE") {
                    $hash['AllDayEvent'] = "True";
                    $hash['Start'] = Horde_iCalendar::_exportDateTime($item['value']);
                    $duration = 1440;
                } else {
                    $hash['AllDayEvent'] = "False";
                    $hash['Start'] = Horde_iCalendar::_exportDateTime($item['value']);
                }
                break;

            case 'DTEND':
                if (!empty($item['params']['VALUE']) && $item['params']['VALUE'] == "DATE") {
                    $hash['AllDayEvent'] = "True";
                    $hash['End'] = Horde_iCalendar::_exportDateTime($item['value']);
                    $duration = 1440;
                } else {
                    $hash['AllDayEvent'] = "False";
                    $hash['End'] = Horde_iCalendar::_exportDateTime($item['value']);
                }
                break;

            case 'SUMMARY':
                $hash['Subject'] = $item['value'];
                break;

            case 'DESCRIPTION':
                $hash['Body'] = $item['value'];
                break;

            case 'LOCATION':
                $hash['Location'] = $item['value'];
                break;
            }
        }

        return $this->array2sif(
            $hash,
            '<?xml version="1.0"?><appointment><Duration>'
            . $duration . '</Duration>',
            '<IsRecurring>False</IsRecurring><MeetingStatus>0</MeetingStatus><BusyStatus>2</BusyStatus></appointment>');
    }

    function vtodo2sif($vcard)
    {
        $iCal = &new Horde_iCalendar();
        if (!$iCal->parsevCalendar($vcard)) {
            return PEAR::raiseError('There was an error importing the data.');
        }

        $components = $iCal->getComponents();

        switch (count($components)) {
        case 0:
            return PEAR::raiseError('No data was found');

        case 1:
            $content = $components[0];
            break;

        default:
            return PEAR::raiseError('Multiple components found; only one is supported.');
        }

        $hash['Complete'] = 'False';
        // Outlook's default for no due date
        $hash['DueDate'] = '45001231T230000Z';

        $attr = $content->getAllAttributes();
        foreach ($attr as $item) {
            switch ($item['name']) {
            case 'DUE':
                $hash['DueDate'] = Horde_iCalendar::_exportDateTime($item['value']);
                break;

            case 'SUMMARY':
                $hash['Subject'] = $item['value'];
                break;

            case 'DESCRIPTION':
                $hash['Body'] = $item['value'];
                break;

            case 'PRIORITY':
                if ($item['value'] == 1) {
                    $hash['Importance'] = 2;
                } elseif ($item['value'] == 5) {
                    $hash['Importance'] = 0;
                } else {
                    $hash['Importance'] = 1;
                }
                break;

            case 'COMPLETED':
                $hash['Complete'] = $item['value'] ? 'True' : 'False';
                break;
            }
        }

        return $this->array2sif(
            $hash,
            '<?xml version="1.0"?><task>',
            '</task>');
    }

}
