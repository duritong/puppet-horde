/**
 * Javascript to trap for the enter key.
 *
 * $Horde: horde/js/enter_key_trap.js,v 1.2.10.3 2006/05/05 15:33:34 chuck Exp $
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */
function enter_key_trap(event)
{
    var key;
    if (event.keyCode) {
        key = event.keyCode;
    } else if (event.which) {
        key = event.which;
    } else {
        return false;
    }

    return (key == 10 || key == 13);
}
