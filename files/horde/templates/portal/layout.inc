<?php if ($layout->isChanged($row, $col)): ?>
<a id="block"></a>
<?php endif; ?>

<table cellspacing="0" class="header" width="100%"><tr>
  <td>
    <?php echo $registry->get('name', $blockInfo['app']) . ': ' . $blocks->getName($blockInfo['app'], $blockInfo['block']) ?>
  </td>
  <td class="rightAlign">
    &nbsp;<?php echo $edit_link ?>
    <?php if ($layout->isRemovable($row, $col)) echo Horde::link($layout->getActionUrl('removeBlock', $row, $col), _("Remove Block"), '', '', '', _("Remove Block")) . Horde::img('close.png', _("Remove Block"), 'align="middle"') . '</a>' ?>
  </td>
</tr></table>

<table cellspacing="0" class="nopadding" width="100%">
  <tr>
    <td colspan="3" align="center">
      <?php if ($row > 0) echo $layout->getControl('expand/up', $row, $col) ?>&nbsp;
      <?php if ($layout->getHeight($row, $col) > 1) echo $layout->getControl('shrink/up', $row, $col) ?>
    </td>
  </tr>
  <tr>
    <td class="leftAlign" width="11">
      <?php if ($col > 0) echo $layout->getControl('expand/left', $row, $col) ?>
      <?php if ($layout->getWidth($row, $col) > 1) echo $layout->getControl('shrink/left', $row, $col) ?>
    </td>
    <td align="center">
      <table>
        <tr>
          <td width="11">&nbsp;</td>
          <td width="11"><?php echo $row > 0 ? $layout->getControl('move/up', $row, $col) : '&nbsp;' ?></td>
          <td width="11">&nbsp;</td>
        </tr>
        <tr>
          <td><?php echo $col > 0 ? $layout->getControl('move/left', $row, $col) : '&nbsp;' ?></td>
          <td>&nbsp;</td>
          <td><?php echo $layout->getControl('move/right', $row, $col) ?></td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td><?php echo $layout->getControl('move/down', $row, $col) ?></td>
          <td>&nbsp;</td>
        </tr>
      </table>
    </td>
    <td class="rightAlign" width="11">
      <?php if ($layout->getWidth($row, $col) > 1) echo $layout->getControl('shrink/right', $row, $col) ?>
      <?php echo $layout->getControl('expand/right', $row, $col) ?>
    </td>
  </tr>
  <tr>
    <td colspan="3" align="center">
        <?php if ($layout->getHeight($row, $col) > 1) echo $layout->getControl('shrink/down', $row, $col) ?>
        <?php echo $layout->getControl('expand/down', $row, $col) ?>
    </td>
  </tr>
</table>

<?php if ($isEditing): ?>
<a id="block"></a>
<table class="nopadding" cellspacing="0" width="100%">
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td valign="middle" class="leftAlign" width="100%">
      <form action="<?php echo Horde::selfUrl() ?>#block" method="post" name="blockform">
        <?php Util::pformInput() ?>
        <input type="hidden" name="row" value="<?php echo $row ?>" />
        <input type="hidden" name="col" value="<?php echo $col ?>" />
        <input type="hidden" name="action" value="save" />
        <table cellspacing="0" width="100%">
          <tr>
            <td class="smallheader" colspan="2"><?php echo _("Block Type") ?></td>
          </tr>
          <tr>
            <td colspan="2">
              <?php echo $blocks->getBlocksWidget($blockInfo['app'], $blockInfo['block'], true) ?>
            </td>
          </tr>
<?php
if ($blocks->isEditable($blockInfo['app'], $blockInfo['block'])):
    $block = &$layout->getBlock($row, $col);
    $defaults = array();
    foreach ($block->_params as $key => $val) {
        $defaults[$key] = $val;
    }
?>
          <tr>
            <td colspan="2" class="smallheader"><?php echo _("Block Settings") ?></td>
          </tr>
<?php $i = 0; foreach ($blocks->getParams($blockInfo['app'], $blockInfo['block']) as $id): $i++; ?>
          <tr>
            <td class="<?php echo ($i % 2) ? 'text' : 'item0' ?> rightAlign" valign="top"><?php echo $blocks->getParamName($blockInfo['app'], $blockInfo['block'], $id) ?>:&nbsp;</td>
            <td class="<?php echo ($i % 2) ? 'text' : 'item0' ?>" valign="top"><?php echo $blocks->getOptionsWidget($blockInfo['app'], $blockInfo['block'], $id, $defaults) ?></td>
          </tr>
<?php endforeach; endif; ?>
        </table>
        <input type="submit" class="button" value="<?php echo _("Save") ?>" />
      </form>
    </td>
  </tr>
</table>
<?php endif; ?>
