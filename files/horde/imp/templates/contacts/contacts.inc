<script type="text/javascript">

function adjustSize()
{
    if (document.getElementById) {
        var table = document.getElementById('contactstable');
        if (table.offsetWidth &&
            table.offsetWidth > 550) {
            window.resizeTo(table.offsetWidth, 300);
        }
    }
}

var display = "<?php echo (!empty($display) ? $display : "name") ?>";

function changeDisplay()
{
    var listDisplay = document.contacts.display;

    if (listDisplay.options[listDisplay.selectedIndex].value != display) {
        display = listDisplay.options[listDisplay.selectedIndex].value;

        s = document.contacts.search_results;
        for (var i = 0; i < s.length; i++) {
            var temp = s.options[i].value;
            if (temp == "") { continue; }

            s.options[i].value = encodeURIComponent(s.options[i].text);
            s.options[i].text = decodeURIComponent(temp);
        }

        d = document.contacts.selected_addresses;
        for (var i = 0; i < d.length; i++) {
            var temp = d.options[i].value;
            if (temp == "") { continue; }

            d.options[i].value = encodeURIComponent(d.options[i].text);
            d.options[i].text = decodeURIComponent(temp);
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

function sameOption(f, item, itemj)
{
    var text = f + ": " + item.text;
    var value = f + ": " + item.value;
    var textj = itemj.text;
    var valuej = itemj.value;

    // Catch exception with NS 7.1
    try {
        return ((text == textj) || (decodeURIComponent(text) == decodeURIComponent(textj))) &&
            ((value == valuej) || (decodeURIComponent(value) == decodeURIComponent(valuej)));
    } catch(e) {
        return (text == textj && value == valuej);
    }
}

function addAddress(f)
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
                for (var j = 0; j < d.length; j++) {
                    var itemj = d.options[j];
                    if (sameOption(f, item, itemj)) {
                        return false;
                    }
                }
                d.options[d.length] = new Option(f + ': ' + item.text, f + ': ' + item.value);
            }
        }
    }
}

