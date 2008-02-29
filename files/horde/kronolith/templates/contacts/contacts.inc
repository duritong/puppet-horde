<script type="text/javascript">
<!--

var display = decodeURIComponent("<?php echo (!empty($display) ? rawurlencode($display) : 'name') ?>");

function changeDisplay()
{
    var listDisplay = document.contacts.display;

    if (listDisplay.options[listDisplay.selectedIndex].value != display) {
        display = listDisplay.options[listDisplay.selectedIndex].value;

        s = document.contacts.search_results;
        for(var i = 0; i < s.length; i++) {
            var temp = s.options[i].value;
            if (temp == "") { continue; }

            s.options[i].value = escape(s.options[i].text);
            s.options[i].text = unescape(temp);
        }

        d = document.contacts.selected_addresses;
        for(var i = 0; i < d.length; i++) {
            var temp = d.options[i].value;
            if (temp == "") { continue; }

            d.options[i].value = escape(d.options[i].text);
            d.options[i].text = unescape(temp);
        }
    }
}

function passAddresses()
{
    var list = document.contacts.selected_addresses;
    var sa = '';

    for (var i = 0; i < list.length; i++) {
        var item = list.options[i];
        if (item.value == '') {
            continue;
        }
        sa += item.value + '|' + item.text + '|';
    }
    document.contacts.sa.value = sa;
}

function addAddress()
{
    var s = document.contacts.search_results;
    var d = document.contacts.selected_addresses;

    if (s.selectedIndex < 0) {
        alert('<?php echo addslashes(_("You must select an address first.")) ?>');
        return false;
    } else {
        for (var i = 0; i < s.length; i++) {
            var item = s.options[i];
            if (item.value == "") { continue; }
            if (item.selected) {
                d.options[d.length] = new Option(item.text, item.value);
            }
        }
    }
}

function updateMessage()
{
    if (parent.opener.closed) {
        alert('<?php echo addslashes(_("The Edit Attendees screen is no longer present. Exiting.")) ?>');
        this.close();
        return;
    }

    if (!parent.opener.document.attendeesForm) {
        alert('<?php echo addslashes(_("You can only use this form from the Edit Attendees screen.")) ?>');
        this.close();
        return;
    }

    var list = document.contacts.selected_addresses;
    var field = parent.opener.document.attendeesForm.newAttendees;
    for (var i = 0; i < list.length; i++) {
        if (list.options[i].value == '') {
            continue;
        }
        if (display == 'name') {
            var address = unescape(list.options[i].value);
        } else {
            var address = list.options[i].text;
        }

        if (field.value) {
            field.value = field.value + ', ' + address;
        } else {
            field.value = address;
        }
    }
    parent.opener.document.attendeesForm.submit();
    this.close();
}

function removeAddress()
{
    var list = document.contacts.selected_addresses;
    for (var i = list.length - 1; i > 0; i--) {
        if (list.options[i].selected) {
            list.options[i] = null;
        }
    }
}

window.onload = function() {
    if (typeof document.getElementById != 'undefined') {
        var searchtable = document.getElementById('searchtable');
        if (searchtable && typeof searchtable.offsetWidth != 'undefined') {
            window.innerWidth = searchtable.offsetWidth;
        }
    }
}

//-->
</script>
<form method="post" name="contacts" onsubmit="passAddresses();" action="contacts.php?nocache=<?php echo md5(mt_rand()) ?>">
<input type="hidden" name="sa" />
<?php Util::pformInput() ?>

<div class="header"><?php echo _("Address Book") ?></div>

<table id="searchtable" width="100%">
<tr>
  <td colspan="3" class="light nowrap">
    <strong><?php echo _("Find") ?></strong>
    <input value="<?php echo htmlspecialchars($search) ?>" name="search" style="width:150px" />
<?php if (count($source_list) > 1): ?>
    <strong><?php echo _("in") ?></strong>
    <select name="source">
    <?php foreach ($source_list as $key => $select): ?>
    <option value="<?php echo htmlspecialchars($key) ?>"<?php if ($key == $source) echo ' selected="selected"' ?>><?php echo htmlspecialchars($select) ?></option>
    <?php endforeach; ?>
    </select>
<?php else: ?>
    <input name="source" type="hidden" value="<?php echo htmlspecialchars(key($source_list)) ?>" />
<?php endif; ?>
    <input type="submit" class="button" value="<?php echo _("Search") ?>" />
  </td>
</tr>
<tr>
  <td width="45%">
    <select name="search_results" multiple="multiple" size="10" style="width:100%" onchange="document.contacts.search_results[0].selected=false" ondblclick="addAddress()">
      <option value=""><?php echo _("* Please select address(es) *") ?></option>
      <?php foreach ($addresses as $addr): if ($display == 'email'): ?>
      <option value="<?php echo rawurlencode($addr['name']) ?>"><?php echo htmlspecialchars($addr['email']) ?></option>
      <?php else: ?>
      <option value="<?php echo rawurlencode($addr['email']) ?>"><?php echo htmlspecialchars($addr['name']) ?></option>
      <?php endif; endforeach; ?>
    </select>
  </td>
  <td width="10%" align="center">
    <input type="button" class="button" onclick="addAddress();" value="<?php echo _("Add") ?> &gt;&gt;" /><br /><br />
    <input type="button" class="button" onclick="removeAddress()" value="&lt;&lt; <?php echo _("Remove") ?>" />
  </td>
  <td width="45%">
    <select name="selected_addresses" multiple="multiple" size="10" style="width:100%" onchange="document.contacts.selected_addresses[0].selected=false">
      <option value=""><?php echo _("* Add these by clicking Add *") ?></option>
      <?php foreach ($selected_addresses as $value => $text): ?>
      <option value="<?php echo htmlspecialchars($value) ?>"><?php echo htmlspecialchars($text) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
</tr>
<tr>
  <td colspan="3" class="light nowrap">
    <strong><?php echo _("Display") ?></strong>
    <select name="display" onchange="changeDisplay();">
      <option value="name"<?php echo $display == 'name' ? ' selected="selected"' : '' ?>><?php echo _("Name") ?></option>
      <option value="email"<?php echo $display == 'email' ? ' selected="selected"' : '' ?>><?php echo _("Email Address") ?></option>
    </select>
  </td>
  <td colspan="2">&nbsp;</td>
</tr>
</table>

<div class="header rightAlign">
  <input type="button" class="button" onclick="updateMessage();" value="<?php echo _("Add to attendees") ?>" />
  <input type="button" class="button" onclick="window.close();" value="<?php echo _("Close window") ?>" />
  <?php echo Help::link('kronolith', 'invite-addressbook') ?>
</div>
</form>