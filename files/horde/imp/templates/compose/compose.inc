<?php $tabindex = 1 ?>

<?php if ($prefs->getValue('auto_expand')): ?>
<iframe id="autoexpand" name="autoexpand" style="width:0;height:0;border-width:0" src="<?php echo Horde::applicationUrl('expand.php') ?>"></iframe>
<?php endif; ?>

<form method="post" name="compose"<?php if ($imp['file_upload']) echo ' enctype="multipart/form-data"'; ?> action="<?php echo Util::addParameter(Horde::url('compose.php'), 'uniq', base_convert(microtime(), 10, 36)) ?>">
<?php if ($imp['file_upload']): ?>
<?php if ($browser->hasQuirk('broken_multipart_form')): ?>
<input type="hidden" name="msie_formdata_is_broken" value="" />
<?php endif; ?>
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $imp['file_upload'] ?>" />
<?php endif; ?>
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="" />
<input type="hidden" name="__formToken_compose" value="<?php echo Horde_Token::generateId('compose'); ?>" />
<?php if (isset($messageCacheID)): ?>
<input type="hidden" name="messageCache" value="<?php echo $messageCacheID ?>" />
<?php endif; ?>
<input type="hidden" name="spellcheck" value="" />
<input type="hidden" name="page" value="<?php echo htmlspecialchars(Util::getFormData('page')) ?>" />
<input type="hidden" name="start" value="<?php echo htmlspecialchars(Util::getFormData('start')) ?>" />
<input type="hidden" name="popup" value="<?php echo htmlspecialchars(Util::getFormData('popup')) ?>" />
<input type="hidden" name="thismailbox" value="<?php echo htmlspecialchars(Util::getFormData('thismailbox')) ?>" />
<input type="hidden" name="attachmentAction" value="" />
<input type="hidden" name="reloaded" value="1" />
<input type="hidden" name="oldrtemode" value="<?php echo $rtemode ?>" />
<input type="hidden" name="rtemode" value="<?php echo $rtemode ?>" />
<?php
    if (!($reply_type = Util::getFormData('reply_type'))) {
        switch ($actionID) {
        case 'reply':
        case 'reply_all':
        case 'reply_list':
            $reply_type = 'reply';
            break;

        case 'forward':
            $reply_type = 'forward';
            break;
        }
    }
?>
<?php if ($reply_type): ?>
<input type="hidden" name="reply_type" value="<?php echo $reply_type ?>" />
<input type="hidden" name="in_reply_to" value="<?php echo htmlspecialchars($header['inreplyto']) ?>" />
<?php if ($reply_type == 'reply'): ?>
<input type="hidden" name="references" value="<?php echo htmlspecialchars($header['references']) ?>" />
<input type="hidden" name="reply_index" value="<?php echo htmlspecialchars($reply_index) ?>" />
<?php endif; ?>
<?php endif; ?>

<h1 class="header"><?php echo htmlspecialchars($title) ?></h1>

<br />
<?php IMP::status(); ?>

<table width="100%" cellspacing="1">
  <tr>
    <td>&nbsp;</td>
    <td>
      <table width="100%" cellspacing="0">
        <tr>
          <td class="leftAlign" nowrap="nowrap">
            <input name="btn_send_message" type="submit" <?php echo Horde::getAccessKeyAndTitle(_("_Send Message")) ?> class="button" onclick="return uniqSubmit('send_message');" value="<?php echo _("Send Message") ?>" />
<?php if ($conf['user']['allow_folders']): ?>
            <input name="btn_save_draft" type="submit" <?php echo Horde::getAccessKeyAndTitle(_("Save _Draft")) ?> class="button" onclick="uniqSubmit('save_draft');" value="<?php echo _("Save Draft") ?>" />
<?php endif; ?>
            <input name="btn_cancel_compose" type="submit" title="<?php echo _("Cancel Message") ?>" class="button" onclick="return confirmCancel();" value="<?php echo _("Cancel Message") ?>" />
          </td>
          <td class="rightAlign" style="padding:2px"><?php echo Help::link('imp', 'compose-buttons') ?>&nbsp;</td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
