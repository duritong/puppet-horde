<?php

$m->set('title', _("Log in"));

$c = &$m->add(new Horde_Mobile_card());

$l->setMobileObject($c);
$notification->notify(array('listeners' => 'status'));

$c->add(new Horde_Mobile_text(sprintf(_("Welcome to %s"), ($mimp_auth) ? $registry->get('name', 'horde') : $registry->get('name'))));

$f = &$c->add(new Horde_Mobile_form(Horde::applicationUrl('redirect.php', false, -1)));
$f->add(new Horde_Mobile_input('imapuser', Util::getFormData('imapuser'), _("Username")));

$p = &$f->add(new Horde_Mobile_input('pass', '', _("Password")));
$p->set('type', 'password');

$f->add($server_select);
if ($conf['server']['change_server']) {
    $f->add(new Horde_Mobile_input('server', Util::getFormData('server'), _("Server")));
    if ($conf['server']['change_port']) {
        $f->add(new Horde_Mobile_input('port', Util::getFormData('port'), _("Port")));
    }
    if ($conf['server']['change_protocol']) {
        $protocol_select = &new Horde_Mobile_select('protocol');
        require_once MIMP_BASE . '/lib/IMAP.php';
        foreach (MIMP_IMAP::protocolList() as $val) {
            $protocol_select->add($val['name'], $val['string']);
        }
        $f->add($protocol_select);
    }
}
$f->add(new Horde_Mobile_submit(_("Log in")));
if (!empty($actionID)) {
    $f->add(new Horde_Mobile_hidden('actionID', $actionID));
}
if (!empty($url_param)) {
    $f->add(new Horde_Mobile_hidden('url', $url_param));
}
$m->add($f);
