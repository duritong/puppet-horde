/**
 * Horde Form Assign Field Javascript Class
 *
 * Provides the javascript class to accompany the Horde_Form assign field.
 *
 * $Horde: horde/js/form_assign.js,v 1.2.10.5 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

Horde_Form_Assign = new Object();

Horde_Form_Assign.deselectHeaders = function(name, side)
{
    if (side) {
        document[name + '__right'][0].selected = false;
    } else {
        document[name + '__left'][0].selected = false;
    }
}

Horde_Form_Assign.move = function(name, direction)
{
    var left = document[name + '__left'];
    var right = document[name + '__right'];

    if (direction) {
        var from = right;
        var to = left;
    } else {
        var from = left;
        var to = right;
    }

    for (var i = 0; i < from.length; ++i) {
        if (from[i].selected) {
            to[to.length] = new Option(from[i].text, from[i].value);
            to[to.length - 1].ondblclick = function()
            {
                Horde_Form_Assign.move(name, 1 - direction);
            }
            from[i] = null;
            --i;
        }
    }

    this.setField(name);
}

Horde_Form_Assign.setField = function(name)
{
    var left = document[name + '__left'];
    var right = document[name + '__right'];

    var values = '';
    var hit = false;
    for (var i = 0; i < left.options.length; ++i) {
        if (i == 0 && !left[i].value) {
            continue;
        }
        values += left.options[i].value + '\t';
        hit = true;
    }
    if (hit) {
        values = values.substring(0, values.length - 1);
    }
    values += '\t\t';
    hit = false;
    for (var i = 0; i < right.options.length; ++i) {
        if (i == 0 && !right[i].value) {
            continue;
        }
        values += right.options[i].value + '\t';
        hit = true;
    }
    if (hit) {
        values = values.substring(0, values.length - 1);
    }
    document[name + '__values'].value = values;
}
