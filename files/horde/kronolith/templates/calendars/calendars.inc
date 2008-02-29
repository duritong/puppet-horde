<div id="page">

<script type="text/javascript">

var editURL = decodeURIComponent('<?php echo rawurlencode(Util::addParameter(Horde::url($registry->get('webroot', 'horde') . '/services/shares/edit.php?app=kronolith', true), 'share', '@ID@', false)) ?>');
var cancelSubmit = false;
var fields = new Array();
<?php foreach ($my_calendars as $id => $cal): ?>
fields['<?php echo htmlspecialchars($id) ?>'] = new Array(
        "<?php echo rawurlencode(String::convertCharset($cal->get('name'), NLS::getCharset(), 'UTF-8')) ?>",
        "<?php echo rawurlencode(String::convertCharset($cal->get('desc'), NLS::getCharset(), 'UTF-8')) ?>");
<?php endforeach; ?>

function newChoice()
{
    var urlObj = document.getElementById ? document.getElementById('calendar_url') : eval('document.all.calendar_url');
    var subscriptionUrlObj = document.getElementById ? document.getElementById('calendar_subscription_url') : eval('document.all.calendar_subscription_url');
    var share = document.shares.share.options[document.shares.share.selectedIndex].value;
    if (share == -1) {
        document.shares.edit_share.value = '';
        document.shares.reset();
        urlObj.innerHTML = '';
        subscriptionUrlObj = '';
    } else {
        document.shares.edit_share.value = decodeURIComponent(share);
        document.shares.id.value = decodeURIComponent(fields[share][0]);
        document.shares.description.value = decodeURIComponent(fields[share][1]);
        urlObj.innerHTML = "<?php echo Util::addParameter(Horde::applicationUrl('month.php', true, -1), 'display_cal', '') ?>" + share;
        subscriptionUrlObj.innerHTML = "<?php echo Util::addParameter(Horde::applicationUrl('ics.php', true, -1), 'c', '') ?>" + share;
    }
}

function checkSubmit()
{
    if (!document.shares.edit_share.value) {
        return false;
    }

    cancel = cancelSubmit;
    cancelSubmit = false;
<?php if ($browser->getBrowser() == 'msie' && $browser->getMajor() >= 4): ?>
    window.event.returnValue = !cancel;
<?php endif; ?>
    return !cancel;
}

function showHide(calendar)
{
    document.showhide.display_cal.value = calendar.id;
    document.showhide.submit();
}

</script>

<?php if (!$prefs->isLocked('display_cals') && ((count($remote_calendars) || count($GLOBALS['all_calendars']) > 1))): ?>
<form action="calendars.php" method="post" name="showhide">
<?php echo Util::formInput() ?>
<input type="hidden" name="display_cal" />

<h1 class="header">
 <?php echo _("Display Calendars") ?>
</h1>

<div class="headerbox">

<?php if (count($my_calendars)): ?>
<div class="control">
 <strong><?php echo _("My Calendars") ?></strong>
</div>
<?php foreach ($my_calendars as $id => $cal): ?>
<div>
 <input onclick="showHide(this)" type="checkbox" name="<?php echo htmlspecialchars($id) ?>" id="<?php echo htmlspecialchars($id) ?>"<?php echo (in_array($id, $display_calendars) ? ' checked="checked"' : '') ?> /> <label for="<?php echo htmlspecialchars($id) ?>"><?php echo htmlspecialchars($cal->get('name')) ?></label>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if (count($shared_calendars)): ?>
<br class="spacer" />
<div class="control">
 <strong><?php echo _("Shared Calendars") ?></strong>
</div>
<?php foreach ($shared_calendars as $id => $cal): ?>
<div>
 <input onclick="showHide(this)" type="checkbox" name="<?php echo htmlspecialchars($id) ?>" id="<?php echo htmlspecialchars($id) ?>"<?php echo (in_array($id, $display_calendars) ? ' checked="checked"' : '') ?> /> <label for="<?php echo htmlspecialchars($id) ?>"><?php echo htmlspecialchars($cal->get('name')) ?></label>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if (count($remote_calendars)): ?>
<br class="spacer" />
<div class="control">
 <strong><?php echo _("Remote Calendars") ?></strong>
