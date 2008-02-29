<?php
/**
 * The Turba_Driver:: class provides a common abstracted interface to the
 * various directory search drivers.  It includes functions for searching,
 * adding, removing, and modifying directory entries.
 *
 * $Horde: turba/lib/Driver.php,v 1.57.2.38 2007/07/10 16:41:06 jan Exp $
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@csh.rit.edu>
 * @package Turba
 */
class Turba_Driver {

    /**
     * The internal name of this source.
     *
     * @var string
     */
    var $name;

    /**
     * The symbolic title of this source.
     *
     * @var string
     */
    var $title;

    /**
     * Hash describing the mapping between Turba attributes and
     * driver-specific fields.
     *
     * @var array
     */
    var $map = array();

    /**
     * Hash with all tabs and their fields.
     *
     * @var array
     */
    var $tabs = array();

    /**
     * List of all fields that can be accessed in the backend (excludes
     * composite attributes, etc.).
     *
     * @var array
     */
    var $fields = array();

    /**
     * Array of fields that must match exactly.
     *
     * @var array
     */
    var $strict = array();

    /**
     * Whether this source stores one address book, or multiple private address
     * books.
     *
     * @var boolean
     */
    var $public = false;

    /**
     * Hash holding the driver's additional parameters.
     *
     * @var array
     */
    var $_params = array();

    /**
     * What can this backend do?
     *
     * @var array
     */
    var $_capabilities = array();

    /**
     * Horde_Share object for this source.
     *
     * @var Horde_Share
     */
     var $share;

     /**
      * Are Horde_Shares enabled for this source?
      *
      * @var boolean
      */
     var $usingShares;

    /**
     * Constructs a new Turba_Driver object.
     *
     * @param array $params  Hash containing additional configuration
     *                       parameters.
     */
    function Turba_Driver($params)
    {
        $this->_params = $params;
    }

    /**
     * Returns the current driver's additional parameters.
     *
     * @return array  Hash containing the driver's additional parameters.
     */
    function getParams()
    {
        return $this->_params;
    }

    /**
     * Checks if this backend has a certain capability.
     *
     * @param string $capability  The capability to check for.
     *
     * @return boolean  Supported or not.
     */
    function hasCapability($capability)
    {
        return !empty($this->_capabilities[$capability]);
    }

    /**
     * Translates the keys of the first hash from the generalized Turba
     * attributes to the driver-specific fields. The translation is based on
     * the contents of $this->map. This ignores composite fields.
     *
     * @param array $hash  Hash using Turba keys.
     *
     * @return array  Translated version of $hash.
     */
    function toDriverKeys($hash)
    {
        $fields = array();
        foreach ($hash as $key => $val) {
            if (isset($this->map[$key]) && !is_array($this->map[$key])) {
                $fields[$this->map[$key]] = $val;
            }
        }
        return $fields;
    }

