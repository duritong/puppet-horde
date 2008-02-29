<?php
$menu_trash = (($imp['base_protocol'] != 'pop3') &&
               $prefs->getValue('use_trash') &&
               ($prefs->getValue('trash_folder') !== null) &&
               $prefs->getValue('empty_trash_menu'));
$menu_spam = (($imp['base_protocol'] != 'pop3') &&
               ($prefs->getValue('spam_folder') !== null) &&
               $prefs->getValue('empty_spam_menu'));
$menu_view = $prefs->getValue('menu_view');
$accesskey = $prefs->getValue('widget_accesskey') ? Horde::getAccessKey(_("Open Fo_lder")) : '';
?>
<form action="mailbox.php" method="get" name="menuform">
<?php echo Util::formInput() ?>
<div id="menu">
<?php if (($imp['base_protocol'] != 'pop3') && $conf['user']['allow_folders']): ?>
 <div class="rightFloat">
  <input type="hidden" name="no_newmail_popup" value="1" />
  <label for="mailbox" accesskey="<?php echo $accesskey ?>">
   <select id="mailbox" name="mailbox" onchange="folderSubmit()">
    <?php echo IMP::flistSelect('', true, array(), $imp['mailbox'], false, false, true) ?>
   </select>
  </label>
 </div>
 <div class="rightFloat">
  <?php
   $link = Horde::link('#', '', '', '', 'folderSubmit(true); return false;');
   printf('<ul><li>%s%s<br />%s</a></li></ul>',
          $link,
          ($menu_view != 'text') ? Horde::img('folders/folder_open.png') : '',
          ($menu_view != 'icon') ? Horde::highlightAccessKey(_("Open Fo_lder"), $accesskey) : '');
  ?>
 </div>
 <?php endif; ?>
 <div class="leftFloat">
  <?php echo IMP::getMenu('string') ?>
 </div>
 <br class="clear" />
</div>
</form>

<?php if ($conf['user']['allow_folders']): ?>
<script type="text/javascript">
<!--
var loading;
function folderSubmit(clear)
{
    if (document.menuform.mailbox[document.menuform.mailbox.selectedIndex].value != '') {
        if ((loading == null) || (clear != null)) {
            loading = true;
            document.menuform.submit();
        }
    }
}
// -->
</script>
<?php endif; ?>
