<div class="header" style="text-align:center">
<?php
if (!$print_view) echo Horde::link($this->link(-1), _("Previous day")) . Horde::img('nav/left.png', '<', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
echo $this->getTime($prefs->getValue('date_format'));
if (!$print_view) echo Horde::link($this->link(1), _("Next day")) . Horde::img('nav/right.png', '>', '', $GLOBALS['registry']->getImageDir('horde')) . '</a>';
?>
</div>

<table cellspacing="0" width="100%" class="striped">
