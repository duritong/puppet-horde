<?php
if (!isset($headers_inc_count)) {
    $headers_inc_count = 0;
} else {
    $headers_inc_count++;
}
$sortImg = ($sortdir) ? 'za.png' : 'az.png';
$sortText = ($sortdir) ? '\/' : '/\\';
$headers = array(
    SORTARRIVAL => array(
        'stext' => _("Sort by Arrival"),
        'text' => _("#"),
        'width' => '4%'
    ),
    SORTDATE => array(
        'stext' => _("Sort by Date"),
        'text' => _("Dat_e"),
        'width' => '10%'
    ),
    SORTTO => array(
        'stext' => _("Sort by To Address"),
        'text' => _("To"),
        'width' => '20%'
    ),
    SORTFROM => array(
        'stext' => _("Sort by From Address"),
        'text' => _("Fro_m"),
        'width' => '20%'
    ),
    SORTTHREAD => array(
        'stext' => _("Sort by Thread"),
        'text' => _("_Thread"),
        'width' => '52%'
    ),
    SORTSUBJECT => array(
        'stext' => _("Sort by Subject"),
        'text' => _("Sub_ject"),
        'width' => '52%'
    ),
    SORTSIZE => array(
        'stext' => _("Sort by Message Size"),
        'text' => _("Si_ze"),
        'width' => '6%'
    )
);

/* If this is the Drafts or Sent-Mail Folder, sort by To instead of
 * From. */
if ($drafts_sm_folder) {
    unset($headers[SORTFROM]);
} else {
    unset($headers[SORTTO]);
}

/* Determine which of Subject/Thread to emphasize. */
if ($search_mbox) {
    unset($headers[SORTTHREAD]);
} else {
    $extra = SORTTHREAD;
    $standard = SORTSUBJECT;
    if ($thread_sort) {
        $extra = SORTSUBJECT;
        $standard = SORTTHREAD;
    }
    $headers[$standard]['extra'] = '&nbsp;<span style="font-size: 10px;">[' . Horde::widget(Util::addParameter($mailbox_url, array('sortby' => $extra, 'actionID' => 'change_sort')), $headers[$extra]['stext'], 'widget" style="font-size: 10px; font-weight: normal;', null, 'if (window.event) window.event.cancelBubble = true; else if (event) event.stopPropagation();', $headers[$extra]['text']) . ']</span>';
    unset($headers[$extra]);
}
?>
<tr class="control"><td colspan="6">
<form method="post" name="messages" action="<?php echo $mailbox_url ?>">
<?php Util::pformInput() ?>
<table class="messageList" cellspacing="1">
<tr class="item">
<?php if (!$headers_inc_count): ?>
  <th onclick="document.messages.checkAll.checked = !document.messages.checkAll.checked; makeSelection(-1);" width="8%"><input type="checkbox" class="checkbox" name="checkAll" onclick="makeSelection(-1); if (window.event) window.event.cancelBubble = true; else if (event) event.stopPropagation();" <?php echo Horde::getAccessKeyAndTitle(_("Check _All/None")) ?> /></th>
<?php else: ?>
  <th>&nbsp;</th>
<?php endif; ?>

<?php foreach ($headers as $key => $val): ?>
  <th class="<?php echo ($sortby == $key) ? 'selected' : 'item' ?>" width="<?php echo $val['width'] ?>" <?php if (!$sortlimit): ?> onclick="document.location.href='<?php echo addslashes(Util::addParameter(($sortby == $key) ? $sort_url : $mailbox_url, array('sortby' => $key, 'actionID' => 'change_sort'))) ?>';"<?php endif; ?>>
<?php if ($sortlimit): ?>
  <?php echo Horde::stripAccessKey($val['text']) ?>
<?php else: ?>
  <?php if ($sortby == $key): ?>
    <?php echo Horde::link(Util::addParameter($sort_url, array('sortby' => $key, 'actionID' => 'change_sort')), $val['stext'], null, null, null, $val['stext']) . Horde::img($sortImg, $sortText, '', $registry->getImageDir('horde')) ?></a>
  <?php endif; ?>
  <?php echo Horde::widget(Util::addParameter(($sortby == $key) ? $sort_url : $mailbox_url, array('sortby' => $key, 'actionID' => 'change_sort')), $val['stext'], 'widget', null, null, $val['text']) ?>
<?php if (isset($val['extra'])) echo $val['extra']; ?>
<?php endif; ?>
  </th>
<?php endforeach; ?>
</tr>
