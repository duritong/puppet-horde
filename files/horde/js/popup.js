/**
 * Horde Popup JavaScript.
 *
 * Provides the javascript to open popup windows.
 *
 * $Horde: horde/js/popup.js,v 1.2.10.4 2006/02/10 18:14:54 chuck Exp $
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */
function popup(url, width, height)
{
    if (!width) {
        width = 600;
    }
    if (!height) {
        height = 500;
    }

    var name = (new Date()).getTime();
    var param = "toolbar=no,location=no,status=yes,scrollbars=yes,resizable=yes,width=" + width + ",height=" + height + ",left=0,top=0";
    var win = window.open(url, name, param);
    if (!win) {
        alert("A popup window could not be opened. Your browser may be blocking popups for this application.");
    } else {
        if (typeof win.name == 'undefined') {
            win.name = name;
        }
        if (typeof win.opener == 'undefined') {
            win.opener = self;
        }
    }

    return win;
}
