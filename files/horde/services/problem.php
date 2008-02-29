<?php
/**
 * $Horde: horde/services/problem.php,v 2.114.8.9 2007/01/02 13:55:15 jan Exp $
 *
 * Copyright 1999-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 1999-2007 Jon Parise <jon@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

/* Send the browser back to the correct page. */
function _returnToPage()
{
    $url = Util::getFormData('return_url', Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/login.php', true));
    header('Location: ' . str_replace('&amp;', '&', $url));
    exit;
}

@define('AUTH_HANDLER', true);
@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';
require_once HORDE_BASE . '/lib/version.php';
require_once 'Horde/Identity.php';

if (!Horde::showService('problem')) {
    _returnToPage();
}

$identity = &Identity::singleton();
$email = $identity->getValue('from_addr');
if (!$email) {
    $email = Util::getFormData('email', Auth::getAuth());
}
$message = Util::getFormData('message', '');
$name = Util::getFormData('name', $identity->getValue('fullname'));
$subject = Util::getFormData('subject', '');

$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'send_problem_report':
    if ($subject && $message) {
        // This is not a gettext string on purpose.
        $remote = (!empty($_SERVER['REMOTE_HOST'])) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $body = "This problem report was received from $remote. " .
            "The user clicked the problem report link from the following location:\n" .
            Util::getFormData('return_url', 'No requesting page') .
            "\nand is using the following browser:\n$user_agent\n\n" .
            str_replace("\r\n", "\n", $message);

        // Default to a relatively reasonable email address.
        if (!$email) {
            $email = 'horde-problem@' . $conf['problems']['maildomain'];
        }

        if (!empty($conf['problems']['tickets']) &&
            $registry->hasMethod('tickets/addTicket')) {
            $info = array_merge($conf['problems']['ticket_params'],
                                array('summary' => $subject,
                                      'comment' => $body,
                                      'user_email' => $email));
            $result = $registry->call('tickets/addTicket', array($info));
            if (is_a($result, 'PEAR_Error')) {
                $notification->push($result);
            } else {
                _returnToPage();
            }
        } else {
            require_once 'Horde/MIME.php';
            require_once 'Horde/MIME/Headers.php';
            require_once 'Horde/MIME/Message.php';

            // Add user's name to the email address if provided.
            if ($name) {
                @list($mailbox, $host) = @explode('@', $email, 2);
                if (empty($host)) {
                    $host = $conf['problems']['maildomain'];
                }
                $email = MIME::rfc822WriteAddress($mailbox, $host, $name);
            }

            $msg_headers = &new MIME_Headers();
            $msg_headers->addReceivedHeader();
            $msg_headers->addMessageIdHeader();
            $msg_headers->addAgentHeader();
            $msg_headers->addHeader('Date', date('r'));
            $msg_headers->addHeader('To', $conf['problems']['email']);
            $msg_headers->addHeader('Subject', _("[Problem Report]") . ' ' . $subject);
            $msg_headers->addHeader('From', $email);
            $msg_headers->addHeader('Sender', 'horde-problem@' . $conf['problems']['maildomain']);

            $mime = &new MIME_Message();
            $mime->addPart(new MIME_Part('text/plain',
                                         String::wrap($body, 80, "\n"),
                                         NLS::getCharset()));
            $msg_headers->addMIMEHeaders($mime);

            $mail_driver = $conf['mailer']['type'];
            $mail_params = $conf['mailer']['params'];
            if ($mail_driver == 'smtp' && $mail_params['auth'] &&
                empty($mail_params['username'])) {
                if (Auth::getAuth()) {
                    $mail_params['username'] = Auth::getAuth();
                    $mail_params['password'] = Auth::getCredential('password');
                } elseif (!empty($conf['problems']['username']) &&
                          !empty($conf['problems']['password'])) {
                    $mail_params['username'] = $conf['problems']['username'];
                    $mail_params['password'] = $conf['problems']['password'];
                }
            }

            if (!is_a($sent = $mime->send($conf['problems']['email'], $msg_headers, $mail_driver, $mail_params), 'PEAR_Error')) {
                /* We succeeded. Return to previous page and exit this script. */
                _returnToPage();
            } else {
                $notification->push($sent);
            }
        }
    }
    break;

case 'cancel_problem_report':
    _returnToPage();
}

$title = _("Problem Description");
$menu = &new Menu(HORDE_MENU_MASK_ALL & ~HORDE_MENU_MASK_PREFS);
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/menu/menu.inc';
$notification->notify(array('listeners' => 'status'));
require HORDE_TEMPLATES . '/problem/problem.inc';
require HORDE_TEMPLATES . '/common-footer.inc';