    /**
     * Takes a hash of Turba key => search value and return a (possibly
     * nested) array, using backend attribute names, that can be turned into a
     * search by the driver. The translation is based on the contents of
     * $this->map, and includes nested OR searches for composite fields.
     *
     * @param array  $hash         Hash of criteria using Turba keys.
     * @param string $search_type  OR search or AND search?
     * @param array  $strict       Fields that must be matched exactly.
     *
     * @return array  An array of search criteria.
     */
    function makeSearch($criteria, $search_type, $strict)
    {
        $search = array();
        $strict_search = array();
        $search_terms = array();
        $subsearch = array();
        $temp = '';
        $lastChar = '\"';
        $glue = '';

        foreach ($criteria as $key => $val) {
            if (isset($this->map[$key])) {
                if (is_array($this->map[$key])) {
                    /* Composite field, break out the search terms. */
                    $parts = explode(' ', $val);
                    if (count($parts) > 1) {
                        /* Only parse if there was more than 1 search term and
                         * 'AND' the cumulitive subsearches. */
                        for ($i = 0; $i < count($parts); $i++) {
                            $term = $parts[$i];
                            $firstChar = substr($term, 0, 1);
                            if ($firstChar =="\"") {
                                $temp = substr($term, 1, strlen($term) - 1);
                                $done = false;
                                while (!$done && $i < count($parts) - 1) {
                                    $lastChar = substr($parts[$i + 1], -1);
                                    if ($lastChar =="\"") {
                                        $temp .= ' ' . substr($parts[$i + 1], 0, -1);
                                        $done = true;
                                        $i++;
                                    } else {
                                        $temp .= ' ' . $parts[$i + 1];
                                        $i++;
                                    }
                                }
                                $search_terms[] = $temp;
                            } else {
                                $search_terms[] = $term;
                            }
                        }
                        $glue = 'AND';
                    } else {
                        /* If only one search term, use original input and
                           'OR' the searces since we're only looking for 1
                           term in any of the composite fields. */
                        $search_terms[0] = $val;
                        $glue = 'OR';
                    }
                    foreach ($this->map[$key]['fields'] as $field) {
                        $field = $this->toDriver($field);
                        if (!empty($strict[$field])) {
                            /* For strict matches, use the original search
                             * vals. */
                            $strict_search[] = array('field' => $field,
                                                     'op' => '=',
                                                     'test' => $val);
                        } else {
                            /* Create a subsearch for each individual search
                             * term. */
                            if (count($search_terms) > 1) {
                                /* Build the 'OR' search for each search term
                                 * on this field. */
                                $atomsearch = array();
                                for ($i = 0; $i < count($search_terms); $i++) {
                                    $atomsearch[] = array('field' => $field,
                                                          'op' => 'LIKE',
                                                          'test' => $search_terms[$i]);
                                }
                                $subsearch[] = array('OR' => $atomsearch);
                                unset($atomsearch);
                                $glue = 'AND';
                            } else {
                                /* $parts may have more than one element, but
                                 * if they are all quoted we will only have 1
                                 * $subsearch. */
                                $subsearch[] = array('field' => $field,
                                                     'op' => 'LIKE',
                                                     'test' => $search_terms[0]);
                                $glue = 'OR';
                            }
                        }
                    }
                    if (count($subsearch)) {
                        $search[] = array($glue => $subsearch);
                    }
                } else {
                    /* Not a composite field. */
                    if (!empty($strict[$this->map[$key]])) {
                        $strict_search[] = array('field' => $this->map[$key],
                                                 'op' => '=',
                                                 'test' => $val);
                    } else {
                        $search[] = array('field' => $this->map[$key],
                                          'op' => 'LIKE',
                                          'test' => $val);
                    }
                }
            }
        }

        if (count($strict_search) && count($search)) {
            return array('AND' => array($strict_search,
                                        array($search_type => $search)));
        } elseif (count($strict_search)) {
            return array('AND' => $strict_search);
        } elseif (count($search)) {
            return array($search_type => $search);
        } else {
            return array();
        }
    }

    /**
     * Translates a single Turba attribute to the driver-specific
     * counterpart. The translation is based on the contents of
     * $this->map. This ignores composite fields.
     *
     * @param string $attribute  The Turba attribute to translate.
     *
     * @return string  The driver name for this attribute.
     */
    function toDriver($attribute)
    {
        if (!isset($this->map[$attribute])) {
            return null;
        }

        if (is_array($this->map[$attribute])) {
            return $this->map[$attribute]['fields'];
        } else {
            return $this->map[$attribute];
        }
    }

    /**
     * Translates an array of hashes from being keyed on driver-specific
     * fields to being keyed on the generalized Turba attributes. The
     * translation is based on the contents of $this->map.
     *
     * @param array $objects  Array of hashes using driver-specific keys.
     *
     * @return array  Translated version of $objects.
     */
    function toTurbaKeys($objects)
    {
        $attributes = array();
        foreach ($objects as $entry) {
            $new_entry = array();

            foreach ($this->map as $key => $val) {
                if (!is_array($val)) {
                    $new_entry[$key] = null;
                    if (isset($entry[$val]) && strlen($entry[$val])) {
                        $new_entry[$key] = trim($entry[$val]);
                    }
                }
            }

            $attributes[] = $new_entry;
        }
        return $attributes;
    }

