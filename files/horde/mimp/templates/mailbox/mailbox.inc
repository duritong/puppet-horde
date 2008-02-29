<?php

$c = &$m->add(new Horde_Mobile_card('m', $title . ' | ' . $msg_count));
$c->softkey('#o', _("Menu"));

$GLOBALS['l']->setMobileObject($c);
$GLOBALS['notification']->notify(array('listeners' => 'status'));

// Header row.
if (!empty($pageOb->end)) {
    $t = &$c->add(new Horde_Mobile_table());
    $t->set('border', 0);
    $t->set('padding', 1);
    $t->set('spacing', 1);

    $r = &$t->add(new Horde_Mobile_row());
    $r->add();
    $r->add(_("From"));
    $r->add(_("Subject"));

    $i = 1;
    foreach ($msgs as $msg) {
        $r = &$t->add(new Horde_Mobile_row());
        $r->add($msg['status']);
        $r->add($msg['from']);
        $l = &$r->add(new Horde_Mobile_link($msg['subject'], $msg['target']));
        $l->set('accesskey', $i++);
    }
}

$m->add($menu);

$m->display();
