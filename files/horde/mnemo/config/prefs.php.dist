<?php
/**
 * $Horde: mnemo/config/prefs.php.dist,v 1.24.2.1 2005/10/22 11:42:15 jan Exp $
 *
 * See horde/config/prefs.php for documentation on the structure of this file.
 */

// Make sure that constants are defined.
@define('MNEMO_BASE', dirname(__FILE__) . '/..');
require_once MNEMO_BASE . '/lib/Mnemo.php';

$prefGroups['display'] = array(
    'column' => _("General Options"),
    'label' => _("Display Options"),
    'desc' => _("Change your note sorting and display options."),
    'members' => array('sortby', 'sortdir')
);

$prefGroups['share'] = array(
    'column' => _("General Options"),
    'label' => _("Default Notepad"),
    'desc' => _("Choose your default Notepad."),
    'members' => array('notepadselect')
);

$prefGroups['deletion'] = array(
    'column' => _("General Options"),
    'label' => _("Delete Confirmation"),
    'desc' => _("Delete button behaviour"),
    'members' => array('delete_opt')
);


// user preferred sorting column
$_prefs['sortby'] = array(
    'value' => MNEMO_SORT_DESC,
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'enum' => array(MNEMO_SORT_DESC => _("Note Text"),
                    MNEMO_SORT_CATEGORY => _("Note Category")),
    'desc' => _("Default sorting criteria:")
);

// user preferred sorting direction
$_prefs['sortdir'] = array(
    'value' => 0,
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'enum' => array(MNEMO_SORT_ASCEND => _("Ascending"),
                    MNEMO_SORT_DESCEND => _("Descending")),
    'desc' => _("Default sorting direction:")
);

// user note categories
$_prefs['memo_categories'] = array(
    'value' => '',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);

// category highlight colors
$_prefs['memo_colors'] = array(
    'value' => '',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);

// default notepad selection widget
$_prefs['notepadselect'] = array('type' => 'special');

// default notepad
// Set locked to true if you don't want users to have multiple notepads.
$_prefs['default_notepad'] = array(
    'value' => Auth::getAuth() ? Auth::getAuth() : 0,
    'locked' => false,
    'shared' => true,
    'type' => 'implicit'
);

// store the notepads to diplay
$_prefs['display_notepads'] = array(
    'value' => 'a:0:{}',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);

// preference for delete confirmation dialog.
$_prefs['delete_opt'] = array(
    'value' => 1,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Do you want to confirm deleting entries?")
);
