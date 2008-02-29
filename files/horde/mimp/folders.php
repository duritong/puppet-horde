<?php
/**
 * $Horde: mimp/folders.php,v 1.39.2.1 2007/01/02 13:55:08 jan Exp $
 *
 * Copyright 2000-2007 Charles J. Hagenbuch <chuck@horde.org>
 * Copyright 2000-2007 Jon Parise <jon@horde.org>
 * Copyright 2000-2007 Anil Madhavapeddy <avsm@horde.org>
 * Copyright 2003-2007 Michael Slusarz <slusarz@horde.org>
 *
 * See the enclosed file COPYING for license information (GPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

$authentication = OP_HALFOPEN;
@define('MIMP_BASE', dirname(__FILE__));
require_once MIMP_BASE . '/lib/base.php';
require_once MIMP_BASE . '/lib/IMAP/Tree.php';

/* Redirect back to the mailbox if folder use is not allowed. */
if (!$conf['user']['allow_folders']) {
    header('Location: ' . Horde::applicationUrl('mailbox.php', true));
    exit;
}

$title = _("Folders");
$m->set('title', $title);

$c = &$m->add(new Horde_Mobile_card('m', $title));
$c->softkey('#o', _("Menu"));
$l->setMobileObject($c);

$notification->notify(array('listeners' => 'status'));

$name_url = Horde::applicationUrl('mailbox.php');
$null = null;
$fb = &$c->add(new Horde_Mobile_block($null));

/* Decide whether or not to show all the unsubscribed folders */
$subscribe = $prefs->getValue('subscribe');
$showAll = (!$subscribe || $_SESSION['mimp']['showunsub']);

/* Initialize the MIMP_Tree object. */
$mimptree = &MIMP_Tree::singleton();

/* Toggle subscribed view, if necessary. */
if ($subscribe && Util::getFormData('ts')) {
    $showAll = !$showAll;
    $_SESSION['mimp']['showunsub'] = $showAll;
    $mimptree->showUnsubscribed($showAll);
}

/* Start iterating through the list of mailboxes, displaying them. */
$mailbox = $mimptree->reset();
do {
    $msgs_info = $row = array();

    $fb->add(new Horde_Mobile_text(str_repeat('..', $mailbox['level'])));
    if ($mimptree->isContainer($mailbox)) {
        $name = &new Horde_Mobile_text($mailbox['label']);
    } else {
        $name = &new Horde_Mobile_link($mailbox['label'], Util::addParameter($name_url, 'mailbox', $mailbox['value']));
    }
    $fb->add($name);

    $t = &$fb->add(new Horde_Mobile_text("\n"));
    $t->set('linebreaks', true);
} while (($mailbox = $mimptree->next()));

$menu = &new Horde_Mobile_card('o', _("Menu"));
$mset = &$menu->add(new Horde_Mobile_linkset());
$mset->add(new Horde_Mobile_link(_("Refresh"), Horde::selfUrl()));
if ($subscribe) {
    $sub_text = ($showAll) ? _("Show Subscribed Folders") : _("Show All Folders");
    $mset->add(new Horde_Mobile_link($sub_text, Util::addParameter(Horde::selfUrl(), 'ts', 1)));
}
MIMP::addMIMPMenu($mset);
$m->add($menu);

$m->display();
