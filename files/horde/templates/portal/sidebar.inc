<div id="expandedSidebar" style="overflow:hidden">
 <div id="menu" style="overflow:hidden">
  <?php if (!empty($conf['logo']['image']) || !Util::getFormData('mozbar')): ?>
  <span class="leftFloat"<?php if (empty($conf['logo']['image'])) echo ' id="themelogo"' ?>>
  <?php
  if (!empty($conf['logo']['image'])) {
      if (!empty($conf['logo']['link'])) {
          echo Horde::link($conf['logo']['link'], null, null, '_top');
      }
      echo Horde::img($conf['logo']['image'], '', '', '');
      if (!empty($conf['logo']['link'])) {
          echo '</a>';
      }
  } else {
      echo '&nbsp;';
  }
  ?>
  </span>
  <span class="leftFloat" id="logopad">&nbsp;</span>
  <div class="clear">&nbsp;</div>
  <?php endif; ?>
 </div>
 <span id="expandButton" class="rightFloat"><?php if (!$is_mozbar) echo Horde::link('#', _("Collapse Sidebar"), '', '', 'toggleMenuFrame(); return false;', _("Collapse Sidebar")) . Horde::img('hide_panel.png') . '</a>' ?></span>
 <div id="sidebarPanel">
  <?php $tree->renderTree() ?>
 </div>
</div>

<div id="hiddenSidebar">
 <div id="menu">
 </div>
  <?php echo Horde::link('#', '', '', '', 'toggleMenuFrame(); return false;') . Horde::img('show_panel.png') . '</a>' ?>
</div>

<script type="text/javascript">

var rtl = <?php echo $rtl ? 'true' : 'false' ?>;
var expandedSidebar = new Horde_Hideable('expandedSidebar');
var hiddenSidebar = new Horde_Hideable('hiddenSidebar');
hiddenSidebar.hide();

function getCookie(name, deflt)
{
    var dc = document.cookie;
    var prefix = name + '=';
    var begin = dc.indexOf('; ' + prefix);
    if (begin == -1) {
        begin = dc.indexOf(prefix);
        if (begin != 0) {
            return deflt;
        }
    } else {
        begin += 2;
    }
    var end = document.cookie.indexOf(';', begin);
    if (end == -1) {
        end = dc.length;
    }
    return unescape(dc.substring(begin + prefix.length, end));
}

function toggleMenuFrame()
{
    if (parent && parent.document.getElementById('hf')) {
        if (expandedSidebar.shown()) {
            parent.document.getElementById('hf').cols = rtl ? '*,20' : '20,*';
            expandedSidebar.hide();
            hiddenSidebar.show();
        } else {
            parent.document.getElementById('hf').cols = (rtl ? '*,' : '') + <?php echo (isset($prefs) ? $prefs->getValue('sidebar_width') : 150) ?> + (rtl ? '' : ',*');
            hiddenSidebar.hide();
            expandedSidebar.show();
        }

        // Expire in one year.
        var expires = new Date();
        expires.setTime(expires.getTime() + 31536000000);
        document.cookie = 'horde_sidebar_expanded=' + expandedSidebar.shown() + ';DOMAIN=<?php echo $GLOBALS['conf']['cookie']['domain'] ?>;PATH=<?php echo $GLOBALS['conf']['cookie']['path'] ?>;expires=' + expires.toGMTString();
    }
}

if (getCookie('horde_sidebar_expanded', true).toString() != expandedSidebar.shown().toString()) {
    toggleMenuFrame();
}

var MenuUpdateHandler = {
    onLoad: function(result)
    {
        // Run the javascript we got back.
        eval(result);
    }
};

function update()
{
    var client = new HTTPClient();
    client.init("<?php echo Horde::applicationUrl('services/portal/sidebar.php?httpclient=1', true) ?>");

    try {
        client.asyncGET(MenuUpdateHandler);
    } catch (e) {
        // Don't do anything on errors; probably a transient failure,
        // and we'll try again after the next interval.
    }
    window.setTimeout('update();', <?php echo $prefs->getValue('menu_refresh_time') ?> * 1000);
}

<?php if ($prefs->getValue('menu_refresh_time')): ?>
// Update the menu if set in user prefs.
<?php if ($GLOBALS['browser']->hasFeature('xmlhttpreq')): ?>
window.setTimeout('update();', <?php echo $prefs->getValue('menu_refresh_time') ?> * 1000);
<?php else: ?>
window.setTimeout('window.location.reload(true);', <?php echo $prefs->getValue('menu_refresh_time') ?> * 1000);
<?php endif; endif; ?>

</script>

<?php if (isset($GLOBALS['notification'])) $GLOBALS['notification']->notify(array('listeners' => array('javascript'))) ?>

</body>
</html>
