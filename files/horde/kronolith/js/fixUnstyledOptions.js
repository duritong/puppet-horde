/**
 * Safari and Konqueror don't style select lists. Find them and add a
 * "*" to options with class="selected" so that the user has an
 * indication of which shares are chosen.
 *
 * $Horde: kronolith/js/fixUnstyledOptions.js,v 1.1.2.2 2006/03/12 03:08:35 chuck Exp $
 */

/* We do everything onload so that the entire document is present
 * before we start searching it for <option> elements. */
if (window.addEventListener) {
    window.addEventListener('load', mark_selected_options, false);
} else if (window.attachEvent) {
    window.attachEvent('onload', mark_selected_options);
} else if (window.onload != null) {
    var old_onload = window.onload;
    window.onload = function(e)
    {
        old_onload(e);
        mark_selected_options();
    };
} else {
    window.onload = mark_selected_options;
}

function mark_selected_options()
{
    if (!document.getElementsByTagName) {
        return;
    }
    options = document.getElementsByTagName('option');
    for (var i = 0; i < options.length; ++i) {
        if (options[i].className.indexOf('selected') != -1) {
            options[i].innerHTML = "* " + options[i].innerHTML;
        }
    }
}
