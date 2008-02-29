<?php
/**
 * SyncML Backend for the Horde Application framework.
 *
 * The backend provides the following functionality:
 *
 * 1) handling of the actual data, i.e.
 *    a) add/replace/delete entries to and retrieve entries from the
 *       backend
 *    b) retrieve history to find out what entries have been changed
 * 2) managing of the map between cliend IDs and server IDs
 * 3) store information about sync anchors (timestamps) of previous
 *    successfuls sync sessions
 * 4) session handling (not yet, still to be done)
 * 5) authorisation (not yet, still to be done)
 * 6) logging
 *
 * Copyright 2005-2007 Karsten Fourmont <karsten@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * $Horde: framework/SyncML/SyncML/Backend.php,v 1.8.2.9 2007/01/02 13:54:41 jan Exp $
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @package SyncML
 */
class SyncML_Backend_Horde {

    /**
     * An array as value indicates that for this client category no
     * server category is defined yet. The array content is the
     * summary text of the last encountered client entry that used
     * this category.
     *
     *   key:   clientCategory
     *   value: serverCategory
     */
    var $_categoriesMap;

    /**
     * Server categories missing on the client.
     *
     *   key:   serverCategory
     *   value: summary of last entry with this category
     */
    var $_missingServerCategories;

    var $_datatree;

    /**
     * Cache for Datatree values
     */
     
    var $_cache;
    
    function SyncML_Backend_Horde()
    {
        $driver = $GLOBALS['conf']['datatree']['driver'];
        $params = Horde::getDriverConfig('datatree', $driver);
        $params = array_merge($params, array('group' => 'syncml'));

        $this->_datatree =& DataTree::singleton($driver, $params);
        $this->_cache = array();
    }

    /**
     * Retrieves an entry from the backend.
     *
     * @param string $database   Database to scan. i.e.
     *                           calendar/tasks/contacts/notes
     * @param string $suid       Server unique id of the entry: for horde
     *                           this is the guid.
     * @param string contentType Content-Type: the mime type in which the
     *                           function shall return the data
     *
     * @return mixed             A string with the data entry or
     *                           or a PEAR_Error object.
     */
    function retrieveEntry($database, $suid, $contentType)
    {
        return $GLOBALS['registry']->call($database . '/export',
                                          array('guid' => $suid, 'contentType' => $contentType));
    }

