<?php


/**
 * The SyncML_Device:: class provides functionality that is
 * potentially (client) device dependant.
 *
 * So if a sync client needs any kind of special data of the data sent
 * to it or received from it, this is done here. There are two source
 * of information to identify an device: The first (and better) one is
 * the DevInf device info sent by the device upon a get request. If
 * DevInf is not supported or sent by the client, the SourceURI of the
 * device may be sufficent to identify it.
 *
 * Information about a few devices already working with SyncML::
 *
 * P800/P900/P910:
 * ---------------
 * Charset:
 * This device is able to handle UTF-8 and sends its XML packages in UTF8.
 * However even though the XML itself is UTF-8, it expects the enclosed
 * vcard-data to be ISO-8859-1 unless explicitly stated otherwise (using the
 * CHARSET option, which is deprecated for VCARD 3.0)
 *
 * Encoding:
 * String values are encoded "QUOTED-PRINTABLE"
 *
 * Other:
 * This devices handles tasks and events in one database.
 *
 * Copyright 2005-2007 Karsten Fourmont <karsten@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * $Horde: framework/SyncML/SyncML/Device.php,v 1.7.2.9 2007/01/02 13:54:41 jan Exp $
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @package SyncML
 */
class SyncML_Device {

    function &factory()
    {
        $si = SyncML_Device::sourceURI();
        $di = SyncML_Device::deviceInfo();

        if (stristr($si, 'sync4j') !== false || stristr($si, 'sc-pim') !== false) {
            include_once 'SyncML/Device/Sync4j.php';
            $device = &new SyncML_Device_sync4j();
        } elseif (!empty($di->_Man) && stristr($di->_Man, 'Sony Ericsson') !== false) {
            include_once 'SyncML/Device/P800.php';
            $device = &new SyncML_Device_P800();
        } elseif (!empty($di->_Man) && stristr($di->_Man, 'synthesis') !== false) {
            include_once 'SyncML/Device/Synthesis.php';
            $device = &new SyncML_Device_Synthesis();
        } elseif (!empty($di->_Man) && stristr($di->_Man, 'nokia') !== false) {
            include_once 'SyncML/Device/Nokia.php';
            $device = &new SyncML_Device_Nokia();
        } else {
            $device = &new SyncML_Device();
        }

        $GLOBALS['backend']->logMessage('Using device class ' . get_class($device),
                                        __FILE__, __LINE__, PEAR_LOG_DEBUG);

        return $device;
    }

    function &singleton()
    {
        static $instance;

        if (!isset($instance)) {
            $instance = SyncML_Device::factory();
        }

        return $instance;
    }

    /**
     * Returns the SourceURI from state.
     */
    function sourceURI()
    {
        return $_SESSION['SyncML.state']->getSourceURI();
    }

    /**
     * Returns the DevInf class from state.
     */
    function deviceInfo()
    {
        return $_SESSION['SyncML.state']->getDeviceInfo();
    }

    /**
     * When a client sends data during a sync but does not provide
     * information about the contenttype with this individual item,
     * this function returns the contenttype the item is supposed to be in.
     *
     * As this is only used to parse to the horde's importdata api functions,
     * some simple guesses for the contenttype are completely sufficient:
     * Horde does not care whether data is text/x-vcalendar or text/calendar.
     */
    function getPreferredContentType($type)
    {
        if ($type == 'contacts') {
            return 'text/x-vcard';
        } elseif ($type == 'notes') {
            return 'text/x-vnote';
        } elseif ($type == 'tasks') {
            return 'text/calendar';
        } elseif ($type == 'calendar') {
            return 'text/calendar';
        }
    }

    /**
     * Returns the preferrred contenttype of the client for the given
     * sync data type (contacts/tasks/notes/calendar).
     *
     * The result is passed as an option to the Horde API export functions.
     * Please note that this is not the contentType ultimately passed to the
     * client but rather the contentType presented to the Horde API export
     * functions. For example the contact api supports a contenttype 
     * text/x-vcard;version=2.1 to create a vcard in vcard 2.1 format.
     *
     * After the data is retrieved from horde, convertServer2Client can do
     * some post-processing and set the correct contentType acceptable for
     * the client if necessary.
     *
     * The default implementation tries to extract the contenttype from the
     * presented device info. If this does not work, some default are used.
     *
     * If the client does not provice proper DevInf data, this function may
     * have to be overwritten to return the correct values.
     *
     * ServerSyncURI: The server database of the sync:
     *                contacts|notes|calendar|tasks
     * sourceSyncURI: The URI for the client database. This is needed as
     *                the DevInf is arranged by sourceSyncURIs
     */
     
