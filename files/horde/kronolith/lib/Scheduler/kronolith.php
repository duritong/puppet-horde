<?php
/**
 * Horde_Scheduler_kronolith::
 *
 * Act on alarms in events and send emails/pages/etc. to users.
 *
 * $Horde: kronolith/lib/Scheduler/kronolith.php,v 1.25.6.14 2006/12/05 22:30:19 chuck Exp $
 *
 * @package Horde_Scheduler
 */
class Horde_Scheduler_kronolith extends Horde_Scheduler {

    /**
     * Cache of event ids that have already been seen/had reminders sent.
     *
     * @var array
     */
    var $_seen = array();

    /**
     * The list of calendars. We store this so we're not fetching it all the
     * time, but update the cache occasionally to find new calendars.
     *
     * @var array
     */
    var $_calendars = array();

    /**
     * The last timestamp that we ran.
     *
     * @var integer
     */
    var $_runtime;

    /**
     * The last time we fetched the full calendar list.
     *
     * @var integer
     */
    var $_listtime;

    function Horde_Scheduler_kronolith($params = array())
    {
        parent::Horde_Scheduler($params);
    }

    function run()
    {
        global $conf;

        $this->_runtime = time();

        // If we haven't fetched the list of calendars in over an
        // hour, re-list to pick up any new ones.
        if ($this->_runtime - $this->_listtime > 3600) {
            global $shares;

            $this->_listtime = $this->_runtime;
            $this->_calendars = $shares->listAllShares();
        }

        // If there are no calendars to monitor, just return.
        if (!count($this->_calendars)) {
            return;
        }

        if (!empty($conf['reminder']['server_name'])) {
            $conf['server']['name'] = $conf['reminder']['server_name'];
        }

        // Check for alarms and act on them.
        $kronolith = &Kronolith_Driver::factory();
        $alarms = Kronolith::listAlarms(new Horde_Date($this->_runtime), array_keys($this->_calendars));
        foreach ($alarms as $calId => $calarms) {
            $kronolith->open($calId);
            foreach ($calarms as $eventId) {
                $event = &$kronolith->getEvent($eventId);
                if (is_a($event, 'PEAR_Error')) {
                    continue;
                }

                if ($event->getRecurType() != KRONOLITH_RECUR_NONE) {
                    /* Set the event's start date to the next
                     * recurrence date. This should avoid problems
                     * when an alarm triggers on a different day from
                     * the actual event, and make $seenid unique for
                     * each occurrence of a recurring event. */
                    $event->start = $event->nextRecurrence($this->_runtime);

                    /* Check for exceptions; do nothing if one is found. */
                    if ($event->hasException($event->start->year, $event->start->month, $event->start->mday)) {
                        continue;
                    }
                }

                $seenid = $eventId . $event->start->timestamp() . $event->getAlarm();
                if (!isset($this->_seen[$seenid])) {
                    $this->_seen[$seenid] = true;
                    $result = $this->remind($calId, $event);
                    if (is_a($result, 'PEAR_Error')) {
                        Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                    }
                }
            }
        }
    }

