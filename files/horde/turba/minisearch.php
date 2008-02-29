<?php
/**
 * $Horde: turba/minisearch.php,v 1.20.4.11 2007/04/05 13:52:16 jan Exp $
 *
 * Copyright 2000-2007 Charles J. Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('TURBA_BASE', dirname(__FILE__));
require_once TURBA_BASE . '/lib/base.php';

$search = Util::getFormData('search');
$results = array();

// Make sure we have a source.
$source = Util::getFormData('source', Turba::getDefaultAddressBook());
if (!isset($cfgSources[$source])) {
    reset($cfgSources);
    $source = key($cfgSources);
}

// Do the search if we have one.
if (!is_null($search)) {
    $driver = &Turba_Driver::singleton($source);
    if (!is_a($driver, 'PEAR_Error')) {
        $criteria['name'] = trim($search);
        $res = $driver->search($criteria);
        if (is_a($res, 'Turba_List')) {
            while ($ob = $res->next()) {
                if ($ob->isGroup()) {
                    continue;
                }
                $att = $ob->getAttributes();
                foreach ($att as $key => $value) {
                    if (!empty($attributes[$key]['type']) &&
                        $attributes[$key]['type'] == 'email') {
                        $results[] = array('name' => $ob->getValue('name'),
                                           'email' => $value,
                                           'source' => $source,
                                           'key' => $att['__key']
                                           );
                        break;
                    }
                }
            }
        }
    }
}

$bodyClass = 'summary';
require TURBA_TEMPLATES . '/common-header.inc';

?>
<script type="text/javascript">
<!--
window.setTimeout('var status = window.parent.document.getElementById(\'turba_minisearch_searching\'); status.style.visibility = \'hidden\'', 10);
window.parent.busyExpanding = false;
//-->
</script>
<?php
if (count($results)) {
    echo '<ul style="margin-top:4px">';
    foreach ($results as $contact) {
        $url = Util::addParameter('display.php', array('source' => $contact['source'],
                                                       'key' => $contact['key']));

        $mail_link = $GLOBALS['registry']->call('mail/compose', array(array('to' => addslashes($contact['email']))));
        if (is_a($mail_link, 'PEAR_Error')) {
            $mail_link = 'mailto:' . urlencode($contact['email']);
            $target = '';
        } else {
            $target = strpos($mail_link, 'javascript:') === 0 ? '' : ' target="_parent"';
        }

        echo '<li class="linedRow">' .
            Horde::link(Horde::applicationUrl($url), _("View Contact"), '', '_parent') . Horde::img('contact.png', _("View Contact")) . '</a> ' .
            '<a href="' . $mail_link . '"' . $target . '>' . htmlspecialchars($contact['name'] . ' <' . $contact['email'] . '>') . '</a></li>';
    }
    echo '</ul>';
} elseif (!is_null($search)) {
    echo _("No contacts found");
}
?>
</body>
</html>
