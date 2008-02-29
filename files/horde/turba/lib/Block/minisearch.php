<?php

$block_name = _("Contact Search");

/**
 * Turba_Minisearch_Block:: Implementation of the Horde_Block API to
 * allows searching of addressbooks from the portal.
 *
 * $Horde: turba/lib/Block/minisearch.php,v 1.17.2.3 2006/01/03 20:50:18 chuck Exp $
 *
 * @package Horde_Block
 */
class Horde_Block_turba_minisearch extends Horde_Block {

    var $_app = 'turba';

    /**
     * The title to go in this block.
     *
     * @return string   The title text.
     */
    function _title()
    {
        global $registry;

        return Horde::link(Horde::url($registry->getInitialPage(), true)) . _("Contact Search") . '</a>';
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content
     */
    function _content()
    {
        global $browser, $registry, $prefs;
        require_once dirname(__FILE__) . '/../base.php';

        if ($browser->hasFeature('iframes')) {
            return Util::bufferOutput('include', TURBA_TEMPLATES . '/block/minisearch.inc');
        } else {
            return '<em>' . _("A browser that supports iFrames is required") . '</em>';
        }
    }

}
