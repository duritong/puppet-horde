 <tr>
  <td>
<?php
if (!is_a($share, 'PEAR_Error') && $share->hasPermission(Auth::getAuth(), PERMS_EDIT)) {
    $label = sprintf(_("Edit \"%s\""), $memo['desc']);
    echo Horde::link(Horde::applicationUrl(Util::addParameter($memourl, 'actionID', 'modify_memo')), $label) .
        Horde::img('edit.png', $label, '', $registry->getImageDir('horde')) . '</a>';
}
?>
  </td>
  <td>
   <?php echo Horde::linkTooltip(Horde::applicationUrl($viewurl), '', '', '', '', ($memo['body'] != $memo['desc']) ? wordwrap($memo['body']) : '') . ((!empty($memo['desc']) || $memo['desc'] == '0') ? htmlspecialchars($memo['desc']) : '<em>' . _("Empty Note") . '</em>') ?></a>
  </td>
  <td style="background:<?php echo $color ?>; color:<?php echo $fgcolor ?>"><?php echo htmlspecialchars($memo['category'] ? $memo['category'] : _("Unfiled")) ?></td>
 </tr>
