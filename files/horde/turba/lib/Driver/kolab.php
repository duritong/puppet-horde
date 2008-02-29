<?php

require_once 'Horde/Kolab.php';
require_once 'Horde/Data.php';

/**
 * Horde Turba driver for the Kolab IMAP Server.
 * Copyright 2004-2007 Horde Project (http://horde.org/)
 *
 * $Horde: turba/lib/Driver/kolab.php,v 1.5.10.9 2007/01/02 13:55:19 jan Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Stuart Binge <omicron@mighty.co.za>
 * @package Turba
 */
class Turba_Driver_kolab extends Turba_Driver {

    /**
     * Our Kolab server connection.
     *
     * @var Kolab
     */
    var $_kolab = null;

    function _init()
    {
        if (isset($this->_kolab)) {
            return true;
        }

        $this->_kolab = new Kolab();

        return $this->_kolab->open($this->_params['share']);
    }

    function _disconnect()
    {
        $this->_kolab->close();
        $this->_kolab = null;
    }

    function _buildContact()
    {
        $k = &$this->_kolab;

        $contact = array(
            'uid' => $k->getUID(),
            'owner' => Auth::getAuth(),
            'job-title' => $k->getStr('job-title'),
            'organization' => $k->getStr('organization'),
            'body' => $k->getStr('body'),
            'web-page' => $k->getStr('web-page'),
            'nick-name' => $k->getStr('nick-name'),
        );

        $name = &$k->getRootElem('name');
        $contact['full-name'] = $k->getElemStr($name, 'full-name');
        $contact['given-name'] = $k->getElemStr($name, 'given-name');
        $contact['last-name'] = $k->getElemStr($name, 'last-name');

        $email = &$k->getRootElem('email');
        $contact['smtp-address'] = $k->getElemStr($email, 'smtp-address');

        $phones = &$k->getAllRootElems('phone');
        for ($i = 0, $j = count($phones); $i < $j; $i++) {
            $phone = &$phones[$i];
            $type = $k->getElemStr($phone, 'type');

            switch ($type) {
            case 'home1':
                $contact['home1'] = $k->getElemStr($phone, 'number');
                break;

            case 'business1':
                $contact['business1'] = $k->getElemStr($phone, 'number');
                break;

            case 'mobile':
                $contact['mobile'] = $k->getElemStr($phone, 'number');
                break;

            case 'businessfax':
                $contact['businessfax'] = $k->getElemStr($phone, 'number');
                break;
            }
        }

        $addresses = &$k->getAllRootElems('address');
        for ($i = 0, $j = count($addresses); $i < $j; $i++) {
            $address = &$addresses[$i];
            $type = $k->getElemStr($address, 'type');

            switch ($type) {
            case 'home':
                $contact['home-street'] = $k->getElemStr($address, 'street');
                $contact['home-locality'] = $k->getElemStr($address, 'locality');
                $contact['home-region'] = $k->getElemStr($address, 'region');
                $contact['home-postal-code'] = $k->getElemStr($address, 'postal-code');
                $contact['home-country'] = $k->getElemStr($address, 'country');
                break;

            case 'business':
                $contact['business-street'] = $k->getElemStr($address, 'street');
                $contact['business-locality'] = $k->getElemStr($address, 'locality');
                $contact['business-region'] = $k->getElemStr($address, 'region');
                $contact['business-postal-code'] = $k->getElemStr($address, 'postal-code');
                $contact['business-country'] = $k->getElemStr($address, 'country');
                break;
            }
        }

        return $contact;
    }

    function _setPhone($type, &$phone, $attributes)
    {
        if (empty($attributes[$type])) {
            $this->_kolab->delRootElem($phone);
        } else {
            if ($phone === false) {
                $phone = &$this->_kolab->appendRootElem('phone');
                $this->_kolab->setElemStr($phone, 'type', $type);
            }
            $this->_kolab->setElemStr($phone, 'number', $attributes[$type]);
        }
    }

    function _setAddress($type, &$address, $attributes)
    {
        if (empty($attributes["$type-street"]) && empty($attributes["$type-locality"]) &&
            empty($attributes["$type-region"]) && empty($attributes["$type-postal-code"]) &&
            empty($attributes["$type-country"])) {
            $this->_kolab->delRootElem($address);
        } else {
            if ($address === false) {
                $address = &$this->_kolab->appendRootElem('address');
                $this->_kolab->setElemStr($address, 'type', $type);
            }
            $this->_kolab->setElemStr($address, 'street', $attributes["$type-street"]);
            $this->_kolab->setElemStr($address, 'locality', $attributes["$type-locality"]);
            $this->_kolab->setElemStr($address, 'region', $attributes["$type-region"]);
            $this->_kolab->setElemStr($address, 'postal-code', $attributes["$type-postal-code"]);
            $this->_kolab->setElemStr($address, 'country', $attributes["$type-country"]);
        }
    }

