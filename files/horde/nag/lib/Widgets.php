<?php
/**
 * The Widgets class provides various functions used to generate and
 * handle form input for Nag.
 *
 * $Horde: nag/lib/Widgets.php,v 1.2.12.8 2007/01/02 13:55:12 jan Exp $
 *
 * Copyright 2002-2007 Jon Parise <jon@horde.org>
 * Copyright 2003-2007 Michael Slusarz <slusarz@bigworm.colorado.edu>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Jon Parise <jon@horde.org>
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @package Nag
 */
class Widgets {

    /**
     * Generates the HTML for a day selection widget.
     *
     * @param string $name      The name of the widget.
     * @param integer $default  The value to select by default. Range: 1-31
     * @param string $params    Any additional parameters to include in the <a>
     *                          tag.
     *
     * @return string  The HTML <select> widget.
     */
    function buildDayWidget($name, $default = null, $params = null)
    {
        $html = '<select id="' . $name . '" name="' . $name. '"';
        if (!is_null($params)) {
            $html .= ' ' . $params;
        }
        $html .= '>';

        for ($day = 1; $day <= 31; $day++) {
            $html .= '<option value="' . $day . '"';
            $html .= ($day == $default) ? ' selected="selected">' : '>';
            $html .= $day . '</option>';
        }

        return $html . "</select>\n";
    }

    /**
     * Generates the HTML for a month selection widget.
     *
     * @param string $name      The name of the widget.
     * @param integer $default  The value to select by default.
     * @param string $params    Any additional parameters to include in the <a>
     *                          tag.
     *
     * @return string  The HTML <select> widget.
     */
    function buildMonthWidget($name, $default = null, $params = null)
    {
        $html = '<select id="' . $name . '" name="' . $name. '"';
        if (!is_null($params)) {
            $html .= ' ' . $params;
        }
        $html .= '>';

        for ($month = 1; $month <= 12; $month++) {
            $html .= '<option value="' . $month . '"';
            $html .= ($month == $default) ? ' selected="selected">' : '>';
            $html .= strftime('%B', mktime(0, 0, 0, $month, 1)) . '</option>';
        }

        return $html . "</select>\n";
    }

    /**
     * Generates the HTML for a year selection widget.
     *
     * @param integer $name    The name of the widget.
     * @param integer $years   The number of years to include.
     *                         If (+): future years
     *                         If (-): past years
     * @param string $default  The timestamp to select by default.
     * @param string $params   Any additional parameters to include in the <a>
     *                         tag.
     *
     * @return string  The HTML <select> widget.
     */
    function buildYearWidget($name, $years, $default = null, $params = null)
    {
        $curr_year = date('Y');
        $yearlist = array();

        $startyear = (!is_null($default) && ($default < $curr_year) && ($years > 0)) ? $default : $curr_year;
        $startyear = min($startyear, $startyear + $years);
        for ($i = 0; $i <= abs($years); $i++) {
            $yearlist[] = $startyear++;
        }
        if ($years < 0) {
            $yearlist = array_reverse($yearlist);
        }

        $html = '<select id="' . $name . '" name="' . $name. '"';
        if (!is_null($params)) {
            $html .= ' ' . $params;
        }
        $html .= '>';

        foreach ($yearlist as $year) {
            $html .= '<option value="' . $year . '"';
            $html .= ($year == $default) ? ' selected="selected">' : '>';
            $html .= $year . '</option>';
        }

        return $html . "</select>\n";
    }

    /**
     * Generates the HTML for an hour selection widget.
     *
     * @param string $name      The name of the widget.
     * @param integer $default  The timestamp to select by default.
     * @param string $params    Any additional parameters to include in the <a>
     *                          tag.
     *
     * @return string  The HTML <select> widget.
     */
    function buildHourWidget($name, $default = null, $params = null)
    {
        global $prefs;
        if (!$prefs->getValue('twentyFour')) {
            $default = ($default + 24) % 12;
        }

        $html = '<select name="' . $name. '"';
        if (!is_null($params)) {
            $html .= ' ' . $params;
        }
        $html .= '>';

        $min = $prefs->getValue('twentyFour') ? 0 : 1;
        $max = $prefs->getValue('twentyFour') ? 23 : 12;
        for ($hour = $min; $hour <= $max; $hour++) {
            $html .= '<option value="' . $hour . '"';
            $html .= ($hour == $default) ? ' selected="selected">' : '>';
            $html .= $hour . '</option>';
        }

        return $html . '</select>';
    }

    function buildAmPmWidget($name, $default = 'am', $amParams = null, $pmParams = null)
    {
        global $prefs;
        if ($prefs->getValue('twentyFour')) {
            return;
        }

        if (is_numeric($default)) {
            $default = date('a', mktime($default));
        }
        if ($default == 'am') {
            $am = ' checked="checked"';
            $pm = '';
        } else {
            $am = '';
            $pm = ' checked="checked"';
        }

        $html  = '<input id="' . $name . '_am" type="radio" name="' . $name . '" value="am"' . $am . (!empty($amParams) ? ' ' . $amParams : '') . ' /><label for="' . $name . '_am"' . (!empty($amParams) ? ' ' . $amParams : '') . '">AM</label>&nbsp;&nbsp;';
        $html .= '<input id="' . $name . '_pm" type="radio" name="' . $name . '" value="pm"' . $pm . (!empty($pmParams) ? ' ' . $pmParams : '') . ' /><label for="' . $name . '_pm"' . (!empty($pmParams) ? ' ' . $pmParams : '') . '">PM</label>';

        return $html;
    }

    /**
     * Generates the HTML for a minute selection widget.
     *
     * @param string $name        The name of the widget.
     * @param integer $increment  The increment between minutes.
     * @param integer $default    The timestamp to select by default.
     * @param string $params      Any additional parameters to include in the
     *                            <a> tag.
     *
     * @return string  The HTML <select> widget.
     */
    function buildMinuteWidget($name, $increment = 1, $default = null,
                               $params = null)
    {
        $html = '<select name="' . $name. '"';
        if (!is_null($params)) {
            $html .= ' ' . $params;
        }
        $html .= '>';

        for ($minute = 0; $minute < 60; $minute += $increment) {
            $html .= '<option value="' . $minute . '"';
            $html .= ($minute == $default) ? ' selected="selected">' : '>';
            $html .= sprintf("%02d", $minute) . '</option>';
        }

        return $html . "</select>\n";
    }

}
