<script type="text/javascript">
<!--
var loading;
function driverSubmit()
{
    if (document.fm_driver.fm_driver[document.fm_driver.fm_driver.selectedIndex].value != '') {
        if (loading == null) {
            loading = true;
            document.fm_driver.submit();
        }
    }
}
// -->
</script>

<?php echo _("Mail server type to connect to:") ?>&nbsp;<br />
<form name="fm_driver" action="<?php echo $fetch_url ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="fetchmail_create" />
<select name="fm_driver" onchange="javascript:driverSubmit()">
  <option value=""><?php echo _("Select the mail server type") ?></option>
<?php foreach (IMP_Fetchmail::listDrivers() as $key => $val): ?>
  <option value="<?php echo $key ?>"><?php echo htmlspecialchars($val) ?></option>
<?php endforeach; ?>
</select>
</form>
</td></tr></table></td></tr></table>

<br />
<table border="0" align="center" width="100%" cellspacing="0" cellpadding="0"><tr><td nowrap="nowrap">
<input type="button" name="back" class="button" onclick="document.location.href='<?php echo $prefs_url ?>'" value="<?php echo _("Return to Fetch Mail") ?>" />
</td></tr></table>
