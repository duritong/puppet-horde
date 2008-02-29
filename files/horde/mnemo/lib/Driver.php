<?php
/**
 * Mnemo_Driver:: defines an API for implementing storage backends for Mnemo.
 *
 * $Horde: mnemo/lib/Driver.php,v 1.25.2.10 2007/01/02 13:55:11 jan Exp $
 *
 * Copyright 2001-2007 Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jon Parise <jon@horde.org>
 * @since   Mnemo 1.0
 * @package Mnemo
 */
class Mnemo_Driver {

    /**
     * Array holding the current memo list.  Each array entry is a hash
     * describing a memo.  The array is indexed numerically by memo ID.
     *
     * @var array
     */
    var $_memos = array();

    /**
     * String containing the current notepad name.
     *
     * @var string
     */
    var $_notepad = '';

    /**
     * Lists memos based on the given criteria. All memos will be
     * returned by default.
     *
     * @return array    Returns a list of the requested memos.
     */
    function listMemos()
    {
        return $this->_memos;
    }

    /**
     * Generate a universal / unique identifier for a task. This is
     * NOT something that we expect to be able to parse into a
     * tasklist and a taskId.
     *
     * @return string  A nice unique string (should be 255 chars or less).
     */
    function generateUID()
    {
        return date('YmdHis') . '.' .
            substr(base_convert(microtime(), 10, 36), -16) .
            '@' . $GLOBALS['conf']['server']['name'];
    }

    /**
     * Update the description (short summary) of a memo.
     *
     * @param integer $memo_id  The memo to update.
     */
    function getMemoDescription($body)
    {
        if (!strstr($body, "\n") && String::length($body) <= 64) {
            return trim($body);
        } else {
            $lines = explode("\n", $body);
            if (!is_array($lines)) {
                return trim(String::substr($body, 0, 64));
            } else {
                // Move to a line with more than spaces.
                $i = 0;
                while (isset($lines[$i]) && !preg_match('|[^\s]|', $lines[$i])) {
                    $i++;
                }
                if (String::length($lines[$i]) <= 64) {
                    return trim($lines[$i]);
                } else {
                    return trim(String::substr($lines[$i], 0, 64));
                }
            }
        }
    }

    /**
     * Attempts to return a concrete Mnemo_Driver instance based on $driver.
     *
     * @param string    $notepad    The name of the current notepad.
     *
     * @param string    $driver     The type of concrete Mnemo_Driver subclass
     *                              to return.  The is based on the storage
     *                              driver ($driver).  The code is dynamically
     *                              included.
     *
     * @param array     $params     (optional) A hash containing any additional
     *                              configuration or connection parameters a
     *                              subclass might need.
     *
     * @return mixed    The newly created concrete Mnemo_Driver instance, or
     *                  false on an error.
     */
    function &factory($notepad = '', $driver = null, $params = null)
    {
        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['storage']['driver'];
        }

        $driver = basename($driver);

        if (is_null($params)) {
            $params = Horde::getDriverConfig('storage', $driver);
        }

        require_once dirname(__FILE__) . '/Driver/' . $driver . '.php';
        $class = 'Mnemo_Driver_' . $driver;
        if (class_exists($class)) {
            $mnemo = &new $class($notepad, $params);
        } else {
            $mnemo = false;
        }

        return $mnemo;
    }

    /**
     * Attempts to return a reference to a concrete Mnemo_Driver instance based
     * on $driver.
     *
     * It will only create a new instance if no Mnemo_Driver instance with the
     * same parameters currently exists.
     *
     * This should be used if multiple storage sources are required.
     *
     * This method must be invoked as: $var = &Mnemo_Driver::singleton()
     *
     * @param string    $notepad    The name of the current notepad.
     *
     * @param string    $driver     The type of concrete Mnemo_Driver subclass
     *                              to return.  The is based on the storage
     *                              driver ($driver).  The code is dynamically
     *                              included.
     *
     * @param array     $params     (optional) A hash containing any additional
     *                              configuration or connection parameters a
     *                              subclass might need.
     *
     * @return mixed    The created concrete Mnemo_Driver instance, or false
     *                  on error.
     */
    function &singleton($notepad = '', $driver = null, $params = null)
    {
        static $instances;

        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['storage']['driver'];
        }

        if (is_null($params)) {
            $params = Horde::getDriverConfig('storage', $driver);
        }

        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($notepad, $driver, $params));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &Mnemo_Driver::factory($notepad, $driver, $params);
        }

        return $instances[$signature];
    }

    /**
     * Export this memo in iCalendar format.
     *
     * @param array  memo      the memo (hash array) to export
     * @param object vcal      a Horde_iCalendar object that acts as container.
     *
     * @return object  Horde_iCalendar_vnote object for this event.
     */
    function toiCalendar($memo, &$calendar)
    {
        global $prefs;

        $vnote = &Horde_iCalendar::newComponent('vnote', $calendar);

        $vnote->setAttribute('UID', $memo['uid']);
        $vnote->setAttribute('BODY', $memo['body']);
        if (!empty($memo['category'])) {
            $vnote->setAttribute('CATEGORIES', $memo['category']);
        }

        /* Get the note's history. */
        $history = &Horde_History::singleton();
        $log = $history->getHistory('mnemo:' . $memo['memolist_id'] . ':' . $memo['uid']);
        if ($log && !is_a($log, 'PEAR_Error')) {
            foreach ($log->getData() as $entry) {
                switch ($entry['action']) {
                case 'add':
                    $created = $entry['ts'];
                    break;

                case 'modify':
                    $modified = $entry['ts'];
                    break;
                }
            }
        }

        if (!empty($created)) {
            $vnote->setAttribute('DCREATED', $created);
        }
        if (!empty($modified)) {
            $vnote->setAttribute('LAST-MODIFIED', $modified);
        }

        return $vnote;
    }

    /**
     * Create a memo (hash array) from a Horde_iCalendar_vnote object.
     *
     * @param Horde_iCalendar_vnote $vnote  The iCalendar data to update from.
     *
     * @return array  Memo (hash array) created from the vNote.
     */
    function fromiCalendar($vNote)
    {
        $memo = array();

        $body = $vNote->getAttribute('BODY');
        if (!is_array($body) && !is_a($body, 'PEAR_Error')) {
            $memo['body'] = $body;
        } else {
            $memo['body'] = '';
        }

        $memo['desc'] = $this->getMemoDescription($memo['body']);

        $cat = $vNote->getAttribute('CATEGORIES');
        if (!is_array($cat) && !is_a($cat, 'PEAR_Error')) {
            $memo['category'] = $cat;
        }

        return $memo;
    }

}