<?php if (!$prefs->isLocked('default_identity')): ?>
    <td class="light rightAlign"><strong><?php echo Horde::label('identity', _("_Identity")) ?>&nbsp;</strong></td>
    <td class="item">
      <table width="100%">
        <tr>
          <td class="leftAlign">
            <input type="hidden" name="last_identity" value="<?php echo $identity->getDefault() ?>" />
    <?php if (count($select_list) > 1): ?>
        <select id="identity" name="identity" tabindex="<?php echo $tabindex++ ?>" onchange="change_identity(this.selectedIndex)" style="direction:ltr">
        <?php foreach ($select_list as $key => $select): ?>
        <option value="<?php echo $key ?>"<?php if ($key == $identity->getDefault()) echo ' selected="selected"' ?>><?php echo htmlspecialchars($select) ?></option>
        <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="hidden" name="identity" value="<?php echo $identity->getDefault() ?>" />
        <?php echo htmlspecialchars($select_list[0]) ?>
    <?php endif; ?>
          </td>
<?php else: ?>
    <td class="light rightAlign"><strong><?php echo _("From") ?>&nbsp;</strong></td>
    <td class="item">
      <table width="100%">
        <tr>
<?php if (!$prefs->isLocked('from_addr')): ?>
          <td class="leftAlign"><input type="text" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="from" value="<?php echo htmlspecialchars($from) ?>" style="direction:ltr"/></td>
<?php else: ?>
          <td class="leftAlign"><strong>&nbsp;<?php echo htmlspecialchars($from) ?></strong></td>
<?php endif; ?>
<?php endif; ?>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-from') ?>&nbsp;</td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td class="light rightAlign"><strong><?php echo Horde::label('to', _("_To")) ?>&nbsp;</strong></td>
    <td class="item">
      <table width="100%">
<?php if (is_array($header['to'])): $first_to = true; ?>
<?php foreach ($header['to'] as $to_item): ?>
        <tr>
<?php if (count($to_item) > 1): ?>
          <td class="leftAlign">
                  <select id="to" tabindex="<?php echo $tabindex++ ?>" name="to_list[<?php echo htmlspecialchars($to_item[0]) ?>]" style="width:350px;direction:ltr">
                    <option value="" selected="selected"><?php echo _("Choose a match or edit the address to the right:") ?></option>
<?php for ($i = 1; $i < count($to_item); $i++): ?>
                    <option value="<?php echo htmlspecialchars($to_item[$i]) ?>"><?php echo htmlspecialchars($to_item[$i]) ?></option>
<?php endfor; ?>
                  </select>
                  <input type="text" size="15" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="to_field[<?php echo htmlspecialchars($to_item[0]) ?>]" value="<?php echo htmlspecialchars($to_item[0]) ?>" style="width:150px;direction:ltr" />
          </td>
<?php else: ?>
          <td class="leftAlign"><input type="text" id="to" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="to_field[]" value="<?php echo htmlspecialchars($to_item) ?>" style="direction:ltr" /></td>
<?php endif; ?>
<?php if ($first_to): $first_to = false; ?>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-to') ?>&nbsp;</td>
<?php else: ?>
          <td>&nbsp;</td>
<?php endif; ?>
        </tr>
<?php endforeach; ?>
<?php else: ?>
        <tr>
          <td class="leftAlign"><input type="text" id="to" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" style="direction:ltr" name="to" value="<?php echo htmlspecialchars($header['to']) ?>" <?php echo ($browser->hasFeature('iframes')) ? 'onchange="expandField(this);" /> <span id="expandingto" class="inProgress" style="visibility:hidden">' . _("Expanding") . '&hellip;</span>' : '/>' ?></td>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-to') ?>&nbsp;</td>
        </tr>
<?php endif; ?>
      </table>
    </td>
  </tr>

