<?php

require_once 'Horde/Notification/Listener.php';

/**
 * The Notification_Listener_audio:: class provides functionality for
 * inserting embedded audio notifications from the stack into the page.
 *
 * $Horde: framework/Notification/Notification/Listener/audio.php,v 1.3.2.3 2007/01/02 13:54:34 jan Exp $
 *
 * Copyright 2005-2007 Cronosys, LLC <http://www.cronosys.com/>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jason M. Felice <jfelice@cronosys.com>
 * @since   Horde 3.0
 * @package Horde_Notification
 */
class Notification_Listener_audio extends Notification_Listener {

    /**
     * Constructor
     */
    function Notification_Listener_audio()
    {
        $this->_handles = array('audio' => '');
    }

    /**
     * Return a unique identifier for this listener.
     *
     * @return string  Unique id.
     */
    function getName()
    {
        return 'audio';
    }

    /**
     * Outputs the embedded audio code if there are any messages on the
     * 'audio' message stack.
     *
     * @param array &$messageStack     The stack of messages.
     * @param array $options  An array of options.
     */
    function notify(&$messageStack, $options = array())
    {
        if (count($messageStack)) {
            while ($message = array_shift($messageStack)) {
                $this->getMessage($message);
            }
        }
    }

    /**
     * Outputs one message.
     *
     * @param array $message  One message hash from the stack.
     */
    function getMessage($message)
    {
        $event = $this->getEvent($message);
        echo '<embed src="', htmlspecialchars($event->getMessage()),
             '" width="0" height="0" autostart="true" />';
    }

}
