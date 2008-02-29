<?php

/**
 * Ingo_Storage:: 'combine' constants
 */
define('INGO_STORAGE_COMBINE_ALL', 1);
define('INGO_STORAGE_COMBINE_ANY', 2);

/**
 * Ingo_Storage:: 'action' constants
 */
define('INGO_STORAGE_ACTION_FILTERS', 0);
define('INGO_STORAGE_ACTION_KEEP', 1);
define('INGO_STORAGE_ACTION_MOVE', 2);
define('INGO_STORAGE_ACTION_DISCARD', 3);
define('INGO_STORAGE_ACTION_REDIRECT', 4);
define('INGO_STORAGE_ACTION_REDIRECTKEEP', 5);
define('INGO_STORAGE_ACTION_REJECT', 6);
define('INGO_STORAGE_ACTION_BLACKLIST', 7);
define('INGO_STORAGE_ACTION_VACATION', 8);
define('INGO_STORAGE_ACTION_WHITELIST', 9);
define('INGO_STORAGE_ACTION_FORWARD', 10);
define('INGO_STORAGE_ACTION_MOVEKEEP', 11);
define('INGO_STORAGE_ACTION_FLAGONLY', 12);
define('INGO_STORAGE_ACTION_NOTIFY', 13);

/**
 * Ingo_Storage:: 'flags' constants
 */
define('INGO_STORAGE_FLAG_ANSWERED', 1);
define('INGO_STORAGE_FLAG_DELETED', 2);
define('INGO_STORAGE_FLAG_FLAGGED', 4);
define('INGO_STORAGE_FLAG_SEEN', 8);

/**
 * Ingo_Storage:: 'type' constants.
 */
define('INGO_STORAGE_TYPE_HEADER', 1);
define('INGO_STORAGE_TYPE_SIZE', 2);
define('INGO_STORAGE_TYPE_BODY', 3);

/**
 * Ingo_Storage:: defines an API to store the various filter rules.
 *
 * $Horde: ingo/lib/Storage.php,v 1.43.8.16 2006/05/01 13:17:38 jan Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Ingo 0.1
 * @package Ingo
 */
class Ingo_Storage {

    /**
     * Driver specific parameters.
     *
     * @var array
     */
    var $_params = array();

    /**
     * Cached rule objects.
     *
     * @var array
     */
    var $_cache = array();

    /**
     * Has _addShutdownCache() been called yet?
     *
     * @var boolean
     */
    var $_shutdownCache = false;

    /**
     * Attempts to return a concrete Ingo_Storage instance based on $driver.
     *
     * @param string $driver  The type of concrete Ingo_Storage subclass to
     *                        return.  This is based on the storage driver
     *                        ($driver).  The code is dynamically included.
     * @param array $params   A hash containing any additional configuration or
     *                        connection parameters a subclass might need.
     *
     * @return mixed  The newly created concrete Ingo_Storage instance, or
     *                false on an error.
     */
    function &factory($driver = null, $params = null)
    {
        if (is_null($driver)) {
            $driver = $GLOBALS['conf']['storage']['driver'];
        }

        $driver = basename($driver);

        if (is_null($params)) {
            $params = Horde::getDriverConfig('storage', $driver);
        }

        require_once dirname(__FILE__) . '/Storage/' . $driver . '.php';
        $class = 'Ingo_Storage_' . $driver;
        if (class_exists($class)) {
            $ingo = &new $class($params);
        } else {
            $ingo = false;
        }

        return $ingo;
    }

    /**
     * Attempts to return a reference to a concrete Ingo_Storage instance
     * based on $driver.  It will only create a new instance if no
     * Ingo_Storage instance with the same parameters currently exists.
     *
     * This should be used if multiple storage sources are required.
     *
     * This method must be invoked as: $var = &Ingo_Storage::singleton();
     *
     * @param string $driver  The type of concrete Ingo_Storage subclass to
     *                        return.  This is based on the storage driver
     *                        ($driver).  The code is dynamically included.
     * @param array $params  A hash containing any additional configuration or
     *                       connection parameters a subclass might need.
     *
     * @return mixed  The created concrete Ingo_Storage instance, or false
     *                on error.
     */
    function &singleton($driver = null, $params = null)
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

