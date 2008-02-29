<?php
/**
 * $Horde: turba/data.php,v 1.70.4.11 2007/01/02 13:55:18 jan Exp $
 *
 * Copyright 2001-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

function _cleanup()
{
    global $import_step;
    $import_step = 1;
    return IMPORT_FILE;
}

/**
 * Remove empty attributes from attributes array.
 *
 * @param mixed $val    Value from attributes array.
 *
 * @return boolean         Boolean used by array_filter.
 */
function _emptyAttributeFilter($var)
{
    if (!is_array($var)) {
        return ($var != '');
    } else {
        foreach ($var as $v) {
            if ($v == '') {
                return false;
            }
        }
        return true;
    }
}

@define('TURBA_BASE', dirname(__FILE__));
require_once TURBA_BASE . '/lib/base.php';
require_once 'Horde/Data.php';

if (!$conf['menu']['import_export']) {
    require TURBA_BASE . '/index.php';
    exit;
}

/* Importable file types. */
$file_types = array('csv'      => _("CSV"),
                    'tsv'      => _("TSV"),
                    'vcard'    => _("vCard"),
                    'mulberry' => _("Mulberry Address Book"),
                    'pine'     => _("Pine Address Book"));

/* Templates for the different import steps. */
$templates = array(
    IMPORT_FILE => array(TURBA_TEMPLATES . '/data/export.inc'),
    IMPORT_CSV => array($registry->get('templates', 'horde') . '/data/csvinfo.inc'),
    IMPORT_TSV => array($registry->get('templates', 'horde') . '/data/tsvinfo.inc'),
    IMPORT_MAPPED => array($registry->get('templates', 'horde') . '/data/csvmap.inc'),
    IMPORT_DATETIME => array($registry->get('templates', 'horde') . '/data/datemap.inc')
);

/* Initial values. */
$import_step = Util::getFormData('import_step', 0) + 1;
$actionID = Util::getFormData('actionID');
$next_step = IMPORT_FILE;
$app_fields = array();
$time_fields = array();
$error = false;
$outlook_mapping = array(
    'firstname' => 'first_name',
    'middlename' => 'middle_name',
    'lastname' => 'last_name',
    'e-mail' => 'email',
    'homeaddress' => 'homeAddress',
    'businessaddress' => 'workAddress',
    'homephone' => 'homePhone',
    'businessphone' => 'workPhone',
    'mobilephone' => 'cellPhone',
    'businessfax' => 'fax',
    'jobtitle' => 'title',
    'company' => 'company',
    'notes' => 'notes',
    'name' => 'name',
    'internetfreebusy' => 'freebusyUrl',
    'nickname' => 'alias',
    'pgpPublicKey' => 'pgpPublicKey',
    'smimePublicKey' => 'smimePublicKey',
    );
$param = array('time_fields' => $time_fields,
               'file_types'  => $file_types,
               'import_mapping' => $outlook_mapping);
$import_format = Util::getFormData('import_format', '');
$driver = $import_format;
if ($driver == 'mulberry' || $driver == 'pine') {
    $driver = 'tsv';
}
if ($actionID != 'select') {
    array_unshift($templates[IMPORT_FILE], TURBA_TEMPLATES . '/data/import.inc');
}

