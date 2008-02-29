<br class="spacer" />
<?php
if (!$prefs->isLocked('search_abook') && $registry->hasMethod('contacts/sources')) {
    $address_books = $registry->call('contacts/sources');
    if (!is_a($address_books, 'PEAR_Error')) {
        $address_bookSaved = unserialize($prefs->getValue('search_abook'));
        $address_bookSelect = '';

        foreach ($address_books as $address_book => $description) {
            if (!is_bool(array_search($address_book, $address_bookSaved))) {
                $address_bookSelect .= '<option value="' . htmlspecialchars($address_book) . '" selected>' . htmlspecialchars($description) . '</option>';
            } else {
                $address_bookSelect .= '<option value="' . htmlspecialchars($address_book) . '">' . htmlspecialchars($description) . '</option>';
            }
        }
    }

    if (isset($address_bookSelect)) {
        echo _("Choose the address books to search for free/busy URLs:") . '<br />';
        echo '<select multiple="multiple" name="search_abook[]">' . $address_bookSelect . '</select><br /><br class="spacer" />';
    }
}