        $signature = serialize(array($driver, $params));
        if (!isset($instances[$signature])) {
            $instances[$signature] = &Ingo_Storage::factory($driver, $params);
        }

        return $instances[$signature];
    }

    /**
     * Retrieves the specified data.
     *
     * @param integer $field  The field name of the desired data
     *                        (INGO_STORAGE_ACTION_* constants).
     * @param boolean $cache  Use the cached object?
     *
     * @return Ingo_Storage_rule|Ingo_Storage_filters  The specified object.
     */
    function &retrieve($field, $cache = true)
    {
        if ($cache) {
            if (!isset($this->_cache[$field])) {
                $this->_cache[$field] = array('mod' => false);
                if (isset($_SESSION['ingo']['storage'][$field])) {
                    require_once 'Horde/SessionObjects.php';
                    $cacheSess = &Horde_SessionObjects::singleton();
                    $this->_cache[$field]['ob'] = $cacheSess->query($_SESSION['ingo']['storage'][$field]);
                } else {
                    $this->_cache[$field]['ob'] = &$this->_retrieve($field);
                }
                if (!$this->_shutdownCache) {
                    register_shutdown_function(array(&$this, '_addCacheShutdown'));
                    $this->_shutdownCache = true;
                }
            }
            $ob = &$this->_cache[$field]['ob'];
        } else {
            $ob = &$this->_retrieve($field);
        }

        return $ob;
    }

    /**
     * Retrieves the specified data from the storage backend.
     *
     * @abstract
     * @access private
     *
     * @param integer $field  The field name of the desired data.
     *                        See lib/Storage.php for the available fields.
     *
     * @return Ingo_Storage_rule|Ingo_Storage_filters  The specified data.
     */
    function _retrieve($field)
    {
        return false;
    }

    /**
     * Stores the specified data.
     *
     * @param Ingo_Storage_rule|Ingo_Storage_filters $ob  The object to store.
     * @param boolean $cache                              Cache the object?
     *
     * @return boolean  True on success.
     */
    function store(&$ob, $cache = true)
    {
        $type = $ob->obType();
        if (in_array($type, array(INGO_STORAGE_ACTION_BLACKLIST,
                                  INGO_STORAGE_ACTION_VACATION,
                                  INGO_STORAGE_ACTION_WHITELIST,
                                  INGO_STORAGE_ACTION_FORWARD))) {
            $filters = $this->retrieve(INGO_STORAGE_ACTION_FILTERS);
            if ($filters->findRuleId($type) === null) {
                switch ($type) {
                case INGO_STORAGE_ACTION_BLACKLIST:
                    $name = 'Blacklist';
                    break;
                case INGO_STORAGE_ACTION_VACATION:
                    $name = 'Vacation';
                    break;
                case INGO_STORAGE_ACTION_WHITELIST:
                    $name = 'Whitelist';
                    break;
                case INGO_STORAGE_ACTION_FORWARD:
                    $name = 'Forward';
                    break;
                }
                $filters->addRule(array('action' => $type, 'name' => $name));
                $result = $this->store($filters, $cache);
                if (is_a($result, 'PEAR_Error')) {
                    return $result;
                }
            }
        }

        if ($cache) {
            $this->_cache[$ob->obType()] = array('ob' => $ob, 'mod' => true);
            if (!$this->_shutdownCache) {
                register_shutdown_function(array(&$this, '_addCacheShutdown'));
                $this->_shutdownCache = true;
            }
        }

        return $this->_store($ob);
    }

    /**
     * Stores the specified data in the storage backend.
     *
     * @abstract
     * @access private
     *
     * @param Ingo_Storage_rule|Ingo_Storage_filters $ob  The object to store.
     *
     * @return boolean  True on success.
     */
    function _store(&$ob)
    {
        return false;
    }

    /**
     * Saves a copy of objects at the end of a request.
     *
     * @access private
     */
    function _addCacheShutdown()
    {
        require_once 'Horde/SessionObjects.php';
        $cache = &Horde_SessionObjects::singleton();

        /* Store the current objects. */
        foreach ($this->_cache as $key => $val) {
            if (!$val['mod'] && isset($_SESSION['ingo']['storage'][$key])) {
                continue;
            }
            if (isset($_SESSION['ingo']['storage'][$key])) {
                $cache->setPruneFlag($_SESSION['ingo']['storage'][$key], true);
            }
            $_SESSION['ingo']['storage'][$key] = $cache->storeOid($val['ob'], false);
        }
    }

    /**
     * Returns information on a given action constant.
     *
     * @param integer $action  The INGO_STORAGE_ACTION_* value.
     *
     * @return stdClass  Object with the following values:
     * <pre>
     * 'flags' => (boolean) Does this action allow flags to be set?
     * 'label' => (string) The label for this action.
     * 'type'  => (string) Either 'folder', 'text', or empty.
     * </pre>
     */
    function getActionInfo($action)
    {
        $ob = &new stdClass;
        $ob->flags = false;
        $ob->type = 'text';

        switch ($action) {
        case INGO_STORAGE_ACTION_KEEP:
            $ob->label = _("Deliver into my Inbox");
            $ob->type = false;
            $ob->flags = true;
            break;

        case INGO_STORAGE_ACTION_MOVE:
            $ob->label = _("Deliver to folder");
            $ob->type = 'folder';
            $ob->flags = true;
            break;

        case INGO_STORAGE_ACTION_DISCARD:
            $ob->label = _("Delete message completely");
            $ob->type = false;
            break;

        case INGO_STORAGE_ACTION_REDIRECT:
            $ob->label = _("Redirect to");
            break;

        case INGO_STORAGE_ACTION_REDIRECTKEEP:
            $ob->label = _("Deliver into my Inbox and redirect to");
            $ob->flags = true;
            break;

        case INGO_STORAGE_ACTION_MOVEKEEP:
            $ob->label = _("Deliver into my Inbox and copy to");
            $ob->type = 'folder';
            $ob->flags = true;
            break;

        case INGO_STORAGE_ACTION_REJECT:
            $ob->label = _("Reject with reason");
            break;

        case INGO_STORAGE_ACTION_FLAGONLY:
            $ob->label = _("Only flag the message");
            $ob->type = false;
            $ob->flags = true;
            break;

        case INGO_STORAGE_ACTION_NOTIFY:
            $ob->label = _("Notify email address");
            break;
        }

        return $ob;
    }

    /**
     * Returns information on a given test string.
     *
     * @param string $action  The test string.
     *
     * @return stdClass  Object with the following values:
     * <pre>
     * 'label' => (string) The label for this action.
     * 'type'  => (string) Either 'int', 'none', or 'text'.
     * </pre>
     */
    function getTestInfo($test)
    {
        /* Mapping of gettext strings -> labels. */
        $labels = array(
            'contains' => _("Contains"),
            'not contain' =>  _("Doesn't contain"),
            'is' => _("Is"),
            'not is' => _("Isn't"),
            'begins with' => _("Begins with"),
            'not begins with' => _("Doesn't begin with"),
            'ends with' => _("Ends with"),
            'not ends with' => _("Doesn't end with"),
            'exists' =>  _("Exists"),
            'not exist' => _("Doesn't exist"),
            'regex' => _("Regular expression"),
            'matches' => _("Matches (with placeholders)"),
            'not matches' => _("Doesn't match (with placeholders)"),
            'less than' => _("Less than"),
            'less than or equal to' => _("Less than or equal to"),
            'greater than' => _("Greater than"),
            'greater than or equal to' => _("Greater than or equal to"),
            'equal' => _("Equal to"),
            'not equal' => _("Not equal to")
        );

        /* The type of tests available. */
        $types = array(
            'int'  => array(
                'less than', 'less than or equal to', 'greater than',
                'greater than or equal to', 'equal', 'not equal'
            ),
            'none' => array(
                'exists', 'not exist'
            ),
            'text' => array(
                'contains', 'not contain', 'is', 'not is', 'begins with',
                'not begins with', 'ends with', 'not ends with', 'regex',
                'matches', 'not matches'
            )
        );

        /* Create the information object. */
        $ob = &new stdClass;
        $ob->label = $labels[$test];
        foreach ($types as $key => $val) {
            if (in_array($test, $val)) {
                $ob->type = $key;
                break;
            }
        }

        return $ob;
    }

}