/* Loop through the action handlers. */
switch ($actionID) {
case 'export':
    $sources = array();
    if (Util::getFormData('selected')) {
        foreach (Util::getFormData('objectkeys') as $objectkey) {
            list($source, $key) = explode(':', $objectkey, 2);
            if (strpos($key, ':')) {
                list($owner, $key) = explode(':', $key, 2);
            }
            if (!isset($sources[$source])) {
                $sources[$source] = array();
            }
            $sources[$source][] = $key;
        }
    } else {
        $source = Util::getFormData('source');
        if (!isset($source) && !empty($cfgSources)) {
            reset($cfgSources);
            $source = key($cfgSources);
        }
        $sources[$source] = array();
    }

    $data = array();
    $contacts = array();
    $all_fields = array();
    foreach ($sources as $source => $objectkeys) {
        /* Create a Turba storage instance. */
        $storage = &Turba_Driver::singleton($source);
        if (is_a($storage, 'PEAR_Error')) {
            $notification->push(sprintf(_("Failed to access the address book: %s"), $storage->getMessage()), 'horde.error');
            $error = true;
            break;
        }

        /* Get the full, sorted contact list. */
        if (count($objectkeys)) {
            $results = &$storage->getObjects($objectkeys);
        } else {
            $results = $storage->search(array());
            if (is_a($results, 'Turba_List')) {
                $results = $results->objects;
            }
        }
        if (is_a($results, 'PEAR_Error')) {
            $notification->push(sprintf(_("Failed to search the directory: %s"), $results->getMessage()), 'horde.error');
            $error = true;
            break;
        }

        $fields = $storage->getFields();
        $all_fields = array_merge($all_fields, $fields);
        $params = $storage->getParams();
        foreach ($results as $ob) {
            $row = array();
            foreach ($fields as $field) {
                if (substr($field, 0, 2) != '__') {
                    $attribute = $ob->getValue($field);
                    if ($attributes[$field]['type'] == 'date') {
                        $row[$field] = strftime('%Y-%m-%d', $attribute);
                    } elseif ($attributes[$field]['type'] == 'time') {
                        $row[$field] = strftime('%R', $attribute);
                    } elseif ($attributes[$field]['type'] == 'datetime') {
                        $row[$field] = strftime('%Y-%m-%d %R', $attribute);
                    } else {
                        $row[$field] = String::convertCharset($attribute, NLS::getCharset(), $params['charset']);
                    }
                }
            }
            $data[] = $row;
        }

        $contacts = array_merge($contacts, $results);
    }
    if (!count($data)) {
        $notification->push(_("There were no addresses to export."), 'horde.message');
        $error = true;
        break;
    }

    /* Make sure that all rows have the same columns if exporting from
     * different sources. */
    if (count($sources) > 1) {
        for ($i = 0; $i < count($data); $i++) {
            foreach ($all_fields as $field) {
                if (!isset($data[$i][$field])) {
                    $data[$i][$field] = '';
                }
            }
        }
    }

    switch (Util::getFormData('exportID')) {
    case EXPORT_CSV:
        $csv = &Horde_Data::singleton('csv');
        $csv->exportFile(_("contacts.csv"), $data, true);
        exit;

    case EXPORT_OUTLOOKCSV:
        $csv = &Horde_Data::singleton('outlookcsv');
        $csv->exportFile(_("contacts.csv"), $data, true, array_flip($outlook_mapping));
        exit;

    case EXPORT_TSV:
        $tsv = &Horde_Data::singleton('tsv');
        $tsv->exportFile(_("contacts.tsv"), $data, true);
        exit;

    case EXPORT_VCARD:
        $cards = array();
        foreach ($contacts as $contact) {
            $cards[] = Turba_Driver::tovCard($contact);
        }

        $vcard = &Horde_Data::singleton('vcard');
        $vcard->exportFile(_("contacts.vcf"), $cards, true);
        exit;
    }
    break;

case IMPORT_FILE:
    $dest = Util::getFormData('dest');
    $storage = &Turba_Driver::singleton($dest);
    if (is_a($storage, 'PEAR_Error')) {
        $notification->push(sprintf(_("Failed to access the address book: %s"), $storage->getMessage()), 'horde.error');
        $error = true;
        break;
    }

    /* Check permissions. */
    $max_contacts = Turba::hasPermission($dest . ':max_contacts', 'source');
    if ($max_contacts !== true &&
        $max_contacts <= $storage->countContacts()) {
        $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d contacts in \"%s\"."), $max_contacts, $storage->title), ENT_COMPAT, NLS::getCharset());
        if (!empty($conf['hooks']['permsdenied'])) {
            $message = Horde::callHook('_perms_hook_denied', array('turba:max_contacts'), 'horde', $message);
        }
        $notification->push($message, 'horde.error', array('content.raw'));
        $error = true;
        break;
    }

    $_SESSION['import_data']['target'] = $dest;
    $_SESSION['import_data']['purge'] = Util::getFormData('purge');
    break;

case IMPORT_MAPPED:
case IMPORT_DATETIME:
    foreach ($cfgSources[$_SESSION['import_data']['target']]['map'] as $field => $null) {
        if (substr($field, 0, 2) != '__' && !is_array($null)) {
            if ($attributes[$field]['type'] == 'monthyear' ||
                $attributes[$field]['type'] == 'monthdayyear') {
                $time_fields[$field] = 'date';
            } elseif ($attributes[$field]['type'] == 'time') {
                $time_fields[$field] = 'time';
            }
        }
    }
    $param['time_fields'] = $time_fields;
    break;
}

if (!$error && !empty($driver)) {
    $data = &Horde_Data::singleton($driver);
    if (is_a($data, 'PEAR_Error')) {
        $notification->push(_("This file format is not supported."), 'horde.error');
        $next_step = IMPORT_FILE;
    } else {
        $next_step = $data->nextStep($actionID, $param);
        if (is_a($next_step, 'PEAR_Error')) {
            $notification->push($next_step->getMessage(), 'horde.error');
            $next_step = $data->cleanup();
        } else {
            /* Raise warnings if some exist. */
            if (method_exists($data, 'warnings')) {
                $warnings = $data->warnings();
                if (count($warnings)) {
                    foreach ($warnings as $warning) {
                        $notification->push($warning, 'horde.warning');
                    }
                    $notification->push(_("The import can be finished despite the warnings."), 'horde.message');
                }
            }
        }
    }
}

