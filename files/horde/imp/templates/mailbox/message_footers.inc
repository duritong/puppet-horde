</table>
<input type="hidden" name="page" value="<?php echo $pageOb->page ?>" />
<input type="hidden" name="actionID" value="" />
<input type="hidden" name="targetMbox" value="" />
<input type="hidden" name="newMbox" value="0" />
<input type="hidden" name="flag" value="" />
</form>

<script type="text/javascript">
<!--

Flags = new Array(<?php
if (isset($flags) && is_array($flags)) {
    for ($i = 0; $i < count($flags); $i++) {
        if ($i > 0) {
            echo ', ';
        }
        echo '"' . $flags[$i] . '"';
    }
}
?>);

var messagelist = new Array(<?php
if (isset($messages) && is_array($messages)) {
    for ($i = 0; $i < count($messages); $i++) {
        if ($i > 0) {
            echo ', ';
        }
        echo '"' . $messages[$i] . '"';
    }
}
?>);

//-->
</script>

</td></tr>
