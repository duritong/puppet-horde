<?php
/**
 * Class for auto-generating the preferences user interface and
 * processing the forms.
 *
 * $Horde: framework/Prefs/Prefs/UI.php,v 1.63.2.16 2007/01/02 13:54:35 jan Exp $
 *
 * Copyright 2001-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 2.1
 * @package Horde_Prefs
 */
class Prefs_UI {

    /**
     * Determine whether or not a preferences group is editable.
     *
     * @param string $group  The preferences group to check.
     *
     * @return boolean  Whether or not the group is editable.
     */
    function groupIsEditable($group)
    {
        global $prefs, $prefGroups;

        static $results = array();

        if (!isset($results[$group])) {
            if (!empty($prefGroups[$group]['url'])) {
                $results[$group] = true;
            } else {
                $results[$group] = false;
                if (isset($prefGroups[$group]['members'])) {
                    foreach ($prefGroups[$group]['members'] as $pref) {
                        if (!$prefs->isLocked($pref)) {
                            $results[$group] = true;
                            return true;
                        }
                    }
                }
            }
        }

        return $results[$group];
    }

    /**
     * Handle a preferences form submission if there is one, updating
     * any preferences which have been changed.
     *
     * @param string $group  The preferences group that was edited.
     * @param object $save   The object where the changed values are
     *                       saved. Must implement setValue(string, string).
     *
     * @return boolean  Whether preferences have been updated.
     */
    function handleForm(&$group, &$save)
    {
        global $prefs, $prefGroups, $_prefs, $notification, $registry;

        $updated = false;

        /* Run through the action handlers */
        if (Util::getPost('actionID') == 'update_prefs') {
            if (isset($group) && Prefs_UI::groupIsEditable($group)) {
                $updated = false;

                foreach ($prefGroups[$group]['members'] as $pref) {
                    if (!$prefs->isLocked($pref) ||
                        ($_prefs[$pref]['type'] == 'special')) {
                        switch ($_prefs[$pref]['type']) {

                        /* These either aren't set or are set in other
                         * parts of the UI. */
                        case 'implicit':
                        case 'link':
                            break;

                        case 'select':
                        case 'text':
                        case 'textarea':
                        case 'password':
                            $updated = $updated | $save->setValue($pref, Util::getPost($pref));
                            break;

                        case 'enum':
                            $val = Util::getPost($pref);
                            if (isset($_prefs[$pref]['enum'][$val])) {
                                $updated = $updated | $save->setValue($pref, $val);
                            } else {
                                $notification->push(_("An illegal value was specified."), 'horde.error');
                            }
                            break;

                        case 'multienum':
                            $vals = Util::getPost($pref);
                            $set = array();
                            $invalid = false;
                            if (is_array($vals)) {
                                foreach ($vals as $val) {
                                    if (isset($_prefs[$pref]['enum'][$val])) {
                                        $set[] = $val;
                                    } else {
                                        $invalid = true;
                                        continue;
                                    }
                                }
                            }

                            if ($invalid) {
                                $notification->push(_("An illegal value was specified."), 'horde.error');
                            } else {
                                $updated = $updated | $save->setValue($pref, @serialize($set));
                            }
                            break;

                        case 'number':
                            $num = Util::getPost($pref);
                            if (intval($num) != $num) {
                                $notification->push(_("This value must be a number."), 'horde.error');
                            } elseif ($num == 0) {
                                $notification->push(_("This number must be at least one."), 'horde.error');
                            } else {
                                $updated = $updated | $save->setValue($pref, $num);
                            }
                            break;

                        case 'checkbox':
                            $val = Util::getPost($pref);
                            $updated = $updated | $save->setValue($pref, isset($val) ? 1 : 0);
                            break;

                        case 'special':
                            /* Code for special elements must be
                             * written specifically for each
                             * application. */
                            if (function_exists('handle_' . $pref)) {
                                $updated = $updated | call_user_func('handle_' . $pref, $updated);
                            }
                            break;
                        }
                    }
                }

                if ($updated) {
                    if (function_exists('prefs_callback')) {
                        prefs_callback();
                    }
                    if (is_a($prefs, 'Prefs_session')) {
                        $notification->push(_("Your options have been updated for the duration of this session."), 'horde.success');
                    } else {
                        $notification->push(_("Your options have been updated."), 'horde.success');
                    }
                    $group = null;
                }
            }
        }

        return $updated;
    }

    /**
     * Generate the UI for the preferences interface, either for a
     * specific group, or the group selection interface.
     *
     * @param string $group  The group to generate the UI for.
     */
    function generateUI($group = null)
    {
        global $browser, $conf, $prefs, $prefGroups, $_prefs, $registry, $app;

        /* Check if any options are actually available. */
        if (is_null($prefGroups)) {
            $GLOBALS['notification']->push(_("There are no options available."), 'horde.message');
        }

        /* Show the header. */
        Prefs_UI::generateHeader($group);

        /* Assign variables to hold select lists. */
        if (!$prefs->isLocked('language')) {
            $GLOBALS['language_options'] = $GLOBALS['nls']['languages'];
            array_unshift($GLOBALS['language_options'], _("Default"));
        }

        if (!empty($group) && Prefs_UI::groupIsEditable($group)) {
            foreach ($prefGroups[$group]['members'] as $pref) {
                if (!$prefs->isLocked($pref)) {
                    /* Get the help link. */
                    if (!empty($_prefs[$pref]['help'])) {
                        $helplink = Help::link(!empty($_prefs[$pref]['shared']) ? 'horde' : $registry->getApp(), $_prefs[$pref]['help']);
                    } else {
                        $helplink = null;
                    }

                    switch ($_prefs[$pref]['type']) {
                    case 'implicit':
                        break;

                    case 'special':
                        require $registry->get('templates', !empty($_prefs[$pref]['shared']) ? 'horde' : $registry->getApp()) . "/prefs/$pref.inc";
                        break;

                    default:
                        require $registry->get('templates', 'horde') . '/prefs/' . $_prefs[$pref]['type'] . '.inc';
                        break;
                    }
                }
            }
            require $registry->get('templates', 'horde') . '/prefs/end.inc';
        } else {
            $span = 100;
            $columns = array();
            if (is_array($prefGroups)) {
                foreach ($prefGroups as $group => $gvals) {
                    if (Prefs_UI::groupIsEditable($group)) {
                        $col = $gvals['column'];
                        unset($gvals['column']);
                        $columns[$col][$group] = $gvals;
                    }
                }
                if (count($columns)) {
                    $span = round(100 / count($columns));
                }
            }

            require $registry->get('templates', 'horde') . '/prefs/overview.inc';
        }
    }

