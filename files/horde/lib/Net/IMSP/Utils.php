<?php
/**
 * Net_IMSP_Utils::
 *
 * $Horde: framework/Net_IMSP/IMSP/Utils.php,v 1.3.10.8 2007/01/02 13:54:28 jan Exp $
 *
 * Copyright 2003-2007 Michael Rubinsky <mrubinsk@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Michael Rubinsky <mrubinsk@horde.org>
 * @package Net_IMSP
 */
class Net_IMSP_Utils {

    /**
     * Utility function to retrieve the names of all the address books
     * that the user has access to, along with the acl for those
     * books.  For information about the $serverInfo array see
     * turba/config/sources.php as this is the cfgSources[] entry for
     * the address books.
     *
     * @param array $serverInfo  Information about the server
     *                           and the current user.
     *
     * @return array  Information about all the address books or PEAR_Error.
     */
    function getAllBooks($serverInfo)
    {
        require_once 'Net/IMSP.php';
        $foundDefault = false;
        $results = array();
        $imsp = &Net_IMSP::singleton('Book', $serverInfo['params']);
        $result = $imsp->init();

        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $books = $imsp->getAddressBookList();
        if (is_a($books, 'PEAR_Error')) {
            return $books;
        }

        $bCount = count($books);
        for ($i = 0; $i < $bCount; $i++) {
            $newBook = $serverInfo;
            if ($books[$i] != $serverInfo['params']['username']) {
                $newBook['title'] = 'IMSP_' . $books[$i];
                $newBook['params']['name'] = $books[$i];
                $newBook['params']['is_root'] = false;
                $newBook['params']['my_rights'] = $imsp->myRights($books[$i]);
            } else {
                $foundDefault = true;
                $newBook['params']['my_rights'] = $imsp->myRights($books[$i]);
            }
            $results[] = $newBook;
        }
        /* If there is no default address book (named username) then we should create one. */
        if (!$foundDefault) {
            $result = $imsp->createAddressBook($serverInfo['params']['username']);
            if (is_a($result, 'PEAR_Error')) {
                return PEAR::raiseError('Login to IMSP host failed.' .
                                        ': Default address book is missing and could not be created.');
            }
        }
        return $results;
    }

    /**
     * Utility function to make it easier for client applications to delete
     * address books without having to create imsp drivers.  The $source array
     * is a horde/turba style $cfgSources entry for the address book being
     * deleted.
     *
     * @param array $source  Information about the address book being deleted.
     *
     * @return mixed  True on success or PEAR_Error on failure.
     */
    function deleteBook($source)
    {
        require_once 'Net/IMSP.php';
        $imsp = &Net_IMSP::singleton('Book', $source['params']);
        $result = $imsp->init();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        $result = $imsp->deleteAddressBook($source['params']['name']);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        return true;
    }

    /**
     * Utility function to help clients create new address books without having
     * to create an imsp driver instance first.
     *
     * @param array $source    Information about the user's default IMSP
     *                         address book.
     * @param string $newName  The name of the new address book.  Note that this
     *                         is automatically qualified with the user's name
     *                         so should not be passed as a fully qualified IMSP
     *                         address book name.
     *
     * @return mixed  true on success or PEAR_Error on failure.
     */
     function createBook($source, $newName)
    {
        require_once 'Net/IMSP.php';
        $imsp = &Net_IMSP::singleton('Book', $source['params']);
        $result = $imsp->init();
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        $name = $source['params']['username'] . '.' . $newName;
        $result = $imsp->createAddressBook($name);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        return true;
    }
}
