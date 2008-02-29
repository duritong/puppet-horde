<?php
/**
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
 * As the P800 was the first device to work with package, most of the
 * required conversions are in Device.php's default handling.
 *
 * Copyright 2005-2007 Karsten Fourmont <karsten@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * $Horde: framework/SyncML/SyncML/Device/P800.php,v 1.7.2.6 2007/01/02 13:54:42 jan Exp $
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @package SyncML
 */
class SyncML_Device_P800 extends SyncML_Device {

    /**
     * Convert the content.
     *
     * Currently strips uid (primary key) information as client and
     * server might use different ones.
     *
     * @param string $content       The content to convert.
     * @param string $contentType   The contentType of the content.
     * @return array                array($newcontent, $newcontentType):
     *                              the converted content and the
     *                              (possibly changed) new ContentType.
     */
    function convertClient2Server($content, $contentType)
    {
        list($content, $contentType) =
            parent::convertClient2Server($content, $contentType);

        /* P800 sends categories as "X-Category". Remove the "X-": */
        $content = preg_replace('/(\r\n|\r|\n)CATEGORIES:X-/', '\1CATEGORIES:', $content, 1);

        /* P800 sends all day events as s.th. like
         * DTSTART:20050505T000000Z^M
         * DTEND:20050505T240000Z^M
         * This is no longer an all day event when converted to local timezone.
         * So manually handle this.
         * */
        if (preg_match('/(\r\n|\r|\n)DTSTART:.*T000000Z(\r\n|\r|\n)/',
                       $content)
            && preg_match('/(\r\n|\r|\n)DTEND:(\d\d\d\d)(\d\d)(\d\d)T240000Z(\r\n|\r|\n)/',
                          $content, $m)) {
            $content = preg_replace('/(\r\n|\r|\n)DTSTART:(.*)T000000Z(\r\n|\r|\n)/',
                                    "$1DTSTART;VALUE=DATE:$2$3", $content);
            /* End timestamp must be converted to next day's date. */
            $s = date('Ymd', mktime(0, 0, 0, $m[3], $m[4], $m[2]) + 24*3600);
            $content = preg_replace('/(\r\n|\r|\n)DTEND:(.*)T240000Z(\r\n|\r|\n)/',
                                    "$1DTEND;VALUE=DATE:$s$3", $content);
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
     * @param string $content       The content to convert.
     * @param string $contentType   The contentType of content as returned from
     *                              the backend.
     * @return array                array($newcontent, $newcontentType):
     *                              the converted content and the
     *                              (possibly changed) new ContentType.
     */
    function convertServer2Client($content, $contentType)
    {
        global $backend;

        list($content, $contentType) =
            parent::convertServer2Client($content, $contentType);

        /* Convert all day events. */
        if (preg_match('/(\r\n|\r|\n)DTSTART;VALUE=DATE:(\d{8})/',
                       $content)
            && preg_match('/(\r\n|\r|\n)DTEND;VALUE=DATE:(\d\d\d\d)(\d\d)(\d\d)/',
                          $content, $m)) {
            $content = preg_replace('/(\r\n|\r|\n)DTSTART;VALUE=DATE:(\d{8})/',
                                    "$1DTSTART:$2T000000Z", $content);
            /* End date must be converted to timestamp. */
            $s = date('Ymd', mktime(0, 0, 0, $m[3], $m[4], $m[2]) - 24*3600);
            $content = preg_replace('/(\r\n|\r|\n)DTEND;VALUE=DATE:(\d{8})/',
                                    "$1DTEND;:${s}T240000Z", $content);
        }

        if (DEBUGLOG_ICALENDARDATA) {
            $fp = @fopen('/tmp/sync/log.txt', 'a');
            if ($fp) {
                @fwrite($fp, "\noutput converted for client ($contentType):\n");
                @fwrite($fp, $content . "\n");
                @fclose($fp);
            }
        }

        return array($content, $contentType);
    }

    /**
     * Some devices like the Sony Ericsson P800/P900/P910 handle vtodos (tasks)
     * and vevents in the same "calendar" sync.
     * This requires special actions on our side as we store this in different
     * databases (nag and kronolith).
     * This function could directly return true but tries to be a bit more
     * generic so it might work for other phones as well.
     */
    function handleTasksInCalendar()
    {
        $di = $this->deviceInfo();

        if (!empty($di->_CTCap['text/x-vcalendar']['BEGIN']->_ValEnum['VEVENT']) &&
            !empty($di->_CTCap['text/x-vcalendar']['BEGIN']->_ValEnum['VTODO'])) {
            return true;
        }

        return parent::handleTasksInCalendar();
    }

    function needsCategoryMapping()
    {
        // P800 uses numeric category codes.
        return true;
    }

}
