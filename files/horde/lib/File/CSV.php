<?php

require_once 'PEAR.php';
require_once 'File.php';

/**
 * The File_CSV package allows reading and creating of CSV data and files.
 *
 * $Horde: framework/File_CSV/CSV.php,v 1.4.2.9 2007/01/02 13:54:16 jan Exp $
 *
 * Copyright 2002-2007 Tomas Von Veschler Cox <cox@idecnet.com>
 * Copyright 2005-2007 Jan Schneider <jan@horde.org>
 *
 * This source file is subject to version 2.0 of the PHP license, that is
 * bundled with this package in the file LICENSE, and is available at through
 * the world-wide-web at http://www.php.net/license/2_02.txt.  If you did not
 * receive a copy of the PHP license and are unable to obtain it through the
 * world-wide-web, please send a note to license@php.net so we can mail you a
 * copy immediately.
 *
 * @author  Tomas Von Veschler Cox <cox@idecnet.com>
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 3.1
 * @package File_CSV
 */
class File_CSV {

    /**
     * Discovers the format of a CSV file (the number of fields, the separator,
     * the quote string, and the line break).
     *
     * We can't use the auto_detect_line_endings PHP setting, because it's not
     * supported by fgets() contrary to what the manual says.
     *
     * @static
     *
     * @param string  The CSV file name
     * @param array   Extra separators that should be checked for.
     *
     * @return array  The format hash.
     */
    function discoverFormat($file, $extraSeps = array())
    {
        if (!$fp = @fopen($file, 'r')) {
            return PEAR::raiseError('Could not open file: ' . $file);
        }

        $seps = array("\t", ';', ':', ',');
        $seps = array_merge($seps, $extraSeps);
        $matches = array();
        $crlf = null;
        $conf = array();

        /* Take the first 10 lines and store the number of ocurrences for each
         * separator in each line. */
        for ($i = 0; ($i < 10) && ($line = fgets($fp));) {
            /* Do we have Mac line endings? */
            $lines = preg_split('/\r(?!\n)/', $line, 10);
            $j = 0;
            $c = count($lines);
            if ($c > 1) {
                $crlf = "\r";
            }
            while ($i < 10 && $j < $c) {
                $line = $lines[$j];
                if (!isset($crlf)) {
                    foreach (array("\r\n", "\n") as $c) {
                        if (substr($line, -strlen($c)) == $c) {
                            $crlf = $c;
                            break;
                        }
                    }
                }
                $i++;
                $j++;
                foreach ($seps as $sep) {
                    $matches[$sep][$i] = substr_count($line, $sep);
                }
            }
        }
        if (isset($crlf)) {
            $conf['crlf'] = $crlf;
        }

        /* Group the results by amount of equal ocurrences. */
        $fields = array();
        $amount = array();
        foreach ($matches as $sep => $lines) {
            $times = array();
            $times[0] = 0;
            foreach ($lines as $num) {
                if ($num > 0) {
                    $times[$num] = (isset($times[$num])) ? $times[$num] + 1 : 1;
                }
            }
            arsort($times);
            $fields[$sep] = key($times);
            $amount[$sep] = $times[key($times)];
        }
        arsort($amount);
        $sep = key($amount);

        $conf['fields'] = $fields[$sep] + 1;
        $conf['sep']    = $sep;

        /* Test if there are fields with quotes arround in the first 5
         * lines. */
        $quotes = '"\'';
        $quote  = '';
        rewind($fp);
        for ($i = 0; ($i < 5) && ($line = fgets($fp)); $i++) {
            if (preg_match("|$sep([$quotes]).*([$quotes])$sep|U", $line, $match)) {
                if ($match[1] == $match[2]) {
                    $quote = $match[1];
                    break;
                }
            }
            if (preg_match("|^([$quotes]).*([$quotes])$sep|", $line, $match) ||
                preg_match("|([$quotes]).*([$quotes])$sep\s$|Us", $line, $match))
            {
                if ($match[1] == $match[2]) {
                    $quote = $match[1];
                    break;
                }
            }
        }
        $conf['quote'] = $quote;

        fclose($fp);

        // XXX What about trying to discover the "header"?
        return $conf;
    }

