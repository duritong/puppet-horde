<input type="hidden" name="actionID" value="" />
<input type="hidden" name="new_mailbox" value="" />
<input type="hidden" name="new_names" value="" />
<input type="hidden" name="old_names" value="" />
<input type="hidden" name="view_subscribed" value="" />
</form>
</div>

<script type="text/javascript">
<!--

displayNames = new Array(<?php
if (isset($displayNames) && is_array($displayNames)) {
    $iMax = count($displayNames);
    for ($i = 0; $i < $iMax; $i++) {
        if ($i > 0) {
            echo ', ';
        }
        echo '"' . $displayNames[$i] . '"';
    }
}
?>);

//-->
</script>
