<?php

require_once 'Horde/Form/Renderer.php';

/**
 * Turba Form Renderer
 *
 * $Horde: turba/lib/Renderer.php,v 1.19.6.1 2005/10/18 12:50:05 jan Exp $
 *
 * @package Turba
 */
class Turba_Renderer extends Horde_Form_Renderer {

    var $_active = false;
    var $_object;

    function setObject(&$object)
    {
        $this->_object = &$object;
    }

    function beginActive($name)
    {
        $this->_active = true;
        parent::beginActive($name);
    }

    function beginInactive($name)
    {
        $this->_active = false;
        parent::beginInactive($name);
    }

    function _sectionHeader($title)
    {
        $actions = array();
        if (!$this->_active && is_a($this->_object, 'Turba_Object')) {
            $params = array('source' => $this->_object->driver->name,
                            'key'    => $this->_object->getValue('__key'));
            if ($this->_object->hasPermission(PERMS_EDIT)) {
                $url = Util::addParameter(Horde::applicationUrl('edit.php'), $params);
                $actions[] = '<li>' . Horde::link($url, _("Edit")) . _("Edit") . '</a>';
            }
            if ($this->_object->hasPermission(PERMS_DELETE)) {
                $url = Util::addParameter(Horde::applicationUrl('delete.php'), $params);
                $actions[] = '<li>' .
                    Horde::link($url, _("Delete"), '', '',
                                $GLOBALS['prefs']->getValue('delete_opt') ?
                                'return window.confirm(\'' . addslashes(_("Really delete this contact?")) . '\');' : '') .
                    _("Delete") . '</a>';
            }
        }
        echo '<div class="header">';
        if (!empty($actions)) {
            echo '<ul>' . implode(' | </li>', $actions) . '</li></ul>';
        }
        echo htmlspecialchars($title);
        echo '</div>';
    }

}
