<?php

require_once 'Horde/Cache.php';

/**
 * Turba directory driver implementation for an IMSP server.
 *
 * $Horde: turba/lib/Driver/imsp.php,v 1.21.4.15 2007/08/05 19:30:08 mrubinsk Exp $
 *
 * @author  Michael Rubinsky <mrubinsk@horde.org>
 * @package Turba
 */
class Turba_Driver_imsp extends Turba_Driver {
    /**
     * Handle for the IMSP connection.
     *
     * @var Net_IMSP
     */
    var $_imsp;

    /**
     * The name of the addressbook.
     *
     * @var string
     */
    var $_bookName  = '';

    /**
     * Holds if we are authenticated.
     *
     * @var boolean
     */
    var $_authenticated = '';

    /**
     * Holds name of the field indicating an IMSP group.
     *
     * @var string
     */
    var $_groupField = '';

    /**
     * Holds value that $_groupField will have if entry is an IMSP group.
     *
     * @var string
     */
    var $_groupValue = '';

    /**
     * Used to set if the current search is for contacts only.
     *
     * @var boolean
     */
    var $_noGroups = '';


    /**
     * Constructs a new Turba imsp driver object.
     *
     * @param array $params  Hash containing additional configuration parameters.
     */
    function Turba_Driver_imsp($params)
    {
        $this->type         = 'imsp';
        $this->params       = $params;
        $this->_groupField  = $params['group_id_field'];
        $this->_groupValue  = $params['group_id_value'];
        $this->_bookName    = $params['name'];
        $this->_myRights    = $params['my_rights'];
        $this->_perms       = $this->_aclToHordePerms($params['my_rights']);
    }