/**
 * Ingo_Storage_rule:: is the base class for the various action objects
 * used by Ingo_Storage.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Storage_rule {

    /**
     * The object type.
     *
     * @var integer
     */
    var $_obtype;

    /**
     * Returns the object rule type.
     *
     * @return integer  The object rule type.
     */
    function obType()
    {
        return $this->_obtype;
    }

    /**
     * Function to manage an internal address list.
     *
     * @access private
     *
     * @param mixed $data    The incoming data (array or string).
     * @param boolean $sort  Sort the list?
     *
     * @return array  The address list.
     */
    function &_addressList($data, $sort)
    {
        $output = array();

        if (is_array($data)) {
            $output = $data;
        } else {
            $data = trim($data);
            $output = (empty($data)) ? array() : preg_split("/\s+/", $data);
        }

        if ($sort) {
            require_once 'Horde/Array.php';
            $output = Horde_Array::prepareAddressList($output);
        }

        return $output;
    }

}

/**
 * Ingo_Storage_blacklist is the object used to hold blacklist rule
 * information.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Storage_blacklist extends Ingo_Storage_rule {

    var $_addr = array();
    var $_folder = '';
    var $_obtype = INGO_STORAGE_ACTION_BLACKLIST;

    /**
     * Sets the list of blacklisted addresses.
     *
     * @param mixed $data    The list of addresses (array or string).
     * @param boolean $sort  Sort the list?
     *
     * @return mixed  PEAR_Error on error, true on success.
     */
    function setBlacklist($data, $sort = true)
    {
        $addr = &$this->_addressList($data, $sort);
        if (!empty($GLOBALS['conf']['storage']['maxblacklist'])) {
            $addr_count = count($addr);
            if ($addr_count > $GLOBALS['conf']['storage']['maxblacklist']) {
                return PEAR::raiseError(sprintf(_("Maximum number of blacklisted addresses exceeded (Total addresses: %s, Maximum addresses: %s).  Could not add new addresses to blacklist."), $addr_count, $GLOBALS['conf']['storage']['maxblacklist']), 'horde.error');
            }
        }

        $this->_addr = $addr;
        return true;
    }

    function setBlacklistFolder($data)
    {
        $this->_folder = $data;
    }

    function getBlacklist()
    {
        return array_filter($this->_addr, array('Ingo', '_filterEmptyAddress'));
    }

    function getBlacklistFolder()
    {
        return $this->_folder;
    }

}