</div>
<?php foreach ($remote_calendars as $id => $cal): ?>
<div>
 <input onclick="showHide(this)" type="checkbox" name="remote_<?php echo htmlspecialchars($cal['url']) ?>" id="remote_<?php echo htmlspecialchars($cal['url']) ?>"<?php echo (in_array($cal['url'], $display_remote_calendars) ? ' checked="checked"' : '') ?> /> <label for="remote_<?php echo htmlspecialchars($cal['url']) ?>"><?php echo htmlspecialchars($cal['name']) ?></label>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>

</form>

<br />
<?php endif; ?>

<?php if ($conf['datatree']['driver'] != 'null'): ?>
<?php if (!$prefs->isLocked('default_share')): ?>
<form method="post" name="create" action="calendars.php" onsubmit="return document.create.id.value != '';">
<?php echo Util::formInput() ?>
<input type="hidden" name="actionID" value="save" />

<h1 class="header">
 <?php echo _("Create Calendar") ?>
</h1>
<table width="100%" cellspacing="0" class="headerbox">
 <tr>
  <td width="15%" class="nowrap" align="right">
   <strong><?php echo _("Name") ?></strong>&nbsp;
  </td>
  <td width="85%">
   <input type="text" name="id" size="30" maxlength="60" />
  </td>
 </tr>
 <tr>
  <td></td>
  <td>
   <input type="submit" name="save" class="button" value="<?php echo _("Create") ?>" />
  </td>
 </tr>
</table>
</form>

<br />
<?php endif; ?>

<form method="post" name="shares" action="calendars.php" onsubmit="return checkSubmit();">
<?php echo Util::formInput() ?>
<input type="hidden" name="actionID" value="save" />
<input type="hidden" name="edit_share" value="" />

<h1 class="header">
 <?php echo _("Edit Calendars") ?>
</h1>

<table width="100%" cellspacing="0" class="headerbox">
 <tr>
  <td width="15%" class="nowrap" align="right">
   <strong><?php echo _("Calendar") ?></strong>&nbsp;
  </td>
  <td width="85%">
   <select name="share" onchange="newChoice()">
    <option value="-1"><?php echo _("Select a calendar to edit") ?></option>
    <?php foreach ($my_calendars as $id => $cal): ?>
    <option value="<?php echo htmlspecialchars($id) ?>"><?php echo htmlspecialchars($cal->get('name')) ?></option>
    <?php endforeach; ?>
   </select>
  </td>
 </tr>
 <tr>
  <td class="nowrap" align="right">
   <strong><?php echo _("Name") ?></strong>&nbsp;
  </td>
  <td>
   <input type="text" name="id" size="30" maxlength="60" />
  </td>
 </tr>
 <tr>
  <td class="nowrap" align="right">
   <strong><?php echo _("Permissions") ?></strong>
  </td>
  <td>
   <input type="button" name="perms" class="button" onclick="if (document.shares.edit_share.value) popup(editURL.replace(/%40ID%40/, document.shares.edit_share.value)); else alert('<?php echo addslashes(_("You need to select a calendar first")) ?>')" value="<?php echo _("Edit") ?>" />
  </td>
 </tr>
 <tr>
  <td align="right" valign="top" class="nowrap">
   <strong><?php echo _("Description") ?></strong>&nbsp;
  </td>
  <td>
   <textarea name="description" rows="4" cols="60"></textarea>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top" class="nowrap">
   <strong><?php echo _("URL") ?></strong>&nbsp;
  </td>
  <td>
   <span id="calendar_url"></span>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top" class="nowrap">
   <strong><?php echo _("Remote Subscription URL") ?></strong>&nbsp;
  </td>
  <td>
   <span id="calendar_subscription_url"></span>
  </td>
 </tr>
 <tr>
  <td></td>
  <td>
   <input type="submit" name="save" class="button" value="<?php echo _("Save") ?>" />
   <input type="submit" name="delete" class="button" onclick="document.shares.actionID.value = 'delete'" value="<?php echo _("Delete") ?>" />
  </td>
 </tr>
</table>

</form>

<br />
<?php endif; ?>

<h1 class="header">
 <?php echo _("My Free/Busy URL") ?>
</h1>
<div class="headerbox">
 <div class="control">
  <?php echo _("Copy the URL below for use wherever you need your Free/Busy URL:") ?>
 </div>
 <p class="text">
  <?php echo Util::addParameter(Horde::applicationUrl('fb.php', true, -1), 'u', Auth::getAuth()) ?>
 </p>
</div>

</div>
