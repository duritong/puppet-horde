<?php
/**
 * The IMP_MIME_Viewer_itip class displays vCalendar/iCalendar data
 * and provides an option to import the data into a calendar source,
 * if one is available.
 *
 * $Horde: imp/lib/MIME/Viewer/itip.php,v 1.37.2.30 2007/05/07 14:20:31 jan Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   IMP 4.0
 * @package Horde_MIME_Viewer
 */
class IMP_MIME_Viewer_itip extends MIME_Viewer {

    /**
     * Force viewing of a part inline, regardless of the Content-Disposition
     * of the MIME Part.
     *
     * @var boolean
     */
    var $_forceinline = true;

    /**
     * The messages to output to the user.
     *
     * @var array
     */
    var $_msgs = array();

    /**
     * The method as marked in either the iCal structure or message header.
     *
     * @var string
     */
    var $_method = 'PUBLISH';

    /**
     * The headers of the message.
     *
     * @var string
     */
    var $_headers;

    /**
     * Render out the currently set iCalendar contents.
     *
     * @param array $params  Any parameters the Viewer may need.
     *
     * @return string  The rendered contents.
     */
    function render($params = array())
    {
        global $registry;
        require_once 'Horde/iCalendar.php';

        // Extract the data.
        $data = $this->mime_part->getContents();
        if (empty($this->_headers) && is_a($params[0], 'IMP_Contents')) {
            $this->_headers = $params[0]->getHeaderOb();
        }

        // Parse the iCal file.
        $vCal = new Horde_iCalendar();
        if (!$vCal->parsevCalendar($data)) {
            return '<h1>' . _("The calendar data is invalid") . '</h1>' .
                '<pre>' . htmlspecialchars($data) . '</pre>';
        }

        // Check if we got vcard data with the wrong vcalendar mime
        // type.
        $c = $vCal->getComponentClasses();
        if (count($c) == 1 && !empty($c['horde_icalendar_vcard'])) {
            $vcard_renderer = &Mime_Viewer::factory($this->mime_part, 'text/x-vcard');
            return $vcard_renderer->render($params);
        }

        // Get the method type.
        $this->_method = $vCal->getAttribute('METHOD');
        if (is_a($this->_method, 'PEAR_Error')) {
            $this->_method = '';
        }

        // Get the iCalendar file components.
        $components = $vCal->getComponents();

        // Handle the action requests.
        $actions = Util::getFormData('action', array());
        foreach ($actions as $key => $action) {
            $this->_msgs[$key] = array();
            switch ($action) {
            case 'delete':
                // vEvent cancellation.
                if ($registry->hasMethod('calendar/delete')) {
                    $guid = $components[$key]->getAttribute('UID');
                    $event = $registry->call('calendar/delete', array('guid' => $guid));
                    if (is_a($event, 'PEAR_Error')) {
                        $this->_msgs[$key][] = array('error', sprintf(_("There was an error deleting the event: %s."), $event->getMessage()));
                    } else {
                        $this->_msgs[$key][] = array('success', _("Event successfully deleted."));
                    }
                } else {
                    $this->_msgs[$key][] = array('warning', _("This action is not supported."));
                }
                break;

            case 'update':
                // vEvent reply.
                if ($registry->hasMethod('calendar/updateAttendee')) {
                    $event = $registry->call('calendar/updateAttendee', array('response' => $components[$key]));
                    if (is_a($event, 'PEAR_Error')) {
                        $this->_msgs[$key][] = array('error', sprintf(_("There was an error updating the event: %s."), $event->getMessage()));
                    } else {
                        $this->_msgs[$key][] = array('success', _("Respondent Status Updated."));
                    }
                } else {
                    $this->_msgs[$key][] = array('warning', _("This action is not supported."));
                }
                break;

            case 'import':
            case 'accept-import':
                // vFreebusy reply.
                // vFreebusy publish.
                // vEvent request.
                // vEvent publish.
                // vTodo publish.
                // vJournal publish.
                switch ($components[$key]->getType()) {
                case 'vEvent':
                    // Import into Kronolith.
                    if ($registry->hasMethod('calendar/import')) {
                        $guid = $registry->call('calendar/import', array('content' => $components[$key], 'contentType' => 'text/calendar'));
                        if (is_a($guid, 'PEAR_Error')) {
                            $this->_msgs[$key][] = array('error', sprintf(_("There was an error importing the event: %s."), $guid->getMessage()));
                        } else {
                            $url = Horde::url($registry->link('calendar/show', array('uid' => $guid)));
                            $this->_msgs[$key][] = array('success', _("The event was added to your calendar.") .
                                                         '&nbsp;' . Horde::link($url, _("View event"), null, '_blank') . Horde::img('mime/icalendar.png', _("View event"), null, $registry->getImageDir('horde')) . '</a>');
                        }
                    } else {
                        $this->_msgs[$key][] = array('warning', _("This action is not supported."));
                    }
                    break;

                case 'vFreebusy':
                    // Import into Kronolith.
                    if ($registry->hasMethod('calendar/import_vfreebusy')) {
                        $res = $registry->call('calendar/import_vfreebusy', array($components[$key]));
                        if (is_a($res, 'PEAR_Error')) {
                            $this->_msgs[$key][] = array('error', sprintf(_("There was an error importing user's free/busy information: %s."), $res->getMessage()));
                        } else {
                            $this->_msgs[$key][] = array('success', _("The user's free/busy information was sucessfully stored."));
                        }
                    } else {
                        $this->_msgs[$key][] = array('warning', _("This action is not supported."));
                    }
                    break;

                case 'vTodo':
                    // Import into Nag.
                    if ($registry->hasMethod('tasks/import')) {
                        $guid = $registry->call('tasks/import', array($components[$key], 'text/x-vtodo'));
                        if (is_a($guid, 'PEAR_Error')) {
                            $this->_msgs[$key][] = array('error', sprintf(_("There was an error importing the task: %s."), $guid->getMessage()));
                        } else {
                            $url = Horde::url($registry->link('tasks/show', array('uid' => $guid)));
                            $this->_msgs[$key][] = array('success', _("The task has been added to your tasklist.") .
                                                         '&nbsp;' . Horde::link($url, _("View task"), null, '_blank') . Horde::img('mime/icalendar.png', _("View task"), null, $registry->getImageDir('horde')) . '</a>');
                        }
                    } else {
                        $this->_msgs[$key][] = array('warning', _("This action is not supported."));
                    }
                    break;

                case 'vJournal':
                default:
                    $this->_msgs[$key][] = array('warning', _("This action is not yet implemented."));
                }

                if ($action != 'accept-import') {
                    break;
                }

            case 'accept':
            case 'accept-import':
            case 'deny':
            case 'tentative':
                // vEvent request.
                if (isset($components[$key]) &&
                    $components[$key]->getType() == 'vEvent') {
                    $vEvent = $components[$key];

                    // Get the organizer details.
                    $organizer = $vEvent->getAttribute('ORGANIZER');
                    if (is_a($organizer, 'PEAR_Error')) {
                        break;
                    }
                    $organizer = parse_url($organizer);
                    $organizerEmail = $organizer['path'];
                    $organizer = $vEvent->getAttribute('ORGANIZER', true);
                    $organizerName = isset($organizer['cn']) ? $organizer['cn'] : '';

                    require_once 'Horde/Identity.php';
                    require_once 'Horde/MIME.php';
                    require_once 'Horde/MIME/Headers.php';
                    require_once 'Horde/MIME/Part.php';

                    // Build the reply.
                    $vCal = new Horde_iCalendar();
                    $vCal->setAttribute('PRODID', '-//The Horde Project//' . HORDE_AGENT_HEADER . '//EN');
                    $vCal->setAttribute('METHOD', 'REPLY');

                    $vEvent_reply = &Horde_iCalendar::newComponent('vevent', $vCal);
                    $vEvent_reply->setAttribute('UID', $vEvent->getAttribute('UID'));
                    if (!is_a($vEvent->getAttribute('SUMMARY'), 'PEAR_error')) {
                        $vEvent_reply->setAttribute('SUMMARY', $vEvent->getAttribute('SUMMARY'));
                    }
                    if (!is_a($vEvent->getAttribute('DESCRIPTION'), 'PEAR_error')) {
                        $vEvent_reply->setAttribute('DESCRIPTION', $vEvent->getAttribute('DESCRIPTION'));
                    }
                    $dtstart = $vEvent->getAttribute('DTSTART', true);
                    $vEvent_reply->setAttribute('DTSTART', $vEvent->getAttribute('DTSTART'), array_pop($dtstart));
                    if (!is_a($vEvent->getAttribute('DTEND'), 'PEAR_error')) {
                        $dtend = $vEvent->getAttribute('DTEND', true);
                        $vEvent_reply->setAttribute('DTEND', $vEvent->getAttribute('DTEND'), array_pop($dtend));
                    } else {
                        $duration = $vEvent->getAttribute('DURATION', true);
                        $vEvent_reply->setAttribute('DURATION', $vEvent->getAttribute('DURATION'), array_pop($duration));
                    }
                    if (!is_a($vEvent->getAttribute('SEQUENCE'), 'PEAR_error')) {
                        $vEvent_reply->setAttribute('SEQUENCE', $vEvent->getAttribute('SEQUENCE'));
                    }
                    $vEvent_reply->setAttribute('ORGANIZER', $vEvent->getAttribute('ORGANIZER'), array_pop($organizer));

                    // Find out who we are and update status.
                    $identity = &Identity::singleton(array('imp', 'imp'));
                    $attendees = $vEvent->getAttribute('ATTENDEE');
                    if (!is_array($attendees)) {
                        $attendees = array($attendees);
                    }
                    foreach ($attendees as $attendee) {
                        $attendee = preg_replace('/mailto:/i', '', $attendee);
                        if (!is_null($id = $identity->getMatchingIdentity($attendee))) {
                            $identity->setDefault($id);
                            break;
                        }
                    }
                    $email = $identity->getFromAddress();
                    $cn = $identity->getValue('fullname');
                    $params = array('CN' => $cn);

                    switch ($action) {
                    case 'accept':
                    case 'accept-import':
                        $message = sprintf(_("%s has accepted."), $cn);
                        $subject = _("Accepted: ") . $vEvent->getAttribute('SUMMARY');
                        $params['PARTSTAT'] = 'ACCEPTED';
                        break;

                    case 'deny':
                        $message = sprintf(_("%s has declined."), $cn);
                        $subject = _("Declined: ") . $vEvent->getAttribute('SUMMARY');
                        $params['PARTSTAT'] = 'DECLINED';
                        break;

                    case 'tentative':
                        $message = sprintf(_("%s has tentatively accepted."), $cn);
                        $subject = _("Tentative: ") . $vEvent->getAttribute('SUMMARY');
                        $params['PARTSTAT'] = 'TENTATIVE';
                        break;
                    }

                    $vEvent_reply->setAttribute('ATTENDEE', 'mailto:' . $email, $params);
                    $vCal->addComponent($vEvent_reply);

                    $mime = new MIME_Part('multipart/alternative');
                    $body = new MIME_Part('text/plain',
                                          String::wrap($message, 76, "\n"),
                                          NLS::getCharset());

                    $ics = new MIME_Part('text/calendar', $vCal->exportvCalendar());
                    $ics->setName('event-reply.ics');
                    $ics->setContentTypeParameter('METHOD', 'REPLY');
                    $ics->setCharset(NLS::getCharset());

                    $mime->addPart($body);
                    $mime->addPart($ics);
                    $mime = &MIME_Message::convertMimePart($mime);

                    // Build the reply headers.
                    $msg_headers = new MIME_Headers();
                    $msg_headers->addReceivedHeader();
                    $msg_headers->addMessageIdHeader();
                    $msg_headers->addHeader('Date', date('r'));
                    $msg_headers->addHeader('From', $email);
                    $msg_headers->addHeader('To', $organizerEmail);

                    $identity->setDefault(Util::getFormData('identity'));
                    $replyto = $identity->getValue('replyto_addr');
                    if (!empty($replyto) && ($replyto != $email)) {
                        $msg_headers->addHeader('Reply-to', $replyto);
                    }
                    $msg_headers->addHeader('Subject', MIME::encode($subject, NLS::getCharset()));
                    $msg_headers->addMIMEHeaders($mime);

                    // Send the reply.
                    $status = $mime->send($organizerEmail, $msg_headers);
                    if (is_a($status, 'PEAR_Error')) {
                        $this->_msgs[$key][] = array('error', sprintf(_("Error sending reply: %s."), $status->getMessage()));
                    } else {
                        $this->_msgs[$key][] = array('success', _("Reply Sent."));
                    }
                } else {
                    $this->_msgs[$key][] = array('warning', _("This action is not supported."));
                }
                break;

            case 'send':
                // vEvent refresh.
                if (isset($components[$key]) &&
                    $components[$key]->getType() == 'vEvent') {
                    $vEvent = $components[$key];
                }

                // vTodo refresh.
            case 'reply':
            case 'reply2m':
                // vfreebusy request.
                if (isset($components[$key]) &&
                    $components[$key]->getType() == 'vFreebusy') {
                    $vFb = $components[$key];

                    // Get the organizer details.
                    $organizer = $vFb->getAttribute('ORGANIZER');
                    if (is_a($organizer, 'PEAR_Error')) {
                        break;
                    }
                    $organizer = parse_url($organizer);
                    $organizerEmail = $organizer['path'];
                    $organizer = $vFb->getAttribute('ORGANIZER', true);
                    $organizerName = isset($organizer['cn']) ? $organizer['cn'] : '';

                    if ($action == 'reply2m') {
                        $startStamp = time();
                        $endStamp = $startStamp + (60 * 24 * 3600);
                    } else {
                        $startStamp = $vFb->getAttribute('DTSTART');
                        if (is_a($startStamp, 'PEAR_Error')) {
                            $startStamp = time();
                        }
                        $endStamp = $vFb->getAttribute('DTEND');
                        if (is_a($endStamp, 'PEAR_Error')) {
                            $duration = $vFb->getAttribute('DURATION');
                            if (is_a($duration, 'PEAR_Error')) {
                                $endStamp = $startStamp + (60 * 24 * 3600);
                            } else {
                                $endStamp = $startStamp + $duration;
                            }
                        }
                    }
                    $vfb_reply = $registry->call('calendar/getFreeBusy',
                                                 array('startStamp' => $startStamp,
                                                       'endStamp' => $endStamp));
                    require_once 'Horde/Identity.php';
                    require_once 'Horde/MIME.php';
                    require_once 'Horde/MIME/Headers.php';
                    require_once 'Horde/MIME/Part.php';

                    // Find out who we are and update status.
                    $identity = &Identity::singleton();
                    $email = $identity->getFromAddress();
                    $cn = $identity->getValue('fullname');

                    // Build the reply.
                    $vCal = new Horde_iCalendar();
                    $vCal->setAttribute('PRODID', '-//The Horde Project//' . HORDE_AGENT_HEADER . '//EN');
                    $vCal->setAttribute('METHOD', 'REPLY');
                    $vCal->addComponent($vfb_reply);

                    $mime = new MIME_Message();
                    $message = _("Attached is a reply to a calendar request you sent.");
                    $body = new MIME_Part('text/plain',
                                          String::wrap($message, 76, "\n"),
                                          NLS::getCharset());

                    $ics = new MIME_Part('text/calendar', $vCal->exportvCalendar());
                    $ics->setName('icalendar.ics');
                    $ics->setContentTypeParameter('METHOD', 'REPLY');
                    $ics->setCharset(NLS::getCharset());

                    $mime->addPart($body);
                    $mime->addPart($ics);

                    // Build the reply headers.
                    $msg_headers = new MIME_Headers();
                    $msg_headers->addReceivedHeader();
                    $msg_headers->addMessageIdHeader();
                    $msg_headers->addHeader('Date', date('r'));
                    $msg_headers->addHeader('From', $email);
                    $msg_headers->addHeader('To', $organizerEmail);

                    $identity->setDefault(Util::getFormData('identity'));
                    $replyto = $identity->getValue('replyto_addr');
                    if (!empty($replyto) && ($replyto != $email)) {
                        $msg_headers->addHeader('Reply-to', $replyto);
                    }
                    $msg_headers->addHeader('Subject', MIME::encode(_("Free/Busy Request Response"), NLS::getCharset()));
                    $msg_headers->addMIMEHeaders($mime);

                    // Send the reply.
                    $status = $mime->send($organizerEmail, $msg_headers);
                    if (is_a($status, 'PEAR_Error')) {
                        $this->_msgs[$key][] = array('error', sprintf(_("Error sending reply: %s."), $status->getMessage()));
                    } else {
                        $this->_msgs[$key][] = array('success', _("Reply Sent."));
                    }
                } else {
                    $this->_msgs[$key][] = array('warning', _("Invalid Action selected for this component."));
                }
                break;

            case 'nosup':
                // vFreebusy request.
            default:
                $this->_msgs[$key][] = array('warning', _("This action is not yet implemented."));
                break;
            }
        }

        // Create the HTML to display the iCal file.
        $html = '';
        if (MIME_Contents::viewAsAttachment()) {
            $html .= Util::bufferOutput('require', $registry->get('templates', 'horde') . '/common-header.inc');
        }
        $html .= '<form method="post" name="iCal" action="' . Horde::selfUrl(true) . '">';

        foreach ($components as $key => $component) {
            switch ($component->getType()) {
            case 'vEvent':
                $html .= $this->_vEvent($component, $key);
                break;

            case 'vTimeZone':
                // Ignore them.
                break;

            case 'vFreebusy':
                $html .= $this->_vFreebusy($component, $key);
                break;

            // @todo: handle stray vcards here as well.
            default:
                $html .= sprintf(_("Unhandled component of type: %s"), $component->getType());
            }
        }

        // Need to work out if we are inline and actually need this.
        $html .= '</form>';
        if (MIME_Contents::viewAsAttachment()) {
            $html .= Util::bufferOutput('require', $registry->get('templates', 'horde') . '/common-footer.inc');
        }

        return $html;
    }

