<?php
/**
 * Ingo_Storage_prefs:: implements the Ingo_Storage:: API to save Ingo data
 * via the Horde preferences system.
 *
 * $Horde: ingo/lib/Storage/prefs.php,v 1.14.12.11 2006/04/25 02:55:53 chuck Exp $
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Michael Slusarz <slusarz@bigworm.colorado.edu>
 * @since   Ingo 0.1
 * @package Ingo
 */
class Ingo_Storage_prefs extends Ingo_Storage {

    /**
     * Constructor.
     *
     * @param array $params  Additional parameters for the subclass.
     */
    function Ingo_Storage_prefs($params = array())
    {
        $this->_params = $params;
    }

    /**
     * Retrieves the specified data from the storage backend.
     *
     * @access private
     *
     * @param integer $field  The field name of the desired data.
     *                        See lib/Storage.php for the available fields.
     *
     * @return Ingo_Storage_rule|Ingo_Storage_filters  The specified data.
     */
    function &_retrieve($field)
    {
        global $prefs;

        switch ($field) {
        case INGO_STORAGE_ACTION_BLACKLIST:
            $ob = new Ingo_Storage_blacklist();
            $data = @unserialize($prefs->getValue('blacklist'));
            if ($data) {
                $ob->setBlacklist($data['a'], false);
                $ob->setBlacklistFolder($data['f']);
            }
            break;

        case INGO_STORAGE_ACTION_FILTERS:
            $ob = new Ingo_Storage_filters();
            $data = @unserialize($prefs->getValue('rules', false));
            if ($data === false) {
                /* Convert rules from the old format. */
                $data = @unserialize($prefs->getValue('rules'));
            } else {
                $data = String::convertCharset($data, $prefs->getCharset(), NLS::getCharset());
            }
            if ($data) {
                $ob->setFilterlist($data);
            }
            break;

        case INGO_STORAGE_ACTION_FORWARD:
            $ob = new Ingo_Storage_forward();
            $data = @unserialize($prefs->getValue('forward'));
            if ($data) {
                $ob->setForwardAddresses($data['a'], false);
                $ob->setForwardKeep($data['k']);
            }
            break;

        case INGO_STORAGE_ACTION_VACATION:
            $ob = new Ingo_Storage_vacation();
            $data = @unserialize($prefs->getValue('vacation', false));
            if ($data === false) {
                /* Convert vacation from the old format. */
                $data = @unserialize($prefs->getValue('vacation'));
            } elseif (is_array($data)) {
                $data = $prefs->convertFromDriver($data, NLS::getCharset());
            }
            if ($data) {
                $ob->setVacationAddresses($data['addresses'], false);
                $ob->setVacationDays($data['days']);
                $ob->setVacationExcludes($data['excludes'], false);
                $ob->setVacationIgnorelist($data['ignorelist']);
                $ob->setVacationReason($data['reason']);
                $ob->setVacationSubject($data['subject']);
            }
            break;

        case INGO_STORAGE_ACTION_WHITELIST:
            $ob = new Ingo_Storage_whitelist();
            $data = @unserialize($prefs->getValue('whitelist'));
            if ($data) {
                $ob->setWhitelist($data, false);
            }
            break;

        default:
            $ob = false;
            break;
        }

        return $ob;
    }

    /**
     * Stores the specified data in the storage backend.
     *
     * @access private
     *
     * @param Ingo_Storage_rule|Ingo_Storage_filters $ob  The object to store.
     *
     * @return boolean  True on success.
     */
    function _store(&$ob)
    {
        global $prefs;

        switch ($ob->obType()) {
        case INGO_STORAGE_ACTION_BLACKLIST:
            $data = array();
            $data['a'] = $ob->getBlacklist();
            $data['f'] = $ob->getBlacklistFolder();
            $ret = $prefs->setValue('blacklist', serialize($data));
            break;

        case INGO_STORAGE_ACTION_FILTERS:
            $ret = $prefs->setValue('rules', serialize(String::convertCharset($ob->getFilterlist(), NLS::getCharset(), $prefs->getCharset())), false);
            break;

        case INGO_STORAGE_ACTION_FORWARD:
            $data = array();
            $data['a'] = $ob->getForwardAddresses();
            $data['k'] = $ob->getForwardKeep();
            $ret = $prefs->setValue('forward', serialize($data));
            break;

        case INGO_STORAGE_ACTION_VACATION:
            $data = array();
            $data['addresses'] = $ob->getVacationAddresses();
            $data['days'] = $ob->getVacationDays();
            $data['excludes'] = $ob->getVacationExcludes();
            $data['ignorelist'] = $ob->getVacationIgnorelist();
            $data['reason'] = $ob->getVacationReason();
            $data['subject'] = $ob->getVacationSubject();
            $data = $prefs->convertToDriver($data, NLS::getCharset());
            $ret = $prefs->setValue('vacation', serialize($data), false);
            break;

        case INGO_STORAGE_ACTION_WHITELIST:
            $ret = $prefs->setValue('whitelist', serialize($ob->getWhitelist()));
            break;

        default:
            $ret = false;
            break;
        }

        return $ret;
    }

}
