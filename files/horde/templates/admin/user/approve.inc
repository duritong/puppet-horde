<h1 class="header">
 <?php echo _("Pending Signups:") ?>
</h1>

<?php $allQueued = $signup->getQueuedSignups(); if (!count($allQueued)): ?>
<div class="text"><em><?php echo _("No pending signups.") ?></em></div>
<?php else: ?>
<table class="striped" width="100%" cellspacing="0">
 <tr class="item">
  <th>&nbsp;</th>
  <th>&nbsp;</th>
  <th><?php echo _("Username") ?></th>
  <th width="100%"><?php echo _("Date Received") ?></th>
 </tr>
<?php foreach ($allQueued as $signup): if ($signup->getName() != DATATREE_ROOT): $data = $signup->getData(); ?>
 <tr>
  <td>
   <form method="post" action="<?php echo Horde::selfUrl() ?>">
    <input type="hidden" name="user_name" value="<?php echo $signup->getName() ?>" />
    <input type="hidden" name="form" value="approve_f" />
    <input type="submit" class="button" value="<?php echo _("Approve") ?>" />
   </form>
  </td>
  <td>
   <form method="post" action="<?php echo Horde::selfUrl() ?>">
    <input type="hidden" name="user_name" value="<?php echo $signup->getName() ?>" />
    <input type="hidden" name="form" value="removequeued_f" />
    <input type="submit" class="button" value="<?php echo _("Remove") ?>" />
   </form>
  </td>
  <td><?php echo htmlspecialchars($signup->getName()) ?></td>
  <td><?php echo date('Y-M-d H:i:s', $data['dateReceived']) ?></td>
 </tr>
<?php endif; ?>
<?php endforeach; ?>
</table>
<?php endif; ?>
<br class="spacer" />
