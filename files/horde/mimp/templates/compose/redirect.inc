<?php

$c = &$m->add(new Horde_Mobile_card('m', $title));
$c->softkey('#o', _("Menu"));

$l->setMobileObject($c);
$notification->notify(array('listeners' => 'status'));

$f = &$c->add(new Horde_Mobile_form('compose.php'));

$f->add(new Horde_Mobile_hidden('mailbox', $mailbox));
$f->add(new Horde_Mobile_hidden('index', $index));
$f->add(new Horde_Mobile_hidden('action', 'redirect_compose'));
$f->add(new Horde_Mobile_hidden('array_index', Util::getFormData('array_index')));

$f->add(new Horde_Mobile_input('to', $header['to'], _("To: ")));

$f->add(new Horde_Mobile_submit(_("Redirect"), 'actionID'));
$f->add(new Horde_Mobile_submit(_("Expand Names"), 'actionID'));

$m->add($menu);

$m->display();
