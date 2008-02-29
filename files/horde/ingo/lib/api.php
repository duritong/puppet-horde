<?php
/**
 * Ingo external API interface.
 *
 * This file defines Ingo's external API interface. Other applications
 * can interact with Ingo through this API.
 *
 * $Horde: ingo/lib/api.php,v 1.16.12.4 2006/01/31 20:00:24 jan Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

$_services['perms'] = array(
    'args' => array(),
    'type' => '{urn:horde}stringArray');

$_services['blacklistFrom'] = array(
    'args' => array('addresses' => '{urn:horde}stringArray'),
    'type' => 'boolean'
);

$_services['showBlacklist'] = array(
    'link' => '%application%/blacklist.php'
);

$_services['whitelistFrom'] = array(
    'args' => array('addresses' => '{urn:horde}stringArray'),
    'type' => 'boolean'
);

$_services['showWhitelist'] = array(
    'link' => '%application%/whitelist.php'
);

$_services['canApplyFilters'] = array(
    'args' => array(),
    'type' => 'boolean'
);

$_services['applyFilters'] = array(
    'args' => array('params' => '{urn:horde}stringArray'),
    'type' => 'boolean'
);

$_services['showFilters'] = array(
    'link' => '%application%/filters.php'
);

function _ingo_perms()
{
    $perms = array();
    $perms['tree']['ingo']['allow_rules'] = false;
    $perms['title']['ingo:allow_rules'] = _("Allow Rules");
    $perms['type']['ingo:allow_rules'] = 'boolean';
    $perms['tree']['ingo']['max_rules'] = false;
    $perms['title']['ingo:max_rules'] = _("Maximum Number of Rules");
    $perms['type']['ingo:max_rules'] = 'int';

    return $perms;
}

function _ingo_blacklistFrom($addresses)
{
    require_once dirname(__FILE__) . '/../lib/base.php';
    global $ingo_storage;

    /* Check for '@' entries in $addresses - this would call all mail to
     * be blacklisted which is most likely not what is desired. */
    $addresses = array_unique($addresses);
    $key = array_search('@', $addresses);
    if ($key !== false) {
        unset($addresses[$key]);
    }

    if (!empty($addresses)) {
        $blacklist = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_BLACKLIST);
        $ret = $blacklist->setBlacklist(array_merge($blacklist->getBlacklist(), $addresses));
        if (is_a($ret, 'PEAR_Error')) {
            $GLOBALS['notification']->push($ret, $ret->getCode());
        } else {
            $ingo_storage->store($blacklist);
            Ingo::updateScript();
            foreach ($addresses as $from) {
                $GLOBALS['notification']->push(sprintf(_("The address \"%s\" has been added to your blacklist."), $from));
            }
        }
    }
}

function _ingo_whitelistFrom($addresses)
{
    require_once dirname(__FILE__) . '/../lib/base.php';
    global $ingo_storage;

    $whitelist = &$ingo_storage->retrieve(INGO_STORAGE_ACTION_WHITELIST);
    $ret = $whitelist->setWhitelist(array_merge($whitelist->getWhitelist(), $addresses));
    if (is_a($ret, 'PEAR_Error')) {
        $GLOBALS['notification']->push($ret, $ret->getCode());
    } else {
        $ingo_storage->store($whitelist);
        Ingo::updateScript();
        foreach ($addresses as $from) {
            $GLOBALS['notification']->push(sprintf(_("The address \"%s\" has been added to your whitelist."), $from));
        }
    }
}

function _ingo_canApplyFilters()
{
    require_once dirname(__FILE__) . '/../lib/base.php';

    $ingo_script = &Ingo::loadIngoScript();
    if ($ingo_script) {
        return $ingo_script->performAvailable();
    } else {
        return false;
    }
}

function _ingo_applyFilters($params = array())
{
    require_once dirname(__FILE__) . '/../lib/base.php';

    $ingo_script = &Ingo::loadIngoScript();
    if ($ingo_script) {
        return $ingo_script->perform($params);
    }
}