    function _createContact(&$xml, $attributes)
    {
        $k = &$this->_kolab;

        $name = &$k->initRootElem('name');
        $k->setElemStr($name, 'full-name', $attributes['full-name']);
        $k->setElemStr($name, 'given-name', $attributes['given-name']);
        $k->setElemStr($name, 'last-name', $attributes['last-name']);

        $email = &$k->initRootElem('email');
        $k->setElemStr($email, 'display-name', $attributes['full-name']);
        $k->setElemStr($email, 'smtp-address', $attributes['smtp-address']);

        $k->setStr('job-title', $attributes['job-title']);
        $k->setStr('organization', $attributes['organization']);
        $k->setStr('body', $attributes['body']);
        $k->setStr('web-page', $attributes['web-page']);
        $k->setStr('nick-name', $attributes['nick-name']);

        // Phones
        $phones = &$k->getAllRootElems('phone');
        $home = false;
        $bus = false;
        $mob = false;
        $fax = false;
        for ($i = 0, $j = count($phones); $i < $j; $i++) {
            $phone = &$phones[$i];
            $type = $k->getElemStr($phone, 'type');

            switch ($type) {
            case 'home1':
                $home = &$phone;
                break;

            case 'business1':
                $bus = &$phone;
                break;

            case 'mobile':
                $mob = &$phone;
                break;

            case 'businessfax':
                $fax = &$phone;
                break;
            }
        }

        $this->_setPhone('home1', $home, $attributes);
        $this->_setPhone('business1', $bus, $attributes);
        $this->_setPhone('mobile', $mob, $attributes);
        $this->_setPhone('businessfax', $fax, $attributes);

        // Addresses
        $home = false;
        $bus = false;
        $addresses = &$k->getAllRootElems('address');
        for ($i = 0, $j = count($addresses); $i < $j; $i++) {
            $address = &$addresses[$i];
            $type = $k->getElemStr($address, 'type');

            switch ($type) {
            case 'home':
                $home = &$address;
                break;

            case 'business':
                $bus = &$address;
                break;
            }
        }

        $this->_setAddress('home', $home, $attributes);
        $this->_setAddress('business', $bus, $attributes);
    }

    /**
     * Searches the Kolab message store with the given criteria and returns a
     * filtered list of results. If the criteria parameter is an empty
     * array, all records will be returned.
     *
     * @param $criteria      Array containing the search criteria.
     * @param $fields        List of fields to return.
     *
     * @return               Hash containing the search results.
     */
    function _search($criteria, $fields)
    {
        $query = $this->_buildIMAPSearch($criteria);

        $results = array();

        $msg_list = $this->_kolab->findObjects($query);
        if (is_a($msg_list, 'PEAR_Error') || empty($msg_list)) {
            return $results;
        }

        foreach ($msg_list as $msg) {
            $result = $this->_kolab->loadObject($msg, true);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }

            $contact = $this->_buildContact();

            $card = array();
            foreach ($fields as $field) {
                $card[$field] = (isset($contact[$field]) ? $contact[$field] : '');
            }

            $results[] = $card;
        }

        return $results;
    }

    /**
     * Read the given data from the Kolab message store and returns the
     * result's fields.
     *
     * @param $criteria      Search criteria.
     * @param $id            Data identifier.
     * @param $fields        List of fields to return.
     *
     * @return               Hash containing the search results.
     */
    function _read($criteria, $id_list, $fields)
    {
        $results = array();

        if ($criteria != 'uid') {
            return $results;
        }

        if (!is_array($id_list)) {
            $id_list = array($id_list);
        }

        foreach ($id_list as $id) {
            $result = $this->_kolab->loadObject($id);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }

            $contact = $this->_buildContact($result);

            $card = array();
            foreach ($fields as $field) {
                $card[$field] = (isset($contact[$field]) ? $contact[$field] : '');
            }

            $results[] = $card;
        }

        return $results;
    }

    /**
     * Adds the specified object to the Kolab message store.
     */
    function _add($attributes)
    {
        $xml = &$this->_kolab->newObject($attributes['uid']);
        if (is_a($xml, 'PEAR_Error')) {
            return $xml;
        }

        $this->_createContact($xml, $attributes);

        return $this->_kolab->saveObject();
    }

    /**
     * Removes the specified object from the Kolab message store.
     */
    function _delete($object_key, $object_id)
    {
        if ($object_key != 'uid') {
            return false;
        }

        return $this->_kolab->removeObjects($object_id);
    }

    /**
     * Updates an existing object in the Kolab message store.
     *
     * @return string  The object id, possibly updated.
     */
    function _save($object_key, $object_id, $attributes)
    {
        if ($object_key != 'uid') {
            return PEAR::raiseError('key must be uid');
        }

        $xml = &$this->_kolab->loadObject($object_id);
        if (is_a($xml, 'PEAR_Error')) {
            return $xml;
        }

        $this->_createContact($xml, $attributes);

        $result = $this->_kolab->saveObject();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $object_id;
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
        return $this->generateUID();
    }

    /**
     * Converts Turba search criteria into a comparable IMAP search string
     *
     * @param array $criteria      The search criteria.
     *
     * @return string  The IMAP search string corresponding to $criteria.
     */
    function _buildIMAPSearch($criteria)
    {
        $values = array_values($criteria);
        $values = $values[0];
        $query = 'HEADER "' . KOLAB_HEADER_TYPE . '" "' . $this->_kolab->getMimeType() . '" ';

        for ($current = 0; $current < count($values); $current++) {
            $temp = $values[$current];

            while (!empty($temp) && !array_key_exists('field', $temp)) {
                $temp = array_values($temp);
                $temp = $temp[0];
            }

            if (empty($temp)) continue;

            $searchkey = $temp['field'];
            $searchval = $temp['test'];

            switch ($searchkey) {
            case 'owner':
                $query .= 'FROM "' . $searchval . '" ';
                break;

            default:
                if (!empty($searchkey)) {
                    $query .= 'BODY "' . $searchkey . '" ';
                }
                if (!empty($searchval)) {
                    $query .= 'BODY "' . $searchval . '" ';
                }
            }
        }

        return trim($query);
    }

}
