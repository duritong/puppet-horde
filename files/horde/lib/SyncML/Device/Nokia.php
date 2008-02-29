<?php
/**
 * The SyncML_Device_Nokia:: class provides functionality that is
 * specific to the Nokia SyncML clients.
 *
 * Copyright 2005-2007 Karsten Fourmont <karsten@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * $Horde: framework/SyncML/SyncML/Device/Nokia.php,v 1.2.2.4 2007/01/02 13:54:42 jan Exp $
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @package SyncML
 */
class SyncML_Device_Nokia extends SyncML_Device {

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

        // FIXME: just swapping out the version number in the header
        // so that the client doesn't immediately deny with a "Format
        // not supported". See http://bugs.horde.org/ticket/?id=1881.
        $content = preg_replace('/(\r\n|\r|\n)VERSION:2.0/', '\1VERSION:1.0', $content, 1);

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

    /* Nokia currently expects notes as text/plain. Maybe we can extract
     * this from DevInf rather than hardcode it.
     */

   function getPreferredContentType($type)
    {
         if ($type == 'notes') {
            return 'text/plain';
        }
        return parent::getPreferredContentType($type);
    }

   function handleTasksInCalendar()
   {
      return true;      
   }
}