    function remind($calId, &$event)
    {
        global $kronolith, $conf, $shares;

        if ($kronolith->getCalendar() != $calId) {
            $kronolith->open($calId);
        }

        require_once 'Horde/Group.php';
        require_once 'Horde/Identity.php';
        require_once 'Horde/MIME.php';
        require_once 'Horde/MIME/Headers.php';
        require_once 'Horde/MIME/Message.php';

        /* Desired logic: list users and groups that can view $calId, and send
         * email to any of them that we can find an email address for. This
         * will hopefully be improved at some point so that people don't get
         * multiple emails, and can set more preferences on how they want to
         * be notified. */
        $share = $shares->getShare($calId);
        if (is_a($share, 'PEAR_Error')) {
            return;
        }

        $recipients = array();
        $emails = array();

        $users = $share->listUsers(PERMS_READ);
        foreach ($users as $user) {
            if (empty($emails[$user])) {
                $identity = &Identity::singleton('none', $user);
                $email = $identity->getValue('from_addr');
                if (strstr($email, '@')) {
                    list($mailbox, $host) = explode('@', $email);
                    $emails[$user] = MIME::rfc822WriteAddress($mailbox, $host, $identity->getValue('fullname'));
                }
            }

            if (!empty($emails[$user])) {
                $prefs = &Prefs::singleton($conf['prefs']['driver'],
                                           'kronolith', $user);
                $prefs->retrieve();
                $shown_calendars = unserialize($prefs->getValue('display_cals'));
                $reminder = $prefs->getValue('event_reminder');
                if (($reminder == 'owner' && $user == $share->get('owner')) ||
                    ($reminder == 'show' && in_array($calId, $shown_calendars)) ||
                    $reminder == 'read') {
                    $lang = $prefs->getValue('language');
                    $twentyFour = $prefs->getValue('twentyFour');
                    $dateFormat = $prefs->getValue('date_format');
                    if (!isset($recipients[$lang][$twentyFour][$dateFormat])) {
                        $recipients[$lang][$twentyFour][$dateFormat] = array();
                    }
                    $recipients[$lang][$twentyFour][$dateFormat][] = $emails[$user];
                }
            }
        }

        $groups = $share->listGroups(PERMS_READ);
        $groupManager = &Group::singleton();
        foreach ($groups as $gid) {
            if (empty($emails[$gid])) {
                $group = $groupManager->getGroupById($gid);
                if ($email = $group->get('email')) {
                    $emails[$gid] = $group->get('email');
                }
            }

            if (!empty($emails[$gid])) {
                $prefs = &Prefs::singleton($conf['prefs']['driver'], 'horde', $gid);
                $prefs->retrieve();
                $lang = $prefs->getValue('language');
                $twentyFour = $prefs->getValue('twentyFour');
                $dateFormat = $prefs->getValue('date_format');
                if (!isset($recipients[$lang][$twentyFour][$dateFormat])) {
                    $recipients[$lang][$twentyFour][$dateFormat] = array();
                }
                $recipients[$lang][$twentyFour][$dateFormat][] = $emails[$gid];
            }
        }

        if (!$recipients) {
            Horde::logMessage(sprintf('No email addresses available to send reminder for %s to recipient(s): %s %s', $event->title, implode(', ', $users), implode(', ', $groups)), __FILE__, __LINE__, PEAR_LOG_INFO);
            return false;
        }

        $msg_headers = new MIME_Headers();
        $msg_headers->addMessageIdHeader();
        $msg_headers->addAgentHeader();
        $msg_headers->addHeader('Date', date('r'));
        $msg_headers->addHeader('To', 'CalendarReminders:;');
        $msg_headers->addHeader('From', $conf['reminder']['from_addr']);

        $mail_driver = $conf['mailer']['type'];
        $mail_params = $conf['mailer']['params'];
        if ($mail_driver == 'smtp' && $mail_params['auth'] &&
            empty($mail_params['username'])) {
            Horde::logMessage('Reminders don\'t work with user based SMTP authentication.', __FILE__, __LINE__, PEAR_LOG_ERR);
            return;
        }

        foreach ($recipients as $lang => $twentyFour) {
            NLS::setLang($lang);
            NLS::setTextdomain('kronolith', KRONOLITH_BASE . '/locale', NLS::getCharset());
            String::setDefaultCharset(NLS::getCharset());

            $msg_headers->removeHeader('Subject');
            $msg_headers->addHeader('Subject', sprintf(_("Reminder: %s"), $event->title));

            foreach ($twentyFour as $tf => $dateFormat) {
                foreach ($dateFormat as $df => $df_recipients) {
                    $message = "\n" . sprintf(_("You requested to be reminded about %s, which is on %s at %s."), $event->title, strftime($df, $event->start->timestamp()), date($tf ? 'H:i' : 'h:ia', $event->start->timestamp())) . "\n\n" . $event->getDescription();

                    $mime = new MIME_Message();
                    $body = new MIME_Part('text/plain', String::wrap($message, 76, "\n"), NLS::getCharset());

                    $mime->addPart($body);
                    $msg_headers->addMIMEHeaders($mime);

                    Horde::logMessage(sprintf('Sending reminder for %s to %s', $event->title, implode(', ', $df_recipients)), __FILE__, __LINE__, PEAR_LOG_DEBUG);
                    $sent = $mime->send(implode(', ', $df_recipients), $msg_headers, $mail_driver, $mail_params);
                    if (is_a($sent, 'PEAR_Error')) {
                        return $sent;
                    }
                }
            }
        }

        return true;
    }

}