<?php if ($conf['compose']['allow_cc']): ?>
  <tr>
    <td class="light rightAlign"><strong><?php echo Horde::label('cc', _("_Cc")) ?>&nbsp;</strong></td>
    <td class="item">
      <table width="100%">
<?php if (is_array($header['cc'])): $first_cc = true; ?>
<?php foreach ($header['cc'] as $cc_item): ?>
        <tr>
<?php if (count($cc_item) > 1): ?>
          <td class="leftAlign">
            <table cellspacing="0">
              <tr>
                <td width="350">
                  <select id="cc" tabindex="<?php echo $tabindex++ ?>" name="cc_list[<?php echo htmlspecialchars($cc_item[0]) ?>]" style="width:350px;direction:ltr">
                    <option value="" selected="selected"><?php echo _("Choose a match or edit the address to the right:") ?></option>
<?php for ($i = 1; $i < count($cc_item); $i++): ?>
                    <option value="<?php echo htmlspecialchars($cc_item[$i]) ?>"><?php echo htmlspecialchars($cc_item[$i]) ?></option>
<?php endfor; ?>
                  </select>
                </td>
                <td width="150">
                  <input type="text" size="15" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="cc_field[<?php echo htmlspecialchars($cc_item[0]) ?>]" value="<?php echo htmlspecialchars($cc_item[0]) ?>" style="width:150px;direction:ltr" />
                </td>
              </tr>
            </table>
          </td>
<?php else: ?>
          <td class="leftAlign"><input type="text" id="cc" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="cc_field[]" value="<?php echo htmlspecialchars($cc_item) ?>" style="direction:ltr" /></td>
<?php endif; ?>
<?php if ($first_cc): $first_cc = false; ?>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-cc') ?>&nbsp;</td>
<?php else: ?>
          <td>&nbsp;</td>
<?php endif; ?>
        </tr>
<?php endforeach; ?>
        <tr>
          <td class="leftAlign"><input type="text" id="cc" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="cc_new" style="direction:ltr" /></td>
          <td>&nbsp;</td>
        </tr>
<?php else: ?>
        <tr>
          <td class="leftAlign"><input type="text" id="cc" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" style="direction:ltr" name="cc" value="<?php echo htmlspecialchars($header['cc']) ?>" <?php echo ($browser->hasFeature('iframes')) ? 'onchange="expandField(this);" /> <span id="expandingcc" class="inProgress" style="visibility:hidden">' . _("Expanding") . '&hellip;</span>' : '/>' ?></td>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-cc') ?>&nbsp;</td>
        </tr>
<?php endif; ?>
      </table>
    </td>
  </tr>
<?php endif; ?>

<?php if ($conf['compose']['allow_bcc']): ?>
  <tr>
    <td class="light rightAlign"><strong><?php echo Horde::label('bcc', _("_Bcc")) ?>&nbsp;</strong></td>
    <td class="item">
      <table width="100%">
<?php if (is_array($header['bcc'])): $first_bcc = true; ?>
<?php foreach ($header['bcc'] as $bcc_item): ?>
        <tr>
<?php if (count($bcc_item) > 1): ?>
          <td class="leftAlign">
            <table cellspacing="0">
              <tr>
                <td width="350">
                  <select id="bcc" tabindex="<?php echo $tabindex++ ?>" name="bcc_list[<?php echo htmlspecialchars($bcc_item[0]) ?>]" style="width:350px;direction:ltr">
                    <option value="" selected="selected"><?php echo _("Choose a match or edit the address to the right:") ?></option>
<?php for ($i = 1; $i < count($bcc_item); $i++): ?>
                    <option value="<?php echo htmlspecialchars($bcc_item[$i]) ?>"><?php echo htmlspecialchars($bcc_item[$i]) ?></option>
<?php endfor; ?>
                  </select>
                </td>
                <td width="150">
                  <input type="text" size="15" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="bcc_field[<?php echo htmlspecialchars($bcc_item[0]) ?>]" value="<?php echo htmlspecialchars($bcc_item[0]) ?>" style="width:150px;direction:ltr" />
                </td>
              </tr>
            </table>
          </td>
