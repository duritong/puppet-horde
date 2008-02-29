<?php

$block_name = _("Menu List");
$block_type = 'tree';

/**
 * $Horde: turba/lib/Block/tree_menu.php,v 1.5.2.1 2005/10/18 12:50:05 jan Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_turba_tree_menu extends Horde_Block {

    var $_app = 'turba';

    function _buildTree(&$tree, $indent = 0, $parent = null)
    {
        global $registry;

        define('TURBA_BASE', dirname(__FILE__) . '/../..');
        require_once TURBA_BASE . '/lib/base.php';

        $browse = Horde::applicationUrl('browse.php');
        $icondir = $registry->getImageDir() . '/menu';

        $tree->addNode($parent . '__new',
                       $parent,
                       _("New Contact"),
                       $indent + 1,
                       false,
                       array('icon' => 'new.png',
                             'icondir' => $icondir,
                             'url' => Horde::applicationUrl('add.php')));

        foreach (Turba::getAddressBooks() as $addressbook => $config) {
            if (!empty($config['browse'])) {
                $tree->addNode($parent . $addressbook,
                               $parent,
                               $config['title'],
                               $indent + 1,
                               false,
                               array('icon' => 'browse.png',
                                     'icondir' => $icondir,
                                     'url' => Util::addParameter($browse, array('source' => $addressbook))));
            }
        }

        $tree->addNode($parent . '__search',
                       $parent,
                       _("Search"),
                       $indent + 1,
                       false,
                       array('icon' => 'search.png',
                             'icondir' => $registry->getImageDir('horde'),
                             'url' => Horde::applicationUrl('search.php')));
    }

}
