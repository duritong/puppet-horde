<?php

/**
 * For decompress(), return a list of files/information about the zipfile.
 */
define('HORDE_COMPRESS_ZIP_LIST', 1);

/**
 * For decompress(), return the data for an individual file in the zipfile.
 */
define('HORDE_COMPRESS_ZIP_DATA', 2);

/**
 * The Horde_Compress_zip class allows ZIP files to be created and
 * read.
 *
 * $Horde: framework/Compress/Compress/zip.php,v 1.11.12.18 2007/01/02 13:54:11 jan Exp $
 *
 * The ZIP compression code is partially based on code from:
 *   Eric Mueller <eric@themepark.com>
 *   http://www.zend.com/codex.php?id=535&single=1
 *
 *   Deins125 <webmaster@atlant.ru>
 *   http://www.zend.com/codex.php?id=470&single=1
 *
 * The ZIP compression date code is partially based on code from
 *   Peter Listiak <mlady@users.sourceforge.net>
 *
 * Copyright 2000-2007 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2002-2007 Michael Cochrane <mike@graftonhall.co.nz>
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Cochrane <mike@graftonhall.co.nz>
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Horde 3.0
 * @package Horde_Compress
 */
class Horde_Compress_zip extends Horde_Compress {

    /**
     * ZIP compression methods.
     *
     * @var array
     */
    var $_methods = array(
        0x0 => 'None',
        0x1 => 'Shrunk',
        0x2 => 'Super Fast',
        0x3 => 'Fast',
        0x4 => 'Normal',
        0x5 => 'Maximum',
        0x6 => 'Imploded',
        0x8 => 'Deflated'
    );

    /**
     * Beginning of central directory record.
     *
     * @var string
     */
    var $_ctrlDirHeader = "\x50\x4b\x01\x02";

    /**
     * End of central directory record.
     *
     * @var string
     */
    var $_ctrlDirEnd = "\x50\x4b\x05\x06\x00\x00\x00\x00";

    /**
     * Beginning of file contents.
     *
     * @var string
     */
    var $_fileHeader = "\x50\x4b\x03\x04";

    /**
     * Create a ZIP compressed file from an array of file data.
     *
     * @param array $data    The data to compress.
     * <pre>
     * Requires an array of arrays - each subarray should contain the
     * following fields:
     * 'data' (string)   --  The data to compress.
     * 'name' (string)   --  The pathname to the file.
     * 'time' (integer)  --  [optional] The timestamp to use for the file.
     * </pre>
     * @param array $params  The parameter array (Unused).
     *
     * @return string  The ZIP file.
     */
    function compress($data, $params = array())
    {
        $contents = $ctrldir = array();

        foreach ($data as $val) {
            $this->_addToZIPFile($val, $contents, $ctrldir);
        }

        return $this->_createZIPFile($contents, $ctrldir);
    }

    /**
     * Decompress a ZIP file and get information from it.
     *
     * @param string $data   The zipfile data.
     * @param array $params  The parameter array.
     * <pre>
     * The following parameters are REQUIRED:
     * 'action' (integer)  =>  The action to take on the data.  Either
     *                         HORDE_COMPRESS_ZIP_LIST or
     *                         HORDE_COMPRESS_ZIP_DATA.
     *
     * The following parameters are REQUIRED for HORDE_COMPRESS_ZIP_DATA also:
     * 'info' (array)   =>  The zipfile list.
     * 'key' (integer)  =>  The position of the file in the archive list.
     * </pre>
     *
     * @return mixed  The requested data.
     */
    function decompress($data, $params)
    {
        if (isset($params['action'])) {
            if ($params['action'] == HORDE_COMPRESS_ZIP_LIST) {
                return $this->_getZipInfo($data);
            } elseif ($params['action'] == HORDE_COMPRESS_ZIP_DATA) {
                // TODO: Check for parameters.
                return $this->_getZipData($data, $params['info'], $params['key']);
            } else {
                return PEAR::raiseError(_("Incorrect action code given."), 'horde.error');
            }
        }

        return PEAR::raiseError(_("You must specify what action to perform."), 'horde.error');
    }