function updateMessage()
{
    if (parent.opener.closed) {
        alert('<?php echo addslashes(_("The message being composed has been closed. Exiting.")) ?>');
        this.close();
        return;
    }

    if (!parent.opener.document.<?php echo $formname ?>) {
        alert('<?php echo addslashes(_("This window must be called from a compose window.")) ?>');
        this.close();
        return;
    }

    var list = document.contacts.selected_addresses;
    for (var i = 1; i < list.length; i++) {
        if (display == "name") {
            var address = decodeURIComponent(list.options[i].value);
            var fullname = list.options[i].text;
        } else {
            var address = list.options[i].text;
            var fullname = decodeURIComponent(list.options[i].value);
        }
        var pos = address.indexOf(':');
        var f = address.substring(0, pos);
        var field = null;

        fullname = fullname.substring(pos + 2, fullname.length);
        fullname = fullname.replace(/\\/g, '\\\\');
        fullname = fullname.replace(/"/g, '\\"');
        if (address.substring(pos + 2, address.length).indexOf(',') > 0) {
            address = fullname + ': ' + address.substring(pos + 2, address.length) + ';';
        } else {
            address = '"' + fullname + '" <' + address.substring(pos + 2, address.length) + '>';
        }
        if (f == 'to') {
            field = parent.opener.document.<?php echo $formname ?>.to;
<?php if (!$to_only): ?>
        } else if (f == 'cc') {
            field = parent.opener.document.<?php echo $formname ?>.cc;
        } else if (f == 'bcc') {
            field = parent.opener.document.<?php echo $formname ?>.bcc;
<?php endif; ?>
        } else {
            continue;
        }

        // Only delimit with commas if at least the 2nd entry and not after
        // a group address.
        if (field.value) {
            if (field.value.substr(field.value.length - 1, 1) != ';') {
                field.value = field.value + ',';
            }
            field.value = field.value + ' ' + address;
        } else {
            field.value = address;
        }
    }
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

</script>
<form method="post" name="contacts" onsubmit="passAddresses();" action="<?php echo Horde::url(Util::addParameter(Horde::applicationUrl('contacts.php'), 'uniq', base_convert(microtime(), 10, 36))) ?>">
<input type="hidden" name="formname" value="<?php echo $formname ?>" />
<input type="hidden" name="searched" value="1" />
<input type="hidden" name="sa" />
<?php Util::pformInput() ?>

<h1 class="header">
  <?php echo _("Address Book") ?>
</h1>

<div id="contactstable" class="headerbox item">

 <p class="control">
   <strong><?php echo _("Find") ?></strong>
   <input value="<?php echo htmlspecialchars($search) ?>" name="search" style="width:150px">

<?php if (count($source_list) > 1): ?>
   <strong><?php echo _("from") ?></strong>
   <select name="source">
   <?php foreach ($source_list as $key => $select): ?>
    <option value="<?php echo $key ?>"<?php if ($key == $source) echo ' selected="selected"' ?>><?php echo htmlspecialchars($select) ?></option>
   <?php endforeach; ?>
   </select>
<?php else: ?>
   <input name="source" type="hidden" value="<?php echo key($source_list) ?>" />
<?php endif; ?>
   <input type="submit" class="button" value="<?php echo _("Search") ?>" />
 </p>

 <table width="100%" cellspacing="2">
  <tr>
   <td width="33%">
    <select name="search_results" multiple="multiple" size="10" style="width:200px" onchange="document.contacts.search_results[0].selected=false"<?php echo $select_event ?>>
     <option value=""><?php echo _("* Please select address(es) *") ?></option>
<?php
foreach ($addresses as $addr) {
    if (!empty($addr['email'])) {
        if ($display == 'email') {
?>
     <option value="<?php echo rawurlencode(String::convertCharset($addr['name'], NLS::getCharset(), 'UTF-8')) ?>"<?php echo $option_event ?>><?php echo $addr['email'] ?></option>
<?php } else { ?>
     <option value="<?php echo rawurlencode(String::convertCharset($addr['email'], NLS::getCharset(), 'UTF-8')) ?>"<?php echo $option_event ?>><?php echo $addr['name'] ?></option>
<?php }}} ?>
    </select>
   </td>
   <td width="33%" style="text-align:center">
    <input type="button" class="button" style="width:60px" onclick="addAddress('to');" value="<?php echo _("To") . " &gt;&gt;" ?>" /><br />&nbsp;<br />
<?php if (!$to_only): ?>
    <input type="button" class="button" style="width:60px" onclick="addAddress('cc');" value="<?php echo _("Cc") . " &gt;&gt;" ?>" /><br />&nbsp;<br />
    <input type="button" class="button" style="width:60px" onclick="addAddress('bcc');" value="<?php echo _("Bcc") . " &gt;&gt;" ?>" />
   </td>
<?php endif; ?>
   <td width="33%">
    <select name="selected_addresses" multiple="multiple" size="10" style="width:200px" onchange="document.contacts.selected_addresses[0].selected = false;">
     <option value=""><?php echo _("* Add these by clicking OK *") ?></option>
<?php foreach ($selected_addresses as $value => $text): ?>
     <option value="<?php echo $value ?>"><?php echo $text ?></option>
<?php endforeach; ?>
    </select>
   </td>
  </tr>
  <tr>
   <td class="nowrap">
    <strong><?php echo _("Display") ?></strong>
    <select name="display" onchange="changeDisplay();">
     <option value="name"<?php echo $display == 'name' ? ' selected="selected"' : '' ?>><?php echo _("Name") ?></option>
     <option value="email"<?php echo $display == 'email' ? ' selected="selected"' : '' ?>><?php echo _("Email Address") ?></option>
    </select>
   </td>
   <td>&nbsp;</td>
   <td>
    <input type="button" class="button" onclick="removeAddress()" value="<?php echo _("Delete") ?>" />
   </td>
  </tr>
 </table>

</div>

<br class="spacer" />

<div>
  <input type="button" class="button" onclick="updateMessage();" value="<?php echo _("OK") ?>" />
  <input type="button" class="button" onclick="window.close();" value="<?php echo _("Cancel") ?>" />
</div>

</form>
<script type="text/javascript">
<!--
adjustSize();
//-->
</script>
