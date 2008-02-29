<?php
/**
 * The MIME_Viewer_vcard class renders out vCards in HTML format.
 *
 * $Horde: framework/MIME/MIME/Viewer/vcard.php,v 1.34.10.11 2007/03/14 15:58:41 jan Exp $
 *
 * Copyright 2002-2007 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 2.0
 * @package Horde_MIME_Viewer
 */
class MIME_Viewer_vcard extends MIME_Viewer {

    /**
     * Render out the vcard contents.
     *
     * @param array $params  Any parameters the Viewer may need.
     *
     * @return string  The rendered contents.
     */
    function render($params = null)
    {
        global $registry, $prefs;

        require_once 'Horde/iCalendar.php';

        $app = false;
        $data = $this->mime_part->getContents();
        $html = '';
        $import_msg = null;
        $title = _("vCard");

        $iCal = &new Horde_iCalendar();
        if (!$iCal->parsevCalendar($data)) {
            $import_msg = _("There was an error importing the contact data.");
        }

        if (Util::getFormData('import') &&
            Util::getFormData('source') &&
            $registry->hasMethod('contacts/import')) {
            $source = Util::getFormData('source');
            $contacts = $registry->call('contacts/import', array($data, 'text/x-vcard', $source));
            if (is_a($contacts, 'PEAR_Error')) {
                $import_msg = _("There was an error importing the contact data.");
            } else {
                if ($iCal->getComponentCount() == 1) {
                    $import_msg = _("The contact was successfully added to your address book.");
                } else {
                    $import_msg = _("Contacts were successfully added to your address book.");
                }
            }
        }

        $html .= '<table cellspacing="1" border="0" cellpadding="1">';
        if (!is_null($import_msg)) {
            $html .= '<tr><td colspan="2" class="header">' . $import_msg . '</td></tr><tr><td>&nbsp;</td></tr>';
        } elseif ($registry->hasMethod('contacts/import') &&
                  $registry->hasMethod('contacts/sources')) {
            $html .= '<tr><td colspan="2" class="smallheader"><form action="' . $_SERVER['PHP_SELF'] . '" method="get" name="vcard_import">' . Util::formInput();
            foreach ($_GET as $key => $val) {
                $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '" />';
            }

            $sources = $registry->call('contacts/sources', array(true));
            if (count($sources) > 1) {
                $html .= '<input type="submit" class="button" name="import" value="' . _("Add to address book:") . '" />';
                $html .= '<select name="source">';
                foreach ($sources as $key => $label) {
                    $selected = ($key == $prefs->getValue('add_source')) ? ' selected="selected"' : '';
                    $html .= '<option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
                }
            } else {
                $html .= '<input type="submit" class="button" name="import" value="' . _("Add to my address book") . '" />';
                $source = array_keys($sources);
                $html .= '<input type="hidden" name="source" value="' . htmlspecialchars($source[0]) . '" />';
            }

            $html .= '</form></td></tr><tr><td>&nbsp;</td></tr>';
        }

        $i = 0;
        foreach ($iCal->getComponents() as $vc) {
            if ($i > 0) {
                $html .= '<tr><td>&nbsp;</td></tr>';
            }
            ++$i;

            $html .= '<tr><td colspan="2" class="header">';
            $fullname = $vc->getAttributeDefault('FN', false);
            if ($fullname !== false) {
                $html .= $fullname;
            }
            $html .= '</td></tr>';

            $n = $vc->printableName();
            if (!empty($n)) {
                $html .= $this->_row(_("Name"), $n);
            }

            $aliases = $vc->getAttributeValues('ALIAS');
            if (!is_a($aliases, 'PEAR_Error')) {
                $html .= $this->_row(_("Alias"), implode('<br />', $aliases));
            }
            $birthdays = $vc->getAttributeValues('BDAY');
            if (!is_a($birthdays, 'PEAR_Error')) {
                $html .= $this->_row(_("Birthday"), date('Y-m-d', $birthdays[0]));
            }

            $labels = $vc->getAllAttributes('LABEL');
            foreach ($labels as $label) {
                if (isset($label['params']['TYPE'])) {
                    foreach ($label['params']['TYPE'] as $type) {
                        $label['params'][String::upper($type)] = true;
                    }
                }
                if (isset($label['params']['HOME'])) {
                    $html .= $this->_row(_("Home Address"), nl2br($label['value']));
                } elseif (isset($label['params']['WORK'])) {
                    $html .= $this->_row(_("Work Address"), nl2br($label['value']));
                } else {
                    $html .= $this->_row(_("Address"), nl2br($label['value']));
                }
            }

            // For vCard 3.0.
            $adrs = $vc->getAllAttributes('ADR');
            foreach ($adrs as $item) {
                if (isset($item['params']['TYPE'])) {
                    if (!is_array($item['params']['TYPE'])) {
                        $item['params']['TYPE'] = array($item['params']['TYPE']);
                    }
                } else {
                    $item['params']['TYPE'] = array();
                    if (isset($item['params']['WORK'])) {
                        $item['params']['TYPE'][] = 'WORK';
                    }
                    if (isset($item['params']['HOME'])) {
                        $item['params']['TYPE'][] = 'HOME';
                    }
                }
                $address = $item['values'];
                foreach ($item['params']['TYPE'] as $adr) {
                    if (String::upper($adr) == 'HOME') {
                        $a = array();
                        if (isset($address[VCARD_ADR_STREET]))   { $a[] = $address[VCARD_ADR_STREET]; }
                        if (isset($address[VCARD_ADR_LOCALITY])) { $a[] = $address[VCARD_ADR_LOCALITY]; }
                        if (isset($address[VCARD_ADR_REGION]))   { $a[] = $address[VCARD_ADR_REGION]; }
                        if (isset($address[VCARD_ADR_POSTCODE])) { $a[] = $address[VCARD_ADR_POSTCODE]; }
                        if (isset($address[VCARD_ADR_COUNTRY]))  { $a[] = $address[VCARD_ADR_COUNTRY]; }
                        $html .= $this->_row(_("Home Address"), nl2br(implode("\n", $a)));

                    } elseif (String::upper($adr) == 'WORK') {
                        $a = array();
                        if (isset($address[VCARD_ADR_STREET]))   { $a[] = $address[VCARD_ADR_STREET]; }
                        if (isset($address[VCARD_ADR_LOCALITY])) { $a[] = $address[VCARD_ADR_LOCALITY]; }
                        if (isset($address[VCARD_ADR_REGION]))   { $a[] = $address[VCARD_ADR_REGION]; }
                        if (isset($address[VCARD_ADR_POSTCODE])) { $a[] = $address[VCARD_ADR_POSTCODE]; }
                        if (isset($address[VCARD_ADR_COUNTRY]))  { $a[] = $address[VCARD_ADR_COUNTRY]; }
                        $html .= $this->_row(_("Work Address"), nl2br(implode("\n", $a)));

                    } else {
                        $a = array();
                        if (isset($address[VCARD_ADR_STREET]))   { $a[] = $address[VCARD_ADR_STREET]; }
                        if (isset($address[VCARD_ADR_LOCALITY])) { $a[] = $address[VCARD_ADR_LOCALITY]; }
                        if (isset($address[VCARD_ADR_REGION]))   { $a[] = $address[VCARD_ADR_REGION]; }
                        if (isset($address[VCARD_ADR_POSTCODE])) { $a[] = $address[VCARD_ADR_POSTCODE]; }
                        if (isset($address[VCARD_ADR_COUNTRY]))  { $a[] = $address[VCARD_ADR_COUNTRY]; }
                        $html .= $this->_row(_("Address"), nl2br(implode("\n", $a)));
                    }
                }
            }

            $numbers = $vc->getAllAttributes('TEL');

            foreach ($numbers as $number) {
                if (isset($number['params']['TYPE'])) {
                    if (!is_array($number['params']['TYPE'])) {
                        $number['params']['TYPE'] = array($number['params']['TYPE']);
                    }
                    foreach ($number['params']['TYPE'] as $type) {
                        $number['params'][String::upper($type)] = true;
                    }
                }
                if (isset($number['params']['FAX'])) {
                    $html .= $this->_row(_("Fax"), $number['value']);
                } else {
                    if (isset($number['params']['HOME'])) {
                        $html .= $this->_row(_("Home Phone"), $number['value']);
                    } elseif (isset($number['params']['WORK'])) {
                        $html .= $this->_row(_("Work Phone"), $number['value']);
                    } elseif (isset($number['params']['CELL'])) {
                        $html .= $this->_row(_("Cell Phone"), $number['value']);
                    } else {
                        $html .= $this->_row(_("Phone"), $number['value']);
                    }
                }
            }

            $addresses = $vc->getAllAttributes('EMAIL');
            $emails = array();
            foreach ($addresses as $address) {
                if (isset($address['params']['TYPE'])) {
                    foreach ($address['params']['TYPE'] as $type) {
                        $address['params'][String::upper($type)] = true;
                    }
                }
                $email = '<a href="';
                if ($registry->hasMethod('mail/compose')) {
                    $email .= $registry->call('mail/compose', array(array('to' => $address['value'])));
                } else {
                    $email .= 'mailto:' . $address['value'];
                }
                $email .= '">' . $address['value'] . '</a>';
                if (isset($address['params']['PREF'])) {
                    array_unshift($emails, $email);
                } else {
                    $emails[] = $email;
                }
            }

            if (count($emails)) {
                $html .= $this->_row(_("Email"), implode('<br />', $emails));
            }

            $title = $vc->getAttributeValues('TITLE');
            if (!is_a($title, 'PEAR_Error')) {
                $html .= $this->_row(_("Title"), $title[0]);
            }

            $role = $vc->getAttributeValues('ROLE');
            if (!is_a($role, 'PEAR_Error')) {
                $html .= $this->_row(_("Role"), $role[0]);
            }

            $org = $vc->getAttributeValues('ORG');
            if (!is_a($org, 'PEAR_Error')) {
                $html .= $this->_row(_("Company"), $org[0]);
                $html .= $this->_row(_("Department"), $org[1]);
            }

            $notes = $vc->getAttributeValues('NOTE');
            if (!is_a($notes, 'PEAR_Error')) {
                $html .= $this->_row(_("Notes"), nl2br($notes[0]));
            }

            $url = $vc->getAttributeValues('URL');
            if (!is_a($url, 'PEAR_Error')) {
                $html .= $this->_row(_("URL"), '<a href="' . $url[0]. '" target="_blank">' . $url[0] . '</a>');
            }
        }

        return Util::bufferOutput('include', $registry->get('templates', 'horde') . '/common-header.inc') .
               $html .
               '</table>' .
               Util::bufferOutput('include', $registry->get('templates', 'horde') . '/common-footer.inc');
    }

    function _row($label, $value)
    {
        return '<tr><td class="item" valign="top">' . $label . '</td><td class="item" valign="top">' . $value . "</td></tr>\n";
    }

    /**
     * Return the MIME content type of the rendered content.
     *
     * @return string  The content type of the output.
     */
    function getType()
    {
        return 'text/html; charset=' . NLS::getCharset();
    }

}