    /**
     * Searches the source based on the provided criteria.
     *
     * @todo Allow $criteria to contain the comparison operator (<, =, >,
     *       'like') and modify the drivers accordingly.
     *
     * @param array $search_criteria  Hash containing the search criteria.
     * @param string $sort_criteria   The requested sort order which is passed
     *                                to Turba_List::sort().
     * @param string $search_type     Do an AND or an OR search (defaults to
     *                                AND).
     * @param array $return_fields    A list of fields to return; defaults to
     *                                all fields.
     * @param array $custom_strict    A list of fields that must match exactly.
     *
     * @return  The sorted, filtered list of search results.
     */
    function &search($search_criteria, $sort_criteria = 'lastname',
                     $search_type = 'AND', $sort_direction = 0,
                     $return_fields = array(), $custom_strict = array())
    {
        require_once TURBA_BASE . '/lib/List.php';
        require_once TURBA_BASE . '/lib/Object.php';

        /* If we are not using Horde_Share, enfore the requirement that the
           current user must be the owner of the addressbook. */
        $strict_fields = array();
        if ($this->usingShares) {
            $user = $this->share->get('uid');
        } else {
            $user = Auth::getAuth();
        }
        $search_criteria['__owner'] = $user;
        $strict_fields = array($this->toDriver('__owner') => true);

        /* Add any fields that must match exactly for this source to the
         * $strict_fields array. */
        foreach ($this->strict as $strict_field) {
            $strict_fields[$strict_field] = true;
        }
        foreach ($custom_strict as $strict_field) {
            $strict_fields[$this->map[$strict_field]] = true;
        }

        /* Translate the Turba attributes to driver-specific attributes. */
        $fields = $this->makeSearch($search_criteria, $search_type, $strict_fields);
        if (count($return_fields)) {
            $return_fields_pre = array_unique(array_merge(array('__key', '__type', '__owner', 'name'), $return_fields));
            $return_fields = array();
            foreach ($return_fields_pre as $field) {
                $result = $this->toDriver($field);
                if (is_array($result)) {
                    foreach ($result as $composite_field) {
                        $composite_result = $this->toDriver($composite_field);
                        if ($composite_result) {
                            $return_fields[] = $composite_result;
                        }
                    }
                } elseif ($result) {
                    $return_fields[] = $result;
                }
            }
        } else {
            $return_fields = array_values($this->fields);
        }

        /* Retrieve the search results from the driver. */
        $objects = $this->_search($fields, $return_fields);
        if (is_a($objects, 'PEAR_Error')) {
            return $objects;
        }

        /* Translate the driver-specific fields in the result back to the more
         * generalized common Turba attributes using the map. */
        $objects = $this->toTurbaKeys($objects);

        require_once TURBA_BASE . '/lib/Object.php';
        $list = &new Turba_List();
        foreach ($objects as $object) {
            $done = false;
            if (!empty($object['__type']) && ucwords($object['__type']) != 'Object') {
                $type = ucwords($object['__type']);
                $class = 'Turba_Object_' . $type;
                if (!class_exists($class)) {
                    require_once TURBA_BASE . '/lib/Object/' . $type . '.php';
                }

                if (class_exists($class)) {
                    $list->insert(new $class($this, $object));
                    $done = true;
                }
            }
            if (!$done) {
                $list->insert(new Turba_Object($this, $object));
            }
        }
        $list->sort($sort_criteria, $sort_direction);

        /* Return the filtered (sorted) results. */
        return $list;
    }

    /**
     * Retrieves a set of objects from the source.
     *
     * @param array $objectIds  The unique ids of the objects to retrieve.
     *
     * @return array  The array of retrieved objects (Turba_Objects).
     */
    function &getObjects($objectIds)
    {
        require_once TURBA_BASE . '/lib/Object.php';
        $criteria = $this->map['__key'];

        $objects = $this->_read($criteria, $objectIds, array_values($this->fields));
        if (is_a($objects, 'PEAR_Error')) {
            return $objects;
        }
        if (!is_array($objects)) {
            $result = PEAR::raiseError(_("Requested object not found."));
            return $result;
        }

        $results = array();
        $objects = $this->toTurbaKeys($objects);
        foreach ($objects as $object) {
            $done = false;
            if (!empty($object['__type']) && ucwords($object['__type']) != 'Object') {
                $type = ucwords($object['__type']);
                $class = 'Turba_Object_' . $type;
                if (!class_exists($class)) {
                    require_once TURBA_BASE . '/lib/Object/' . $type . '.php';
                }

                if (class_exists($class)) {
                    $results[] = &new $class($this, $object);
                    $done = true;
                }
            }
            if (!$done) {
                $results[] = &new Turba_Object($this, $object);
            }
        }

        return $results;
    }

    /**
     * Retrieves one object from the source.
     *
     * @param string $objectId  The unique id of the object to retrieve.
     *
     * @return Turba_Object  The retrieved object.
     */
    function &getObject($objectId)
    {
        $result = &$this->getObjects(array($objectId));
        if (is_a($result, 'PEAR_Error')) {
            // Fall through.
        } elseif (empty($result[0])) {
            $result = PEAR::raiseError('No results');
        } else {
            $result = $result[0];
            if (!isset($this->map['__owner'])) {
                $result->attributes['__owner'] = Auth::getAuth();
            }
        }

        return $result;
    }