<?php else: ?>
          <td class="leftAlign"><input type="text" id="bcc" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="bcc_field[]" value="<?php echo htmlspecialchars($bcc_item) ?>" style="direction:ltr" /></td>
<?php endif; ?>
<?php if ($first_bcc): $first_bcc = false; ?>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-bcc') ?>&nbsp;</td>
<?php else: ?>
          <td>&nbsp;</td>
<?php endif; ?>
        </tr>
<?php endforeach; ?>
        <tr>
          <td class="leftAlign"><input type="text" id="bcc" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="bcc_new" style="direction:ltr" /></td>
          <td>&nbsp;</td>
        </tr>
<?php else: ?>
        <tr>
          <td class="leftAlign"><input type="text" id="bcc" size="55" tabindex="<?php echo $tabindex++ ?>" class="fixed" style="direction:ltr" name="bcc" value="<?php echo htmlspecialchars($header['bcc']); ?>" <?php echo ($browser->hasFeature('iframes')) ? 'onchange="expandField(this);" /> <span id="expandingbcc" class="inProgress" style="visibility:hidden">' . _("Expanding") . '&hellip;</span>' : '/>' ?></td>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-bcc') ?>&nbsp;</td>
        </tr>
<?php endif; ?>
      </table>
    </td>
  </tr>
<?php endif; ?>

  <tr>
    <td class="light rightAlign"><strong><?php echo Horde::label('subject', _("S_ubject")) ?>&nbsp;</strong></td>
    <td class="item">
      <table width="100%">
        <tr>
          <td class="leftAlign"><input type="text" id="subject" tabindex="<?php echo $tabindex++ ?>" class="fixed" name="subject" value="<?php echo htmlspecialchars($header['subject']) ?>" size="55" <?php if ($browser->isBrowser('msie')) echo 'onkeydown="subjectTab();" ' ?>/></td>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-subject') ?>&nbsp;</td>
        </tr>
      </table>
    </td>
  </tr>

<?php
$set_priority = $prefs->getValue('set_priority');
$locked_charset = $prefs->isLocked('sending_charset');
?>
<?php if ($set_priority || !$locked_charset): ?>
  <tr>
    <td class="light rightAlign"><strong><?php echo ($locked_charset) ? Horde::label('priority', _("_Priority")) : Horde::label('charset', _("C_harset")) ?>&nbsp;</strong></td>
    <td class="item">
      <table width="100%">
        <tr>
<?php if (!$locked_charset): ?>
          <td>
            <select id="charset" name="charset" tabindex="<?php echo $tabindex++ ?>" style="direction:ltr">
<?php foreach ($nls['encodings'] as $charset => $name): ?>
              <option value="<?php echo $charset ?>"<?php if (strtolower($charset) == strtolower($encoding)) echo ' selected="selected"' ?>><?php echo $name ?></option>
<?php endforeach; ?>
            </select>
          </td>
<?php endif; ?>

<?php if ($set_priority): ?>
<?php $priority = substr(Util::getFormData('x_priority', '3'), 0, 1); ?>
<?php if (!$locked_charset): ?>
          <td class="rightAlign"><?php echo Horde::label('priority', _("_Priority")) ?>&nbsp;</td>
<?php endif; ?>
          <td class="leftAlign">
            <select id="priority" name="x_priority" tabindex="<?php echo $tabindex++ ?>">
              <option value="1 (Highest)"<?php if ($priority == '1)') echo ' selected="selected"' ?>>1 (<?php echo _("Highest") ?>)</option>
              <option value="2 (High)"<?php if ($priority == '2') echo ' selected="selected"' ?>>2 (<?php echo _("High") ?>)</option>
              <option value="3 (Normal)"<?php if ($priority == '3') echo ' selected="selected"' ?>>3 (<?php echo _("Normal") ?>)</option>
              <option value="4 (Low)"<?php if ($priority == '4') echo ' selected="selected"' ?>>4 (<?php echo _("Low") ?>)</option>
              <option value="5 (Lowest)"<?php if ($priority == '5') echo ' selected="selected"' ?>>5 (<?php echo _("Lowest") ?>)</option>
            </select>
          </td>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-priority') ?>&nbsp;</td>
