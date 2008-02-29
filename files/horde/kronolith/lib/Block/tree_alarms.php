<?php

$block_name = _("Menu Alarms");
$block_type = 'tree';

/**
 * $Horde: kronolith/lib/Block/tree_alarms.php,v 1.1.2.4 2006/04/27 03:59:51 chuck Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_kronolith_tree_alarms extends Horde_Block {

    var $_app = 'kronolith';

    function _buildTree(&$tree, $indent = 0, $parent = null)
    {
        @define('KRONOLITH_BASE', dirname(__FILE__) . '/../..');
        require_once KRONOLITH_BASE . '/lib/base.php';

        $now = time();
        $alarmCount = 0;
        $alarms = Kronolith::listAlarms(new Horde_Date($now), $GLOBALS['display_calendars']);
        foreach ($alarms as $calId => $calAlarms) {
            $GLOBALS['kronolith']->open($calId);
            foreach ($calAlarms as $eventId) {
                $event = &$GLOBALS['kronolith']->getEvent($eventId);
                if (is_a($event, 'PEAR_Error')) {
                    $GLOBALS['notification']->push($event);
                    return;
                }
                $eventDate = $event->nextRecurrence($now);
                if ($eventDate && $event->hasException($eventDate->year, $eventDate->month, $eventDate->mday)) {
                    continue;
                }

                $alarmCount++;
                $url = Util::addParameter(Horde::applicationUrl('viewevent.php'),
                                          array('eventID' => $eventId,
                                                'calendar' => $calId));

                $tree->addNode($parent . $calId . $eventId,
                               $parent,
                               $event->getTitle(),
                               $indent + 1,
                               false,
                               array('icon' => 'alarm.png',
                                     'icondir' => $GLOBALS['registry']->getImageDir(),
                                     'title' => $event->getTooltip(),
                                     'url' => $url));
            }
        }

        if ($registry->get('url', $parent)) {
            $purl = $registry->get('url', $parent);
        } elseif ($registry->get('status', $parent) == 'heading' ||
                  !$registry->get('webroot')) {
            $purl = null;
        } else {
            $purl = Horde::url($registry->getInitialPage($parent));
        }
        $pnode_params = array('url' => $purl,
                              'icon' => $registry->get('icon', $parent),
                              'icondir' => '');
        $pnode_name = $registry->get('name', $parent);
        if ($alarmCount) {
            $pnode_name = '<strong>' . $pnode_name . '</strong>';
        }

        $tree->addNode($parent, $registry->get('menu_parent', $parent),
                       $pnode_name, $indent, false, $pnode_params);
    }

}