    /**
     * Reads a row from a CSV file and returns it as an array.
     *
     * @param string $file  The name of the CSV file.
     * @param array $conf   The configuration for the CSV file.
     *
     * @return array|boolean  The CSV data or false if no more data available.
     */
    function read($file, &$conf)
    {
        $fp = File_CSV::getPointer($file, $conf, FILE_MODE_READ);
        if (is_a($fp, 'PEAR_Error')) {
            return $fp;
        }

        $line = fgets($fp);
        if (!$line) {
            return false;
        }

        /* Use readQuoted() if we have Mac line endings. */
        if (preg_match('/\r(?!\n)/', $line)) {
            fseek($fp, -strlen($line), SEEK_CUR);
            return File_CSV::readQuoted($file, $conf);
        }

        File_CSV::_line(File_CSV::_line() + 1);

        if ($conf['fields'] == 1) {
            return array($line);
        }

        $fields = explode($conf['sep'], $line);
        if ($conf['quote']) {
            $last = &$fields[count($fields) - 1];
            /* Fallback to read the line with readQuoted() when guess that the
             * simple explode won't work right. */
            if ((substr($last, -strlen($conf['crlf'])) == $conf['crlf'] &&
                 $last{0} == $conf['quote'] &&
                 $last{strlen(rtrim($last)) - 1} != $conf['quote']) ||
                (count($fields) != $conf['fields'])
                    // XXX perhaps there is a separator inside a quoted field
                    //preg_match("|{$conf['quote']}.*{$conf['sep']}.*{$conf['quote']}|U", $line)
                )
            {
                fseek($fp, -strlen($line), SEEK_CUR);
                return File_CSV::readQuoted($file, $conf);
            } else {
                $last = rtrim($last);
                foreach ($fields as $k => $v) {
                    $fields[$k] = File_CSV::unquote($v, $conf['quote'], $conf['crlf']);
                }
            }
        } else {
            $fields[count($fields) -1] = rtrim($fields[count($fields) -1]);
        }

        if (count($fields) < $conf['fields']) {
            File_CSV::warning(sprintf(_("Wrong number of fields in line %d. Expected %d, found %d."), File_CSV::_line(), $conf['fields'], count($fields)));
            $fields = array_merge($fields, array_fill(0, $conf['fields'] - count($fields), ''));
        } elseif (count($fields) > $conf['fields']) {
            File_CSV::warning(sprintf(_("More fields found in line %d than the expected %d."), File_CSV::_line(), $conf['fields']));
            array_splice($fields, $conf['fields']);
        }

        return $fields;
    }

    /**
     * Reads a row from a CSV file and returns it as an array.
     * This method is able to read fields with multiline data and normalizes
     * linebreaks to single newline characters (0x0a).
     *
     * @param string $file  The name of the CSV file.
     * @param array $conf   The configuration for the CSV file.
     *
     * @return array|boolean  The CSV data or false if no more data available.
     */
    function readQuoted($file, &$conf)
    {
        $fp = File_CSV::getPointer($file, $conf, FILE_MODE_READ);
        if (is_a($fp, 'PEAR_Error')) {
            return $fp;
        }

        /* A buffer with all characters of the current field read so far. */
        $buff = '';
        /* The current character. */
        $c = null;
        /* The read fields. */
        $ret = array();
        /* The number of the current field. */
        $i = 1;
        /* Are we inside a quoted field? */
        $in_quote = false;

        while (($ch = fgetc($fp)) !== false) {
            /* Previous character. */
            $prev = $c;
            /* Current character. */
            $c = $ch;

            /* Simple character. */
            if ($c != $conf['quote'] &&
                $c != $conf['sep'] &&
                strpos($conf['crlf'], $c) === false) {
                $buff .= $c;
                continue;
            }

            if ($conf['quote'] && $c == $conf['quote'] &&
                ($prev == $conf['sep'] ||
                 $prev == substr($conf['crlf'], -1) ||
                 $prev === null))
            {
                /* Quote begin. */
                $in_quote = true;
            } elseif ($in_quote) {
                /* Quote end? */
                if ($c == $conf['sep'] && $prev == $conf['quote']) {
                    $in_quote = false;
                } elseif ($c == substr($conf['crlf'], -1)) {
                    $sub = (strlen($conf['crlf']) > 1 &&
                            $prev == substr($conf['crlf'], -2, 1))
                        ? 2
                        : 1;
                    if ((strlen($buff) >= $sub) &&
                        ($buff{strlen($buff) - $sub} == $conf['quote']))
                    {
                        $in_quote = false;
                    } else {
                        File_CSV::_line(File_CSV::_line() + 1);
                    }
                }
            }

            /* End of line or end of field. */
            if (!$in_quote &&
                ($c == $conf['sep'] || $c == substr($conf['crlf'], -1))) {
                /* More fields than expected. */
                if ($c == $conf['sep'] &&
                    (count($ret) + 1) == $conf['fields']) {
                    while ($c !== false && $c != substr($conf['crlf'], -1)) {
                        $c = fgetc($fp);
                    }
                    File_CSV::warning(sprintf(_("More fields found in line %d than the expected %d."), File_CSV::_line(), $conf['fields']));
                }

                if ($prev == substr($conf['crlf'], -2, 1)) {
                    $buff = substr($buff, 0, -1);
                }

                /* Less fields than expected. */
                if (($c == substr($conf['crlf'], -1)) &&
                    ($i != $conf['fields'])) {
                    if ($i == 1) {
                        /* Skip empty lines. */
                        return $ret;
                    }
                    File_CSV::warning(sprintf(_("Wrong number of fields in line %d. Expected %d, found %d."), File_CSV::_line(), $conf['fields'], $i));

                    $ret[] = File_CSV::unquote($buff, $conf['quote'], $conf['crlf']);
                    $ret = array_merge($ret, array_fill(0, $conf['fields'] - $i, ''));
                    return $ret;
                }

                $ret[] = File_CSV::unquote($buff, $conf['quote'], $conf['crlf']);
                if (count($ret) == $conf['fields']) {
                    return $ret;
                }

                $buff = '';
                $i++;
                continue;
            }
            $buff .= $c;
        }

        return feof($fp) ? false : $ret;
    }