    function getPreferredContentTypeClient($serverSyncURI, $sourceSyncURI)
    {
        $di = $this->deviceInfo();
        $ds = $di->getDataStore($sourceSyncURI);
        if (!empty($ds)) {
            $r = $ds->getPreferredRXContentType();
            if (!empty($ds)) {
                if ($r == 'text/x-vcard' &&
                        $ds->getPreferredRXContentTypeVersion() == '2.1') {
                    /* Special custom mimetype which makes the horde turba API
                     * return a version 2.1 rather than 3.0 vcard.
                     */
                    return 'text/x-vcard;version=2.1';
                }
                return $r;
            }
        }

        /* No Info in DevInf. Use defaults: */
        if ($serverSyncURI == 'contacts') {
            return 'text/x-vcard';
        } elseif ($serverSyncURI == 'notes') {
            return 'text/x-vnote';
        } elseif ($serverSyncURI == 'tasks') {
            return 'text/x-vtodo';
        } elseif ($serverSyncURI == 'calendar') {
            return 'text/calendar';
        }
    }

    /**
     * Convert the content received from the client for the horde backend.
     *
     * Currently strips uid (primary key) information as client and
     * server might use different ones.
     *
     * Charset conversions might be added here too.
     *
     * @param string $content       The content to convert
     * @param string $contentType   The contentType of the content
     *
     * @return array                array($newcontent, $newcontentType):
     *                              the converted content and the
     *                              (possibly changed) new ContentType.
     */
    function convertClient2Server($content, $contentType)
    {
        global $backend;
        if (DEBUGLOG_ICALENDARDATA) {
            $fp = @fopen('/tmp/sync/log.txt', 'a');
            if ($fp) {
                @fwrite($fp, "\ninput received from client ($contentType)\n");
                if (strstr($contentType,'sif/') !== false) {
                    // sync4fj sif/* data is base64_encoded.
                    @fwrite($fp, base64_decode($content) . "\n");
                } else {
                    @fwrite($fp, $content . "\n");
                }
                @fclose($fp);
            }
        }

        // Always remove client UID. UID will be seperately passed in
        // XML.
        $content = preg_replace('/(\r\n|\r|\n)UID:.*?(\r\n|\r|\n)/', '\1', $content, 1);

        if ($this->needsCategoryMapping() &&
            preg_match('/(\r\n|\r|\n)CATEGORIES[^\:]*:(.*?)(\r\n|\r|\n)/',
                       $content, $m)) {
            $cats = explode(',', $m[2]);
            if( preg_match('/(\r\n|\r|\n)SUMMARY[^\:]*:(.*?)(\r\n|\r|\n)/',
                       $content, $m)) {
                $summary = $m[2];
            } else {
                $summary = 'unknown';
            }


            foreach ($cats as $cat) {
                $results[] = $backend->mapClientCategory2Server($cat, $summary);
            }
            $content = preg_replace('/(\r\n|\r|\nCATEGORIES[^\:]*:)(.*?)(\r\n|\r|\n)/',
                                    '$1' . implode(',',$results) . '$3', $content);

        }
        // Ensure valid newline termination.
        if (substr($content, -1) != "\n" && substr($content, -1) != "\r") {
            $content .= "\r\n";
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
     * Charset conversions might be added here too.
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
        if (is_array($contentType)) {
            $contentType = $contentType['ContentType'];
        }

        if (DEBUGLOG_ICALENDARDATA) {
            $fp = @fopen('/tmp/sync/log.txt', 'a');
            if ($fp) {
                @fwrite($fp, "\noutput received from horde backend " .
                        "($contentType):\n");
                @fwrite($fp,$content . "\n");
                @fclose($fp);
            }
        }

        /* Remove special version=2.1 indicator used for horde api. */
        if ($contentType == 'text/x-vcard;version=2.1') {
            $contentType = 'text/x-vcard';
        }

        /* Always remove server UID. UID will be seperately passed in XML. */
        $content = preg_replace('/(\r\n|\r|\n)UID:.*?(\r\n|\r|\n)/', '\1', $content, 1);

        $di = $this->deviceInfo();

        if ($this->needsCategoryMapping() &&
            preg_match('/(\r\n|\r|\n)CATEGORIES[^\:]*:(.*?)(\r\n|\r|\n)/',
                       $content, $m)) {
            $cats = explode(',', $m[2]);

            if( preg_match('/(\r\n|\r|\n)SUMMARY[^\:]*:(.*?)(\r\n|\r|\n)/',
                       $content, $m)) {
                $summary = $m[2];
            } else {
                $summary = 'unknown';
            }

            foreach ($cats as $cat) {
                $results[] = $backend->mapServerCategory2Client($cat, $summary);
            }
            preg_replace('/(\r\n|\r|\nCATEGORIES[^\:]*:)(.*?)(\r\n|\r|\n)/',
                         '$1' . implode(',',$results) . '$2', $content);
        }

        switch ($contentType) {
        case 'text/calendar' :
        case 'text/x-icalendar' :
        case 'text/x-vcalendar' :
        case 'text/x-vevent' :
        case 'text/x-vtodo' :
        case 'text/x-vnote':
            break;

        case 'text/x-vcard' :
            // If we can extract from the Device Info, that the client
            // uses TEL;WORK: 0800 123 instead of TEL;TYPE=WORK:
            // 0800123, convert the data accordingly.
            if (!empty($di->_CTCap['text/x-vcard']['TEL']) &&
                empty($di->_CTCap['text/x-vcard']['TEL']->_params['TYPE']) &&
                !empty($di->_CTCap['text/x-vcard']['TEL']->_params['VOICE'])) {
                $content = preg_replace('/(\r\n|\r|\n)TEL;TYPE=HOME/', '\1TEL;HOME;VOICE', $content, 1);
                $content = preg_replace('/(\r\n|\r|\n)TEL;TYPE=WORK/', '\1TEL;WORK;VOICE', $content, 1);
                $content = preg_replace('/(\r\n|\r|\n)TEL;TYPE=CELL/', '\1TEL;VOICE;CELL', $content, 1);
                $content = preg_replace('/(\r\n|\r|\n)TEL;TYPE=FAX/', '\1TEL;WORK;FAX', $content, 1);
                $content = preg_replace('/(\r\n|\r|\n)TEL;TYPE=PAGER/', '\1TEL;PAGER;WORK', $content, 1);
            }

            // If we can extract from the Device Info, that the client
            // uses ADR;WORK: ... instead of ADR;TYPE=WORK: 0800123,
            // convert the data accordingly.
            if (!empty($di->_CTCap['text/x-vcard']['ADR']) &&
                empty($di->_CTCap['text/x-vcard']['ADR']->_params['TYPE']) &&
                !empty($di->_CTCap['text/x-vcard']['ADR']->_params['WORK'])) {
                $content = preg_replace('/(\r\n|\r|\n)ADR;TYPE=WORK/', '\1ADR;WORK', $content, 1);
                $content = preg_replace('/(\r\n|\r|\n)ADR;TYPE=HOME/', '\1ADR;HOME', $content, 1);
            }

            break;
        }

        return array($content, $contentType);
    }

    /**
     * Some devices like the Sony Ericsson P800/P900/P910 handle
     * vtodos (tasks) and vevents in the same "calendar" sync.  This
     * requires special actions on our side as we store this in
     * different databases (nag and kronolith).  This function
     * determines whether the client does it like that.  Currently
     * this is done by checking the DevInf information. For different
     * clients there may be different ways to find out how the client
     * likes its tasks.
     */
    function handleTasksInCalendar()
    {
        // Default: tasks and events are seperate databases.
        return false;
    }

    /**
     * Some devices need a mapping of client vs. server categorie names.
     * The backend provides a mechanism for this mapping. It is used
     * when this needsCategoryMapping returns true.
     * For special cases the mapping may also be implemented directly in
     * convertClient2Server and convertServer2Client. In this case
     * needsCategoryMapping may return false.
     */
    function needsCategoryMapping()
    {
        return false;
    }

}
