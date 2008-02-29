<?php
/**
 * $Horde: imp/spelling.php,v 2.67.6.9 2007/02/05 02:56:04 chuck Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

define('IMP_SPELL_CHANGE',     1);
define('IMP_SPELL_CHANGE_ALL', 2);
define('IMP_SPELL_IGNORE',     3);
define('IMP_SPELL_IGNORE_ALL', 4);

/* Base list of words to ignore. */
$ignore_list = array(
    'com', 'cc', 'www', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul',
    'aug', 'sep', 'oct', 'nov', 'dec', 'fwd', 'dns', 'http', 'ca', 'html',
    'tm', 'mmunity', 'co', 'op', 'https', 'netscape', 'webmail', 'bcc',
    'jpg', 'gif', 'email', 'tel', 'ie', 'eg'
);

/**
 * Find the offset of a given word.
 *
 * @param string $message  The text of the message.
 * @param string $word     The word to find.
 * @param integer $start   The offset.
 *
 * @return integer  The offset of the word.
 */
function _findOffset($message, $word, $start)
{
    $offset = $start;
    $pos = -1;

    /* If all things are right the word is at offset - 1 */
    if ($start > 3) {
        $start -= 3;
    } else {
        $start = 0;
    }
    while (($pos == -1) && ($start >= 0)) {
        $pos = String::pos($message, $word, $start);
        if (($pos == '') && !is_int($pos)) {
            $start--;
            $pos = -1;
        }
    }

    return $pos;
}


/**
 * Highlight in error in a given message.
 *
 * @param string $error    The misspelled word.
 * @param string $message  The text of the message.
 * @param integer $start   The offset.
 *
 * @return string  The string with the error highlighted.
 */
function _highlightError($error, $message, $offset)
{
    $pos = strpos($message, $error, ((($offset - 1) > 0) ? ($offset - 1) : 0));
    if (($pos - 15) > 0) {
        $start = $pos - 15;
    } else {
        $start = 0;
    }
    $length = String::length($error) + 30;

    $message = substr_replace($message, '<span style="color:#ff0000">' . $error . '</span>', $pos, String::length($error));
    $message = String::substr($message, $start, $length + 28);

    return $message;
}

/* Fetch and clean form data.  These are spelling.php specific form variables
 * only. */
$f_opt = Util::getFormData('opt');
$f_subs = Util::getFormData('subs');
$f_oldword = Util::getFormData('oldword');
$f_subtext = Util::getFormData('subtext');
$f_wordoffset = Util::getFormData('wordoffset');
$f_oldmsg = Util::getFormData('oldmsg', Util::getFormData('message'));
$f_currmsg = Util::getFormData('currmsg', $f_oldmsg);
$f_newmsg = Util::getFormData('newmsg');
$f_done_action = Util::getFormData('done_action');

/* Get from data from compose.php input that is used locally in
 * spelling.php. */
$f_rtemode = Util::getFormData('rtemode');

/* ignoreall is an array - we need to unserialize the data. */
if (($ignoreall = Util::getFormData('ignoreall'))) {
    $ignoreall = unserialize($ignoreall);
} else {
    $ignoreall = array();
}

switch ($actionID) {
case 'spell_check_forward':
    for ($i = 0; $i < count($f_opt); $i++) {
        $skipword = false;

        /* If they have an word with no suggestions and they
           don't type in a replacement, ignore it. */
        if (!empty($f_subtext[$i])) {
            $replacement = $f_subtext[$i];
        } else {
            if ($f_subs[$i] == '0') {
                $ignoreall[] = String::lower($f_oldword[$i], true);
                $skipword = true;
            } else {
                $replacement = $f_subs[$i];
            }
        }

        if (!$skipword) {
            $pos = -1;

            $realoffset = $f_wordoffset[$i];

            /* Just in case things are whackily out. */
            $msg_length = String::length($f_currmsg);
            if ($realoffset > $msg_length) {
                $realoffset = $msg_length - 1;
            }

            $pos = _findOffset($f_currmsg, $f_oldword[$i], $realoffset);

            switch ($f_opt[$i]) {
            case IMP_SPELL_IGNORE:
                $consume = $pos + String::length($f_oldword[$i]);
                $addition = String::substr($f_currmsg, 0, $consume);
                $f_newmsg .= $addition;
                $f_currmsg = String::substr($f_currmsg, $consume);

                /* Adjust offsets, as they could be wildly out */
                for ($msgnum = 0; $msgnum < count($f_wordoffset); $msgnum++) {
                    $f_wordoffset[$msgnum] -= $consume;
                }
                break;

            case IMP_SPELL_IGNORE_ALL:
                $ignoreall[] = String::lower($f_oldword[$i], true);
                break;

            case IMP_SPELL_CHANGE_ALL:
                $f_currmsg = str_replace($f_oldword[$i], $replacement, $f_currmsg);
                break;

            case IMP_SPELL_CHANGE:
                if (!in_array(String::lower($f_oldword[$i], true), $ignoreall)) {
                    /* Let's try and keep those offsets semi correct. */
                    $adjoffset = String::length($replacement) - String::length($f_oldword[$i]);
                    for ($msgnum = 0; $msgnum < count($f_wordoffset); $msgnum++) {
                        $f_wordoffset[$msgnum] += $adjoffset;
                    }

                    $tempmessage  = String::substr($f_currmsg, 0, $pos);
                    $tempmessage .= $replacement;
                    $tempmessage .= String::substr($f_currmsg, $pos + String::length($f_oldword[$i]));
                    $f_currmsg = $tempmessage;
                }
                break;
            }
        }
    }
    break;

case 'spell_check_send':
    $f_done_action = 'send';
    /* FALLTHROUGH */

default:
    $f_message = $f_currmsg;

    /* Have to start another wordlist to incorporate into the spell
       check dictionary methinks. */
    $ignoreall = $ignore_list;
    break;
}

