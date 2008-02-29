<?php
/**
 * The Editor_htmlarea:: class provides an WYSIWYG editor for use
 * in the Horde Framework.
 *
 * $Horde: framework/Editor/Editor/htmlarea.php,v 1.24.4.5 2007/01/02 13:54:16 jan Exp $
 *
 * Copyright 2003-2007 Nuno Loureiro <nuno@co.sapo.pt>
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Nuno Loureiro <nuno@co.sapo.pt>
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 3.0
 * @package Horde_Editor
 */
class Horde_Editor_htmlarea extends Horde_Editor {

    /**
     * Constructor. Include the necessary javascript files.
     */
    function Horde_Editor_htmlarea($params = array())
    {
        global $registry, $notification, $prefs;

        Horde::addScriptFile('htmlarea.js', 'horde');
        Horde::addScriptFile('/services/editor/htmlarea/htmlarea.js', 'horde', true);
        $script = Horde::url(Util::addParameter($registry->get('webroot', 'horde') . '/services/javascript.php', 'app', 'horde'), true);
        $js = 'HTMLArea.loadScript("' . Util::addParameter($script, 'file',  'htmlarea_lang.js', false) . '"); ';

        $plugins = '';
        if ($prefs->getValue('tableoperations')) {
            $js .= 'HTMLArea.loadPlugin("TableOperations"); ' .
                   'HTMLArea.loadScript("' . Util::addParameter($script, 'file',  'htmlarea_table_lang.js', false) . '"); ';
            $plugins .= 'editor.registerPlugin(TableOperations); ';
        }
        if ($prefs->getValue('contextmenu')) {
            $js .= 'HTMLArea.loadPlugin("ContextMenu"); ' .
                   'HTMLArea.loadScript("' . Util::addParameter($script, 'file',  'htmlarea_context_lang.js', false) . '"); ';
            $plugins .= 'editor.registerPlugin(ContextMenu); ';
        }
        if ($prefs->getValue('listtype')) {
            $js .= 'HTMLArea.loadPlugin("ListType"); ' .
                   'HTMLArea.loadScript("' . Util::addParameter($script, 'file',  'htmlarea_listtype_lang.js', false) . '"); ';
            $plugins .= 'editor.registerPlugin(ListType); ';
        }
        if ($prefs->getValue('anselimage') && $registry->hasMethod('images/listGalleries')) {
            $js .= 'HTMLArea.loadPlugin("AnselImage"); ' .
                   'HTMLArea.loadScript("' . Util::addParameter($script, 'file',  'htmlarea_anselimage_lang.js', false) . '"); ';
            $plugins .= 'editor.registerPlugin(AnselImage); ';
        }

        $js .= 'HTMLArea.onload = function() { ' .
               'var config = new HTMLArea.Config() ;' .
               'config.debug = false; ' .
               'config.hideSomeButtons(" showhelp "); ';
        if (isset($params['config'])) {
            foreach ($params['config'] as $config => $value) {
                $js .= 'config.' . $config . ' = "' . addslashes($value) . '";';
            }
        }

        if (isset($params['id'])) {
            $js .= 'var textareas = [document.getElementById("' . $params['id'] . '")]; ';
        } else {
            $js .= 'var textareas = document.getElementsByTagName("textarea"); ';
        }
        $js .= 'for (var i = textareas.length - 1; i >= 0; i--) { ' .
               'var editor = new HTMLArea(textareas[i], config); ' .
               $plugins .
               'editor.generate()}}; ' .
               'HTMLArea.init();';

        $notification->push($js, 'javascript');
    }

}
