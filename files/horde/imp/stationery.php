<?php
/**
 * $Horde: imp/stationery.php,v 2.1.2.4 2007/01/02 13:54:54 jan Exp $
 *
 * Copyright 2005-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you did
 * not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('IMP_BASE', dirname(__FILE__));
$authentication = OP_HALFOPEN;
require_once IMP_BASE . '/lib/base.php';
require_once 'Horde/Prefs/UI.php';

$compose_url = Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/services/prefs.php', true), 'app', 'imp', false);

/* Is the preference locked? */
if ($prefs->isLocked('stationery')) {
    header('Location: ' . $compose_url);
    exit;
}

/* Retrieve stationery. */
$stationery_list = @unserialize($prefs->getValue('stationery', false));
if (is_array($stationery_list)) {
    $stationery_list = String::convertCharset($stationery_list, $prefs->getCharset());
} else {
    $stationery_list = array();
}
$stationery = null;

/* Get form data. */
$id = Util::getFormData('id');
if (!is_null($id)) {
    $id = (int)$id;
}
$selected = Util::getFormData('stationery');
if (strlen($selected)) {
    $selected = (int)$selected;
}

/* Run through the action handlers. */
$actionID = Util::getFormData('actionID');
$updated = false;
switch ($actionID) {
case 'update':
    if (Util::getFormData('edit')) {
        /* Stationery has been switched. */
        if (strlen($selected)) {
            /* Edit existing. */
            $stationery = array('n' => $stationery_list[$selected]['n'],
                                't' => $stationery_list[$selected]['t'],
                                'c' => $stationery_list[$selected]['c']);
            $id = $selected;
        } else {
            /* Create new. */
            $stationery = array('n' => '', 't' => 'plain', 'c' => '');
        }
    } elseif (Util::getFormData('delete')) {
        /* Delete stationery. */
        if (isset($stationery_list[$id])) {
            $updated = sprintf(_("The stationery \"%s\" has been deleted."), $stationery_list[$id]['n']);
            unset($stationery_list[$id]);
        }
    } else {
        $last_type = Util::getFormData('last_type');
        $type = Util::getFormData('type', 'plain');
        $name = Util::getFormData('name', '');
        $content = Util::getFormData('content', '');
        $stationery = array('n' => $name, 't' => $type, 'c' => $content);
        if (!empty($last_type)) {
            if ($last_type != $type) {
                /* Switching text format. */
                if ($type == 'plain') {
                    require_once 'Horde/Text/Filter.php';
                    $content = Text_Filter::filter($content, 'html2text');
                }
                $stationery['c'] = $content;
            } else {
                /* Saving stationery. */
                if (is_null($id)) {
                    $id = count($stationery_list);
                    $stationery_list[] = $stationery;
                    $updated = sprintf(_("The stationery \"%s\" has been added."), $stationery['n']);
                } else {
                    $stationery_list[$id] = $stationery;
                    $updated = sprintf(_("The stationery \"%s\" has been updated."), $stationery['n']);
                }
            }
        } elseif (!is_null($id)) {
            $stationery = $stationery_list[$id];
        }
    }

    break;
}

if ($updated) {
    $prefs->setValue('stationery', serialize(String::convertCharset($stationery_list, NLS::getCharset(), $prefs->getCharset())), false);
    $notification->push($updated, 'horde.success');
}

if (!is_null($stationery) && $stationery['t'] == 'html') {
    require_once 'Horde/Editor.php';
    $editor = &Horde_Editor::singleton('xinha', array('id' => 'content'));
}

/* Show the header. */
require_once 'Horde/Prefs/UI.php';
require IMP_BASE . '/config/prefs.php';
$app = 'imp';
$group = 'compose';

Prefs_UI::generateHeader();
require IMP_TEMPLATES . '/stationery/prefs.inc';

require $registry->get('templates', 'horde') . '/common-footer.inc';
