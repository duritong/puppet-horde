/**
 * General Horde UI effects javascript - fixes for IE that can't
 * easily be done without browser detection.
 *
 * $Horde: horde/js/horde.ie.js,v 1.1.2.2 2006/01/29 10:18:20 chuck Exp $
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

ToolTips_Option_Windowed_Controls = 1;

if (typeof Array.prototype.push == 'undefined') {
     Array.prototype.push = function() {
         var l = this.length;
         for (var i = 0; i < arguments.length; i++) {
             this[l + i] = arguments[i];
         }
         return this.length
     }
}
