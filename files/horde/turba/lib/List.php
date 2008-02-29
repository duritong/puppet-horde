<?php
/**
 * The Turba_List:: class provides an interface for dealing with a
 * list of Turba_Objects.
 *
 * $Horde: turba/lib/List.php,v 1.41.10.4 2005/10/18 12:50:05 jan Exp $
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@csh.rit.edu>
 * @package Turba
 */
class Turba_List {

    /**
     * The array containing the Turba_Objects represented in this list.
     *
     * @var array
     */
    var $objects = array();

    /**
     * The field to compare objects by.
     *
     * @var string
     */
    var $_usortCriteria;

    /**
     * Constructor.
     */
    function Turba_List($ids = array())
    {
        if ($ids) {
            foreach ($ids as $value) {
                list($source, $key) = explode(':', $value);
                $driver = &Turba_Driver::singleton($source);
                if (is_a($driver, 'Turba_Driver')) {
                    $this->insert($driver->getObject($key));
                }
            }
        }
    }

    /**
     * Inserts a new object into the list.
     *
     * @param Turba_Object $object  The object to insert.
     */
    function insert($object)
    {
        if (is_a($object, 'Turba_Object')) {
            $key = $object->getSource() . ':' . $object->getValue('__key');
            if (!isset($this->objects[$key])) {
                $this->objects[$key] = $object;
            }
        }
    }

    /**
     * Resets our internal pointer to the beginning of the list. Use this to
     * hide the internal storage (array, list, etc.) from client objects.
     */
    function reset()
    {
        reset($this->objects);
    }

    /**
     * Returns the next Turba_Object in the list. Use this to hide internal
     * implementation details from client objects.
     *
     * @return Turba_Object  The next object in the list.
     */
    function next()
    {
        list(,$tmp) = each($this->objects);
        return $tmp;
    }

    /**
     * Returns the number of Turba_Objects that are in the list. Use this to
     * hide internal implementation details from client objects.
     *
     * @return integer  The number of objects in the list.
     */
    function count()
    {
        return count($this->objects);
    }

    /**
     * Filters/Sorts the list based on the specified sort routine.
     *
     * @param $sort  The sort method.
     * @param $low   The low end of the sort range.
     * @param $high  The high end of the sort range.
     * @param $dir   Sort direction, 0 = ascending, 1 = descending
     */
    function sort($sort = 'lastname', $dir = 0)
    {
        global $prefs, $attributes;

        $sorted_objects = array();

        foreach ($this->objects as $key => $object) {
            $lastname = $object->getValue('lastname');
            if (!$lastname) {
                $lastname = Turba::guessLastname($object->getValue('name'));
            }
            $object->setValue('lastname', $lastname);
            $sorted_objects[$key] = $object;
        }

        $this->_usortCriteria = $sort;

        // Set the comparison type based on the type of attribute we're
        // sorting by.
        $this->_usortType = 'text';
        if (isset($attributes[$sort])) {
            if (!empty($attributes[$sort]['cmptype'])) {
                $this->_usortType = $attributes[$sort]['cmptype'];
            } elseif ($attributes[$sort]['type'] == 'int' ||
                      $attributes[$sort]['type'] == 'intlist' ||
                      $attributes[$sort]['type'] == 'number') {
                $this->_usortType = 'int';
            }
        }

        usort($sorted_objects, array($this, 'cmp'));

        if ($dir == 1) {
            $this->objects = array_reverse($sorted_objects);
        } else {
            $this->objects = $sorted_objects;
        }
    }

    /**
     * Usort helper function.
     *
     * Compares two Turba_Objects based on the member variable
     * $_usortCriteria, taking care to sort numerically if it is an integer
     * field.
     *
     * @param Turba_Object $a  The first Turba_Object to compare.
     * @param Turba_Object $b  The second Turba_Object to compare.
     *
     * @return integer  Comparison of the two field values.
     */
    function cmp($a, $b)
    {
        switch ($this->_usortType) {
        case 'int':
            return ($a->getValue($this->_usortCriteria) > $b->getValue($this->_usortCriteria)) ? 1 : -1;
            break;

        case 'text':
        default:
            $acmp = String::lower($a->getValue($this->_usortCriteria), true);
            $bcmp = String::lower($b->getValue($this->_usortCriteria), true);

            // Use strcoll for locale-safe comparisons.
            return strcoll($acmp, $bcmp);
        }
    }

}
