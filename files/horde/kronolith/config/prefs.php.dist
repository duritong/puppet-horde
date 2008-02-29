<?php
/**
 * $Horde: kronolith/config/prefs.php.dist,v 1.66.2.8 2006/06/29 09:00:14 jan Exp $
 *
 * See horde/config/prefs.php for documentation on the structure of this file.
 */

$prefGroups['view'] = array(
    'column' => _("Display Options"),
    'label' => _("User Interface"),
    'desc' => _("Select confirmation options, how to display the different views and choose default view."),
    'members' => array('confirm_delete', 'defaultview',
                       'time_between_days', 'week_start_monday',
                       'day_hour_start', 'day_hour_end', 'slots_per_hour',
                       'show_icons', 'show_legend', 'show_fb_legend',
                       'show_shared_side_by_side')
);

$prefGroups['summary'] = array(
    'column' => _("Display Options"),
    'label' => _("Portal Options"),
    'desc' => _("Select which events to show in the portal."),
    'members' => array('summary_days', 'summary_alarms')
);

if ($registry->hasMethod('tasks/listTasks')) {
    $prefGroups['tasks'] = array(
        'column' => _("Display Options"),
        'label' => _("Tasks"),
        'desc' => _("Do you want to show tasks which are due on your calendar?"),
        'members' => array('show_tasks', 'show_task_colors')
    );
}

$prefGroups['share'] = array(
    'column' => _("Calendars"),
    'label' => _("Default Calendar"),
    'desc' => _("Choose your default calendar."),
    'members' => array('shareselect')
);

$prefGroups['remote'] = array(
    'column' => _("Calendars"),
    'label' => _("Remote Calendars"),
    'desc' => _("Manage remote calendars."),
    'members' => array('remote_cal_management')
);

$prefGroups['notification'] = array(
    'column' => _("Calendars"),
    'label' => _("Notifications"),
    'desc' => _("Choose if you want to be notified of new, edited, and deleted events."),
    'members' => array('event_notification', 'event_reminder')
);

$prefGroups['freebusy'] = array(
    'column' => _("Calendars"),
    'label' => _("Free/Busy Information"),
    'desc' => _("Set your free/busy calendars and your own and other users' free/busy options."),
    'members' => array('fb_cals_select', 'freebusy_days')
);
if ($registry->hasMethod('contacts/sources')) {
    $prefGroups['freebusy']['members'][] = 'search_abook_select';
    $prefGroups['freebusy']['members'][] = 'display_contact';
}


// confirm deletion of events which don't recur?
$_prefs['confirm_delete'] = array(
    'value' => 1,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Confirm deletion of events?")
);

// default view
$_prefs['defaultview'] = array(
    'value' => 'month',
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'enum' => array('day' => _("Day"),
                    'week' => _("Week"),
                    'workweek' => _("Work Week"),
                    'month' => _("Month")),
    'desc' => _("Select the view to display on startup:")
);

// Display the timeslots between each day column, in week view.
$_prefs['time_between_days'] = array(
    'value' => 0,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Show time of day between each day in week views?")
);

// what day does the week start with
$_prefs['week_start_monday'] = array(
    'value' => '0',
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'desc' => _("Select the first weekday:"),
    'enum' => array('0' => _("Sunday"),
                    '1' => _("Monday"))
);

// start of the time range in day/week views:
$_prefs['day_hour_start'] = array(
    'value' => 16,
    'locked' => false,
    'shared' => false,
    'type' => 'select',
    'desc' => _("What time should day and week views start, when there are no earlier events?")
);

// end of the time range in day/week views:
$_prefs['day_hour_end'] = array(
    'value' => 48,
    'locked' => false,
    'shared' => false,
    'type' => 'select',
    'desc' => _("What time should day and week views end, when there are no later events?")
);

// number of slots in each hour:
$_prefs['slots_per_hour'] = array(
    'value' => 1,
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'desc' => _("How long should the time slots on the day and week views be?"),
    'enum' => array(4 => _("15 minutes"),
                    3 => _("20 minutes"),
                    2 => _("30 minutes"),
                    1 => _("1 hour"))
);

// show delete/alarm icons in the calendar view?
$_prefs['show_icons'] = array(
    'value' => 1,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Show delete, alarm, and recurrence icons in calendar views?")
);

// show category legend?
// a value of 0 = no, 1 = yes
$_prefs['show_legend'] = array(
    'value' => 1,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Show category legend?")
);

// show free/busy legend?
// a value of 0 = no, 1 = yes
$_prefs['show_fb_legend'] = array(
    'value' => 1,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Show free/busy legend?")
);