    /**
     * Return text/html as the content-type.
     *
     * @return string "text/html" constant
     */
    function getType()
    {
        return 'text/html; charset=' . NLS::getCharset();
    }

    /**
     * Return the html for a vFreebusy.
     */
    function _vFreebusy($vfb, $id)
    {
        global $registry, $prefs;

        $html = '';
        $desc = '';
        $sender = $vfb->getName();
        switch ($this->_method) {
        case 'PUBLISH':
            $desc = _("%s has sent you free/busy information.");
            break;

        case 'REQUEST':
            $sender = $this->_headers->getValue('From');
            $desc = _("%s requests your free/busy information.");
            break;

        case 'REPLY':
            $desc = _("%s has replied to a free/busy request.");
            break;
        }

        $html .= '<h1 class="header">' . sprintf($desc, $sender) . '</h1>';

        if ($this->_msgs) {
            foreach ($this->_msgs[$id] as $msg) {
                $html .= '<p class="notice">' . Horde::img('alerts/' . $msg[0] . '.png', '', null, $registry->getImageDir('horde')) . $msg[1] . '</p>';
            }
        }

        $start = $vfb->getAttribute('DTSTART');
        if (!is_a($start, 'PEAR_Error')) {
            if (is_array($start)) {
                $html .= '<p><strong>' . _("Start") . ':</strong> ' . strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $start['month'], $start['mday'], $start['year'])) . '</p>';
            } else {
                $html .= '<p><strong>' . _("Start") . ':</strong> ' . strftime($prefs->getValue('date_format') . ' ' . $prefs->getValue('time_format'), $start) . '</p>';
            }
        }

        $end = $vfb->getAttribute('DTEND');
        if (!is_a($end, 'PEAR_Error')) {
            if (is_array($end)) {
                $html .= '<p><strong>' . _("End") . ':</strong> ' . strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $end['month'], $end['mday'], $end['year'])) . '</p>';
            } else {
                $html .= '<p><strong>' . _("End") . ':</strong> ' . strftime($prefs->getValue('date_format') . ' ' . $prefs->getValue('time_format'), $end) . '</p>';
            }
        }

        $html .= '<h2 class="smallheader">' . _("Actions") . '</h2>' .
            '<select name="action[' . $id . ']">';

        switch ($this->_method) {
        case 'PUBLISH':
            if ($registry->hasMethod('calendar/import_vfreebusy')) {
                $html .= '<option value="import">' .   _("Remember the free/busy information.") . '</option>';
            } else {
                $html .= '<option value="nosup">' . _("Reply with Not Supported Message") . '</option>';
            }
            break;

        case 'REQUEST':
            if ($registry->hasMethod('calendar/getFreeBusy')) {
                $html .= '<option value="reply">' .   _("Reply with requested free/busy information.") . '</option>' .
                    '<option value="reply2m">' . _("Reply with free/busy for next 2 months.") . '</option>';
            } else {
                $html .= '<option value="nosup">' . _("Reply with Not Supported Message") . '</option>';
            }

            $html .= '<option value="deny">' . _("Deny request for free/busy information") . '</option>';
            break;

        case 'REPLY':
            if ($registry->hasMethod('calendar/import_vfreebusy')) {
                $html .= '<option value="import">' .   _("Remember the free/busy information.") . '</option>';
            } else {
                $html .= '<option value="nosup">' . _("Reply with Not Supported Message") . '</option>';
            }
            break;
        }

        return $html . '</select> <input type="submit" class="button" value="' . _("Go") . '/>';
    }

    /**
     * Returns the html for a vEvent.
     *
     * @todo IMP 5: move organizerName() from Horde_iCalendar_vevent to
     *       Horde_iCalendar
     */
    function _vEvent($vevent, $id)
    {
        global $registry, $prefs;

        $html = '';
        $desc = '';
        $sender = $vevent->organizerName();
        $options = array();

        $attendees = $vevent->getAttribute('ATTENDEE');
        if (!is_a($attendees, 'PEAR_Error') &&
            !empty($attendees) &&
            !is_array($attendees)) {
            $attendees = array($attendees);
        }
        $attendee_params = $vevent->getAttribute('ATTENDEE', true);

        switch ($this->_method) {
        case 'PUBLISH':
            $desc = _("%s wishes to make you aware of \"%s\".");
            if ($registry->hasMethod('calendar/import')) {
                $options[] = '<option value="import">' .   _("Add this to my calendar") . '</option>';
            }
            break;

        case 'REQUEST':
            // Check that you are one of the attendees here.
            $is_attendee = false;
            $rsvp = false;
            if (!is_a($attendees, 'PEAR_Error') && !empty($attendees)) {
                require_once 'Horde/Identity.php';
                $identity = &Identity::singleton(array('imp', 'imp'));
                for ($i = 0, $c = count($attendees); $i < $c; ++$i) {
                    $attendee = parse_url($attendees[$i]);
                    if (!empty($attendee['path']) &&
                        $identity->hasAddress($attendee['path'])) {
                        $is_attendee = true;
                        if (!empty($attendee_params[$i]['RSVP']) &&
                            String::upper($attendee_params[$i]['RSVP']) == 'TRUE') {
                            $rsvp = true;
                        }
                        break;
                    }
                }
            }
                    
            $desc = $is_attendee
                ? _("%s requests your presence at \"%s\".")
                : _("%s wishes to make you aware of \"%s\".");
            if ($registry->hasMethod('calendar/import')) {
                if ($rsvp) {
                    $options[] = '<option value="accept-import">' .   _("Accept and add to my calendar") . '</option>';
                }
                $options[] = '<option value="import">' .   _("Add to my calendar") . '</option>';
            }
            if ($rsvp) {
                $options[] = '<option value="accept">' . _("Accept request") . '</option>';
                $options[] = '<option value="tentative">' . _("Tentatively Accept request") . '</option>';
                $options[] = '<option value="deny">' . _("Deny request") . '</option>';
            }
            // $options[] = '<option value="delegate">' . _("Delegate position") . '</option>';
            break;

        case 'ADD':
            $desc = _("%s wishes to ammend \"%s\".");
            if ($registry->hasMethod('calendar/import')) {
                $options[] = '<option value="import">' .   _("Update this event on my calendar") . '</option>';
            }
            break;

        case 'REFRESH':
            $desc = _("%s wishes to receive the latest information about \"%s\".");
            $options[] = '<option value="send">' . _("Send Latest Information") . '</option>';
            if (!$found && $registry->hasMethod('calendar/eventFromGUID')) {
                $existing_vevent = $registry->call('calendar/eventFromGUID', array('guid' => $vevent->getAttribute('UID')));
                if (!is_a($existing_vevent, 'Pear_error')) {
                    $existing_vevent->updateFromvEvent($vevent);
                    $vevent = $existing_vevent;
                    $found = true;
                }
            }
            break;

        case 'REPLY':
            $desc = _("%s has replied to the invitation to \"%s\".");
            $sender = $this->_headers->getValue('From');
            if ($registry->hasMethod('calendar/updateAttendee')) {
                $options[] = '<option value="update">' . _("Update respondent status") . '</option>';
            }
            break;

        case 'CANCEL':
            $desc = _("%s has cancelled \"%s\".");
            if ($registry->hasMethod('calendar/delete')) {
                $options[] = '<option value="delete">' . _("Delete from my calendar") . '</option>';
            }
            break;
        }

        $summary = $vevent->getAttribute('SUMMARY');
        if (is_a($summary, 'PEAR_Error')) {
            $desc = sprintf($desc, htmlspecialchars($sender), _("Unknown Meeting"));
        } else {
            $desc = sprintf($desc, htmlspecialchars($sender), htmlspecialchars($summary));
        }

        $html .= '<h2 class="header">' . $desc . '</h2>';

        if ($this->_msgs) {
            foreach ($this->_msgs[$id] as $msg) {
                $html .= '<p class="notice">' . Horde::img('alerts/' . $msg[0] . '.png', '', null, $registry->getImageDir('horde')) . $msg[1] . '</p>';
            }
        }

        $start = $vevent->getAttribute('DTSTART');
        if (!is_a($start, 'PEAR_Error')) {
            if (is_array($start)) {
                $html .= '<p><strong>' . _("Start") . ':</strong> ' . strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $start['month'], $start['mday'], $start['year'])) . '</p>';
            } else {
                $html .= '<p><strong>' . _("Start") . ':</strong> ' . strftime($prefs->getValue('date_format') . ' ' . $prefs->getValue('time_format'), $start) . '</p>';
            }
        }

        $end = $vevent->getAttribute('DTEND');
        if (!is_a($end, 'PEAR_Error')) {
            if (is_array($end)) {
                $html .= '<p><strong>' . _("End") . ':</strong> ' . strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $end['month'], $end['mday'], $end['year'])) . '</p>';
            } else {
                $html .= '<p><strong>' . _("End") . ':</strong> ' . strftime($prefs->getValue('date_format') . ' ' . $prefs->getValue('time_format'), $end) . '</p>';
            }
        }

        $sum = $vevent->getAttribute('SUMMARY');
        if (!is_a($sum, 'PEAR_Error')) {
            $html .= '<p><strong>' . _("Summary") . ':</strong> ' . htmlspecialchars($sum) . '</p>';
        } else {
            $html .= '<p><strong>' . _("Summary") . ':</strong> <em>' . _("None") . '</em></p>';
        }

        $desc = $vevent->getAttribute('DESCRIPTION');
        if (!is_a($desc, 'PEAR_Error')) {
            $html .= '<p><strong>' . _("Description") . ':</strong> ' . nl2br(htmlspecialchars($desc)) . '</p>';
        }

        $loc = $vevent->getAttribute('LOCATION');
        if (!is_a($loc, 'PEAR_Error')) {
            $html .= '<p><strong>' . _("Location") . ':</strong>' . htmlspecialchars($loc) . '</p>';
        }

        if (!is_a($attendees, 'PEAR_Error') && !empty($attendees)) {
            $html .= '<h2 class="smallheader">' . _("Attendees") . '</h2>';

            $html .= '<table><thead class="leftAlign"><tr><th>' . _("Name") . '</th><th>' . _("Role") . '</th><th>' . _("Status") . '</th></tr></thead><tbody>';
            foreach ($attendees as $key => $attendee) {
                $attendee = parse_url($attendee);
                $attendee = empty($attendee['path']) ? _("Unknown") : $attendee['path'];

                if (isset($attendee_params[$key]['CN'])) {
                    $attendee = $attendee_params[$key]['CN'];
                }

                $role = _("Required Participant");
                if (isset($attendee_params[$key]['ROLE'])) {
                    switch ($attendee_params[$key]['ROLE']) {
                    case 'CHAIR':
                        $role = _("Chair Person");
                        break;

                    case 'OPT-PARTICIPANT':
                        $role = _("Optional Participant");
                        break;

                    case 'NON-PARTICIPANT':
                        $role = _("Non Participant");
                        break;

                    case 'REQ-PARTICIPANT':
                    default:
                        // Already set above.
                        break;
                    }
                }

                $status = _("Awaiting Response");
                if (isset($attendee_params[$key]['PARTSTAT'])) {
                    $status = $this->_partstatToString($attendee_params[$key]['PARTSTAT'], $status);
                }

                $html .= '<tr><td>' . htmlspecialchars($attendee) . '</td><td>' . htmlspecialchars($role) . '</td><td>' . htmlspecialchars($status) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        if ($options) {
            $html .= '<h2 class="smallheader">' . _("Actions") . '</h2>' .
                '<select name="action[' . $id . ']">' .
                implode("\n", $options) .
                '</select> <input type="submit" class="button" value="' . _("Go") . '" />';
        }

        return $html;
    }

    /**
     * Translate the Participation status to string.
     *
     * @param string $value    The value of PARTSTAT.
     * @param string $default  The value to return as default.
     *
     * @return string   The translated string.
     */
    function _partstatToString($value, $default = null)
    {
        switch ($value) {
        case 'ACCEPTED':
            return _("Accepted");
            break;

        case 'DECLINED':
            return _("Declined");
            break;

        case 'TENTATIVE':
            return _("Tentatively Accepted");
            break;

        case 'DELEGATED':
            return _("Delegated");
            break;

        case 'COMPLETED':
            return _("Completed");
            break;

        case 'IN-PROCESS':
            return _("In Process");
            break;

        case 'NEEDS-ACTION':
        default:
            return is_null($default) ? _("Needs Action") : $default;
        }
    }

}