<?php endif; ?>

        </tr>
      </table>
    </td>
  </tr>
<?php endif; ?>

<?php if (!$prefs->isLocked('stationery') && !empty($stationery_list)): ?>
  <tr>
    <td class="light rightAlign" valign="middle"><strong><?php echo Horde::label('stationery', _("Stationery")) ?>&nbsp;</strong></td>
    <td class="item">
      <table width="100%">
        <tr>
          <td class="leftAlign">
             <select name="stationery" id="stationery" onchange="uniqSubmit('change_stationery');">
               <option value=""><?php echo _("Select a stationery:") ?></option>
<?php foreach ($stationery_list as $stationery_id => $stationery_choice): ?>
               <option value="<?php echo $stationery_id ?>"<?php if ($stationery === $stationery_id) echo ' selected="selected"' ?>><?php echo $stationery_choice['n'] ?></option>
<?php endforeach; ?>
            </select>
          </td>
        </tr>
      </table>
    </td>
  </tr>
<?php endif; ?>

  <tr>
    <td>&nbsp;</td>
    <td class="item">
      <table width="100%" cellspacing="0">
        <tr>
          <td>
            <table width="100%" cellspacing="0">
              <tr>
<?php if ($registry->hasMethod('contacts/search')): ?>
<?php $contacts_url = Horde::applicationUrl('contacts.php'); ?>
                <td align="center"><?php echo Horde::link('#', '', 'widget', null, 'window.open(\'' . $contacts_url . '\', \'contacts\', \'toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes,width=550,height=300,left=100,top=100\'); return false;') . Horde::img('addressbook_browse.png') . '<br />' . _("Address Book") . '</a>' ?></td>
                <td align="center"><?php echo Horde::link('#', '', 'widget', null, "uniqSubmit('compose_expand_addr'); return false;") . Horde::img('expand.png') . '<br />' . _("Expand Names") . '</a>' ?></td>
<?php endif; ?>
<?php if (!empty($conf['utils']['spellchecker'])): ?>
                <td align="center"><?php echo Horde::link('#', '', 'widget', null, "uniqSubmit('spell_check'); return false;") . Horde::img('spellcheck.png') . '<br />' . _("Spell Check") . '</a>' ?></td>
<?php endif; ?>
<?php if ($browser->hasFeature('javascript') && $conf['compose']['special_characters']): ?>
                <td align="center"><?php echo Horde::link('#', '', 'widget', null, 'window.open(\'' . Horde::url($registry->get('webroot', 'horde') . '/services/keyboard.php') . '\', \'chars\', \'height=220,width=400\'); return false;') . Horde::img('keyboard.png', null, null, $registry->getImageDir('horde')) . '<br />' . _("Special Characters") . '</a>' ?></td>
<?php endif; ?>
<?php if ($imp['file_upload']): ?>
                <td align="center"><?php echo Horde::link('#attachments', '', 'widget', null, 'document.compose.upload_1.focus(); return false;') . Horde::img('manage_attachments.png') . '<br />' . _("Attachments") . '</a>' ?></td>
<?php endif; ?>
              </tr>
            </table>
          </td>
          <td class="item rightAlign" rowspan="2"><?php echo Help::link('imp', 'compose-options') ?>&nbsp;</td>
        </tr>
        <tr>
          <td>
            <table width="100%" cellspacing="1">
              <tr>
                <td class="item leftAlign" valign="bottom">