    /**
     * Get entries that have been modified in the server database.
     *
     * @param string $syncIdentifier  Identifies the client device to allow the
     *                                user to sync with different devices.
     *                                Normally the SourceURI from the
     *                                SyncHeader
     * @param string $database        Database to scan. i.e.
     *                                calendar/tasks/contacts/notes
     * @param integer $from_ts        Start timestamp.
     * @param integer $to_ts          Exclusive end timestamp. Not yet
     *                                implemented.
     *
     * @return array  PEAR_Error or assoc array of changes with key=suid,
     *                value=cuid. If no cuid can be found for a suid, value is
     *                null. Then a Sync Add command has to be created to add
     *                this entry in the client database.
     */
    function getServerModifications($syncIdentifier, $database, $from_ts, $to_ts)
    {
        global $registry;

        if ($from_ts == 0) {
            // Complete sync: everything is sent during
            // getServerAdditions. No need to send modifications.
            return array();
        }

        // Get changes.
        $changes = $registry->call($database. '/listBy', array('action' => 'modify', 'timestamp' => $from_ts));
        if (is_a($changes, 'PEAR_Error')) {
            $this->logMessage("SyncML: $database/listBy failed for modify:"
                              . $changes->getMessage(),
                              __FILE__, __LINE__, PEAR_LOG_WARNING);
            return array();
        }

        $r = array();

        foreach ($changes as $suid) {
            $suid_ts = $registry->call($database . '/getActionTimestamp', array($suid, 'modify'));
            $sync_ts = $this->getChangeTS($syncIdentifier, $database, $suid);
            if ($sync_ts && $sync_ts >= $suid_ts) {
                // Change was done by us upon request of client.
                // Don't mirror that back to the client.
                $this->logMessage("change: $suid ignored, came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
                continue;
            }
            $cuid = $this->getCuid($syncIdentifier, $database, $suid);
            if (!$cuid) {
                $this->logMessage("Unable to create change for $suid: client-id not found in map. Trying add instead.",
                                  __FILE__, __LINE__, PEAR_LOG_WARNING);
                $r[$suid] = null;
            } else {
                $r[$suid] = $cuid;
            }
        }

        return $r;
    }

    /**
     * Get entries that have been deleted from the server database.
     *
     * @param string $database  Database to scan. i.e.
     *                          calendar/tasks/contacts/notes
     * @param integer $from_ts
     * @param integer $to_ts    Exclusive
     *
     * @return array  PEAR_Error or assoc array of deletions with key=suid,
     *                value=cuid.
     */
    function getServerDeletions($syncIdentifier, $database, $from_ts, $to_ts)
    {
        global $registry;

        if ($from_ts == 0) {
            // Complete sync: everything is sent during
            // getServerAdditions.  No need to send deletions.
            return array();
        }

        // Get deletions.
        $deletes = $registry->call($database. '/listBy', array('action' => 'delete', 'timestamp' => $from_ts));

        if (is_a($deletes, 'PEAR_Error')) {
            $this->logMessage("SyncML: $database/listBy failed for delete:"
                              . $deletes->getMessage(),
                              __FILE__, __LINE__, PEAR_LOG_WARNING);
            return array();
        }

        $r = array();

        foreach ($deletes as $suid) {
            $suid_ts = $registry->call($database. '/getActionTimestamp', array($suid, 'delete'));
            $sync_ts = $this->getChangeTS($syncIdentifier, $database, $suid);
            if ($sync_ts && $sync_ts >= $suid_ts) {
                // Change was done by us upon request of client.
                // Don't mirror that back to the client.
                $this->logMessage("SyncML: delete $suid ignored, came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
                continue;
            }
            $cuid = $this->getCuid($syncIdentifier, $database, $suid);
            if (!$cuid) {
                $this->logMessage("Unable to create delete for $suid: locid not found in map",
                                  __FILE__, __LINE__, PEAR_LOG_WARNING);
                continue;
            }

            $r[$suid] = $cuid;
        }

        return $r;
    }

    /**
     * Get entries that have been added to the server database.
     *
     * @param string $database  Database to scan. i.e.
     *                          calendar/tasks/contacts/notes
     * @param integer $from_ts
     * @param integer $to_ts    Exclusive
     *
     * @return array  PEAR_Error or assoc array of deletions with key=suid,
     *                value=0. (array style is chosen to match change & del)
     */
    function getServerAdditions($syncIdentifier, $database, $from_ts, $to_ts)
    {
        global $registry;

        if ($from_ts == 0) {
            // Return all db entries directly rather than bother
            // history:
            $adds = $registry->call($database. '/list');
        } else {
            $adds = $registry->call($database. '/listBy', array('action' => 'add', 'timestamp' => $from_ts));
        }

        if (is_a($adds, 'PEAR_Error')) {
            $this->logMessage("SyncML: $database/listBy failed for add:"
                              . $adds->getMessage(),
                              __FILE__, __LINE__, PEAR_LOG_WARNING);
            return array();
        }

        $this->logMessage('add modifications retrieved. count='
                          . count($adds),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $r = array();

        foreach ($adds as $suid) {
            $suid_ts = $registry->call($database . '/getActionTimestamp', array($suid, 'add'));
            $sync_ts = $this->getChangeTS($syncIdentifier, $database, $suid);
            if ($sync_ts && $sync_ts >= $suid_ts) {
                // Change was done by us upon request of client.
                // Don't mirror that back to the client.
                $this->logMessage("add: $suid ignored, came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
                continue;
            }

            $cuid = $this->getCuid($syncIdentifier, $database, $suid);

            if ($cuid && $from_ts == 0) {
                // For slow sync (ts = 0), do not add data for which
                // we have a locid again. This is a heuristic to
                // avoid duplication of entries.
                $this->logMessage("skipping add of guid $suid as there already is a cuid $cuid", __FILE__, __LINE__, PEAR_LOG_DEBUG);
                continue;
            }
            $r[$suid] = 0;
        }

        return $r;
    }

    /**
     * Adds an entry into the server database.
     *
     * @param string $database     Database where to add.
     *                             calendar/tasks/contacts/notes
     * @param string $content      The actual data
     * @param string $contentType  Mimetype of $content
     * @param string $cuid         Client ID of this entry (for map)
     *
     * @return array  PEAR_Error or suid (Horde guid) of new entry
     */
    function importEntry($syncIdentifier, $database, $content, $contentType, $cuid)
    {
        global $registry;

        $tasksandcalendarcombined = false;

        // Checks if the client sends us a vtodo in a calendar sync:
        if ($database == 'calendar'
             && preg_match('/(\r\n|\r|\n)BEGIN[^:]*:VTODO/', "\n" . $content)) {
            $serverdatabase = 'tasks';
        } else {
            $serverdatabase = $database;
        }

        $suid = $registry->call($serverdatabase . '/import',
                                array($content, $contentType));

        $this->logMessage("add to server db $serverdatabase cuid $cuid -> suid $suid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

        if (!is_a($suid, 'PEAR_Error')) {
            $ts = $registry->call($serverdatabase. '/getActionTimestamp', array($suid, 'add'));
            if (!$ts) {
                $this->logMessage('Unable to find add-ts for ' . $suid . ' at ' . $ts, __FILE__, __LINE__, PEAR_LOG_ERR);
            }
            $this->createUidMap($syncIdentifier, $database, $cuid, $suid, $ts);
        }

        return $suid;
    }

    /**
     * Deletes an entry from the server database.
     *
     * @param string $database  Database where to add.
     *                          calendar/tasks/contacts/notes
     * @param string $cuid      Client ID of the entry
     *
     * @return array  PEAR_Error or suid (Horde guid) of deleted entry.
     */
    function deleteEntry($syncIdentifier, $database, $cuid)
    {
        global $registry;

        // Find server ID for this entry:
        $suid = $this->getSuid($syncIdentifier, $database, $cuid);
        if (!is_a($suid, 'PEAR_Error')) {
            $registry->call($database. '/delete', array($suid));
            $ts = $registry->call($database . '/getActionTimestamp', array($suid, 'delete'));
            // We can't remove the mapping entry as we need to keep
            // the timestamp information.
            $this->createUidMap($syncIdentifier, $database, $cuid, $suid, $ts);
        }

        return $suid;
    }

    /**
     * Replaces an entry in the server database.
     *
     * @param string $database     Database where to replace.
     *                             calendar/tasks/contacts/notes
     * @param string $content      The actual data
     * @param string $contentType  Mimetype of $content
     * @param string $cuid         Client ID of this entry
     *
     * @return array  PEAR_Error or suid (Horde guid) of modified entry.
     */
    function replaceEntry($syncIdentifier, $database, $content, $contentType, $cuid)
    {
        global $registry;

        // Checks if the client sends us a vtodo in a calendar sync:
        if ($database == 'calendar'
             && preg_match('/(\r\n|\r|\n)BEGIN[^:]*:VTODO/', "\n" . $content)) {
            $serverdatabase = 'tasks';
        } else {
            $serverdatabase = $database;
        }

        $suid = $this->getSuid($syncIdentifier, $database, $cuid);

        $this->logMessage("replace in db $serverdatabase cuid $cuid suid $suid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

        if ($suid) {
            // Entry exists: replace current one.
            $ok = $registry->call($serverdatabase . '/replace',
                                  array($suid, $content, $contentType));
            if (is_a($ok, 'PEAR_Error')) {
                return $ok;
            }
            $ts = $registry->call($serverdatabase . '/getActionTimestamp', array($suid, 'modify'));
            $this->createUidMap($syncIdentifier, $database, $cuid, $suid, $ts);
        } else {
            return PEAR::raiseError('No map entry found');
        }

        return $suid;
    }

    /**
     * Retrieves information about the previous sync if any. Returns
     * false if no info found or a DateTreeObject with at least the
     * following attributes:
     *
     * ClientAnchor: the clients Next Anchor of the previous sync.
     * ServerAnchor: the Server Next Anchor of the previous sync.
     */
    function &getSyncSummary($syncIdentifier, $type)
    {
        return $this->_getOb($syncIdentifier . ':summary:' . $type);
    }

    /**
     * After a successful sync, the client and server's Next Anchors
     * are written to the database so they can be used to negotiate
     * upcoming syncs.
     */
    function writeSyncSummary($syncIdentifier,
                              $clientAnchorNext, $serverAnchorNext)
    {
        if (!isset($serverAnchorNext) || !is_array($serverAnchorNext)) {
            $this->logMessage('SyncML internal error: no anchor provided '
                              . 'in writeSyncSummary',
                              __FILE__, __LINE__, PEAR_LOG_ERR);
            die('SyncML internal error: no anchor provided in writeSyncSummary');
        }

        foreach (array_keys($serverAnchorNext) as $type) {
            $s = $syncIdentifier . ':summary:' . $type;

            // Set $locid.
            $info = &new DataTreeObject($s);
            $info->set('ClientAnchor', $clientAnchorNext);
            $info->set('ServerAnchor', $serverAnchorNext);
            $r = $this->_datatree->add($info);
            if (is_a($r, 'PEAR_Error')) {
                // Object already exists: update instead.
                $this->_datatree->updateData($info);
            }
            $this->_cache[$info->getName()] = $info;
        }
    }

    /**
     * Create a map entries to map between server and client IDs.
     *
     * Puts a given client $cuid and Horde server $suid pair into the
     * map table to allow mapping between the client's and server's
     * IDs.  Actually there are two maps: from the suid to the cuid
     * and vice versa.
     * If an entry already exists, it is overwritten.
     */
    function createUidMap($syncIdentifier, $type, $cuid, $suid, $ts=0)
    {
        // Set $cuid.
        $gid = &new DataTreeObject($syncIdentifier . ':' . $type
                                   . ':suid2cuid:' . $suid);
        $gid->set('datastore', $type);
        $gid->set('cuid', $cuid);
        $gid->set('ts', $ts);

        $r = $this->_datatree->add($gid);
        if (is_a($r, 'PEAR_Error')) {
            // Object already exists: update instead.
            $r = $this->_datatree->updateData($gid);
        }
        $this->_cache[$gid->getName()] = $gid;
        $this->dieOnError($r, __FILE__, __LINE__);

        // Set $globaluid
        $lid = &new DataTreeObject($syncIdentifier . ':' . $type
                                   . ':cuid2suid:' . $cuid);
        $lid->set('suid', $suid);
        $r = $this->_datatree->add($lid);
        if (is_a($r, 'PEAR_Error')) {
            // Object already exists: update instead.
            $r = $this->_datatree->updateData($lid);
        }
        $this->_cache[$lid->getName()] = $lid;
        $this->dieOnError($r, __FILE__, __LINE__);

        // If tasks and events are handled at once, we need to store
        // the map entry in both databases:
        $session =& $_SESSION['SyncML.state'];
        $device =& $session->getDevice();

        if ($device->handleTasksInCalendar()
            && ($type == 'tasks' || $type == 'calendar') ) {
            $type = $type == 'tasks' ? 'calendar' : 'tasks' ; // the other one

            // Set $cuid.
            $gid = &new DataTreeObject($syncIdentifier . ':' . $type
                                       . ':suid2cuid:' . $suid);
            $gid->set('datastore', $type);
            $gid->set('cuid', $cuid);
            $gid->set('ts', $ts);

            $r = $this->_datatree->add($gid);
            if (is_a($r, 'PEAR_Error')) {
                // Object already exists: update instead.
                $r = $this->_datatree->updateData($gid);
            }
            $this->_cache[$gid->getName()] = $gid;
            $this->dieOnError($r, __FILE__, __LINE__);

            // Set $globaluid
            $lid = &new DataTreeObject($syncIdentifier . ':' . $type
                                       . ':cuid2suid:' . $cuid);
            $lid->set('suid', $suid);
            $r = $this->_datatree->add($lid);
            if (is_a($r, 'PEAR_Error')) {
                // Object already exists: update instead.
                $r = $this->_datatree->updateData($lid);
            }
            $this->_cache[$lid->getName()] = $lid;
            $this->dieOnError($r, __FILE__, __LINE__);
        }
    }

    /**
     * Returns the timestamp (if set) of the last change to the
     * obj:guid, that was caused by the client.
     *
     * @access private
     *
     * This is stored to
     * avoid mirroring these changes back to the client.
     */
    function getChangeTS($syncIdentifier, $database, $suid)
    {
        $gid =& $this->_getOb($syncIdentifier . ':' . $database . ':suid2cuid:' . $suid);
        if (is_a($gid, 'PEAR_Error')) {
            return false;
        }

        return $gid->get('ts');
    }

    /**
     * Retrieves the Horde server guid (like
     * kronolith:0d1b415fc124d3427722e95f0e926b75) for a given client
     * cuid. Returns false if no such id is stored yet.
     *
     * @access private
     *
     * Opposite of getLocId which returns the locid for a given guid.
     */
    function getSuid($syncIdentifier, $type, $cuid)
    {
        $lid =& $this->_getOb($syncIdentifier . ':' . $type . ':cuid2suid:' . $cuid);
        if (is_a($lid, 'PEAR_Error')) {
            return false;
        }

        return $lid->get('suid');
    }

    /**
     * Converts a suid server id (i.e. Horde GUID) to a cuid client ID
     * as used by the sync client (like 12) returns false if no such
     * id is stored yet.
     *
     * @access private
     */
    function getCuid($syncIdentifier, $database, $suid)
    {
        $gid =& $this->_getOb($syncIdentifier . ':' . $database  . ':suid2cuid:' . $suid);
        if (is_a($gid, 'PEAR_Error')) {
            return false;
        }

        return $gid->get('cuid');
    }

    /**
     * Logs a message in the backend.
     *
     * @param mixed $message     Either a string or a PEAR_Error object.
     * @param string $file       What file was the log function called from
     *                           (e.g. __FILE__)?
     * @param integer $line      What line was the log function called from
     *                           (e.g. __LINE__)?
     * @param integer $priority  The priority of the message. One of:
     * <pre>
     * PEAR_LOG_EMERG
     * PEAR_LOG_ALERT
     * PEAR_LOG_CRIT
     * PEAR_LOG_ERR
     * PEAR_LOG_WARNING
     * PEAR_LOG_NOTICE
     * PEAR_LOG_INFO
     * PEAR_LOG_DEBUG
     * </pre>
     */
    function logMessage($message, $file = __FILE__, $line = __LINE__,
                        $priority = PEAR_LOG_INFO)
    {
        if (is_string($message)) {
            $message = "SyncML: " . $message;
        }
        Horde::logMessage($message, $file, $line, $priority);
    }

    function mapClientCategory2Server($clientCategory, $summary  = 'unknown') {

        if (empty($clientCategory)) {
            return '';
        }
        $this->_loadCategoriesMap();

        if (!empty($this->_categoriesMap[$clientCategory]) &&
            !is_array($this->_categoriesMap[$clientCategory])) {
            return $this->_categoriesMap[$clientCategory];
        }

        /* store the clientCategory as lacking a map. */
        $this->_categoriesMap[$clientCategory] = array($summary);
        $this->_storeCategoriesMap();
        return $clientCategory;
    }

    function mapServerCategory2Client($serverCategory, $summary = 'unknown') {

        if (empty($serverCategory)) {
            return '';
        }

        $this->_loadCategoriesMap();

        $c = array_search($serverCategory, $this->_categoriesMap);
        if (!empty($c) && !is_array($c)) {
            return $c;
        }

        /* Store the serverCategory as lacking a map. */
        $this->_missingServerCategories[$serverCategory] = $summary;
        $this->_storeCategoriesMap();

        return $serverCategory;
    }

    function _loadCategoriesMap()
    {
        if (is_array($this->_categoriesMap)) {
            return;
        }

        $map =& $this->_getOb($_SESSION['SyncML.state']->getSyncIdentifier() . ':categories');
        if (is_a($map, 'PEAR_Error')) {
            $this->_categoriesMap = array();
            $this->_missingServerCategories = array();
        } else {
            $this->_categoriesMap = $map->get('map');
            $this->_missingServerCategories = $map->get('missingServerCategories');
        }
    }

    function _storeCategoriesMap()
    {
        if (!is_array($this->_categoriesMap)) {
            return; // nothing to do
        }

        $map = &new DataTreeObject($_SESSION['SyncML.state']->getSyncIdentifier()
                                   . ':categories');
        $map->set('map', $this->_categoriesMap);
        $map->set('missingServerCategories', $this->_missingServerCategories);

        $r = $this->_datatree->add($map);
        if (is_a($r, 'PEAR_Error')) {
            // Object already exists: update instead.
            $r = $this->_datatree->updateData($map);
        }
        $this->_cache[$map->getName()] = $map;
        $this->dieOnError($r, __FILE__, __LINE__);
    }

    /**
     * This is a small helper function that can be included to check
     * whether a given $obj is a PEAR_Error or not. If so, it logs
     * to debug, var_dumps the $obj and exits.
     */
    function dieOnError($obj, $file = __FILE__, $line = __LINE__)
    {
        if (!is_a($obj, 'PEAR_Error')) {
            return;
        }

        $this->logMessage('SyncML: PEAR Error: ' . $obj->getMessage(), $file, $line, PEAR_LOG_ERR);
        print "PEAR ERROR\n\n";
        var_dump($obj);
    }

    function &_getOb($name)
    {
        if (!isset($this->_cache[$name])) {
            $this->_cache[$name] =& $this->_datatree->getObject($name);
        }
        return $this->_cache[$name];
    }

}
