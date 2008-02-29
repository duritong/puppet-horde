<script type="text/javascript">
<!--

function toggleAll(checked)
{
    for (i = 0; i < document.search.elements.length; i++) {
        if (document.search.elements[i].name.indexOf('search_folders') >= 0) {
            document.search.elements[i].checked = checked;
        }
    }
}

function dateCheck(field)
{
    if (document.search.elements['search_' + field + '_month'].selectedIndex == 0)
        document.search.elements['search_' + field + '_month'].selectedIndex = "<?php echo date('m') ?>";

    if (document.search.elements['search_' + field + '_day'].selectedIndex == 0)
        document.search.elements['search_' + field + '_day'].selectedIndex = "<?php echo date('d') ?>";

    if (document.search.elements['search_' + field + '_year'].value == "")
        document.search.elements['search_' + field + '_year'].value = "<?php echo date('Y') ?>";
}

function formCheck()
{
<?php if (empty($search['mbox'])): ?>
    var num_selected = 0;

    for (i = 0; i < document.search.elements.length; i++) {
        if (document.search.elements[i].id.indexOf('preselected_folders') >= 0) {
            num_selected++;
        } else if (document.search.elements[i].name.indexOf('search_folders') >= 0) {
            if (document.search.elements[i].checked) {
                num_selected++;
            }
        }
    }

    if (num_selected == 0) {
        alert('<?php echo addslashes(_("Please select at least one folder to search.")) ?>');
        document.search.search_from.focus();
        return false;
    }
<?php endif; ?>

    document.search.actionID.value = 'do_search';
    return true;
}

// -->
</script>
