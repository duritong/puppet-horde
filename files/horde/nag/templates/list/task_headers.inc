<?php $sortdirclass = $sortdir ? 'sortup' : 'sortdown' ?>

<script type="text/javascript">

var PREFS_UPDATE_TIMEOUT;

function table_sortCallback(tableId, column, sortDown)
{
    if (typeof PREFS_UPDATE_TIMEOUT != "undefined") {
        window.clearTimeout(PREFS_UPDATE_TIMEOUT);
    }

    PREFS_UPDATE_TIMEOUT = window.setTimeout('doPrefsUpdate("' + column + '", "' + sortDown + '")', 300);
}

function doPrefsUpdate(column, sortDown)
{
    baseurl = '<?php echo Horde::applicationUrl('pref_api.php', true) ?>';
    try {
        new Ajax.Request(baseurl, { parameters: { app: 'nag', pref: 'sortby', value: encodeURIComponent(column.substring(1)) } });
        new Ajax.Request(baseurl, { parameters: { app: 'nag', pref: 'sortdir', value: encodeURIComponent(sortDown) } });
    } catch (e) {}
}

</script>

<table id="tasks" width="100%" cellspacing="0" class="sortable nowrap">
<thead>
 <tr class="item leftAlign">
  <th id="s<?php echo NAG_SORT_COMPLETION ?>"<?php if ($sortby == NAG_SORT_COMPLETION) echo ' class="' . $sortdirclass . '"' ?> width="2%">
   <?php echo Horde::link(Horde::applicationUrl(Util::addParameter($baseurl, 'sortby', NAG_SORT_COMPLETION)), _("Sort by Completion Status"), 'sortlink') . Horde::img('checkbox.png', _("Completed?")) ?></a>
  </th>
<?php if ($showTasklist): ?>
  <th id="s<?php echo NAG_SORT_OWNER ?>"<?php if ($sortby == NAG_SORT_OWNER) echo ' class="' . $sortdirclass . '"' ?> width="2%">
   <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($baseurl, 'sortby', NAG_SORT_OWNER)), _("Sort by User Name"), 'sortlink', '', '', _("_Task List")) ?>&nbsp;
  </th>
<?php endif; ?>
  <th id="s<?php echo NAG_SORT_PRIORITY ?>"<?php if ($sortby == NAG_SORT_PRIORITY) echo ' class="' . $sortdirclass . '"' ?> width="2%">
   <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($baseurl, 'sortby', NAG_SORT_PRIORITY)), _("Sort by Priority"), 'sortlink', '', '', _("P_ri")) ?>&nbsp;
  </th>
  <th width="2%" class="nosort">
   <?php echo Horde::img('edit.png', _("Edit Task"), null, $registry->getImageDir('horde')) ?>
  </th>
  <th id="s<?php echo NAG_SORT_NAME ?>"<?php if ($sortby == NAG_SORT_NAME) echo ' class="' . $sortdirclass . '"' ?> width="60%">
   <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($baseurl, 'sortby', NAG_SORT_NAME)), _("Sort by Name"), 'sortlink', '', '', _("Na_me")) ?>&nbsp;
  </th>
  <th width="2%" class="nosort"><?php echo Horde::img('note.png', _("Task Note?")) ?></th>
  <th width="2%" class="nosort"><?php echo Horde::img('alarm.png', _("Task Alarm?")) ?></th>
  <th id="s<?php echo NAG_SORT_DUE ?>"<?php if ($sortby == NAG_SORT_DUE) echo ' class="' . $sortdirclass . '"' ?> width="12%">
   <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($baseurl, 'sortby', NAG_SORT_DUE)), _("Sort by Due Date"), 'sortlink', '', '', _("_Due Date")) ?>&nbsp;
  </th>
  <th id="s<?php echo NAG_SORT_CATEGORY ?>"<?php if ($sortby == NAG_SORT_CATEGORY) echo ' class="' . $sortdirclass . '"' ?> width="20%">
   <?php echo Horde::widget(Horde::applicationUrl(Util::addParameter($baseurl, 'sortby', NAG_SORT_CATEGORY)), _("Sort by Category"), 'sortlink', '', '', _("Cat_egory")) ?>&nbsp;
   <?php
   if (Auth::getAuth() && (!$GLOBALS['prefs']->isLocked('categories') ||
                           !$GLOBALS['prefs']->isLocked('category_colors'))) {
       $categoryUrl = Util::addParameter(Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/prefs.php'), array('app' => 'horde', 'group' => 'categories'));
       echo ' ' . Horde::link($categoryUrl, _("Edit categories and colors"), '', '_blank', 'popup(this.href); return false;') . Horde::img('colorpicker.png', _("Edit categories and colors"), '', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
   }
   ?>
  </th>
 </tr>
</thead>
<tbody>