    /**
     * Adds a new entry to the contact source.
     *
     * @param array $attributes  The attributes of the new object to add.
     *
     * @return mixed  The new __key value on success, or a PEAR_Error object
     *                on failure.
     */
    function add($attributes)
    {
        /* Only set __type and __owner if they are not already set. */
        if (!isset($attributes['__type'])) {
            $attributes['__type'] = 'Object';
        }
        if (isset($this->map['__owner']) && !isset($attributes['__owner'])) {
            if (strpos($this->name , ':')) {
                 list($source, $attributes['__owner']) = explode(':', $this->name, 2);
            } else {
                $attributes['__owner'] = Auth::getAuth();
            }
        }

        /* Get field values before we translate keys so we can use
         * them in the history log. */
        if (isset($attributes['__owner'])) {
            $turbaOwner = $attributes['__owner'];
        }
        if (!isset($attributes['__uid'])) {
            $attributes['__uid'] = $this->generateUID();
        }

        $key = $attributes['__key'] = $this->_makeKey($this->toDriverKeys($attributes));
        $uid = $attributes['__uid'];

        $attributes = $this->toDriverKeys($attributes);
        $result = $this->_add($attributes);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        /* Log the creation of this item in the history log. */
        $history = &Horde_History::singleton();
        $history->log('turba:' . (!empty($turbaOwner) ? $turbaOwner : Auth::getAuth()) . ':' . $uid, array('action' => 'add'), true);

        return $key;
    }

    /**
     * Deletes the specified entry from the contact source.
     *
     * @param string $object_id  The ID of the object to delete.
     */
    function delete($object_id)
    {
        $object = &$this->getObject($object_id);
        if (is_a($object, 'PEAR_Error')) {
            return $object;
        }

        if (!$object->hasPermission(PERMS_DELETE)) {
            return PEAR::raiseError(_("Permission denied"));
        }

        $result = $this->_delete($this->toDriver('__key'), $object_id);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        /* Log the deletion of this item in the history log. */
        if ($object->getValue('__uid')) {
            $history = &Horde_History::singleton();
            $history->log('turba:' . ($object->getValue('__owner') ? $object->getValue('__owner') : Auth::getAuth()) . ':' . $object->getValue('__uid'),
                          array('action' => 'delete'), true);
        }

        return true;
    }

    /**
     * Deletes all contacts from an address book.
     *
     * @param string  $sourceName  The identifier of the address book to
     *                             delete.  If omitted, will clear the current
     *                             user's 'default' address book for this source
     *                             type.
     *
     * @return mixed  True on success, PEAR_Error on failure.
     */
    function deleteAll($sourceName = '')
    {
        if (!$this->hasCapability('delete_all')) {
            return PEAR::raiseError('Not supported');
        } else {
            return $this->_deleteAll($sourceName);
        }
    }

    /**
     * Modifies an existing entry in the contact source.
     *
     * @param Turba_Object $object  The object to update.
     *
     * @return string  The object id, possibly updated.
     */
    function save($object)
    {
        $attributes = $this->toDriverKeys($object->getAttributes());
        $key = $this->toDriverKeys(array('__key' => $object->getValue('__key')));
        list($object_key, $object_id) = each($key);

        $object_id = $this->_save($object_key, $object_id, $attributes);
        if (is_a($object_id, 'PEAR_Error')) {
            return $object_id;
        }

        /* Log the modification of this item in the history log. */
        if ($object->getValue('__uid')) {
            $history = &Horde_History::singleton();
            $history->log('turba:' . ($object->getValue('__owner') ? $object->getValue('__owner') : Auth::getAuth()) . ':' . $object->getValue('__uid'),
                          array('action' => 'modify'), true);
        }

        return $object_id;
    }

    /**
     * Returns the number of contacts of the current user in this address book.
     *
     * @return integer  The number of contacts that the user owns.
     */
    function countContacts()
    {
        static $count;

        if (!isset($count)) {
            $count = $this->_search(array('AND' => array(array('field' => $this->toDriver('__owner'), 'op' => '=', 'test' => Auth::getAuth()))), array($this->toDriver('__key')));
            if (is_a($count, 'PEAR_Error')) {
                return $count;
            }
            $count = count($count);
        }

        return $count;
    }

    /**
     * Returns the criteria available for this source except '__key'.
     *
     * @return array  An array containing the criteria.
     */
    function getCriteria()
    {
        $criteria = $this->map;
        unset($criteria['__key']);
        return $criteria;
    }