<?php if ($conf['user']['allow_folders'] && !$prefs->isLocked('save_sent_mail')): ?>
                  <input id="ssm" type="checkbox" name="save_sent_mail"<?php if ($ssm_check) echo ' checked="checked"'; ?> />
                  <?php echo Horde::label('ssm', _("Sa_ve a copy in ")) ?>
<?php
    $sent_mail_folder = $identity->getValue('sent_mail_folder');
    if (empty($sent_mail_folder)) {
        $sent_mail_folder = Util::getFormData('sent_mail_folder');
    }
    if (!empty($conf['user']['select_sentmail_folder']) &&
        !$prefs->isLocked('sent_mail_folder')) {
    $folders = IMP::flistSelect('', false, array('INBOX'), $sent_mail_folder);
    echo ' <select onchange="document.compose.save_sent_mail.checked=\'checked\'" tabindex="' . $tabindex++ . '" name="sent_mail_folder">' . $folders . '</select>';
} else {
    if (!empty($sent_mail_folder)) {
        $sent_mail_folder = '&quot;' . IMP::displayFolder($sent_mail_folder) . '&quot;';
    }
    echo '<span id="sent_mail_folder">' . $sent_mail_folder . '</span>';
}
?>
<?php endif; ?>

<?php
    $d_read = $prefs->getValue('disposition_request_read');
    $d_deliv = $prefs->getValue('disposition_request_delivery');
?>
<?php if ($conf['compose']['allow_receipts'] && ($d_read != 'never')): ?>
                <br /><input id="rrr" type="checkbox" name="request_read_receipt"<?php if (($d_read != 'ask') || (Util::getFormData('request_read_receipt') == 'on')) echo ' checked="checked"'; ?> /> <?php echo Horde::label('rrr', _("Request a _Read Receipt")) ?>
<?php endif; ?>
                </td>
              </tr>

<?php if ($browser->hasFeature('rte') && !$prefs->isLocked('compose_html')): ?>
              <tr>
                <td colspan="2">
                  <table width="100%" cellspacing="0">
                    <tr>
                      <td class="item leftAlign" width="20">
                        <?php echo Horde::img('compose.png', _("Switch Composition Method"), 'align="middle"') ?>
                      </td>
                      <td class="item leftAlign">
                        <?php echo Horde::link('#', _("Switch Composition Method"), '', '', "document.compose.rtemode.value='" . ($rtemode ? 0 : 1) . "'; uniqSubmit(); return false;") ?>
                        <?php echo $rtemode ? _("Switch to plain text composition") : _("Switch to HTML composition") ?></a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
<?php endif; ?>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td class="light rightAlign" valign="top"><strong><?php echo Horde::label('message', _("Te_xt")) ?>&nbsp;</strong></td>
    <td class="item">
      <textarea class="composebody" tabindex="<?php echo $tabindex++ ?>" name="message" id="message" rows="20" cols="80"><?php echo htmlspecialchars($msg) ?></textarea>
    </td>
  </tr>

  <tr>
    <td>&nbsp;</td>
    <td>
      <table width="100%" cellspacing="0">
        <tr>
          <td class="leftAlign" nowrap="nowrap">
            <input name="btn_send_message" type="submit" <?php echo Horde::getAccessKeyAndTitle(_("_Send Message"), true) ?> class="button" onclick="return uniqSubmit('send_message');" value="<?php echo _("Send Message") ?>" />
<?php if ($conf['user']['allow_folders']): ?>
            <input name="btn_save_draft" type="submit" <?php echo Horde::getAccessKeyAndTitle(_("Save _Draft"), true) ?> class="button" onclick="uniqSubmit('save_draft');" value="<?php echo _("Save Draft") ?>" />
<?php endif; ?>
            <input name="btn_cancel_compose" type="submit" title="<?php echo _("Cancel Message") ?>" class="button" onclick="return confirmCancel();" value="<?php echo _("Cancel Message") ?>" />
          </td>
          <td class="rightAlign" style="padding:2px"><?php echo Help::link('imp', 'compose-buttons') ?></td>
        </tr>
