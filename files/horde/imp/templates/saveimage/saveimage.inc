<form method="post" name="save_image_dialog" action="<?php echo Horde::applicationUrl('saveimage.php') ?>">
<input type="hidden" name="actionID" value="save_image" />
<input type="hidden" name="id" value="<?php echo $id ?>" />
<input type="hidden" name="index" value="<?php echo $index ?>" />


<h1 class="header">
  <?php echo Horde::img('mime/image.png', _("Image"), null, $registry->getImageDir('horde')) ?>
  <?php echo _("Select the gallery to save the image in.") ?>
</h1>
<p class="item">
   <select id="gallery" name="gallery">
<?php echo $gallerylist ?>
   </select>
</p>

<div class="nowrap">
  <input type="submit" name="submit" class="button" value="<?php echo _("Submit") ?>" />&nbsp;
  <input type="button" class="button" onclick="window.close();" value="<?php echo _("Cancel") ?>" />
</div>

</form>
</body>
</html>
