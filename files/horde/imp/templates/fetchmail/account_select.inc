<script type="text/javascript">
<!--
var loading;
function accountSubmit(isnew)
{
    if ((isnew != null) || (document.fm_switch.account[document.fm_switch.account.selectedIndex].value != '')) {
        if (loading == null) {
            loading = true;
            document.fm_switch.submit();
        }
    }
}
// -->
</script>

<?php echo _("Your accounts:") ?>&nbsp;<br />
<form name="fm_switch" action="<?php echo $fetch_url ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="fetchmail_prefs_edit" />
<select name="account" onchange="javascript:accountSubmit()">
  <option value=""><?php echo _("Select an account") ?></option>
<?php for ($i = 0, $iMax = count($accounts); $i < $iMax; $i++): ?>
  <option value="<?php echo $i ?>"><?php echo htmlspecialchars($accounts[$i]) ?></option>
<?php endfor; ?>
</select> <?php echo _("or") ?>
<input type="button" class="button" onclick="document.fm_switch.actionID.value='fetchmail_create';accountSubmit(true);return true;" value="<?php echo _("Create a New Account") ?>" />
</form>
</td></tr></table></td></tr></table>

<p>
 <input type="button" name="back" class="button" onclick="document.location.href='<?php echo $prefs_url ?>'" value="<?php echo _("Return to Fetch Mail") ?>" />
</p>
