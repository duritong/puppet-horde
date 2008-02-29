<script type="text/javascript">
<!--

var selfUrl = '<?php echo Util::addParameter(Horde::selfUrl(false, true), 'display_cal', '', false); ?>';

function open_calendar_search()
{
    var name = 'calendar_search_window';
    name = window.open('<?php echo Horde::applicationUrl('calendar_search.php', true) ?>', name, 'toolbar=no,location=no,status=yes,scrollbars=yes,resizable=yes,width=300,height=200');
    if (!eval('name.opener')) {
        name.opener = self;
    }
}

// -->
</script>