    /**
     * Returns all non-composite fields for this source. Useful for importing
     * and exporting data, etc.
     *
     * @return array  The field list.
     */
    function getFields()
    {
        return array_flip($this->fields);
    }

    /**
     * Generates a universal/unique identifier for a contact. This is NOT
     * something that we expect to be able to parse into an addressbook and a
     * contactId.
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
     * Exports a given Turba_Object as an iCalendar vCard.
     *
     * @param Turba_Object $object    A Turba_Object.
     * @param string       $version   The vcard version to produce.
     *
     * @return Horde_iCalendar_vcard  A Horde_iCalendar_vcard object.
     */
    function tovCard($object, $version = '2.1')
    {
        require_once 'Horde/iCalendar/vcard.php';
        require_once 'Horde/MIME.php';

        $hash = $object->getAttributes();
        $vcard = &new Horde_iCalendar_vcard($version);
        $formattedname = false;
        $charset = $version == '2.1' ? array('CHARSET' => NLS::getCharset()) : array();

        foreach ($hash as $key => $val) {
            if ($version != '2.1') {
                $val = String::convertCharset($val, NLS::getCharset(), 'utf-8');
            }

            switch ($key) {
            case 'name':
                $vcard->setAttribute('FN', $val, MIME::is8bit($val) ? $charset : array());
                $formattedname = true;
                break;
            case 'nickname':
            case 'alias':
                $vcard->setAttribute('NICKNAME', $val, MIME::is8bit($val) ? $charset : array());
                break;
            case 'homePhone':
                if ($version == '2.1') {
                    $vcard->setAttribute('TEL', $val, array('HOME' => null));
                } else {
                    $vcard->setAttribute('TEL', $val, array('TYPE'=>'HOME'));
                }
                break;
            case 'workPhone':
                if ($version == '2.1') {
                    $vcard->setAttribute('TEL', $val, array('WORK' => null));
                } else {
                    $vcard->setAttribute('TEL', $val, array('TYPE'=>'WORK'));
                }
                break;
            case 'cellPhone':
                if ($version == '2.1') {
                    $vcard->setAttribute('TEL', $val, array('CELL' => null));
                } else {
                    $vcard->setAttribute('TEL', $val, array('TYPE'=>'CELL'));
                }
                break;

            case 'fax':
                if ($version == '2.1') {
                    $vcard->setAttribute('TEL', $val, array('FAX' => null));
                } else {
                    $vcard->setAttribute('TEL', $val, array('TYPE'=>'FAX'));
                }
                break;

            case 'email':
                $vcard->setAttribute('EMAIL', Horde_iCalendar_vcard::getBareEmail($val));
                break;

            case 'title':
                $vcard->setAttribute('TITLE', $val, MIME::is8bit($val) ? $charset : array());
                break;

            case 'notes':
                $vcard->setAttribute('NOTE', $val, MIME::is8bit($val) ? $charset : array());
                break;

            case 'website':
                $vcard->setAttribute('URL', $val);
                break;

            case 'birthday':
                $vcard->setAttribute('BDAY', $val);
                break;
            }
        }

        // No explicit firstname/lastname in data source: we have to guess.
        if (!isset($hash['lastname'])) {
            $i = strpos($hash['name'], ',');
            if (is_int($i)) {
                // Assume Last, First
                $hash['lastname'] = String::substr($hash['name'], 0, $i);
                $hash['firstname'] = trim(String::substr($hash['name'], $i + 1));
            } elseif (is_int(strpos($hash['name'], ' '))) {
                // Assume everything after last space as lastname
                $i = strrpos($hash['name'], ' ');
                $hash['lastname'] = trim(String::substr($hash['name'], $i + 1));
                $hash['firstname'] = String::substr($hash['name'], 0, $i);
            } else {
                $hash['lastname'] = $hash['name'];
                $hash['firstname'] = '';
            }
        }

        $a = array(
            VCARD_N_FAMILY => isset($hash['lastname']) ? $hash['lastname'] : '',
            VCARD_N_GIVEN  => isset($hash['firstname']) ? $hash['firstname'] : '',
            VCARD_N_ADDL   => isset($hash['initials']) ? $hash['initials'] : '',
            VCARD_N_PREFIX => isset($hash['salutation']) ? $hash['salutation'] : '',
            VCARD_N_SUFFIX => '',
        );
        $val = implode(';', $a);
        if ($version != '2.1') {
            $val = String::convertCharset($val, NLS::getCharset(), 'utf-8');
            $a = String::convertCharset($a, NLS::getCharset(), 'utf-8');
        }
        $vcard->setAttribute('N', $val, MIME::is8bit($val) ? $charset : array(), false, $a);

        if (!$formattedname) {
            $val = empty($hash['firstname']) ? $hash['lastname'] : $hash['firstname'] . ' ' . $hash['lastname'];
            $vcard->setAttribute('FN', $val, MIME::is8bit($val) ? $charset : array());
        }

        $org = array();
        if (isset($hash['company'])) {
            $org[] = $hash['company'];
        }
        if (isset($hash['department'])) {
            $org[] = $hash['department'];
        }
        $val = implode(';', $org);
        if ($version != '2.1') {
            $val = String::convertCharset($val, NLS::getCharset(), 'utf-8');
            $org = String::convertCharset($org, NLS::getCharset(), 'utf-8');
        }
        $vcard->setAttribute('ORG', $val, MIME::is8bit($val) ? $charset : array(), false, $org);

        /* We can't know if this particular turba source uses a single Address
         * field or multiple for street/city/province/postcode/country. Try to
         * deal with both. */
        if (isset($hash['homeAddress']) && !isset($hash['homeStreet'])) {
            $hash['homeStreet'] = $hash['homeAddress'];
        }
        $a = array(
            VCARD_ADR_POB      => '',
            VCARD_ADR_EXTEND   => '',
            VCARD_ADR_STREET   => isset($hash['homeStreet']) ? $hash['homeStreet'] : '',
            VCARD_ADR_LOCALITY => isset($hash['homeCity']) ? $hash['homeCity'] : '',
            VCARD_ADR_REGION   => isset($hash['homeProvince']) ? $hash['homeProvince'] : '',
            VCARD_ADR_POSTCODE => isset($hash['homePostalCode']) ? $hash['homePostalCode'] : '',
            VCARD_ADR_COUNTRY  => isset($hash['homeCountry']) ? $hash['homeCountry'] : '',
        );

        $val = implode(';', $a);
        if ($version == '2.1') {
            $params = array('HOME' => null);
            if (MIME::is8bit($val)) {
                $params['CHARSET'] = NLS::getCharset();
            }
        } else {
            $params = array('TYPE' => 'HOME');
            $val = String::convertCharset($val, NLS::getCharset(), 'utf-8');
            $a = String::convertCharset($a, NLS::getCharset(), 'utf-8');
        }
        $vcard->setAttribute('ADR', $val, $params, true, $a);

        if (isset($hash['workAddress']) && !isset($hash['workStreet'])) {
            $hash['workStreet'] = $hash['workAddress'];
        }
        $a = array(
            VCARD_ADR_POB      => '',
            VCARD_ADR_EXTEND   => '',
            VCARD_ADR_STREET   => isset($hash['workStreet']) ? $hash['workStreet'] : '',
            VCARD_ADR_LOCALITY => isset($hash['workCity']) ? $hash['workCity'] : '',
            VCARD_ADR_REGION   => isset($hash['workProvince']) ? $hash['workProvince'] : '',
            VCARD_ADR_POSTCODE => isset($hash['workPostalCode']) ? $hash['workPostalCode'] : '',
            VCARD_ADR_COUNTRY  => isset($hash['workCountry']) ? $hash['workCountry'] : '',
        );

        $val = implode(';', $a);
        if ($version == '2.1') {
            $params = array('WORK' => null);
            if (MIME::is8bit($val)) {
                $params['CHARSET'] = NLS::getCharset();
            }
        } else {
            $params = array('TYPE' => 'WORK');
            $val = String::convertCharset($val, NLS::getCharset(), 'utf-8');
            $a = String::convertCharset($a, NLS::getCharset(), 'utf-8');
        }
        $vcard->setAttribute('ADR', $val, $params, true, $a);

        return $vcard;
    }

