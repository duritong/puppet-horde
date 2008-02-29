<?php
/**
 * The Horde_SessionObjects:: class provides a way for storing data
 * (usually, but not necessarily, objects) in the current user's
 * session.
 *
 * $Horde: framework/SessionObjects/SessionObjects.php,v 1.6.12.9 2007/01/02 13:54:38 jan Exp $
 *
 * Copyright 2003-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If youq
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 1.3
 * @package Horde_SessionObjects
 */
class Horde_SessionObjects {

    /**
     * The list of oids to prune at the end of a request.
     *
     * @var array
     */
    var $_pruneList = null;

    /**
     * The name of the store.
     *
     * @var string
     */
    var $_name = 'horde_session_objects';

    /**
     * Allow store() to overwrite current objects?
     *
     * @var boolean
     */
    var $_overwrite = false;

    /**
     * The maximum number of objects that the store should hold.
     *
     * @var integer
     */
    var $_size = 20;

    /**
     * Returns a reference to the global Horde_SessionObjects object,
     * only creating it if it doesn't already exist.
     *
     * This method must be invoked as:
     *   $objectstore = &Horde_SessionObjects::singleton();
     *
     * @return Horde_SessionObjects  The Horde_SessionObjects instance.
     */
    function &singleton()
    {
        static $object;

        if (!isset($object)) {
            $object = new Horde_SessionObjects();
        }

        return $object;
    }

    /**
     * Constructor.
     *
     * @param array $params  The parameter array.
     * <pre>
     * Optional Parameters:
     * 'name'  --  The name of the session variable to store the objects in.
     * 'size'  --  The maximum size of the (non-prunable) object store.
     * </pre>
     */
    function Horde_SessionObjects($params = array())
    {
        if (isset($params['name'])) {
            $this->_name = $params['name'];
        }

        if (isset($params['size']) && is_int($params['size'])) {
            $this->_size = $params['size'];
        }
    }

    /**
     * Wrapper around store that will return the oid instead.
     *
     * @see store
     *
     * @param mixed $data     The data to store in the session store.
     * @param boolean $prune  If false, this object will not be pruned from the
     *                        store if the maximum store size is exceeded.
     *
     * @return string  The MD5 string representing the object's ID.
     */
    function storeOid($data, $prune = true)
    {
        $oid = $this->oid($data);
        $this->store($oid, $data, $prune);
        return $oid;
    }

    /**
     * Attempts to store an object in the session store.
     *
     * @param string $oid     Object ID used as the storage key.
     * @param mixed $data     The data to store in the session store.
     * @param boolean $prune  If false, this object will not be pruned from the
     *                        store if the maximum store size is exceeded.
     *
     * @return boolean  True on success, false on failure.
     */
    function store($oid, $data, $prune = true)
    {
        /* Set up object now. */
        $dataObject = array();
        $dataObject['data'] = serialize($data);
        $dataObject['prune'] = $prune;

        if (!isset($_SESSION[$this->_name])) {
            $_SESSION[$this->_name] = array();
            $_SESSION[$this->_name]['__prune'] = 0;
        }

        if ($this->_overwrite || !isset($_SESSION[$this->_name][$oid])) {
            $_SESSION[$this->_name][$oid] = $dataObject;
            if ($prune) {
                $_SESSION[$this->_name]['__prune']++;
            }
        }

        /* Check for prunable Oids. */
        $this->_pruneOids();

        return true;
    }

    /**
     * Overwrites a current element in the object store.
     *
     * @param string $oid     Object ID used as the storage key.
     * @param mixed $data     The data to store in the session store.
     * @param boolean $prune  If false, this object will not be pruned from the
     *                        store if the maximum store size is exceeded.
     *
     * @return boolean  True on success, false on failure.
     */
    function overwrite($oid, $data, $prune = true)
    {
        $this->_overwrite = true;
        $success = $this->store($oid, $data, $prune);
        $this->_overwrite = false;
        return $success;
    }

    /**
     * Attempts to retrive an object from the store.
     *
     * @param string $oid   Object ID to query.
     * @param enum $type    NOT USED
     * @param integer $val  NOT USED
     *
     * @return mixed  The requested object, or false on failure.
     */
    function &query($oid, $type = null, $val = null)
    {
        if (!isset($_SESSION[$this->_name]) ||
            (is_null($oid) || !isset($_SESSION[$this->_name][$oid]))) {
            $object = false;
        } else {
            $object = unserialize($_SESSION[$this->_name][$oid]['data']);
        }
        return $object;
    }

    /**
     * Sets the prune flag on a store object.
     *
     * @param string $oid     The object ID.
     * @param boolean $prune  True to allow pruning, false for no pruning.
     */
    function setPruneFlag($oid, $prune)
    {
        if (isset($_SESSION[$this->_name][$oid]) &&
            ($_SESSION[$this->_name][$oid]['prune'] != $prune)) {
            $_SESSION[$this->_name][$oid]['prune'] = $prune;
            if ($prune) {
                $_SESSION[$this->_name]['__prune']++;
            } else {
                $_SESSION[$this->_name]['__prune']--;
            }
        }
    }

    /**
     * Generates an OID for an object.
     *
     * @param mixed $data  The data to store in the store.
     *
     * @return string $oid  An object ID to use as the storage key.
     */
    function oid($data)
    {
        return md5(serialize($data));
    }

    /**
     * Generate the list of prunable oids.
     *
     * @access private
     */
    function _pruneOids()
    {
        if (is_null($this->_pruneList) &&
            isset($_SESSION[$this->_name]['__prune']) &&
            ($_SESSION[$this->_name]['__prune'] > $this->_size)) {
            $this->_pruneList = array();
            foreach ($_SESSION[$this->_name] as $key => $val) {
                if ($val['prune']) {
                    $this->_pruneList[] = $key;
                }
            }
            register_shutdown_function(array(&$this, '_prune'));
        }
    }

    /**
     * Prune old store entries at request shutdown.
     *
     * @access private
     */
    function _prune()
    {
        $pruneOids = array_slice($this->_pruneList, 0, $_SESSION[$this->_name]['__prune'] - $this->_size);
        foreach ($pruneOids as $val) {
            unset($_SESSION[$this->_name][$val]);
        }
        $_SESSION[$this->_name]['__prune'] -= count($pruneOids);
    }

}
