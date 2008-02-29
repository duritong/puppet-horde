<br class="spacer" />
<?php
if (!$prefs->isLocked('fb_cals')) {
    $fb_cals = Kronolith::ListCalendars();
    $fb_calsSaved = unserialize($prefs->getValue('fb_cals'));
    $fb_calSelect = '';
    foreach ($fb_cals as $fb_cal => $cal) {
        if (!is_bool(array_search($fb_cal, $fb_calsSaved))) {
            $fb_calSelect .= '<option value="' . htmlspecialchars($fb_cal) . '" selected="selected">' . htmlspecialchars($cal->get('name')) . '</option>';
        } else {
            $fb_calSelect .= '<option value="' . htmlspecialchars($fb_cal) . '">' . htmlspecialchars($cal->get('name')) . '</option>';
        }
    }

    if ($fb_calSelect) {
        echo _("Choose the calendars to include in your free/busy information:") . '<br />' .
            '<select multiple="multiple" name="fb_cals[]">' . $fb_calSelect . '</select><br /><br class="spacer" />';
    }
}
