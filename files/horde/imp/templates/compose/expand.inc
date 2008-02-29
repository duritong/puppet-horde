<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<html>
<head><title></title></head>
<body>
<script type="text/javascript">
<!--
<?php if (isset($address)): ?>
<?php if (is_a($address, 'PEAR_Error')): ?>
window.parent.document.getElementById('expanding<?php echo htmlspecialchars($field_name) ?>').innerHTML='<?php echo addslashes(sprintf(_("The field \"%s\" can't be expanded."), htmlspecialchars($field_name))) ?>';
<?php elseif (is_array($address)): ?>
window.parent.document.getElementById('expanding<?php echo htmlspecialchars($field_name) ?>').innerHTML='<?php echo addslashes(sprintf(_("More than one address found for field \"%s\"."), htmlspecialchars($field_name))) ?>';
<?php else: ?>
window.setTimeout('var status = window.parent.document.getElementById(\'expanding<?php echo htmlspecialchars($field_name) ?>\'); status.style.visibility = \'hidden\';window.parent.document.<?php echo htmlspecialchars($form_name . '.' . $field_name) ?>.value = \'<?php echo addslashes(addslashes($address)) ?>\';', 10);
<?php endif; ?>
<?php endif; ?>
window.parent.busyExpanding = false;
//-->
</script>
</body>
</html>
