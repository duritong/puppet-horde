<?php
/**
 * $Horde: ingo/vacation.php,v 1.28.8.8 2007/01/02 13:55:02 jan Exp $
 *
 * Copyright 2002-2007 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('INGO_BASE', dirname(__FILE__));
require_once INGO_BASE . '/lib/base.php';

/* Redirect if vacation is not available. */
if (!in_array(INGO_STORAGE_ACTION_VACATION, $_SESSION['ingo']['script_categories'])) {
    $notification->push(_("Vacation is not supported in the current filtering driver."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('filters.php', true));
    exit;
}

/* Get vacation object. */
$vacation = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_VACATION);

/* Perform requested actions. */
$actionID = Util::getFormData('actionID');
switch ($actionID) {
case 'rule_update':
    $addr = Util::getFormData('addresses');
    if (empty($addr)) {
        $notification->push(_("You must specify at least one email address for which the vacation messages should be activated."), 'horde.error');
    } else {
        $vacation->setVacationAddresses(Util::getFormData('addresses'));
        $vacation->setVacationDays(Util::getFormData('days'));
        $vacation->setVacationExcludes(Util::getFormData('excludes'));
        $vacation->setVacationIgnorelist((Util::getFormData('ignorelist') == '1'));
        $vacation->setVacationReason(Util::getFormData('reason'));
        $vacation->setVacationSubject(Util::getFormData('subject'));
        if (is_a($result = $ingo_storage->store($vacation), 'PEAR_Error')) {
            $notification->push($result);
        } else {
            $notification->push(_("Changes saved."), 'horde.success');
            if ($prefs->getValue('auto_update')) {
                Ingo::updateScript();
            }
        }
    }

    /* Update the timestamp for the rules. */
    $_SESSION['ingo']['change'] = time();

    break;
}

/* Make sure we have at least one address. */
if (!$vacation->getVacationAddresses()) {
    require_once 'Horde/Identity.php';
    $identity = &Identity::singleton('none');
    $addresses = implode("\n", $identity->getAll('from_addr'));
    /* Remove empty lines. */
    $addresses = preg_replace('/\n+/', "\n", $addresses);
    if (empty($addresses)) {
        $addresses = Auth::getAuth();
    }
    $vacation->setVacationAddresses($addresses);
}

/* Get the vacation rule. */
$filters = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_FILTERS);
$vac_rule = $filters->findRule(INGO_STORAGE_ACTION_VACATION);

$title = _("Vacation Edit");
require INGO_TEMPLATES . '/common-header.inc';
require INGO_TEMPLATES . '/menu.inc';
require INGO_TEMPLATES . '/vacation/vacation.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
