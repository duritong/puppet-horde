/**
 * Javascript code for attaching an onkeydown listener to textarea and
 * text input elements to prevent loss of data when the user hits the
 * ESC key.
 *
 * $Horde: horde/js/ieEscGuard.js,v 1.1.2.3 2005/10/18 11:33:38 jan Exp $
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

/* This code is only relevant for IE. Therefore try not to do anything
 * if the user isn't using IE, even if this script is included. This
 * check will catch some browsers that are not IE but identify
 * themselves as IE. Hopefully if they pass through they support what
 * IE supports. */
if (navigator.appVersion.indexOf('MSIE') != -1 && document.all) {
    /* We do everything onload so that the entire document is present
     * before we start searching it. */
    window.attachEvent('onload', guard);
}

/**
 * Finds all text inputs (input type="text") and textarea tags, and
 * attaches the onkeydown listener to them to avoid ESC clearing the
 * text.
 */
function guard()
{
    /* Finds all textareas. */
    var textareas = document.all.tags('TEXTAREA');
    for (var i = 0; i < textareas.length; i++) {
        textareas[i].attachEvent('onkeydown', disableEscape);
    }

    /* Finds _all_ <input> tags. */
    var inputs = document.all.tags('INPUT');
    for (var i = 0; i < inputs.length; i++) {
        /* Only attach to <input type="text"> tags. */
        if (inputs[i].type == 'text') {
            inputs[i].attachEvent('onkeydown', disableEscape);
        }
    }
}

/**
 * Returns false if the user hit the ESC key, preventing IE from
 * blanking the field. Otherwise returns true.
 *
 * return boolean  Whether or not to allow the key event.
 */
function disableEscape()
{
    return window.event.keyCode != 27;
}
