/**
 * Javascript code for finding all tables with classname "striped" and
 * dynamically striping their row colors.
 *
 * $Horde: ingo/js/stripe.js,v 1.1.2.1 2006/03/14 17:29:13 chuck Exp $
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

/* We do everything onload so that the entire document is present
 * before we start searching it for tables. */
if (window.addEventListener) {
    window.addEventListener('load', findStripedTables, false);
} else if (window.attachEvent) {
    window.attachEvent('onload', findStripedTables);
} else if (window.onload != null) {
    var oldOnLoad = window.onload;
    window.onload = function(e)
    {
        oldOnLoad(e);
        findStripedTables();
    };
} else {
    window.onload = findStripedTables;
}

function findStripedTables()
{
    if (!document.getElementsByTagName) {
        return;
    }
    tables = document.getElementsByTagName('table');
    for (i = 0; i < tables.length; i++) {
        if (tables[i].className.indexOf('striped') != -1) {
            stripe(tables[i]);
        }
    }
}

function stripe(table)
{
    // The flag we'll use to keep track of whether the current row is
    // odd or even.
    var even = false;

    // Tables can have more than one tbody element; get all child
    // tbody tags and interate through them.
    var tbodies = table.childNodes;
    for (var c = 0; c < tbodies.length; c++) {
        if (tbodies[c].tagName == 'TBODY') {
            var trs = tbodies[c].childNodes;
            for (var i = 0; i < trs.length; i++) {
                if (trs[i].tagName == 'TR') {
                    trs[i].className = trs[i].className.replace(/ ?rowEven ?/, '').replace(/ ?rowOdd ?/, '');
                    if (trs[i].className) {
                        trs[i].className += ' ';
                    }
                    trs[i].className += even ? 'rowEven' : 'rowOdd';

                    // Flip from odd to even, or vice-versa.
                    even = !even;
                }
            }
        }
    }
}
