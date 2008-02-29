<script type="text/javascript">
var busyExpanding = false;
function mini_search()
{
    if (document.busyExpanding) {
        return false;
    }
    document.getElementById('turba_minisearch_searching').style.visibility = 'visible';
    document.getElementById('turba_minisearch_iframe').style.display = 'inline';
}
</script>

<form name="turba_minisearch" action="<?php echo Horde::applicationUrl('minisearch.php') ?>" onsubmit="mini_search();" target="turba_minisearch_iframe">
<?php Util::pformInput() ?>
<p class="control"><strong><?php echo _("Quick Search") ?></strong></p>

<input name="search" size="35" />
<input type="submit" class="button" value="<?php echo _("Search") ?>" />
<span id="turba_minisearch_searching" class="inProgress"><?php echo _("Searching...") ?></span>
</form>

<div><iframe id="turba_minisearch_iframe" name="turba_minisearch_iframe" style="width:100%; height:100px; border:none; display:none" frameborder="0" src="<?php echo Horde::applicationUrl('minisearch.php') ?>"></iframe></div>
