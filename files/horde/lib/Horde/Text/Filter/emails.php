<?php
/**
 * The Text_Filter_emails:: class finds email addresses in a block of text and
 * turns them into links.
 *
 * Parameters:
 * <pre>
 * always_mailto -- If true, a mailto: link is generated always.  Only if no
 *                  mail/compose registry API method exists otherwise.
 * class         -- CSS class of the generated <a> tag.  Defaults to none.
 * </pre>
 *
 * $Horde: framework/Text_Filter/Filter/emails.php,v 1.15.10.14 2007/03/01 07:10:31 slusarz Exp $
 *
 * Copyright 2003-2007 Tyler Colbert <tyler-hordeml@colberts.us>
 * Copyright 2004-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author Tyler Colbert <tyler-hordeml@colberts.us>
 * @author Jan Schneider <jan@horde.org>
 * @package Horde_Text
 */
class Text_Filter_emails extends Text_Filter {

    /**
     * Filter parameters.
     *
     * @var array
     */
    var $_params = array('always_mailto' => false,
                         'class' => '',
                         'capital_tags' => false);

    /**
     * Returns a hash with replace patterns.
     *
     * @return array  Patterns hash.
     */
    function getPatterns()
    {
        global $registry;

        $class = empty($this->_params['class']) ? '' : ' class="' . $this->_params['class'] . '"';
        $tag = $this->_params['capital_tags'] ? 'A' : 'a';

        $regexp = <<<EOR
            /
            # Version 1: mailto: links with any valid email characters.
            # Pattern 1: Outlook parenthesizes in sqare brackets
            (\[\s*)?
            # Pattern 2: mailto: protocol prefix
            (mailto:\s?)
            # Pattern 3: email address
            ([^\s\?"<]*)
            # Pattern 4 to 6: Optional parameters
            ((\?)([^\s"<]*[\w+#?\/&=]))?
            # Pattern 7: Closing Outlook square bracket
            ((?(1)\s*\]))

            |
            # Version 2 Pattern 8 and 9: simple email addresses.
            (^|\s|&lt;)([\w-+.=]+@[-A-Z0-9.]*[A-Z0-9])
            # Pattern 10 to 12: Optional parameters
            ((\?)([^\s"<]*[\w+#?\/&=]))?

            /eix
EOR;

        if (is_a($registry, 'Registry') &&
            $registry->hasMethod('mail/compose') &&
            !$this->_params['always_mailto']) {
            /* If we have a mail/compose registry method, use it. */
            $replacement = 'Text_Filter_emails::callback(\'' . $tag .
                '\', \'' . $class . '\', \'$1\', \'$2\', \'$3\', \'$4\', \'$6\', \'$7\', \'$8\', \'$9\', \'$10\', \'$12\')';
        } else {
            /* Otherwise, generate a standard mailto: and let the
             * browser handle it. */
            $replacement = <<<EOP
                '$8' === '' ?

                '$1$2<$tag$class href="mailto:$3$4" title="' . sprintf(_("New Message to %s"), htmlspecialchars('$3')) .
                '">$3$4</$tag>$7' :

                '$8<$tag$class href="mailto:$9$10" title="' . sprintf(_("New Message to %s"), htmlspecialchars('$9')) .
                '">$9$10</$tag>'
EOP;
        }

        return array('regexp' => array($regexp => $replacement));
    }

    function callback($tag, $class, $bracket1, $protocol, $email, $args_long,
                      $args, $bracket2, $prefix, $email2, $args_long2, $args2)
    {
        if (!empty($email2)) {
            $args = $args2;
            $email = $email2;
            $args_long = $args_long2;
        }

        parse_str($args, $extra);
        $url = $GLOBALS['registry']->call('mail/compose',
                                          array(array('to' => $email),
                                          $extra));
        $url = str_replace('&amp;', '&', $url);
        if (substr($url, 0, 11) == 'javascript:') {
            $href = '#';
            $onclick = ' onclick="' . substr($url, 11) . '"';
        } else {
            $href = $url;
            $onclick = '';
        }

        return $bracket1 . $protocol . $prefix . '<' . $tag . $class . ' href="' .
            $href . '" title="' . sprintf(_("New Message to %s"), htmlspecialchars($email)) . '"' .
            $onclick . '>' . htmlspecialchars($email) . $args_long . '</' . $tag . '>' . $bracket2;
    }

}