/* We have a final result set. */
if (is_array($next_step)) {
    /* Create a category manager. */
    require_once 'Horde/Prefs/CategoryManager.php';
    $cManager = new Prefs_CategoryManager();
    $categories = $cManager->get();

    /* Create a Turba storage instance. */
    $dest = $_SESSION['import_data']['target'];
    $storage = &Turba_Driver::singleton($dest);
    if (is_a($storage, 'PEAR_Error')) {
        $notification->push(sprintf(_("Failed to access the address book: %s"), $storage->getMessage()), 'horde.error');
    } elseif (!count($next_step)) {
        $notification->push(sprintf(_("The %s file didn't contain any contacts."),
                                    $file_types[$_SESSION['import_data']['format']]), 'horde.error');
    } else {
        /* Purge old address book if requested. */
        if ($_SESSION['import_data']['purge']) {
            if (strpos($dest, ':')) {
                list($sourceType, $uid) = explode(':', $dest, 2);
                $owner = $uid;
            } else {
                $owner = Auth::getAuth();
            }
            $result = $storage->deleteAll($owner);
            if (is_a($result, 'PEAR_Error')) {
                $notification->push(sprintf(_("The address book could not be purged: %s"), $result->getMessage()), 'horde.error');
            } else {
                $notification->push(_("Address book successfully purged."), 'horde.success');
            }
        }

        $error = false;
        foreach ($next_step as $row) {
            if (is_a($row, 'Horde_iCalendar_vcard')) {
                $row = $storage->toHash($row);
            }

            /* Don't search for empty attributes. */
            $row = array_filter($row, '_emptyAttributeFilter');
            $result = $storage->search($row);
            if (is_a($result, 'PEAR_Error')) {
                $notification->push($result, 'horde.error');
                $error = true;
                break;
            } elseif ($result->count()) {
                $result->reset();
                $object = $result->next();
                $notification->push(sprintf(_("\"%s\" already exists and was not imported."), $object->getValue('name')), 'horde.message');
            } else {
                /* Get share information. */
                if ($storage->usingShares) {
                    $row['__owner'] = $storage->share->get('uid');
                } else {
                    $row['__owner'] = Auth::getAuth();
                }
                $result = $storage->add($row);
                if (is_a($result, 'PEAR_Error')) {
                    $notification->push(sprintf(_("There was an error importing the data: %s"),
                                                $result->getMessage()), 'horde.error');
                    $error = true;
                    break;
                }

                if (!empty($row['category']) &&
                    !in_array($row['category'], $categories)) {
                    $cManager->add($row['category']);
                    $categories[] = $row['category'];
                }
            }
        }
        if (!$error) {
            $notification->push(sprintf(_("%s file successfully imported."),
                                        $file_types[$_SESSION['import_data']['format']]), 'horde.success');
        }
    }
    $next_step = $data->cleanup();
}

switch ($next_step) {
case IMPORT_MAPPED:
case IMPORT_DATETIME:
    foreach ($cfgSources[$_SESSION['import_data']['target']]['map'] as $field => $null) {
        if (substr($field, 0, 2) != '__' && !is_array($null)) {
            $app_fields[$field] = $attributes[$field]['label'];
        }
    }
    break;
}

$title = _("Import/Export Address Books");
require TURBA_TEMPLATES . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';

$default_source = $prefs->getValue('default_dir');
if ($next_step == IMPORT_FILE) {
    /* Build the directory sources select widget. */
    $unique_source = '';
    $source_options = array();
    foreach (Turba::getAddressBooks() as $key => $entry) {
        if (!empty($entry['export'])) {
            $source_options[] = '<option value="' . htmlspecialchars($key) . '">' .
                htmlspecialchars($entry['title']) . "</option>\n";
            $unique_source = $key;
        }
    }

    /* Build the directory destination select widget. */
    $unique_dest = '';
    $dest_options = array();
    $hasWriteable = false;
    foreach (Turba::getAddressBooks(PERMS_EDIT) as $key => $entry) {
        $selected = ($key == $default_source) ? ' selected="selected"' : '';
        $dest_options[] = '<option value="' . htmlspecialchars($key) . '" ' . $selected . '>' .
            htmlspecialchars($entry['title']) . "</option>\n";
        $unique_dest = $key;
        $hasWriteable = true;
    }

    if (!$hasWriteable) {
        array_shift($templates[$next_step]);
    }

    /* Build the charset options. */
    $charsets = $nls['encodings'];
    $all_charsets = $nls['charsets'];
    natcasesort($all_charsets);
    foreach ($all_charsets as $charset) {
        if (!isset($charsets[$charset])) {
            $charsets[$charset] = $charset;
        }
    }
    $my_charset = NLS::getCharset(true);
}

foreach ($templates[$next_step] as $template) {
    require $template;
}
require $registry->get('templates', 'horde') . '/common-footer.inc';