<?php if ($prefs->getValue('use_pgp') || $prefs->getValue('use_smime')): ?>
        <tr>
          <td class="item leftAlign" nowrap="nowrap">
            &nbsp;<?php echo Horde::label('encrypt_options', _("Encr_yption Options")) ?>:&nbsp;
            <select id="encrypt_options" name="encrypt_options">
<?php
    $encryptOpt = Util::getFormData('encrypt_options');
    require IMP_TEMPLATES . '/compose/encrypt_list.inc';
?>
            </select>
          </td>
          <td class="item rightAlign"><?php echo Help::link('imp', 'compose-options-encrypt') ?>&nbsp;</td>
        </tr>
<?php if ($prefs->getValue('use_pgp') && $prefs->getValue('pgp_public_key')): ?>
        <tr>
          <td class="item leftAlign" nowrap="nowrap">
            <input id="pap" type="checkbox" name="pgp_attach_pubkey" <?php if (Util::getFormData('pgp_attach_pubkey', $prefs->getValue('pgp_attach_pubkey'))) echo 'checked="checked" ' ?>/>
            <?php echo Horde::label('pap', _("Attach a copy of your PGP public key to your message?")) ?>
          </td>
          <td class="item rightAlign"><?php echo Help::link('imp', 'pgp-compose-attach-pubkey') ?>&nbsp;</td>
        </tr>
<?php endif; ?>
<?php endif; ?>
      </table>
    </td>
  </tr>

<?php
if ($imp['file_upload']):
    $localeinfo = NLS::getLocaleInfo();
?>
  <tr>
    <td colspan="2">&nbsp;</td>
  </tr>

  <tr class="header">
    <td colspan="2" height="25" class="header">
      <strong>&nbsp;<a id="attachments"></a><?php echo _("Attachments") ?></strong>
<?php if ($GLOBALS['registry']->hasMethod('files/selectlistLink')): ?>
&nbsp;<?php $res = $GLOBALS['registry']->call('files/selectlistLink', array(_("Attach Files"), 'widget', 'compose', true)); if (!is_a($res, 'PEAR_Error')) echo $res; ?>
      <input type="hidden" name="selectlist_selectid" value="" />
<?php endif; ?>
    </td>
  </tr>

  <tr>
    <td>&nbsp;</td>
    <td class="item">
      <table width="100%">
        <tr>
<?php if (!$imp_compose->maxAttachmentSize()): ?>
          <td class="leftAlign" colspan="2">
            <?php echo _("Maximum total attachment size reached.") ?>
          </td>
<?php else: if (!isset($attachments)): ?>
          <td class="leftAlign" colspan="2">
            <?php echo _("Maximum number of attachments reached.") ?>
          </td>
<?php else: ?>
          <td class="leftAlign">
            <table>
              <tr id="attachment_row_1">
                <td class="leftAlign">
                  <strong><?php echo _("File") ?> 1:</strong>&nbsp;<input name="upload_1" tabindex="<?php echo $tabindex++ ?>" type="file" onchange="attachmentChanged()" size="25" class="fixed" style="direction:ltr" />
                </td>
                <td class="leftAlign">
                  <select name="upload_disposition_1">
                    <option value="attachment" selected="selected"><?php echo _("Attachment") ?></option>
                    <option value="inline"><?php echo _("Inline") ?></option>
                  </select>
                </td>
              </tr>
              <tr>
                <td colspan="2">
                  (<?php echo _("Maximum Attachment Size") . ': ' . number_format($imp_compose->maxAttachmentSize(), 0, $localeinfo['decimal_point'], $localeinfo['thousands_sep']) . ' ' . _("bytes") ?>)
                </td>
              </tr>
            </table>
          </td>
<?php endif; ?>
<?php endif; ?>
          <td>
            <input type="submit" name="btn_add_attachment" class="button" onclick="uniqSubmit('add_attachment');" value="<?php echo _("Update") ?>" />
          </td>
          <td class="rightAlign"><?php echo Help::link('imp', 'compose-attach') ?>&nbsp;</td>
        </tr>
      </table>
    </td>
  </tr>