/**
 * Ingo_Storage_whitelist is the object used to hold whitelist rule
 * information.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Storage_whitelist extends Ingo_Storage_rule {

    var $_addr = array();
    var $_obtype = INGO_STORAGE_ACTION_WHITELIST;

    /**
     * Sets the list of whitelisted addresses.
     *
     * @param mixed $data    The list of addresses (array or string).
     * @param boolean $sort  Sort the list?
     *
     * @return mixed  PEAR_Error on error, true on success.
     */
    function setWhitelist($data, $sort = true)
    {
        $addr = &$this->_addressList($data, $sort);
        $addr = array_filter($addr, array('Ingo', '_filterEmptyAddress'));
        if (!empty($GLOBALS['conf']['storage']['maxwhitelist'])) {
            $addr_count = count($addr);
            if ($addr_count > $GLOBALS['conf']['storage']['maxwhitelist']) {
                return PEAR::raiseError(sprintf(_("Maximum number of whitelisted addresses exceeded (Total addresses: %s, Maximum addresses: %s).  Could not add new addresses to whitelist."), $addr_count, $GLOBALS['conf']['storage']['maxwhitelist']), 'horde.error');
            }
        }

        $this->_addr = $addr;
        return true;
    }

    function getWhitelist()
    {
        return array_filter($this->_addr, array('Ingo', '_filterEmptyAddress'));
    }

}