    /**
     * Get the list of files/data from the zip archive.
     *
     * @access private
     *
     * @param string &$data  The zipfile data.
     *
     * @return array  KEY: Position in zipfile
     *                VALUES: 'attr'    --  File attributes
     *                        'crc'     --  CRC checksum
     *                        'csize'   --  Compressed file size
     *                        'date'    --  File modification time
     *                        'name'    --  Filename
     *                        'method'  --  Compression method
     *                        'size'    --  Original file size
     *                        'type'    --  File type
     */
    function _getZipInfo(&$data)
    {
        $entries = array();

        /* Get details from Central directory structure. */
        $fhStart = strpos($data, $this->_ctrlDirHeader);

        do {
            if (strlen($data) < $fhStart + 31) {
                return PEAR::raiseError(_("Invalid ZIP data"));
            }
            $info = unpack('vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength', substr($data, $fhStart + 10, 20));
            $name = substr($data, $fhStart + 46, $info['Length']);

            $entries[$name] = array(
                'attr' => null,
                'crc' => sprintf("%08s", dechex($info['CRC32'])),
                'csize' => $info['Compressed'],
                'date' => null,
                '_dataStart' => null,
                'name' => $name,
                'method' => $this->_methods[$info['Method']],
                '_method' => $info['Method'],
                'size' => $info['Uncompressed'],
                'type' => null
            );

            $entries[$name]['date'] =
                mktime((($info['Time'] >> 11) & 0x1f),
                       (($info['Time'] >> 5) & 0x3f),
                       (($info['Time'] << 1) & 0x3e),
                       (($info['Time'] >> 21) & 0x07),
                       (($info['Time'] >> 16) & 0x1f),
                       ((($info['Time'] >> 25) & 0x7f) + 1980));

            if (strlen($data) < $fhStart + 43) {
                return PEAR::raiseError(_("Invalid ZIP data"));
            }
            $info = unpack('vInternal/VExternal', substr($data, $fhStart + 36, 6));

            $entries[$name]['type'] = ($info['Internal'] & 0x01) ? 'text' : 'binary';
            $entries[$name]['attr'] =
                (($info['External'] & 0x10) ? 'D' : '-') .
                (($info['External'] & 0x20) ? 'A' : '-') .
                (($info['External'] & 0x03) ? 'S' : '-') .
                (($info['External'] & 0x02) ? 'H' : '-') .
                (($info['External'] & 0x01) ? 'R' : '-');
        } while (($fhStart = strpos($data, $this->_ctrlDirHeader, $fhStart + 46)) !== false);

        /* Get details from local file header. */
        $fhStart = strpos($data, $this->_fileHeader);

        do {
            if (strlen($data) < $fhStart + 34) {
                return PEAR::raiseError(_("Invalid ZIP data"));
            }
            $info = unpack('vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength/vExtraLength', substr($data, $fhStart + 8, 25));
            $name = substr($data, $fhStart + 30, $info['Length']);
            $entries[$name]['_dataStart'] = $fhStart + 30 + $info['Length'] + $info['ExtraLength'];
        } while (strlen($data) > $fhStart + 30 + $info['Length'] &&
                 ($fhStart = strpos($data, $this->_fileHeader, $fhStart + 30 + $info['Length'])) !== false);

        return array_values($entries);
    }

    /**
     * Returns the data for a specific archived file.
     *
     * @access private
     *
     * @param string &$data  The zip archive contents.
     * @param array &$info   The information array from _getZipInfo().
     * @param integer $key   The position of the file in the archive.
     *
     * @return string  The file data.
     */
    function _getZipData(&$data, &$info, $key)
    {
        if (($info[$key]['_method'] == 0x8) && Util::extensionExists('zlib')) {
            /* If the file has been deflated, and zlib is installed,
               then inflate the data again. */
            return @gzinflate(substr($data, $info[$key]['_dataStart'], $info[$key]['csize']));
        } elseif ($info[$key]['_method'] == 0x0) {
            /* Files that aren't compressed. */
            return substr($data, $info[$key]['_dataStart'], $info[$key]['csize']);
        }

        return '';
    }

