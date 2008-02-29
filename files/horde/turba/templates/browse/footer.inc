<div class="control">
 <strong><?php echo _("Records displayed:") ?></strong>
<?php
if (!empty($numDisplayed)) {
    echo '[' . sprintf(_("%s to %s of %s"), $start, $end, $numDisplayed) . ']';
} else {
    echo _("None");
}
if (isset($pager)) {
    echo $pager->render();
}
?>
</div>