    /**
     * Static function to convert a Horde_iCalendar_vcard object into a Turba
     * Object Hash with Turba attributes suitable as a parameter for add().
     *
     * @see add()
     *
     * @param Horde_iCalendar_vcard $vcard  The Horde_iCalendar_vcard object
     *                                      to parse.
     *
     * @return array  A Turba attribute hash.
     */
    function toHash(&$vcard)
    {
        if (!is_a($vcard, 'Horde_iCalendar_vcard')) {
            return PEAR::raiseError('Invalid parameter for Turba_Driver::toHash(), expected Horde_iCalendar_vcard object.');
        }

        $hash = array();
        $attr = $vcard->getAllAttributes();
        foreach ($attr as $item) {
            if (empty($item['value'])) {
                continue;
            }

            switch ($item['name']) {
            case 'FN':
                if (isset($this->fields['name'])) {
                    $hash['name'] = $item['value'];
                }
                break;

            case 'N':
                $name = $item['values'];
                $hash['lastname'] = $name[VCARD_N_FAMILY];
                $hash['firstname'] = $name[VCARD_N_GIVEN];
                break;

            case 'NICKNAME':
                $hash['nickname'] = $item['value'];
                $hash['alias'] = $item['value'];
                break;

            // We use LABEL but also support ADR.
            case 'LABEL':
                if (isset($item['params']['HOME'])) {
                    $hash['homeAddress'] = $item['value'];
                } elseif (isset($item['params']['WORK'])) {
                    $hash['workAddress'] = $item['value'];
                } else {
                    $hash['workAddress'] = $item['value'];
                }
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
                        $prefix = 'home';
                        break;

                    case 'WORK':
                        $prefix = 'work';
                        break;

                    default:
                        $prefix = null;
                    }

                    if ($prefix) {
                        $hash[$prefix . 'Address'] = '';

                        if (!empty($address[VCARD_ADR_STREET])) {
                            $hash[$prefix . 'Street'] = $address[VCARD_ADR_STREET];
                            $hash[$prefix . 'Address'] .= $hash[$prefix . 'Street'] . "\n";
                        }
                        if (!empty($address[VCARD_ADR_LOCALITY])) {
                            $hash[$prefix . 'City'] = $address[VCARD_ADR_LOCALITY];
                            $hash[$prefix . 'Address'] .= $hash[$prefix . 'City'];
                        }
                        if (!empty($address[VCARD_ADR_REGION])) {
                            $hash[$prefix . 'Province'] = $address[VCARD_ADR_REGION];
                            $hash[$prefix . 'Address'] .= ', ' . $hash[$prefix . 'Province'];
                        }
                        if (!empty($address[VCARD_ADR_POSTCODE])) {
                            $hash[$prefix . 'PostalCode'] = $address[VCARD_ADR_POSTCODE];
                            $hash[$prefix . 'Address'] .= ' ' . $hash[$prefix . 'PostalCode'];
                        }
                        if (!empty($address[VCARD_ADR_COUNTRY])) {
                            $hash[$prefix . 'Country'] = $address[VCARD_ADR_COUNTRY];
                            $hash[$prefix . 'Address'] .= "\n" . $hash[$prefix . 'Country'];
                        }

                        $hash[$prefix . 'Address'] = trim($hash[$prefix . 'Address']);
                    }
                }
                break;

            case 'TEL':
                if (isset($item['params']['FAX'])) {
                    $hash['fax'] = $item['value'];
                } elseif (isset($item['params']['TYPE'])) {
                    if (!is_array($item['params']['TYPE'])) {
                        $item['params']['TYPE'] = array($item['params']['TYPE']);
                    }
                    // For vCard 3.0.
                    foreach ($item['params']['TYPE'] as $tel) {
                        if (String::upper($tel) == 'WORK') {
                            $hash['workPhone'] = $item['value'];
                        } elseif (String::upper($tel) == 'HOME') {
                            $hash['homePhone'] = $item['value'];
                        } elseif (String::upper($tel) == 'CELL') {
                            $hash['cellPhone'] = $item['value'];
                        } elseif (String::upper($tel) == 'FAX') {
                            $hash['fax'] = $item['value'];
                        }
                    }
                } else {
                    if (isset($item['params']['CELL'])) {
                        $hash['cellPhone'] = $item['value'];
                    } elseif (isset($item['params']['HOME'])) {
                        $hash['homePhone'] = $item['value'];
                    } elseif (isset($item['params']['WORK'])) {
                        $hash['workPhone'] = $item['value'];
                    } else {
                        $hash['homePhone'] = $item['value'];
                    }
                }
                break;

            case 'EMAIL':
                if (isset($item['params']['PREF']) || !isset($hash['email'])) {
                    $hash['email'] = Horde_iCalendar_vcard::getBareEmail($item['value']);
                }
                break;

            case 'TITLE':
                $hash['title'] = $item['value'];
                break;

            case 'ORG':
                // The VCARD 2.1 specification requires the presence of two
                // SEMI-COLON separated fields: Organizational Name and
                // Organizational Unit. Additional fields are optional.
                $hash['company'] = !empty($item['values'][0]) ? $item['values'][0] : '';
                $hash['department'] = !empty($item['values'][1]) ? $item['values'][1] : '';
                break;

            case 'NOTE':
                $hash['notes'] = $item['value'];
                break;

            case 'URL':
                $hash['website'] = $item['value'];
                break;

            case 'BDAY':
                $hash['birthday'] = $item['value']['year'] . '-' . $item['value']['month'] . '-' .  $item['value']['mday'];
                break;
            }
        }

        /* Ensure we have a valid name field. */
        if (isset($this->fields['name']) && empty($hash['name'])) {
            $hash['name'] = $hash['firstname'] . ' ' . $hash['lastname'];
            if (trim($hash['name']) === '') {
                $hash['name'] = $vcard->getAttributeDefault('FN');
            }
        }
        if (isset($this->fields['lastname']) && empty($hash['lastname'])) {
            $hash['lastname'] = $vcard->getAttributeDefault('FN');
        }

        return $hash;
    }

    /**
     * Checks if the current user has the requested permissions on this
     * source.
     *
     * @param integer $perm  The permission to check for.
     *
     * @return boolean  True if the user has permission, otherwise false.
     */
     function hasPermission($perm)
     {
         if ($this->usingShares) {
             return $this->share->hasPermission(Auth::getAuth(), $perm);
         } else {
             return Turba::hasPermission($this->name, 'source', $perm);
         }
     }

    /**
     * Creates an object key for a new object.
     *
     * @param array $attributes  The attributes (in driver keys) of the
     *                           object being added.
     *
     * @return string  A unique ID for the new object.
     */
    function _makeKey($attributes)
    {
        return md5(mt_rand());
    }

    /**
     * Static method to construct Turba_Driver objects. Use this so that we
     * can return PEAR_Error objects if anything goes wrong.
     *
     * Should only be called by Turba_Driver::singleton().
     *
     * @see Turba_Driver::singleton()
     * @access private
     *
     * @param string $name   String containing the internal name of this
     *                       source.
     * @param array $config  Array containing the configuration information for
     *                       this source.
     */
    function &factory($name, $config)
    {
        $class = basename($config['type']);
        include_once dirname(__FILE__) . '/Driver/' . $class . '.php';
        $class = 'Turba_Driver_' . $class;
        if (class_exists($class)) {
            $driver = &new $class($config['params']);
        } else {
            $driver = PEAR::raiseError(sprintf(_("Unable to load the definition of %s."), $class));
            return $driver;
        }

        $result = $driver->_init();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        /* Store name and title. */
        $driver->name = $name;
        $driver->title = $config['title'];

        /* Store and translate the map at the Source level. */
        $driver->map = $config['map'];
        foreach ($driver->map as $key => $val) {
            if (!is_array($val)) {
                $driver->fields[$key] = $val;
            }
        }

        /* Store tabs. */
        if (isset($config['tabs'])) {
            $driver->tabs = $config['tabs'];
        }

        /* Store strict fields. */
        if (isset($config['strict'])) {
            $driver->strict = $config['strict'];
        }

        /* Get a share object if we need it. */
        if (!empty($config['use_shares'])) {
            if ((strpos($name, ':') === false)) {
                if (!$GLOBALS['turba_shares']->exists($name . ':' . Auth::getAuth())) {
                    /* User's 'default' share for this source type not present */
                    $params = array('sourceType' => $name);
                    $share = &Turba::createShare($params, true);
                    if (is_a($share, 'PEAR_Error')) {
                        return $share;
                    }
                }
                $name .= ':' . Auth::getAuth();
            }
            $driver->share = &$GLOBALS['turba_shares']->getShare($name);
            if (is_a($driver->share, 'PEAR_Error')) {
                return $driver->share;
            }
            $driver->usingShares = true;
        } else {
            $driver->usingShares = false;
        }

        return $driver;
    }

    /**
     * Attempts to return a reference to a concrete Turba_Driver instance
     * based on the $config array. It will only create a new instance if no
     * Turba_Driver instance with the same parameters currently exists.
     *
     * This method must be invoked as:
     *   $driver = &Turba_Driver::singleton()
     *
     * @param string $name   String containing the internal name of this
     *                       source.
     *
     * @return Turba_Driver  The concrete Turba_Driver reference, or a
     *                       PEAR_Error on error.
     */
    function &singleton($name)
    {
        static $instances = array();

        if (!isset($instances[$name])) {
            if (!isset($GLOBALS['cfgSources'][$name])) {
                $error = PEAR::raiseError(sprintf(_("The address book \"%s\" does not exist."), $name));
                return $error;
            }
            $instances[$name] = &Turba_Driver::factory($name, $GLOBALS['cfgSources'][$name]);
        }

        return $instances[$name];
    }

}
