<?php
/**
 * $Horde: horde/admin/sqlshell.php,v 1.18.10.10 2007/01/02 13:54:03 jan Exp $
 *
 * Copyright 1999-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

@define('HORDE_BASE', dirname(__FILE__) . '/..');
require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/Menu.php';
require_once 'Horde/Help.php';
require_once 'DB.php';

if (!Auth::isAdmin()) {
    Horde::fatal('Forbidden.', __FILE__, __LINE__);
}

$title = _("SQL Shell");
Horde::addScriptFile('stripe.js', 'horde', true);
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/admin/common-header.inc';

?>
<div style="padding:10px">
<form name="sqlshell" action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post">
<?php Util::pformInput() ?>

<?php

$dbh = &DB::connect($conf['sql']);
if (is_a($dbh, 'PEAR_Error')) {
    Horde::fatal($dbh, __FILE__, __LINE__);
}
$dbh->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);

if (Util::getFormData('list-tables')) {
    $description = 'LIST TABLES';
    $result = $dbh->getListOf('tables');
} elseif (Util::getFormData('list-dbs')) {
    $description = 'LIST DATABASES';
    $result = $dbh->getListOf('databases');
} elseif ($command = trim(Util::getFormData('sql'))) {
    // Keep a cache of prior queries for convenience.
    if (!isset($_SESSION['_sql_query_cache'])) {
        $_SESSION['_sql_query_cache'] = array();
    }
    if (($key = array_search($command, $_SESSION['_sql_query_cache'])) !== false) {
        unset($_SESSION['_sql_query_cache'][$key]);
    }
    array_unshift($_SESSION['_sql_query_cache'], $command);
    while (count($_SESSION['_sql_query_cache']) > 20) {
        array_pop($_SESSION['_sql_query_cache']);
    }

    // Parse out the query results.
    $result = $dbh->query(String::convertCharset($command, NLS::getCharset(), $conf['sql']['charset']));
}

if (isset($result)) {
    if (isset($command)) {
        echo '<h1 class="header">' . _("Query") . '</h1><br /><pre class="text">' . htmlspecialchars($command) . '</pre>';
    }

    echo '<h1 class="header">' . _("Results") . '</h1><br />';

    if (is_a($result, 'PEAR_Error')) {
        echo '<pre class="text">'; var_dump($result); echo '</pre>';
    } else {
        if (is_object($result)) {
            echo '<table cellspacing="1" class="item striped">';
            $first = true;
            $i = 0;
            while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
                if ($first) {
                    echo '<tr>';
                    foreach ($row as $key => $val) {
                        echo '<th align="left">' . (empty($key) ? '&nbsp;' : htmlspecialchars(String::convertCharset($key, $conf['sql']['charset']))) . '</th>';
                    }
                    echo '</tr>';
                    $first = false;
                }
                echo '<tr>';
                foreach ($row as $val) {
                    echo '<td class="fixed">' . (empty($val) ? '&nbsp;' : htmlspecialchars(String::convertCharset($val, $conf['sql']['charset']))) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        } elseif (is_array($result)) {
            echo '<table cellspacing="1" class="item striped">';
            $first = true;
            $i = 0;
            foreach ($result as $val) {
                if ($first) {
                    echo '<tr><th align="left">' . (isset($description) ? htmlspecialchars($description) : '&nbsp;') . '</th></tr>';
                    $first = false;
                }
                echo '<tr><td class="fixed">' . (empty($val) ? '&nbsp;' : htmlspecialchars(String::convertCharset($val, $conf['sql']['charset']))) . '</td></tr>';
            }
            echo '</table>';
        } else {
            echo '<strong>' . _("Success") . '</strong>';
        }
    }

    echo '<br />';
}
?>

<?php if (isset($_SESSION['_sql_query_cache']) &&
          count($_SESSION['_sql_query_cache'])): ?>
  <select name="query_cache" onchange="document.sqlshell.sql.value = document.sqlshell.query_cache[document.sqlshell.query_cache.selectedIndex].value;">
  <?php foreach ($_SESSION['_sql_query_cache'] as $query): ?>
    <option value="<?php echo htmlspecialchars($query) ?>"><?php echo htmlspecialchars($query) ?></option>
  <?php endforeach; ?>
  </select>
  <input type="button" value="<?php echo _("Paste") ?>" class="button" onclick="document.sqlshell.sql.value = document.sqlshell.query_cache[document.sqlshell.query_cache.selectedIndex].value;" />
  <input type="button" value="<?php echo _("Run") ?>" class="button" onclick="document.sqlshell.sql.value = document.sqlshell.query_cache[document.sqlshell.query_cache.selectedIndex].value; document.sqlshell.submit();" />
  <br />
<?php endif; ?>

<textarea class="fixed" name="sql" rows="10" cols="60">
<?php if (!empty($command)) echo htmlspecialchars($command) ?></textarea>
<br />
<input type="submit" class="button" value="<?php echo _("Execute") ?>" />
<input type="button" class="button" value="<?php echo _("Clear Query") ?>" onclick="document.sqlshell.sql.value=''" />
<?php if (!empty($command)): ?>
<input type="reset" class="button" value="<?php echo _("Restore Last Query") ?>" />
<?php endif; ?>
<?php if ($dbh->getSpecialQuery('tables') !== null): ?><input type="submit" class="button" name="list-tables" value="<?php echo _("List Tables") ?>" /> <?php endif; ?>
<?php if ($dbh->getSpecialQuery('databases') !== null): ?><input type="submit" class="button" name="list-dbs" value="<?php echo _("List Databases") ?>" /> <?php endif; ?>
<?php echo Help::link('admin', 'admin-sqlshell') ?>

</form>
</div>
<?php

require HORDE_TEMPLATES . '/common-footer.inc';