// collapsed or side by side view
$_prefs['show_shared_side_by_side'] = array(
    'value' => 0,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Show shared calendars side-by-side?")
);

// days to show in summary
$_prefs['summary_days'] = array(
    'value' => 7,
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'desc' => _("Select the time span to show:"),
    'enum' => array(1 => '1 ' . _("day"),
                    2 => '2 ' . _("days"),
                    3 => '3 ' . _("days"),
                    4 => '4 ' . _("days"),
                    5 => '5 ' . _("days"),
                    6 => '6 ' . _("days"),
                    7 => '1 ' . _("week"),
                    14 => '2 ' . _("weeks"),
                    21 => '3 ' . _("weeks"),
                    28 => '4 ' . _("weeks"))
);

// show alarms in summary?
$_prefs['summary_alarms'] = array(
    'value' => 0,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Show only events that have an alarm set?")
);

// show due tasks in the calendar views?
$_prefs['show_tasks'] = array(
    'value' => 0,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Show due tasks in the calendar?")
);

// show task colors?
$_prefs['show_task_colors'] = array(
    'value' => 1,
    'locked' => false,
    'shared' => false,
    'type' => 'checkbox',
    'desc' => _("Show tasks using category colors?")
);

// default calendar selection widget
$_prefs['shareselect'] = array('type' => 'special');

// store the calendars to diplay
$_prefs['display_cals'] = array(
    'value' => 'a:0:{}',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);

// default calendar
// Set locked to true if you don't want users to have multiple calendars.
$_prefs['default_share'] = array(
    'value' => Auth::getAuth() ? Auth::getAuth() : 0,
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);

// manage remote calendars
$_prefs['remote_cal_management'] = array(
    'value' => '',
    'locked' => false,
    'shared' => false,
    'type' => 'special',
    'desc' => _("Edit Remote Calendars")
);

// store the remote calendars to display
$_prefs['remote_cals'] = array(
    'value' => 'a:0:{}',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);

// store the remote calendars to display
$_prefs['display_remote_cals'] = array(
    'value' => 'a:0:{}',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);

// new event notifications
$_prefs['event_notification'] = array(
    'value' => '',
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'enum' => array('' => _("No"),
                    'owner' => _("On my calendars only"),
                    'show' => _("On all shown calendars"),
                    'read' => _("On all calendars I have read access to")),
    'desc' => _("Choose if you want to be notified of new, edited, and deleted events by email:")
);

// reminder notifications
$_prefs['event_reminder'] = array(
    'value' => 'owner',
    'locked' => false,
    'shared' => false,
    'type' => 'enum',
    'enum' => array('' => _("No"),
                    'owner' => _("On my calendars only"),
                    'show' => _("On all shown calendars"),
                    'read' => _("On all calendars I have read access to")),
    'desc' => _("Choose if you want to receive email reminders for events with alarms:")
);

// number of days to generate free/busy information for:
$_prefs['freebusy_days'] = array(
    'value' => 30,
    'locked' => false,
    'shared' => false,
    'type' => 'number',
    'desc' => _("How many days of free/busy information should we generate?")
);

// address books to search for free/busy URLs.
$_prefs['search_abook'] = array(
    // If you want the localsql address book to be the default, use:
    // 'value' => 'a:1:{i:0;s:8:"localsql";}',
    'value' => 'a:0:{}',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit',
    'desc' => _("Choose the address books to search for free/busy URLs:")
);

// address books selector.
$_prefs['search_abook_select'] = array('type' => 'special');

// By default, display all contacts in the address book when loading
// the contacts screen.  If your default address book is large and
// slow to display, you may want to disable and lock this option.
$_prefs['display_contact'] = array(
    'value' => 1,
    'locked' => false,
    'shared' => true,
    'type' => 'checkbox',
    'desc' => _("List all contacts when loading the contacts screen? (if disabled, you will only see contacts that you search for explicitly)"),
);

// Calendars to include in generating free/busy URLs.
$_prefs['fb_cals'] = array(
    'value' => 'a:0:{}',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit',
    'desc' => _("Choose the calendars to include when generating free/busy URLs:")
);

// Free/busy calendars selector.
$_prefs['fb_cals_select'] = array('type' => 'special');

// The following two preferences are no longer used and only necessary for the
// upgrade script.
$_prefs['event_categories'] = array(
    'value' => '',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);
$_prefs['event_colors'] = array(
    'value' => '',
    'locked' => false,
    'shared' => false,
    'type' => 'implicit'
);