/**
 * Ingo_Storage_forward is the object used to hold mail forwarding rule
 * information.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Storage_forward extends Ingo_Storage_rule {

    var $_addr = array();
    var $_keep = true;
    var $_obtype = INGO_STORAGE_ACTION_FORWARD;

    function setForwardAddresses($data, $sort = true)
    {
        $this->_addr = &$this->_addressList($data, $sort);
    }

    function setForwardKeep($data)
    {
        $this->_keep = $data;
    }

    function getForwardAddresses()
    {
        return $this->_addr;
    }

    function getForwardKeep()
    {
        return $this->_keep;
    }

}

/**
 * Ingo_Storage_vacation is the object used to hold vacation rule
 * information.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Storage_vacation extends Ingo_Storage_rule {

    var $_addr = array();
    var $_days = 7;
    var $_excludes = array();
    var $_ignorelist = true;
    var $_obtype = INGO_STORAGE_ACTION_VACATION;
    var $_reason = '';
    var $_subject = '';

    function setVacationAddresses($data, $sort = true)
    {
        $this->_addr = &$this->_addressList($data, $sort);
    }

    function setVacationDays($data)
    {
        $this->_days = $data;
    }

    function setVacationExcludes($data, $sort = true)
    {
        $this->_excludes = &$this->_addressList($data, $sort);
    }

    function setVacationIgnorelist($data)
    {
        $this->_ignorelist = $data;
    }

    function setVacationReason($data)
    {
        $this->_reason = $data;
    }

    function setVacationSubject($data)
    {
        $this->_subject = $data;
    }

    function getVacationAddresses()
    {
        return $this->_addr;
    }

    function getVacationDays()
    {
        return $this->_days;
    }

    function getVacationExcludes()
    {
        return $this->_excludes;
    }

    function getVacationIgnorelist()
    {
        return $this->_ignorelist;
    }

    function getVacationReason()
    {
        return $this->_reason;
    }

    function getVacationSubject()
    {
        return $this->_subject;
    }

}

/**
 * Ingo_Storage_filters is the object used to hold user-defined filtering rule
 * information.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Ingo 1.0
 * @package Ingo
 */
class Ingo_Storage_filters {

    /**
     * The filter list.
     *
     * @var array
     */
    var $_filters = array();

    /**
     * The object type.
     *
     * @var integer
     */
    var $_obtype = INGO_STORAGE_ACTION_FILTERS;

    /**
     * Returns the object rule type.
     *
     * @return integer  The object rule type.
     */
    function obType()
    {
        return $this->_obtype;
    }

    /**
     * Propagates the filter list with data.
     *
     * @param array $data  A list of rule hashes.
     */
    function setFilterlist($data)
    {
        $this->_filters = $data;
    }

    /**
     * Returns the filter list.
     *
     * @return array  The list of rule hashes.
     */
    function getFilterlist()
    {
        return $this->_filters;
    }

    /**
     * Returns a single rule hash.
     *
     * @param integer $id  A rule number.
     *
     * @return array  The requested rule hash.
     */
    function getRule($id)
    {
        return $this->_filters[$id];
    }

    /**
     * Returns a rule hash with default value used when creating new rules.
     *
     * @return array  A rule hash.
     */
    function getDefaultRule()
    {
        return array(
            'name' => _("New Rule"),
            'combine' => INGO_STORAGE_COMBINE_ALL,
            'conditions' => array(),
            'action' => INGO_STORAGE_ACTION_KEEP,
            'action-value' => '',
            'stop' => true,
            'flags' => 0,
            'disable' => false
        );
    }

