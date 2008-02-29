<?php
/**
 * Horde_Form_Action_reload is a Horde_Form Action that reloads the
 * form with the current (not the original) value after the form element
 * that the action is attached to is modified.
 *
 * $Horde: framework/Form/Form/Action/reload.php,v 1.7.10.5 2007/01/02 13:54:19 jan Exp $
 *
 * Copyright 2003-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Horde_Form
 */
class Horde_Form_Action_reload extends Horde_Form_Action {

    var $_trigger = array('onchange');

    function getActionScript($form, $renderer, $varname)
    {
        return 'if (this.value) { document.' . $form->getName() . '.formname.value=\'\';' .
            'document.' . $form->getName() . '.submit() }';
    }

}
