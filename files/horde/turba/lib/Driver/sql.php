<?php
/**
 * Turba directory driver implementation for PHP's PEAR database abstraction
 * layer.
 *
 * $Horde: turba/lib/Driver/sql.php,v 1.59.10.17.2.1 2008/02/15 16:44:11 chuck Exp $
 *
 * @author  Jon Parise <jon@csh.rit.edu>
 * @package Turba
 */
class Turba_Driver_sql extends Turba_Driver {

    /**
     * What can this backend do?
     *
     * @var array
     */
    var $_capabilities = array(
        'delete_all' => true,
        'delete_addressbook' => true
    );

    /**
     * Handle for the database connection.
     *
     * @var DB
     */
    var $_db;

    function _init()
    {
        include_once 'DB.php';
        $this->_db = &DB::connect($this->_params,
                                  array('persistent' => !empty($this->_params['persistent'])));
        if (is_a($this->_db, 'PEAR_Error')) {
            return $this->_db;
        }

        // Set DB portability options.
        switch ($this->_db->phptype) {
        case 'mssql':
            $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
            break;
        default:
            $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
        }

        if ($this->_params['phptype'] == 'oci8') {
            $this->_db->query('ALTER SESSION SET NLS_DATE_FORMAT = \'YYYY-MM-DD\'');
        }

        return true;
    }