    /**
     * Initialize the IMSP connection and check for error.
     */
    function _init()
    {
        global $prefs, $conf;
        require_once 'Net/IMSP.php';
        $this->_imsp = &Net_IMSP::singleton('Book', $this->params);
        $result = $this->_imsp->init();
        if (is_a($result, 'PEAR_Error')) {
            $this->_authenticated = false;
            return $result;
        }

        if (!empty($conf['log'])) {
            $logParams = $conf['log'];
            $result = $this->_imsp->setLogger($conf['log']);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        $this->_authenticated = true;
        return true;
    }

    /**
     * Returns all entries matching $critera.
     *
     * @param array $criteria  Array containing the search criteria.
     * @param array $fields    List of fields to return.
     *
     * @return array  Hash containing the search results.
     */
    function _search($criteria, $fields)
    {
        $query = array();
        $results = array();

        if (!$this->_authenticated) {
            return array();
        }

        /* Get the search criteria. */
        $imspSearch = array();
        if (count($criteria)) {
            foreach ($criteria as $key => $vals) {
                if (strval($key) == 'OR') {
                    $names = $this->_doSearch($vals, 'OR');
                } elseif (strval($key) == 'AND') {
                    $names = $this->_doSearch($vals, 'AND');
                }
            }
        }

        /* Now we have a list of names, get the rest. */
        $namesCount = count($names);
        for ($i = 0; $i < $namesCount; $i++) {
            $temp = $this->_read('name', array($names[$i]), $fields);
            if (is_a($temp, 'PEAR_Error')) {
                $GLOBALS['notification']->push($temp, 'horde.error');
            } else {
                $result = $temp[0];
                if (is_a($result, 'PEAR_Error')) {
                    $GLOBALS['notification']->push($results, 'horde.error');
                } elseif (($this->_noGroups) && (isset($result[$this->_groupField])) &&
                          ($result[$this->_groupField]) == $this->_groupValue) {
                    unset($result);
                } else {
                    $results[] = $result;
                }
            }
        }

        Horde::logMessage(sprintf('IMSP returned %s results',
                                  count($results)), __FILE__, __LINE__, PEAR_LOG_DEBUG);
        return array_values($results);
    }

    /**
     * Reads the given data from the IMSP server and returns the
     * result's fields.
     *
     * @param array $criteria  (Ignored: Always 'name' for IMSP) Search criteria.
     * @param array $id        Array of data identifiers.
     * @param array $fields    List of fields to return.
     *
     * @return array  Hash containing the search results.
     */
    function _read($criteria, $id, $fields)
    {
        $results = array();
        if (!$this->_authenticated) {
            return $results;
        }
        $id = array_values($id);
        $idCount = count($id);
        $result = array();
        $members = array();
        $tmembers = array();

        for ($i = 0; $i < $idCount; $i++) {
            $temp = $this->_imsp->getEntry($this->_bookName, $id[$i]);
            if (is_a($temp, 'PEAR_Error')) {
                return $temp;
            } else {
                $temp['fullname'] = $temp['name'];
                $isIMSPGroup = false;
                if (!isset($temp['__owner'])) {
                    $temp['__owner'] = Auth::getAuth();
                }

                if ((isset($temp[$this->_groupField])) &&
                    ($temp[$this->_groupField] == $this->_groupValue)) {
                    $isIMSPGroup = true;
                }
                // Get the group members that might have been added from other
                // IMSP applications.
                if ($isIMSPGroup) {
                    if (isset($temp['email'])) {
                        $emailList = $this->_getGroupEmails($temp['email']);
                        $count = count($emailList);
                        for ($j = 0; $j < $count; $j++) {
                            $memberName = $this->_imsp->search
                                ($this->_bookName, array('email' => trim($emailList[$j])));

                            if (count($memberName)) {
                                $members[] = $memberName[0];
                            }
                        }
                    }
                    if (isset($temp['__members'])) {
                        $tmembers = @unserialize($temp['__members']);
                    }
                    $temp['__members'] = serialize($this->_removeDuplicated(
                                                   array($members, $tmembers)));
                    $temp['__type'] = 'Group';
                    $temp['email'] = null;
                    $result = $temp;
                } else {
                    // IMSP contact.
                    $count = count($fields);
                    for ($j = 0; $j < $count; $j++) {
                        if (isset($temp[$fields[$j]])) {
                            $result[$fields[$j]] = $temp[$fields[$j]];
                        }
                    }
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Adds the specified object to the IMSP server.
     */
    function _add($attributes)
    {
        /* We need to map out Turba_Object_Groups back to IMSP groups before
         * writing out to the server. We need to array_values() it in
         * case an entry was deleted from the group. */
        if ($attributes['__type'] == 'Group') {
            /* We may have a newly created group. */
            $attributes[$this->_groupField] = $this->_groupValue;
            if (!isset($attributes['__members'])) {
                $attributes['__members'] = '';
                $attributes['email'] = ' ';
            }

            $temp = unserialize($attributes['__members']);
            if (is_array($temp)) {
                $members = array_values($temp);
            } else {
                $members = array();
            }

            if (count($members)) {
                $result = $this->_read('name', $members, array('email'));
                if (!is_a($result, 'PEAR_Error')) {
                    $count = count($result);
                    for ($i = 0; $i < $count; $i++) {
                        if (isset($result[$i]['email'])) {
                            $contact = sprintf("%s<%s>\n", $members[$i],
                                               $result[$i]['email']);
                            $attributes['email'] .= $contact;
                        }
                    }
                }
            }
        }

        unset($attributes['__type']);
        unset($attributes['fullname']);
        if (!$this->params['contact_ownership']) {
            unset($attributes['__owner']);
        }

        return $this->_imsp->addEntry($this->_bookName, $attributes);
    }

    /**
     * Deletes the specified object from the IMSP server.
     */
    function _delete($object_key, $object_id)
    {
        return $this->_imsp->deleteEntry($this->_bookName, $object_id);
    }

    /**
     * Saves the specified object to the IMSP server.
     *
     * @param string $object_key  (Ignored) name of the field
     *                            in $attributes[] to treat as key.
     * @param string $object_id   The value of the key field.
     * @param array  $attributes  Contains the field names and values of the entry.
     *
     * @return string  The object id, possibly updated.
     */
    function _save($object_key, $object_id, $attributes)
    {
        /* Check if the key changed, because IMSP will just write out
         * a new entry without removing the previous one. */
        if ($attributes['name'] != $this->_makeKey($attributes)) {
            $this->_delete($object_key, $attributes['name']);
            $attributes['name'] = $this->_makeKey($attributes);
            $object_id = $attributes['name'];
        }

        $result = $this->_add($attributes);
        return is_a($result, 'PEAR_Error') ? $result : $object_id;
    }

    /**
     * Create an object key for a new object.
     *
     * @param array $attributes  The attributes (in driver keys) of the
     *                           object being added.
     *
     * @return string  A unique ID for the new object.
     */
    function _makeKey($attributes)
    {
        return $attributes['fullname'];
    }

    /**
     * Parses out $emailText into an array of pure email addresses
     * suitable for searching the IMSP datastore with.
     *
     * @param $emailText string single string containing email addressses.
     * @return array of pure email address.
     */
    function _getGroupEmails($emailText)
    {
        $result = preg_match_all("(\w[-._\w]*\w@\w[-._\w]*\w\.\w{2,3})",
                                 $emailText, $matches);

        return $matches[0];
    }

    /**
     * Parses the search criteria, requests the individual searches from the
     * server and performs any necessary ANDs / ORs on the results.
     *
     * @param array  $criteria  Array containing the search criteria.
     * @param string $glue      Type of search to perform (AND / OR).
     *
     * @return array  Array containing contact names that match $criteria.
     */
    function _doSearch($criteria, $glue)
    {
        $results = array();
        $names = array();
        foreach ($criteria as $key => $vals) {
            if (!empty($vals['OR'])) {
                $results[] = $this->_doSearch($vals['OR'], 'OR');
            } elseif (!empty($vals['AND'])) {
                $results[] = $this->_doSearch($vals['AND'], 'AND');
            } else {
                /* If we are here, and we have a ['field'] then we
                 * must either do the 'AND' or the 'OR' search. */
                if (isset($vals['field'])) {
                    $results[] = $this->_sendSearch($vals);
                } else {
                    foreach ($vals as $test) {
                        if (!empty($test['OR'])) {
                            $results[] = $this->_doSearch($test['OR'], 'OR');
                        } elseif (!empty($test['AND'])) {
                            $results[] = $this->_doSearch($test['AND'], 'AND');
                        } else {
                            $results[] = $this->_doSearch(array($test), $glue);
                        }
                    }
                }
            }
        }

        if ($glue == 'AND') {
            $names = $this->_getDuplicated($results);
        } elseif ($glue == 'OR') {
            $names = $this->_removeDuplicated($results);
        }

        return $names;
    }

    /**
     * Sends a search request to the server.
     *
     * @param array $criteria  Array containing the search critera.
     *
     * @return array  Array containing a list of names that match the search.
     */
    function _sendSearch($criteria)
    {
        global $conf;
        $names = '';
        $imspSearch = array();
        $searchkey = $criteria['field'];
        $searchval = $criteria['test'];
        $searchop = $criteria['op'];
        $hasName = false;
        $this->_noGroups = false;
        $cache = &Horde_Cache::singleton($conf['cache']['driver'], Horde::getDriverConfig('cache', $conf['cache']['driver']));
        $key = implode(".", array_merge($criteria, array($this->_bookName)));

        /* Now make sure we aren't searching on a dynamically created
         * field. */
        switch ($searchkey) {
        case 'fullname':
            if (!$hasName) {
                $searchkey = 'name';
                $hasName = true;
            } else {
                $searchkey = '';
            }
            break;

        case '__owner':
            if (!$this->params['contact_ownership']) {
                $searchkey = '';
                $hasName = true;
            }
            break;
        }

        /* Are we searching for only Turba_Object_Groups or Turba_Objects?
         * This is needed so the 'Show Lists' and 'Show Contacts'
         * links work correctly in Turba. */
        if ($searchkey == '__type') {
            switch ($searchval) {
            case 'Group':
                $searchkey = $this->_groupField;
                $searchval = $this->_groupValue;
                break;

            case 'Object':
                if (!$hasName) {
                    $searchkey = 'name';
                    $searchval = '';
                    $hasName = true;
                } else {
                    $searchkey = '';
                }
                $this->_noGroups = true;
                break;
            }
        }

        if (!$searchkey == '') {
            // Check $searchval for content and for strict matches.
            if (strlen($searchval) > 0) {
                if ($searchop == 'LIKE') {
                    $searchval = '*' . $searchval . '*';
                }
            } else {
                $searchval = '*';
            }
            $imspSearch[$searchkey] = $searchval;
        }
        if (!count($imspSearch)) {
            $imspSearch['name'] = '*';
        }

        /* Finally get to the command.  Check the cache first, since each 'Turba'
           search may consist of a number of identical IMSP searchaddress calls in
           order for the AND and OR parts to work correctly.  15 Second lifetime
           should be reasonable for this. This should reduce load on IMSP server
           somewhat.*/
        $results = $cache->get($key, 15);

        if ($results) {
            $names = explode(',', $results);
        }

        if (!$names) {
            $names = $this->_imsp->search($this->_bookName, $imspSearch);
            if (is_a($names, 'PEAR_Error')) {
                $GLOBALS['notification']->push($names, 'horde.error');
            } else {
                $cache->set($key, implode(",", $names));
                return $names;
            }
        } else {
            return $names;
        }
    }

    /**
     * Returns only those names that are duplicated in $names
     *
     * @param array $names  A nested array of arrays containing names
     *
     * @return array  Array containing the 'AND' of all arrays in $names
     */
    function _getDuplicated($names)
    {
        $results = array();
        $matched = array();
        /* If there is only 1 array, simply return it. */
        if (count($names) < 2) {
            return $names[0];
        } else {
            for ($i = 0; $i < count($names); $i++) {
                $results = array_merge($results, $names[$i]);
            }
            $search = array_count_values($results);
            foreach ($search as $key => $value) {
                if ($value > 1) {
                    $matched[] = $key;
                }
            }
        }

        return $matched;
    }

    /**
     * Returns an array with all duplicate names removed.
     *
     * @param array $names  Nested array of arrays containing names.
     *
     * @return array  Array containg the 'OR' of all arrays in $names.
     */
    function _removeDuplicated($names)
    {
        $unames = array();
        for ($i = 0; $i < count($names); $i++) {
            if (is_array($names[$i])) {
                $unames = array_merge($unames, $names[$i]);
            }
        }
        return array_unique($unames);
    }

    /**
     * Checks if the current user has the requested permission
     * on this source.
     *
     * @param integer $perm  The permission to check for.
     *
     * @return boolean  true if user has permission, false otherwise.
     */
     function hasPermission($perm)
     {
         return $this->_perms & $perm;
     }

     /**
      * Converts an acl string to a Horde Permissions bitmask.
      *
      * @param string $acl  A standard, IMAP style acl string.
      *
      * @return integer  Horde Permissions bitmask.
      */
     function _aclToHordePerms($acl)
     {
         $hPerms = 0;
         if (strpos($acl, 'w') !== false) {
             $hPerms |= PERMS_EDIT;
         }
         if (strpos($acl, 'r') !== false) {
             $hPerms |= PERMS_READ;
         }
         if (strpos($acl, 'd') !== false) {
             $hPerms |= PERMS_DELETE;
         }
         if (strpos($acl, 'l') !== false) {
             $hPerms |= PERMS_SHOW;
         }
         return $hPerms;
     }
}
