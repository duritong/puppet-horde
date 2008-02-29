<?php

require_once 'Horde/UI/VarRenderer/html.php';

/**
 * $Horde: framework/UI/UI/VarRenderer/tableset_html.php,v 1.3.2.1 2005/10/18 11:01:36 jan Exp $
 *
 * @package Horde_UI
 * @since   Horde 3.1
 */
class Horde_UI_VarRenderer_tableset_html extends Horde_UI_VarRenderer_html {

    function _renderVarInput_tableset(&$form, &$var, &$vars)
    {
        $header = $var->type->getHeader();
        $name   = $var->getVarName();
        $values = $var->getValues();
        $checkedValues = $var->getValue($vars);
        $actions = $this->_getActionScripts($form, $var);

        $html = '<table width="100%" class="item" cellspacing="1"><tr class="selected">' .
            '<th class="widget" align="right" width="1%">&nbsp;</th>';
        foreach ($header as $col_title) {
            $html .= sprintf('<th align="left" class="widget">%s</th>', $col_title);
        }
        $html .= '</tr>';

        if (!is_array($checkedValues)) {
            $checkedValues = array();
        }
        $i = 0;
        foreach ($values as $value => $displays) {
            $class = 'item' . ($i + 1) % 2;
            $checked = (in_array($value, $checkedValues)) ? ' checked="checked"' : '';
            $html .= sprintf('<tr class="%s">', $class) .
                sprintf('<td align="center"><input id="%s%s" type="checkbox" name="%s[]" value="%s"%s%s /></td>',
                        $name,
                        $i,
                        $name,
                        $value,
                        $checked,
                        $actions);
            foreach ($displays as $col) {
                $html .= sprintf('<td align="left">&nbsp;%s</td>', $col);
            }
            $html .= '</tr>';
            $i++;
        }

        return $html . '</table>';
    }

    function _renderVarDisplay_tableset(&$form, &$var, &$vars)
    {
        $header = $var->type->getHeader();
        $name   = $var->getVarName();
        $values = $var->getValues();
        $checkedValues = $var->getValue($vars);
        $actions = $this->_getActionScripts($form, $var);

        $html = '<table width="100%" cellspacing="1"><tr class="selected">' .
            '<th class="widget" align="right" width="1%">&nbsp;</th>';
        foreach ($header as $col_title) {
            $html .= sprintf('<th align="left" class="widget">%s</th>', $col_title);
        }
        $html .= '</tr>';

        if (!is_array($checkedValues)) {
            $checkedValues = array();
        }
        $i = 0;
        foreach ($values as $value => $displays) {
            $class   = 'item' . ($i + 1) % 2;
            $checked = (in_array($value, $checkedValues)) ? '[ <strong><font color=green>V</font></strong> ]' : '[ <strong><font color=red>X</font></strong> ]';
            $html .= sprintf('<tr class="%s">', $class) .
                sprintf('<td align="center">%s</td>', $checked);
            foreach ($displays as $col) {
                $html .= sprintf('<td align="left">&nbsp;%s</td>', $col);
            }
            $html .= '</tr>';
            $i++;
        }

        return $html . '</table>';
    }

}
