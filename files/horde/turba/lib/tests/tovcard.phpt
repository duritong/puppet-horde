--TEST--
Turba_Driver::toVcard() test.
--FILE--
<?php

require dirname(__FILE__) . '/../Object.php';
require dirname(__FILE__) . '/../Driver.php';

$attributes = array(
  'name' => 'Jan Schneider�',
  'salutation' => 'Mr.',
  'firstname' => 'Jan',
  'initials' => 'K.',
  'lastname' => 'Schneider�',
  'email' => 'jan@horde.org',
  'alias' => 'yunosh',
  'homeAddress' => 'Sch�nestr. 15
33604 Bielefeld',
  'workStreet' => 'H�bschestr. 19',
  'workCity' => 'K�ln',
  'workProvince' => 'Allg�u',
  'workPostalcode' => '33602',
  'workCountry' => 'D�nemark',
  'homePhone' => '+49 521 555123',
  'workPhone' => '+49 521 555456',
  'cellPhone' => '+49 177 555123',
  'fax' => '+49 521 555789',
  'birthday' => '1971-10-01',
  'title' => 'Developer (���)',
  'company' => 'Horde Project',
  'department' => '���',
  'notes' => 'A German guy (���)',
  'website' => 'http://janschneider.de'
);

$driver = new Turba_Driver(array());
$object = new Turba_Object($driver, $attributes);
$vcard = $driver->tovCard($object, '2.1');
echo $vcard->exportvCalendar();
$vcard = $driver->tovCard($object, '3.0');
echo $vcard->exportvCalendar();

?>
--EXPECT--
BEGIN:VCARD
VERSION:2.1
FN;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:=
Jan Schneider=F6
EMAIL:jan@horde.org
NICKNAME:yunosh
TEL;HOME:+49 521 555123
TEL;WORK:+49 521 555456
TEL;CELL:+49 177 555123
TEL;FAX:+49 521 555789
BDAY:1971-10-01
TITLE;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:=
Developer (=E4=F6=FC)
NOTE;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:=
A German guy (=E4=F6=FC)
URL:http://janschneider.de
N;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:=
Schneider=F6;Jan;K.;Mr.;
ORG;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:=
Horde Project;=E4=F6=FC
ADR;HOME;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:=
;;Sch=F6nestr. 15=0D=0A33604 Bielefeld;;;;
ADR;WORK;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:=
;;H=FCbschestr. 19;K=F6ln;Allg=E4u;;D=E4nemark
BODY:
END:VCARD
BEGIN:VCARD
VERSION:3.0
FN:Jan Schneiderö
EMAIL:jan@horde.org
NICKNAME:yunosh
TEL;TYPE=HOME:+49 521 555123
TEL;TYPE=WORK:+49 521 555456
TEL;TYPE=CELL:+49 177 555123
TEL;TYPE=FAX:+49 521 555789
BDAY:1971-10-01
TITLE:Developer (äöü)
NOTE:A German guy (äöü)
URL:http://janschneider.de
N:Schneiderö;Jan;K.;Mr.;
ORG:Horde Project;äöü
ADR;TYPE=HOME:;;Schönestr. 15\n33604 Bielefeld;;;;
ADR;TYPE=WORK:;;Hübschestr. 19;Köln;Allgäu;;Dänemark
BODY:
END:VCARD