/* Special treatment depending on language (quotes are not equally treated
   by ispell in english and in french). */
switch ($language) {
case 'fr_FR':
    $tocheck = str_replace("'", "\\'", escapeShellCmd($f_currmsg));
    break;

default:
    $tocheck = $f_currmsg;
    break;
}

$spellchecker = $conf['utils']['spellchecker'];

/* Protect against lines beginning with "special" characters as defined at
   http://aspell.net/man-html/Through-A-Pipe.html#Through%20A%20Pipe */
if (strpos($spellchecker, 'aspell') !== false) {
    $tocheck = '^' . str_replace("\n", "\n^", $tocheck);
}

/* Save the message to a temporary file. */
$spellFile = Horde::getTempFile('spell');
$fp = fopen($spellFile, 'w');
fwrite($fp, $tocheck);
fclose($fp);

/* Run the actual spell check. */
if (empty($spellchecker)) {
    $notification->push(_("No spellchecking program configured."), 'horde.error');
} else {
    /* Retrieve any spelling options. */
    $spell_opt = '';
    if (isset($nls['spelling'][$language])) {
        $spell_opt = $nls['spelling'][$language];
    }
    if (strpos($spellchecker, 'aspell') != false) {
        $spell_opt .= ' -e';
    }
    if ($f_rtemode) {
        // Try to use the Spellchecker's HTML mode
        if (strpos($spellchecker, 'ispell') !== false) {
            $spell_opt .= ' -h';
        } elseif (strpos($spellchecker, 'aspell') !== false) {
            $spell_opt .= ' -H';
        }
    }

    exec(escapeshellcmd($spellchecker . ' -a ' . $spell_opt) . ' < ' . $spellFile, $warnings);
}

$msg = '';
for ($i = 0; $i < count($warnings); $i++) {
    if (substr($warnings[$i], 0, 1) == '&') {
        $parts = explode(': ', $warnings[$i]);
        $info = explode(' ', $parts[0]);
        if (preg_match('|^[A-Z]*$|', $info[1])) {
            $ignoreall[] = String::lower($info[1], true);
        } else {
            $error[] = array($info[1], $info[3], $parts[1]);
        }
    }
    if (preg_match('|^#|', $warnings[$i])) {
        $info = explode(' ', $warnings[$i]);
        if (preg_match('|^[A-Z]*$|', $info[1])) {
            $ignoreall[] = String::lower($info[1], true);
        } else {
            $error[] = array($info[1], $info[2], '');
        }
    }
}

/* Generate a pretty word-wrapped version for displaying. */
$display_msg = $f_message = "\n" . $f_newmsg . $f_currmsg;
if (!$f_rtemode) {
    $display_msg = htmlspecialchars($display_msg);
    $display_msg = String::wrap($display_msg, 80, "\n", NLS::getCharset(), true);
}
if ($browser->hasQuirk('double_linebreak_textarea')) {
    $display_msg = preg_replace('/(\r?\n){3}/', '$1', $display_msg);
}

/* Alter the "Done" behavior if we're sending the message after spell check. */
if ($f_done_action === 'send') {
    $spell_check_done_action = 'send_message';
    $spell_check_done_caption = _("(Send message after applying all changes made thus far.  Changes on the current screen will NOT be applied.)");
} else {
    $spell_check_done_action = 'spell_check_done';
    $spell_check_done_caption = _("(Return to the compose screen after applying all changes made thus far.  Changes on the current screen will NOT be applied.)");
}
