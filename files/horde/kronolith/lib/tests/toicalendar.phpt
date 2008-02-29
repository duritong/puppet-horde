--TEST--
Kronolith_Event::toiCalendar() test.
--FILE--
<?php

require 'Horde/CLI.php';
Horde_CLI::init();
define('AUTH_HANDLER', true);
require dirname(__FILE__) . '/../base.php';
require 'Horde/iCalendar.php';

$driver = new Kronolith_Driver();
$object = new Kronolith_Event($driver);
$object->start = new Horde_Date('2007-03-15 13:10:20');
$object->end = new Horde_Date('2007-03-15 14:20:00');
$object->setCreatorId('joe');
$object->setUID('20070315143732.4wlenqz3edq8@horde.org');
$object->setTitle('H�bscher Termin');
$object->setDescription("Sch�ne Bescherung\nNew line");
$object->setCategory('Sch�ngeistiges');
$object->setLocation('Allg�u');
$object->setAlarm(10);
$object->setRecurType(KRONOLITH_RECUR_DAILY);
$object->setRecurInterval(2);
$object->addException(2007, 3, 19);

$ical = new Horde_iCalendar('1.0');
$cal = $object->toiCalendar($ical);
$ical->addComponent($cal);
echo $ical->exportvCalendar();

$ical = new Horde_iCalendar('2.0');
$cal = $object->toiCalendar($ical);
$ical->addComponent($cal);
echo $ical->exportvCalendar();

$object->setStatus(KRONOLITH_STATUS_TENTATIVE);
$object->setRecurType(KRONOLITH_RECUR_DAY_OF_MONTH);
$object->setRecurInterval(1);
$object->exceptions = array();
$object->addException(2007, 4, 15);
$object->setAttendees(
    array('juergen@example.com' =>
          array('attendance' => KRONOLITH_PART_REQUIRED,
                'response' => KRONOLITH_RESPONSE_NONE),
          'jane@example.com' =>
          array('attendance' => KRONOLITH_PART_OPTIONAL,
                'response' => KRONOLITH_RESPONSE_ACCEPTED),
          'jack@example.com' =>
          array('attendance' => KRONOLITH_PART_NONE,
                'response' => KRONOLITH_RESPONSE_DECLINED),
          'jenny@example.com' =>
          array('attendance' => KRONOLITH_PART_NONE,
                'response' => KRONOLITH_RESPONSE_TENTATIVE)));

$ical = new Horde_iCalendar('1.0');
$cal = $object->toiCalendar($ical);
$ical->addComponent($cal);
echo $ical->exportvCalendar();

$ical = new Horde_iCalendar('2.0');
$cal = $object->toiCalendar($ical);
$ical->addComponent($cal);
echo $ical->exportvCalendar();

?>
--EXPECTF--
BEGIN:VCALENDAR
VERSION:1.0
PRODID:-//The Horde Project//Horde_iCalendar Library//EN
METHOD:PUBLISH
BEGIN:VEVENT
DTSTART:20070315T121020Z
DTEND:20070315T132000Z
DTSTAMP:%d%d%d%d%d%d%d%dT%d%d%d%d%d%dZ
UID:20070315143732.4wlenqz3edq8@horde.org
SUMMARY;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:=
H=FCbscher Termin
TRANSP:OPAQUE
ORGANIZER;CN=joe:mailto:joe
DESCRIPTION;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:=
Sch=F6ne Bescherung=0D=0ANew line
CATEGORIES;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:=
Sch=F6ngeistiges
LOCATION;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:=
Allg=E4u
STATUS:CONFIRMED
AALARM:20070315T120020Z
RRULE:D2 #0
EXDATE:20070319T000000
END:VEVENT
END:VCALENDAR
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//The Horde Project//Horde_iCalendar Library//EN
METHOD:PUBLISH
BEGIN:VEVENT
DTSTART:20070315T121020Z
DTEND:20070315T132000Z
DTSTAMP:%d%d%d%d%d%d%d%dT%d%d%d%d%d%dZ
UID:20070315143732.4wlenqz3edq8@horde.org
SUMMARY:Hübscher Termin
TRANSP:OPAQUE
ORGANIZER;CN=joe:mailto:joe
DESCRIPTION:Schöne Bescherung\nNew line
CATEGORIES:Schöngeistiges
LOCATION:Allgäu
STATUS:CONFIRMED
AALARM:20070315T120020Z
RRULE:FREQ=DAILY;INTERVAL=2
EXDATE;VALUE=DATE:20070319
END:VEVENT
END:VCALENDAR
BEGIN:VCALENDAR
VERSION:1.0
PRODID:-//The Horde Project//Horde_iCalendar Library//EN
METHOD:PUBLISH
BEGIN:VEVENT
DTSTART:20070315T121020Z
DTEND:20070315T132000Z
DTSTAMP:%d%d%d%d%d%d%d%dT%d%d%d%d%d%dZ
UID:20070315143732.4wlenqz3edq8@horde.org
SUMMARY;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:=
H=FCbscher Termin
TRANSP:OPAQUE
ORGANIZER;CN=joe:mailto:joe
DESCRIPTION;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:=
Sch=F6ne Bescherung=0D=0ANew line
CATEGORIES;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:=
Sch=F6ngeistiges
LOCATION;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:=
Allg=E4u
STATUS:TENTATIVE
ATTENDEE;EXPECT=REQUIRE;STATUS=NEEDS ACTION;RSVP=YES:juergen@example.com
ATTENDEE;EXPECT=REQUEST;STATUS=ACCEPTED:jane@example.com
ATTENDEE;EXPECT=FYI;STATUS=DECLINED:jack@example.com
ATTENDEE;EXPECT=FYI;STATUS=TENTATIVE:jenny@example.com
AALARM:20070315T120020Z
RRULE:MD1 15 #0
EXDATE:20070415T000000
END:VEVENT
END:VCALENDAR
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//The Horde Project//Horde_iCalendar Library//EN
METHOD:PUBLISH
BEGIN:VEVENT
DTSTART:20070315T121020Z
DTEND:20070315T132000Z
DTSTAMP:%d%d%d%d%d%d%d%dT%d%d%d%d%d%dZ
UID:20070315143732.4wlenqz3edq8@horde.org
SUMMARY:Hübscher Termin
TRANSP:OPAQUE
ORGANIZER;CN=joe:mailto:joe
DESCRIPTION:Schöne Bescherung\nNew line
CATEGORIES:Schöngeistiges
LOCATION:Allgäu
STATUS:TENTATIVE
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:
 juergen@example.com
ATTENDEE;ROLE=OPT-PARTICIPANT;PARTSTAT=ACCEPTED:mailto:jane@example.com
ATTENDEE;ROLE=NON-PARTICIPANT;PARTSTAT=DECLINED:mailto:jack@example.com
ATTENDEE;ROLE=NON-PARTICIPANT;PARTSTAT=TENTATIVE:mailto:jenny@example.com
AALARM:20070315T120020Z
RRULE:FREQ=MONTHLY;INTERVAL=1
EXDATE;VALUE=DATE:20070415
END:VEVENT
END:VCALENDAR
