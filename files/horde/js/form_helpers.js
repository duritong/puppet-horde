/**
 * Javascript to add events to form elements
 *
 * $Horde: horde/js/form_helpers.js,v 1.3.4.7 2007/01/02 13:55:04 jan Exp $
 *
 * Copyright 2004-2007 Matt Kynaston <matt@kynx.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

/**
 * Adds the given event to an element. If the element already has
 * script for the event, the new event is appended.
 *
 * @param object element   The element to add the event to.
 * @param string event     The name of the event.
 * @param string|function function  The javascript to execute.
 */
function addEvent(element, event, code)
{
    if (!element) {
        return false;
    }

    // Assign new anonymous function if we're passed a js string
    // instead of a function reference.
    if (typeof code == 'string') {
        code = new Function(code);
    }

    if (element.addEventListener) {
        element.addEventListener(event.replace(/on/, ''), code, false);
    } else if (element.attachEvent) {
        element.attachEvent(event, code);
    } else if (element.onload != null) {
        var OldEvent = element[event];
        newCode = Function(e)
        {
            oldEvent(e);
            code();
        };
        element[event] = newCode;
    } else {
        element[event] = code;
    }

    return true;
}

/**
 * Returns given value as a number, or zero if NaN.
 *
 * @param mixed  val
 *
 * @return number
 */
function toNumber(val)
{
    if (isNaN(val)) {
        return 0;
    } else {
        return Number(val);
    }
}

/**
 * Sets the enabled state of one element based on the values of another.
 *
 * Takes four or more arguments, in the form:
 *   checkEnabled(source, target, true, value1, value2, value3...)
 *
 * @param object   The element to check
 * @param string   The element to enable/disable
 * @param boolean  Whether to enable or disable the target
 * @param mixed    The value to check against
 */
function checkEnabled()
{
    if (arguments.length > 2) {
        objSrc = arguments[0];
        objTarget = objSrc.form.elements[arguments[1]];
        enabled = arguments[2];
        toggle = false;
        if (objTarget) {
            switch (objSrc.type.toLowerCase()) {
            case 'select-one' :
                val = objSrc.options[objSrc.selectedIndex].value;
                break;

            case 'select-multiple' :
                val = new Array();
                count = 0;
                for (var i = 0; i < objSrc.length; ++i) {
                    if (objSrc.options[i].selected) {
                        val[count] = objSrc.options[i].value;
                    }
                }
                break;

            case 'checkbox' :
                if (objSrc.checked) {
                    val = objSrc.value;
                    toggle = true;
                }
                break;

            default :
                val = objSrc.value;
            }

            for (var i = 3; i < arguments.length; ++i) {
                if (typeof(val) == 'object' && (arguments[i] in val)) {
                    toggle = true;
                    break;
                } else if (arguments[i] == val) {
                    toggle = true;
                    break;
                }
            }

            objTarget.disabled = toggle ? !enabled : enabled;
            if (!objTarget.disabled) {
                objTarget.focus();
            }
        }
    }
}

/**
 * Sets the target field to the sum of a range of fields.
 *
 * Takes three or more arguments, in the form:
 *    sumFields(form, target, field1, field2, field3...)
 *
 * @param object  The form to check
 * @param string  The name of the target element
 * @param string  One or more field names to sum
 */
function sumFields()
{
    if (arguments.length > 2) {
        objFrm = arguments[0];
        objTarget = objFrm.elements[arguments[1]];
        sum = 0;
        if (objTarget) {
            for (var i = 2; i < arguments.length; ++i) {
                objSrc = objFrm.elements[arguments[i]];
                if (objSrc) {
                    switch (objSrc.type.toLowerCase()) {
                    case 'select-one':
                        sum += toNumber(objSrc.options[objSrc.selectedIndex].value);
                        break;

                    case 'select-multiple' :
                        for (var j = 0; j < objSrc.length; ++j) {
                            sum += toNumber(objSrc.options[j].value);
                        }
                        break;

                    case 'checkbox' :
                        if (objSrc.checked) {
                            sum += toNumber(objSrc.value);
                        }
                        break;

                    default :
                        sum += toNumber(objSrc.value);
                    }
                }
            }

            objTarget.value = sum;
        }
    }
}