    /**
     * Generates the the full header of a preference screen including
     * menu and navigation bars.
     *
     * @param string $group  The group to generate the header for.
     */
    function generateHeader($group = null)
    {
        global $registry, $prefGroups, $app, $perms, $prefs, $notification;

        $title = _("User Options");
        if ($group == 'identities' && !$prefs->isLocked('default_identity')) {
            $notification->push('newChoice()', 'javascript');
        }
        require $registry->get('templates', $app) . '/common-header.inc';
        if (is_callable(array($app, 'getMenu'))) {
            $menu = call_user_func(array($app, 'getMenu'));
            require $registry->get('templates', 'horde') . '/menu/menu.inc';
        } else {
            /* Use a default menu. */
            require_once 'Horde/Menu.php';
            $menu = &new Menu();
            require $registry->get('templates', 'horde') . '/menu/menu.inc';
        }

        if (is_callable(array($app, 'status'))) {
            call_user_func(array($app, 'status'));
        } else {
            $GLOBALS['notification']->notify(array('listeners' => 'status'));
        }

        /* Get list of accessible applications. */
        $apps = array();
        foreach ($registry->applications as $application => $params) {
            // Make sure the app is installed and has a prefs file.
            if (!file_exists($registry->get('fileroot', $application) . '/config/prefs.php')) {
                continue;
            }

            if ($params['status'] == 'heading' ||
                $params['status'] == 'block') {
                continue;
            }

            /* Check if the current user has permisson to see this
             * application, and if the application is active.
             * Administrators always see all applications. */
            if ((Auth::isAdmin() && $params['status'] != 'inactive') ||
                ($registry->hasPermission($application) &&
                 ($params['status'] == 'active' || $params['status'] == 'notoolbar'))) {
                $apps[$application] = _($params['name']);
            }
        }
        asort($apps);

        /* Show the current application and a form for switching
         * applications. */
        require $registry->get('templates', 'horde') . '/prefs/app.inc';

        /* If there's only one prefGroup, just show it. */
        if (empty($group) && count($prefGroups) == 1) {
            $group = array_keys($prefGroups);
            $group = array_pop($group);
        }

        if (!empty($group) && Prefs_UI::groupIsEditable($group)) {
            require $registry->get('templates', 'horde') . '/prefs/begin.inc';
        }
    }

    /**
     * Generate the content of the title bar navigation cell (previous | next
     * option group).
     *
     * @param string $group  Current option group.
     */
    function generateNavigationCell($group)
    {
        global $prefGroups, $registry, $app;

        // Search for previous and next groups.
        $previous = null;
        $next = null;
        $last = null;
        $first = null;
        $found = false;
        $finish = false;
        foreach ($prefGroups as $pgroup => $gval) {
            if (Prefs_UI::groupIsEditable($pgroup)) {
                if (!$first) {
                    $first = $pgroup;
                }
                if (!$found) {
                    if ($pgroup == $group) {
                        $previous = $last;
                        $found = true;
                    }
                } else {
                    if (!$finish) {
                        $finish = true;
                        $next = $pgroup;
                    }
                }
                $last = $pgroup;
            }
        }
        if (!$previous) {
            $previous = $last;
        }
        if (!$next) {
            $next = $first;
        }

        /* Don't loop if there's only one group. */
        if ($next == $previous) {
            return;
        }

        echo '<ul><li>';
        if (!empty($prefGroups[$previous]['url'])) {
            echo Horde::link(Horde::applicationUrl($prefGroups[$previous]['url']),
                             _("Previous options"));
            echo '&lt;&lt; ' . $prefGroups[$previous]['label'];
        } else {
            echo Horde::link(Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/services/prefs.php'), array('group' => $previous, 'app' => $app)),
                             _("Previous options"));
            echo '&lt;&lt; ' . $prefGroups[$previous]['label'];
        }
        echo '</a>&nbsp;|&nbsp;';
        if (!empty($prefGroups[$next]['url'])) {
            echo Horde::link(Horde::applicationUrl($prefGroups[$next]['url']),
                             _("Next options"));
            echo $prefGroups[$next]['label'] . ' &gt;&gt;';
        } else {
            echo Horde::link(Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/services/prefs.php'), array('group' => $next, 'app' => $app)),
                             _("Next options"));
            echo $prefGroups[$next]['label'] . ' &gt;&gt;';
        }
        echo '</a></li></ul>';
    }

    /**
     * Get the default application to show preferences for. Defaults
     * to 'horde'.
     */
    function getDefaultApp()
    {
        $applications = $GLOBALS['registry']->listApps(null, true, PERMS_READ);
        return isset($applications['horde']) ? 'horde' : array_shift($applications);
    }

}
