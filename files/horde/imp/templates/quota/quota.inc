<?php
if (is_a($quota, 'PEAR_Error')) {
    $class = 'quotawarn';
    $message = $quota->getMessage();
} else {
    if ($quota['limit'] != 0) {
        $quota['usage'] = $quota['usage'] / (1024 * 1024.0);
        $quota['limit'] = $quota['limit'] / (1024 * 1024.0);
        $percent = ($quota['usage'] * 100) / $quota['limit'];
        if ($percent >= 90) {
            $class = 'quotaalert';
        } elseif ($percent >= 75) {
            $class = 'quotawarn';
        } else {
            $class = 'control';
        }
        $message = sprintf(_("Quota status: %.2fMB / %.2fMB  (%.2f%%)"), $quota['usage'], $quota['limit'], $percent);
    } elseif ($quota['usage'] != 0) {
        $quota['usage'] = $quota['usage'] / (1024 * 1024.0);
        $class = 'control';
        $message = sprintf(_("Quota status: %.2fMB / NO LIMIT"), $quota['usage']);
    } else {
        $class = 'control';
        $message = sprintf(_("Quota status: NO LIMIT"));
    }
}
?>
<table width="100%" border="0" cellpadding="3" cellspacing="0">
  <tr><td class="<?php echo $class ?>" align="center">
    <?php echo $message ?>
  </td></tr>
</table>