    /**
     * Writes a hash into a CSV file.
     *
     * @param string $file   The name of the CSV file.
     * @param array $fields  The CSV data.
     * @param array $conf    The configuration for the CSV file.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function write($file, $fields, &$conf)
    {
        if (is_a($fp = File_CSV::getPointer($file, $conf, FILE_MODE_WRITE), 'PEAR_Error')) {
            return $fp;
        }

        if (count($fields) != $conf['fields']) {
            return PEAR::raiseError(sprintf(_("Wrong number of fields. Expected %d, found %d."), $conf['fields'], count($fields)));
        }

        $write = '';
        for ($i = 0; $i < count($fields); $i++) {
            if (!is_numeric($fields[$i]) && $conf['quote']) {
                $write .= $conf['quote'] . $fields[$i] . $conf['quote'];
            } else {
                $write .= $fields[$i];
            }
            if ($i < (count($fields) - 1)) {
                $write .= $conf['sep'];
            } else {
                $write .= $conf['crlf'];
            }
        }

        if (!fwrite($fp, $write)) {
            return PEAR::raiseError(sprintf(_("Cannot write to file \"%s\""), $file));
        }

        return true;
    }

    /**
     * Removes surrounding quotes from a string and normalizes linebreaks.
     *
     * @param string $field  The string to unquote.
     * @param string $quote  The quote character.
     * @param string $crlf   The linebreak character.
     *
     * @return string  The unquoted data.
     */
    function unquote($field, $quote, $crlf)
    {
        /* Skip empty fields (form: ;;) */
        if (!strlen($field)) {
            return $field;
        }
        if ($quote && $field{0} == $quote && $field{strlen($field)-1} == $quote) {
            return str_replace($crlf, "\n", substr($field, 1, -1));
        }
        return $field;
    }

    /**
     * Sets or gets the current line being parsed.
     *
     * @param integer $line  If specified, the current line.
     *
     * @return integer  The current line.
     */
    function _line($line = null)
    {
        static $current_line = 0;

        if (!is_null($line)) {
            $current_line = $line;
        }

        return $current_line;
    }

    /**
     * Adds a warning to or retrieves and resets the warning stack.
     *
     * @param string  A warning string.  If not specified, the existing
     *                warnings will be returned instead and the warning stack
     *                gets emptied.
     *
     * @return array  If no parameter has been specified, the list of existing
     *                warnings.
     */
    function warning($warning = null)
    {
        static $warnings = array();

        if (is_null($warning)) {
            $return = $warnings;
            $warnings = array();
            return $return;
        }

        $warnings[] = $warning;
    }

    /**
     * Returns or creates the file descriptor associated with a file.
     *
     * @static
     *
     * @param string $file  The name of the file
     * @param array $conf   The configuration
     * @param string $mode  The open mode. FILE_MODE_READ or FILE_MODE_WRITE.
     *
     * @return resource  The file resource or PEAR_Error on error.
     */
    function getPointer($file, &$conf, $mode = FILE_MODE_READ)
    {
        static $resources = array();
        static $config = array();

        if (isset($resources[$file])) {
            $conf = $config[$file];
            return $resources[$file];
        }
        if (is_a($error = File_CSV::_checkConfig($conf), 'PEAR_Error')) {
            return $error;
        }
        $config[$file] = $conf;

        $fp = @fopen($file, $mode);
        if (!is_resource($fp)) {
            return PEAR::raiseError(sprintf(_("Can't open file \"%s\"."), $file));
        }
        $resources[$file] = $fp;
        File_CSV::_line(0);

        if ($mode == FILE_MODE_READ && !empty($conf['header'])) {
            if (is_a($header = File_CSV::read($file, $conf), 'PEAR_Error')) {
                return $header;
            }
        }

        return $fp;
    }

    /**
     * Checks the configuration given by the user.
     *
     * @param array $conf    The configuration assoc array
     * @param string $error  The error will be written here if any
     */
    function _checkConfig(&$conf)
    {
        // check conf
        if (!is_array($conf)) {
            return PEAR::raiseError('Invalid configuration.');
        }

        if (!isset($conf['fields']) || !is_numeric($conf['fields'])) {
            return PEAR::raiseError(_("The number of fields must be numeric."));
        }

        if (isset($conf['sep'])) {
            if (strlen($conf['sep']) != 1) {
                return PEAR::raiseError(_("The separator must be one single character."));
            }
        } elseif ($conf['fields'] > 1) {
            return PEAR::raiseError(_("No separator specified."));
        }

        if (!empty($conf['quote'])) {
            if (strlen($conf['quote']) != 1) {
                return PEAR::raiseError(_("The quote character must be one single character."));
            }
        } else {
            $conf['quote'] = '';
        }

        if (!isset($conf['crlf'])) {
            $conf['crlf'] = "\n";
        }
    }

}