    /**
     * Searches for the first rule of a certain action type and returns its
     * number.
     *
     * @param integer $action  The field type of the searched rule
     *                         (INGO_STORAGE_ACTION_* constants).
     *
     * @return integer  The number of the first matching rule or null.
     */
    function findRuleId($action)
    {
        foreach ($this->_filters as $id => $rule) {
            if ($rule['action'] == $action) {
                return $id;
            }
        }
    }

    /**
     * Searches for and returns the first rule of a certain action type.
     *
     * @param integer $action  The field type of the searched rule
     *                         (INGO_STORAGE_ACTION_* constants).
     *
     * @return array  The first matching rule hash or null.
     */
    function findRule($action)
    {
        $id = $this->findRuleId($action);
        if ($id !== null) {
            return $this->getRule($id);
        }
    }

    /**
     * Adds a rule hash to the filters list.
     *
     * @param array $rule       A rule hash.
     * @param boolean $default  If true merge the rule hash with default rule
     *                          values.
     */
    function addRule($rule, $default = true)
    {
        if ($default) {
            $this->_filters[] = array_merge($this->getDefaultRule(), $rule);
        } else {
            $this->_filters[] = $rule;
        }
    }

    /**
     * Updates an existing rule with a rule hash.
     *
     * @param array $rule  A rule hash
     * @param integer $id  A rule number
     */
    function updateRule($rule, $id)
    {
        $this->_filters[$id] = $rule;
    }

    /**
     * Deletes a rule from the filters list.
     *
     * @param integer $id  Number of the rule to delete.
     *
     * @return boolean  True if the rule has been found and deleted.
     */
    function deleteRule($id)
    {
        if (isset($this->_filters[$id])) {
            unset($this->_filters[$id]);
            $this->_filters = array_values($this->_filters);
            return true;
        }

        return false;
    }

    /**
     * Creates a copy of an existing rule.
     *
     * The created copy is added to the filters list right after the original
     * rule.
     *
     * @param integer $id  Number of the rule to copy.
     *
     * @return boolean  True if the rule has been found and copied.
     */
    function copyRule($id)
    {
        if (isset($this->_filters[$id])) {
            $newrule = $this->_filters[$id];
            $newrule['name'] = sprintf(_("Copy of %s"), $this->_filters[$id]['name']);
            $this->_filters = array_merge(array_slice($this->_filters, 0, $id + 1), array($newrule), array_slice($this->_filters, $id + 1));
            return true;
        }

        return false;
    }

    /**
     * Moves a rule up in the filters list.
     *
     * @param integer $id     Number of the rule to move.
     * @param integer $steps  Number of positions to move the rule up.
     */
    function ruleUp($id, $steps = 1)
    {
        for ($i = 0; $i < $steps && $id > 0;) {
            $temp = $this->_filters[$id - 1];
            $this->_filters[$id - 1] = $this->_filters[$id];
            $this->_filters[$id] = $temp;
            /* Continue to move up until we swap with a viewable category. */
            if (in_array($temp['action'], $_SESSION['ingo']['script_categories'])) {
                $i++;
            }
            $id--;
        }
    }

    /**
     * Moves a rule down in the filters list.
     *
     * @param integer $id     Number of the rule to move.
     * @param integer $steps  Number of positions to move the rule down.
     */
    function ruleDown($id, $steps = 1)
    {
        $rulecount = count($this->_filters) - 1;
        for ($i = 0; $i < $steps && $id < $rulecount;) {
            $temp = $this->_filters[$id + 1];
            $this->_filters[$id + 1] = $this->_filters[$id];
            $this->_filters[$id] = $temp;
            /* Continue to move down until we swap with a viewable
               category. */
            if (in_array($temp['action'], $_SESSION['ingo']['script_categories'])) {
                $i++;
            }
            $id++;
        }
    }

    /**
     * Disables a rule.
     *
     * @param integer $id  Number of the rule to disable.
     */
    function ruleDisable($id)
    {
        $this->_filters[$id]['disable'] = true;
    }

    /**
     * Enables a rule.
     *
     * @param integer $id  Number of the rule to enable.
     */
    function ruleEnable($id)
    {
        $this->_filters[$id]['disable'] = false;
    }

}