<?php
    $save_attach = $prefs->getValue('save_attachments');
    $show_link_attach = ($conf['compose']['link_attachments'] && !$conf['compose']['link_all_attachments']);
    $show_save_attach = ((strpos($save_attach, 'prompt') === 0) && !$conf['compose']['link_all_attachments']);
?>
<?php if ($show_link_attach || $show_save_attach): ?>
  <tr>
    <td>&nbsp;</td>
    <td class="item">
      <table width="100%" cellspacing="2">
<?php if ($show_save_attach): ?>
<?php $save_attach_val = Util::getFormData('save_attachments_select', ($save_attach == 'prompt_yes')); ?>
        <tr>
          <td class="leftAlign"><strong><?php echo _("Save Attachments with message in sent-mail folder?") ?></strong>&nbsp;</td>
          <td class="leftAlign">
            <select name="save_attachments_select">
              <option value="1"<?php if ($save_attach_val == 1) echo ' selected="selected"' ?>><?php echo _("Yes") ?></option>
              <option value="0"<?php if ($save_attach_val == 0) echo ' selected="selected"' ?>><?php echo _("No") ?></option>
            </select>
          </td>
          <td class="item rightAlign"><?php echo Help::link('imp', 'compose-save-attachments') ?>&nbsp;</td>
        </tr>
<?php endif; ?>
<?php if ($show_link_attach): ?>
<?php $link_attach_val = Util::getFormData('link_attachments'); ?>
        <tr>
          <td class="leftAlign"><strong><?php echo _("Link Attachments?") ?></strong>&nbsp;</td>
          <td class="leftAlign">
            <select name="link_attachments">
              <option value="1"<?php if ($link_attach_val == 1) echo ' selected="selected"' ?>><?php echo _("Yes") ?></option>
              <option value="0"<?php if ($link_attach_val == 0) echo ' selected="selected"' ?>><?php echo _("No") ?></option>
            </select>
          </td>
          <td class="item rightAlign"><?php echo Help::link('imp', 'compose-link-attachments') ?>&nbsp;</td>
        </tr>
<?php endif; ?>
      </table>
    </td>
  </tr>
<?php endif; ?>
<?php if ($imp_compose->numberOfAttachments()): ?>
  <tr>
    <td>&nbsp;</td>
    <td class="item">
      <table width="100%">
        <tr>
          <td class="leftAlign">
            <table class="item" cellspacing="2">
<?php
    require_once 'Horde/MIME/Contents.php';
    $mime_contents = &new MIME_Contents(new MIME_Part());

    foreach ($imp_compose->getAttachments() as $file_num => $mime) {
        $file_num++;
        $disposition = $mime->getDisposition();
        $viewer = &$mime_contents->getMIMEViewer($mime);
        $linked = Util::getFormData (sprintf('file_linked_%d', $file_num));
        if ($mime->getType() != 'application/octet-stream') {
            $preview_url = Util::addParameter(Horde::applicationUrl('view.php'), array('actionID' => 'compose_attach_preview', 'id' => $file_num, 'messageCache' => $imp_compose->getMessageCacheId()));
        } else {
            $preview_url = null;
        }
        require IMP_TEMPLATES . '/compose/attachments.inc';
    }
?>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td colspan="4" class="leftAlign"><strong><?php echo _("Total Attachments Size:") ?></strong> <?php echo number_format($imp_compose->sizeOfAttachments() / 1024, 2, $localeinfo['decimal_point'], $localeinfo['thousands_sep']) ?> <?php echo _("KB") ?></th>
              </tr>
            </table>
          </td>
          <td class="rightAlign" valign="top"><?php echo Help::link('imp', 'compose-attachments') ?>&nbsp;</td>
        </tr>
      </table>
    </td>
  </tr>
<?php endif; ?>
<?php endif; ?>

</table>
</form>
