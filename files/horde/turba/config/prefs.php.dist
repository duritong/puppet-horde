<?php
/**
 * $Horde: turba/config/prefs.php.dist,v 1.28.10.4 2007/06/29 17:48:38 chuck Exp $
 *
 * See horde/config/prefs.php for documentation on the structure of this file.
 */

$prefGroups['addressbooks'] = array(
    'column' => _("Display Options"),
    'label' => _("Address Books"),
    'desc' => _("Choose which address books to use."),
    'members' => array('default_dir', 'addressbookselect'),
);

$prefGroups['columns'] = array(
    'column' => _("Display Options"),
    'label' => _("Column Options"),
    'desc' => _("Select which fields to display in the address lists."),
    'members' => array('columnselect'),
);

$prefGroups['display'] = array(
    'column' => _("Display Options"),
    'label' => _("Display"),
    'desc' => _("Select view to display by default, sort options, and paging options."),
    'members' => array('initial_page', 'sortby', 'sortdir', 'maxpage', 'perpage'),
);

$prefGroups['format'] = array(
    'column' => _("Display Options"),
    'label' => _("Name Format"),
    'desc' => _("Select which format to display names."),
    'members' => array('name_format'),
);

if (!empty($GLOBALS['conf']['imsp']['enabled']) ||
    !isset($GLOBALS['conf']['imsp']['enabled'])) {
    $prefGroups['imsp'] = array(
        'column' => _("Other Options"),
        'label' => _("IMSP Address Book Administration"),
        'desc' => _("Add and Delete IMSP address books"),
        'members' => array('imsp_opt'),
    );
}

// address book selection widget
$_prefs['addressbookselect'] = array(
    'locked' => false,
    'type' => 'special',
);

// address books to be displayed
$_prefs['addressbooks'] = array(
    'value' => '',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit',
);

// columns selection widget
$_prefs['columnselect'] = array(
    'locked' => false,
    'type' => 'special',
);

// columns to be displayed
$_prefs['columns'] = array(
    'value' => "netcenter\temail\nbigfoot\temail\nverisign\temail\nlocalsql\temail",
    'locked' => false,
    'shared' => false,
    'type' => 'implicit',
);

// user preferred sorting column
// zero based int representing the column number to sort by
$_prefs['sortby'] = array(
    'value' => 0,
    'locked' => false,
    'shared' => false,
    'type' => 'implicit',
);

// user preferred sorting direction
$_prefs['sortdir'] = array(
    'value' => 0,
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'enum' => array(0 => _("Ascending"),
                    1 => _("Descending")),
    'desc' => _("Default sorting direction:"),
);

// number of maximum pages and items per page
$_prefs['maxpage'] = array(
    'value' => 10,
    'locked' => false,
    'shared' => false,
    'type' => 'number',
    'desc' => _("Maximum number of pages"),
);

$_prefs['perpage'] = array(
    'value' => 20,
    'locked' => false,
    'shared' => false,
    'type' => 'number',
    'desc' => _("Number of items per page"),
);

// the page to display.  Either 'browse.php' or 'search.php'
$_prefs['initial_page'] = array(
    'value' => 'search.php',
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'desc' => _("View to display by default:"),
    'enum' => array('browse.php' => _("Address Book Listing"),
                    'search.php' => _("Search")),
);

// the format to display names.  Either 'last_first' or 'first_last'
$_prefs['name_format'] = array(
    'value' => 'none',
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'desc' => _("Select the format used to display names:"),
    'enum' => array('last_first' => _("\"Lastname, Firstname\" (ie. Doe, John)"),
                    'first_last' => _("\"Firstname Lastname\"  (ie. John Doe)"),
                    'none' => _("no formatting")),
);

// Default directory
$_prefs['default_dir'] = array(
    'value' => '',
    // 'value' => 'localsql',
    'locked' => false,
    'shared' => true,
    'type' => 'select',
    'desc' => _("This will be the default address book when adding or importing contacts."),
);

// preference for holding any preferences-based addressbooks.
$_prefs['prefbooks'] = array(
    'value' => '',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit',
);

if (!empty($GLOBALS['conf']['imsp']['enabled']) ||
    !isset($GLOBALS['conf']['imsp']['enabled'])) {
    $_prefs['imsp_opt'] = array(
        'locked' => false,
        'type' => 'special',
    );
}