    /**
     * Returns the number of contacts of the current user in this address book.
     *
     * @return integer  The number of contacts that the user owns.
     */
    function countContacts()
    {
        static $count;

        if ($this->usingShares) {
            $test = $this->share->get('uid');
        } else {
            $test = Auth::getAuth();
        }
        if (!isset($count[$test])) {
            /* Build up the full query. */
            $query = 'SELECT COUNT(*) FROM ' . $this->_params['table'] .
                     ' WHERE ' . $this->toDriver('__owner') . ' = ?';
            $values = array($test);

            /* Log the query at a DEBUG log level. */
            Horde::logMessage('SQL query by Turba_Driver_sql::countContacts(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);

            /* Run query. */
            $count[$test] = $this->_db->getOne($query, $values);
        }

        return $count[$test];
    }

    /**
     * Searches the SQL database with the given criteria and returns a
     * filtered list of results. If the criteria parameter is an empty array,
     * all records will be returned.
     *
     * @param array $criteria  Array containing the search criteria.
     * @param array $fields    List of fields to return.
     *
     * @return array  Hash containing the search results.
     */
    function _search($criteria, $fields)
    {
        /* Build the WHERE clause. */
        $where = '';
        $values = array();
        if (count($criteria) || !empty($this->_params['filter'])) {
            foreach ($criteria as $key => $vals) {
                if ($key == 'OR' || $key == 'AND') {
                    if (!empty($where)) {
                        $where .= ' ' . $key . ' ';
                    }
                    $binds = $this->_buildSearchQuery($key, $vals);
                    $where .= '(' . $binds[0] . ')';
                    $values += $binds[1];
                }
            }
            $where = ' WHERE ' . $where;
            if (count($criteria) && !empty($this->_params['filter'])) {
                $where .= ' AND ';
            }
            if (!empty($this->_params['filter'])) {
                $where .= $this->_params['filter'];
            }
        }

        /* Build up the full query. */
        $query = 'SELECT ' . implode(', ', $fields) . ' FROM ' . $this->_params['table'] . $where;

        /* Log the query at a DEBUG log level. */
        Horde::logMessage('SQL query by Turba_Driver_sql::_search(): ' . $query,
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Run query. */
        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        $results = array();
        $iMax = count($fields);
        while ($row = $result->fetchRow()) {
            if (is_a($row, 'PEAR_Error')) {
                Horde::logMessage($row, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $result;
            }

            $row = $this->_convertFromDriver($row);

            $entry = array();
            for ($i = 0; $i < $iMax; $i++) {
                $field = $fields[$i];
                $entry[$field] = $row[$i];
            }
            $results[] = $entry;
        }

        return $results;
    }

    /**
     * Reads the given data from the SQL database and returns the result's
     * fields.
     *
     * @param array $criteria  Search criteria.
     * @param string $id       Data identifier.
     * @param array $fields    List of fields to return.
     *
     * @return  Hash containing the search results.
     */
    function _read($criteria, $id, $fields)
    {
        $values = array();

        $in = '';
        if (is_array($id)) {
            if (!count($id)) {
                return array();
            }

            foreach ($id as $key) {
                $in .= empty($in) ? '?' : ', ?';
                $values[] = $this->_convertToDriver($key);
            }
            $where = $criteria . ' IN (' . $in . ')';
        } else {
            $where = $criteria . ' = ?';
            $values[] = $this->_convertToDriver($id);
        }
        if (isset($this->map['__owner'])) {
            if ($this->usingShares) {
                $owner = $this->share->get('uid');
            } else {
                $owner = Auth::getAuth();
            }
            $where .= ' AND ' . $this->map['__owner'] . ' = ?';
            $values[] = $this->_convertToDriver($owner);
        }
        if (!empty($this->_params['filter'])) {
            $where .= ' AND ' . $this->_params['filter'];
        }

        $query  = 'SELECT ' . implode(', ', $fields) . ' ';
        $query .= 'FROM ' . $this->_params['table'] . ' WHERE ' . $where;

        /* Log the query at a DEBUG log level. */
        Horde::logMessage('SQL query by Turba_Driver_sql::_read(): ' . $query,
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $result = $this->_db->getAll($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        $results = array();
        $iMax = count($fields);
        if (!is_a($result, 'PEAR_Error')) {
            foreach ($result as $row) {
                $entry = array();
                for ($i=0; $i < $iMax; $i++) {
                    $field = $fields[$i];
                    $entry[$field] = $this->_convertFromDriver($row[$i]);
                }
                $results[] = $entry;
            }
        }

        return $results;
    }

    /**
     * Adds the specified object to the SQL database.
     */
    function _add($attributes)
    {
        $fields = array();
        $values = array();
        foreach ($attributes as $field => $value) {
            $fields[] = $field;
            $values[] = $this->_convertToDriver($value);
        }

        $query  = 'INSERT INTO ' . $this->_params['table'] . ' (' . implode(', ', $fields) . ')';
        $query .= ' VALUES (' . str_repeat('?, ', count($values) - 1) . '?)';

        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return true;
    }

    /**
     * Deletes the specified object from the SQL database.
     */
    function _delete($object_key, $object_id)
    {
        $query = 'DELETE FROM ' . $this->_params['table'] .
                 ' WHERE ' . $object_key . ' = ?';
        $values = array($object_id);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage('SQL query by Turba_Driver_sql::_delete(): ' . $query,
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return true;
    }

    /**
     * Deletes all contacts from a specific address book.
     *
     * @param string  $sourceName  The owner_id of the address book to
     *                             delete.  If omitted, the user's default
     *                             address book will be cleared.
     *
     * @return boolean  True if the operation worked.
     */
    function _deleteAll($sourceName = '')
    {
        if (!Auth::getAuth()) {
            return PEAR::raiseError('permission denied');
        }

        $query = 'DELETE FROM ' . $this->_params['table'] . ' WHERE owner_id = ?';

        if (!$sourceName) {
            $values = array(Auth::getAuth());
        } else {
            $values = array($sourceName);
        }
        /* Log the query at a DEBUG log level. */
        Horde::logMessage('SQL query by Turba_Driver_sql::_deleteAll(): ' . $query,
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        return $this->_db->query($query, $values);
    }

    /**
     * Saves the specified object in the SQL database.
     *
     * @return string  The object id, possibly updated.
     */
    function _save($object_key, $object_id, $attributes)
    {
        $where = $object_key . ' = ?';
        unset($attributes[$object_key]);

        $fields = array();
        $values = array();
        foreach ($attributes as $field => $value) {
            $fields[] = $field . ' = ?';
            $values[] = $this->_convertToDriver($value);
        }

        $values[] = $object_id;

        $query  = 'UPDATE ' . $this->_params['table'] . ' SET ' . implode(', ', $fields) . ' ';
        $query .= 'WHERE ' . $where;

        /* Log the query at a DEBUG log level. */
        Horde::logMessage('SQL query by Turba_Driver_sql::_save(): ' . $query,
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $result = $this->_db->query($query, $values);
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        return $object_id;
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
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Builds a piece of a search query.
     *
     * @param string $glue      The glue to join the criteria (OR/AND).
     * @param array  $criteria  The array of criteria.
     *
     * @return array  An SQL fragment and a list of values suitable for binding
     *                as an array.
     */
    function _buildSearchQuery($glue, $criteria)
    {
        require_once 'Horde/SQL.php';

        $clause = '';
        $values = array();

        foreach ($criteria as $key => $vals) {
            if (!empty($vals['OR']) || !empty($vals['AND'])) {
                if (!empty($clause)) {
                    $clause .= ' ' . $glue . ' ';
                }
                $binds = $this->_buildSearchQuery(!empty($vals['OR']) ? 'OR' : 'AND', $vals);
                $clause .= '(' . $binds[0] . ')';
                $values = array_merge($values, $binds[1]);
            } else {
                if (isset($vals['field'])) {
                    if (!empty($clause)) {
                        $clause .= ' ' . $glue . ' ';
                    }
                    $rhs = $this->_convertToDriver($vals['test']);
                    $binds = Horde_SQL::buildClause($this->_db, $vals['field'], $vals['op'], $rhs, true);
                    if (is_array($binds)) {
                        $clause .= $binds[0];
                        $values = array_merge($values, $binds[1]);
                    } else {
                        $clause .= $binds;
                    }
                } else {
                    foreach ($vals as $test) {
                        if (!empty($test['OR']) || !empty($test['AND'])) {
                            if (!empty($clause)) {
                                $clause .= ' ' . $glue . ' ';
                            }
                            $binds = $this->_buildSearchQuery(!empty($vals['OR']) ? 'OR' : 'AND', $test);
                            $clause .= '(' . $binds[0] . ')';
                            $values = array_merge($values, $binds[1]);
                        } else {
                            if (!empty($clause)) {
                                $clause .= ' ' . $key . ' ';
                            }
                            $rhs = $this->_convertToDriver($test['test']);
                            $binds = Horde_SQL::buildClause($this->_db, $test['field'], $test['op'], $rhs, true);
                            if (is_array($binds)) {
                                $clause .= $binds[0];
                                $values = array_merge($values, $binds[1]);
                            } else {
                                $clause .= $binds;
                            }
                        }
                    }
                }
            }
        }

        return array($clause, $values);
    }

    /**
     * Converts a value from the driver's charset to the default charset.
     *
     * @param mixed $value  A value to convert.
     *
     * @return mixed        The converted value.
     */
    function _convertFromDriver($value)
    {
        return String::convertCharset($value, $this->_params['charset']);
    }

    /**
     * Converts a value from the default charset to the driver's charset.
     *
     * @param mixed $value  A value to convert.
     *
     * @return mixed        The converted value.
     */
    function _convertToDriver($value)
    {
        return String::convertCharset($value, NLS::getCharset(), $this->_params['charset']);
    }

}