    /**
     * Checks to see if the data is a valid ZIP file.
     *
     * @param string &$data  The ZIP file data.
     *
     * @return boolean  True if valid, false if invalid.
     */
    function checkZipData(&$data)
    {
        if (strpos($data, $this->_fileHeader) === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Converts a UNIX timestamp to a 4-byte DOS date and time format
     * (date in high 2-bytes, time in low 2-bytes allowing magnitude
     * comparison).
     *
     * @access private
     *
     * @param integer $unixtime  The current UNIX timestamp.
     *
     * @return integer  The current date in a 4-byte DOS format.
     */
    function _unix2DOSTime($unixtime = null)
    {
        $timearray = (is_null($unixtime)) ? getdate() : getdate($unixtime);

        if ($timearray['year'] < 1980) {
            $timearray['year']    = 1980;
            $timearray['mon']     = 1;
            $timearray['mday']    = 1;
            $timearray['hours']   = 0;
            $timearray['minutes'] = 0;
            $timearray['seconds'] = 0;
        }

        return (($timearray['year'] - 1980) << 25) |
                ($timearray['mon'] << 21) |
                ($timearray['mday'] << 16) |
                ($timearray['hours'] << 11) |
                ($timearray['minutes'] << 5) |
                ($timearray['seconds'] >> 1);
    }

    /**
     * Adds a "file" to the ZIP archive.
     *
     * @access private
     *
     * @param array &$file      See Horde_Compress_zip::createZipFile().
     * @param array &$contents  An array of existing zipped files.
     * @param array &$ctrldir   An array of central directory information.
     */
    function _addToZIPFile(&$file, &$contents, &$ctrldir)
    {
        $data = &$file['data'];
        $name = str_replace('\\', '/', $file['name']);

        /* See if time/date information has been provided. */
        $ftime = null;
        if (isset($file['time'])) {
            $ftime = $file['time'];
        }

        /* Get the hex time. */
        $dtime    = dechex($this->_unix2DosTime($ftime));
        $hexdtime = chr(hexdec($dtime[6] . $dtime[7])) .
                    chr(hexdec($dtime[4] . $dtime[5])) .
                    chr(hexdec($dtime[2] . $dtime[3])) .
                    chr(hexdec($dtime[0] . $dtime[1]));

        $fr  = $this->_fileHeader;   /* Begin creating the ZIP data. */
        $fr .= "\x14\x00";           /* Version needed to extract. */
        $fr .= "\x00\x00";           /* General purpose bit flag. */
        $fr .= "\x08\x00";           /* Compression method. */
        $fr .= $hexdtime;            /* Last modification time/date. */

        /* "Local file header" segment. */
        $unc_len = strlen($data);
        $crc     = crc32($data);
        $zdata   = gzcompress($data);
        $zdata   = substr(substr($zdata, 0, strlen($zdata) - 4), 2);
        $c_len   = strlen($zdata);

        $fr .= pack('V', $crc);            /* CRC 32 information. */
        $fr .= pack('V', $c_len);          /* Compressed filesize. */
        $fr .= pack('V', $unc_len);        /* Uncompressed filesize. */
        $fr .= pack('v', strlen($name));   /* Length of filename. */
        $fr .= pack('v', 0);               /* Extra field length. */
        $fr .= $name;                      /* File name. */

        /* "File data" segment. */
        $fr .= $zdata;

        /* Add this entry to array. */
        $old_offset = strlen(implode('', $contents));
        $contents[] = &$fr;

        /* Add to central directory record. */
        $cdrec  = $this->_ctrlDirHeader;
        $cdrec .= "\x00\x00";                /* Version made by. */
        $cdrec .= "\x14\x00";                /* Version needed to extract */
        $cdrec .= "\x00\x00";                /* General purpose bit flag */
        $cdrec .= "\x08\x00";                /* Compression method */
        $cdrec .= $hexdtime;                 /* Last mod time/date. */
        $cdrec .= pack('V', $crc);           /* CRC 32 information. */
        $cdrec .= pack('V', $c_len);         /* Compressed filesize. */
        $cdrec .= pack('V', $unc_len);       /* Uncompressed filesize. */
        $cdrec .= pack('v', strlen($name));  /* Length of filename. */
        $cdrec .= pack('v', 0);              /* Extra field length. */
        $cdrec .= pack('v', 0);              /* File comment length. */
        $cdrec .= pack('v', 0);              /* Disk number start. */
        $cdrec .= pack('v', 0);              /* Internal file attributes. */
        $cdrec .= pack('V', 32);             /* External file attributes -
                                                'archive' bit set. */
        $cdrec .= pack('V', $old_offset);    /* Relative offset of local
                                                header. */
        $cdrec .= $name;                     /* File name. */
        /* Optional extra field, file comment goes here. */

        // Save to central directory array. */
        $ctrldir[] = &$cdrec;
    }

    /**
     * Creates the ZIP file.
     * Official ZIP file format: http://www.pkware.com/appnote.txt
     *
     * @access private
     *
     * @return string  The ZIP file.
     */
    function _createZIPFile(&$contents, &$ctrlDir)
    {
        $data = implode('', $contents);
        $dir = implode('', $ctrlDir);

        return $data . $dir . $this->_ctrlDirEnd .
            /* Total # of entries "on this disk". */
            pack('v', count($ctrlDir)) .
            /* Total # of entries overall. */
            pack('v', count($ctrlDir)) .
            /* Size of central directory. */
            pack('V', strlen($dir)) .
            /* Offset to start of central dir. */
            pack('V', strlen($data)) .
            /* ZIP file comment length. */
            "\x00\x00";
    }

}
