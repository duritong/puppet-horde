<?php

require_once 'Horde/Kolab.php';

/**
 * Horde Mnemo driver for the Kolab IMAP server.
 *
 * $Horde: mnemo/lib/Driver/kolab.php,v 1.7.2.8 2007/01/02 13:55:11 jan Exp $
 *
 * Copyright 2004-2007 Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Stuart Binge <omicron@mighty.co.za>
 * @since   Mnemo 2.0
 * @package Mnemo
 */
class Mnemo_Driver_kolab extends Mnemo_Driver {

    /**
     * Hash containing connection parameters (not used).
     *
     * @var array
     */
    var $_params = array();

    /**
     * Our Kolab server connection.
     *
     * @var Kolab
     */
    var $_kolab = null;

    function Mnemo_Driver_kolab($notepad, $params = array())
    {
        $this->_notepad = $notepad;
        $this->_params = $params;
    }

    function _connect()
    {
        if (isset($this->_kolab)) {
            return true;
        }

        $this->_kolab = new Kolab();

        return $this->_kolab->open($this->_notepad);
    }

    function _disconnect()
    {
        $this->_kolab->close();
        $this->_kolab = null;
    }

    function _buildNote()
    {
        return array(
            'memolist_id' => $this->_notepad,
            'memo_id' => $this->_kolab->getUID(),
            'uid' => $this->_kolab->getUID(),
            'desc' => $this->_kolab->getStr('summary'),
            'body' => $this->_kolab->getStr('body'),
            'category' => $this->_kolab->getStr('categories'),
        );
    }

    /**
     * Retrieve one note from the store.
     *
     * @param string $noteId  The ID of the note to retrieve.
     *
     * @return array  The array of note attributes.
     */
    function get($noteId)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $result = $this->_kolab->loadObject($noteId);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_buildNote();
    }

    /**
     * Retrieve one note by UID.
     *
     * @param string $uid  The UID of the note to retrieve.
     *
     * @return array  The array of note attributes.
     */
    function getByUID($uid)
    {
        return PEAR::raiseError('Not supported');
    }

    function _setObject($desc, $body, $category = '', $uid = null)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        if (isset($uid)) {
            $result = $this->_kolab->loadObject($uid);
        } else {
            $uid = md5(uniqid(mt_rand(), true));
            $result = $this->_kolab->newObject($uid);
        }
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $this->_kolab->setStr('summary', $desc);
        $this->_kolab->setStr('body', $body);
        $this->_kolab->setStr('categories', $category);

        $result = $this->_kolab->saveObject();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $uid;
    }

    /**
     * Add a note to the backend storage.
     *
     * @param string $desc      The description (long) of the note.
     * @param string $body      The description (long) of the note.
     * @param string $category  The category of the note.
     *
     * @return integer  The numeric ID of the new note.
     */
    function add($desc, $body, $category = '')
    {
        return $this->_setObject($desc, $body, $category);
    }

    /**
     * Modify an existing note.
     *
     * @param integer $noteId   The note to modify.
     * @param string $desc      The description (long) of the note.
     * @param string $body      The description (long) of the note.
     * @param string $category  The category of the note.
     */
    function modify($noteId, $desc, $body, $category = '')
    {
        $result = $this->_setObject($desc, $body, $category, $noteId);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $result == $noteId;
    }

    /**
     * Move a note to a new notepad.
     *
     * @param string $noteId      The note to move.
     * @param string $newNotepad  The new notepad.
     */
    function move($noteId, $newNotepad)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_kolab->moveObject($noteId, $newNotepad);
    }

    function delete($noteId)
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_kolab->removeObjects($noteId);
    }

    function deleteAll()
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_kolab->removeAllObjects();
    }

    /**
     * Retrieves all of the notes from $this->_notepad from the database.
     *
     * @return mixed  True on success, PEAR_Error on failure.
     */
    function retrieve()
    {
        $result = $this->_connect();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $this->_memos = array();

        $msg_list = $this->_kolab->listObjects();
        if (is_a($msg_list, 'PEAR_Error')) {
            return $msg_list;
        }

        if (empty($msg_list)) {
            return true;
        }

        foreach ($msg_list as $msg) {
            $xml = &$this->_kolab->loadObject($msg, true);
            if (is_a($xml, 'PEAR_Error')) {
                return $xml;
            }

            $this->_memos[$this->_kolab->getUID()] = $this->_buildNote($xml);
        }

        return true;
    }

}
