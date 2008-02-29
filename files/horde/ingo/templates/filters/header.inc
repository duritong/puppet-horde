<script type="text/javascript">
addEvent(window, 'load', function toggleRow() {
    if (!document.getElementsByTagName) {
        return;
    }

    tables = document.getElementsByTagName('table');
    for (var i = 0; i < tables.length; ++i) {
        if (tables[i].className.indexOf('striped') != -1) {
            var trs = tables[i].getElementsByTagName('tr');
            for (var j = 0; j < trs.length; j++) {
                trs[j].onmouseover = function() {
                    this.className += ' selected'; return false
                }
                trs[j].onmouseout = function() {
                    this.className = this.className.replace('selected', ''); return false
                }
            }
        }
    }
});

function moveFromTo(from, to, upurl, downurl)
{
    var steps = to - from;
    if (steps < 0) {
        window.location = upurl + '&steps=' + -steps;
    } else if (steps > 0) {
        window.location = downurl + '&steps=' + steps;
    }
    return true;
}
</script>
<form method="post" name="filters" action="<?php echo Horde::applicationUrl('filters.php') ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="actionID" value="" />
<div class="header">
 <ul>
  <li><?php echo Help::link('ingo', 'filters_rules') ?></li>
 </ul>
 <?php echo _("Existing Rules") ?>
</div>
<table width="100%" cellspacing="0" class="striped">
<thead>
 <tr class="item">
  <th width="80"><?php echo _("Edit") ?></th>
  <th><?php echo _("Rule") ?></th>
  <th width="50"><?php echo _("Enabled") ?></th>
  <th width="120" colspan="2"><?php echo _("Move") ?></th>
 </tr>
</thead>
<tbody>
