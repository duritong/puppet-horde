<script type="text/javascript">
<!--

var busyExpanding = false;
var searchFields  = new Array();
var searchValues  = new Array();

function expandField(field)
{
<?php if ($GLOBALS['prefs']->getValue('auto_expand')): ?>
    if (document.frames) {
        var iframe = document.frames['autoexpand'];
    } else if (document.getElementById) {
        var iframe = document.getElementById('autoexpand');
    } else {
        return;
    }
    if (!iframe) {
        return;
    }
    if (!field) {
        field = new Object();
        field.name  = searchFields.slice(0, 1);
        field.value = searchValues.slice(0, 1);
    }
    if (busyExpanding) {
       searchFields[searchFields.length] = field.name;
       searchValues[searchValues.length] = field.value;
       window.setTimeout('expandField()', 500);
       return;
    }
    busyExpanding = true;
    var doc;
    if (iframe.contentDocument) {
        doc = iframe.contentDocument;
    } else if (iframe.contentWindow) {
        doc = iframe.contentWindow.document;
    } else if (iframe.document) {
        doc = iframe.document;
    } else {
        return true;
    }
    var url = '<?php echo Horde::url(Util::addParameter('expand.php', 'actionID', 'expand_addresses'), true) ?>';
    url += '&field_name=' + _escape(field.name);
    url += '&field_value=' + _escape(field.value);
    url += '&form_name=' + _escape(field.form.name);

    var status = document.getElementById('expanding' + field.name);
    status.innerHTML = '<?php echo _("Expanding") ?>';
    status.style.visibility = 'visible';

    doc.location.replace(url);
<?php endif; ?>
}

function _escape(value)
{
    if (typeof encodeURIComponent == 'undefined') {
        return escape(value);
    } else {
        return encodeURIComponent(value);
    }
}

// -->
</script>
