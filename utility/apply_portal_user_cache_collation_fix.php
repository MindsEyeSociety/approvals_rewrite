<?php
/**
 * apply_portal_user_cache_collation_fix.php — One-time maintenance script that
 * fixes the collation mismatch on `portal_user_cache.ww_number`.
 *
 * portal_user_cache (sql/add_portal_user_cache.sql) was created with
 * CHARSET=utf8mb4 but no explicit COLLATE, so it silently picked up MySQL 8's
 * server default utf8mb4_0900_ai_ci. The 2026-09-01 latin1->utf8mb4 migration
 * (sql/migrate_latin1_to_utf8mb4.sql) converted `users` to utf8mb4_unicode_ci
 * but skipped portal_user_cache, leaving `users.ww_number` and
 * `portal_user_cache.ww_number` on different collations. Every `=` join
 * between them (services/UserService.class.php lines 23 and 49) then throws
 * an uncaught mysqli_sql_exception: "Illegal mix of collations
 * (utf8mb4_unicode_ci,IMPLICIT) and (utf8mb4_0900_ai_ci,IMPLICIT)" — an
 * uncaught exception on every load of UserList.php.
 *
 * This script executes the ALTER TABLE committed in
 * sql/fix_portal_user_cache_collation.sql so the fix SQL lives in exactly one
 * place, printing the ww_number collations on both tables before and after so
 * the fix is independently verifiable rather than assumed. It is idempotent —
 * re-running it after the fix has already been applied re-runs the (now
 * no-op) ALTER and reports matching collations again — so it is safe to
 * re-run if in doubt. CLI-only: refuses to run under a web SAPI so it cannot
 * be triggered over HTTP.
 *
 * Per this repo's convention for one-off maintenance scripts (see
 * utility/cleanup_org_encoding.php), this script stays in the repo after use
 * as a record of the fix rather than being deleted.
 *
 * Usage (one-off, as user nta):
 *   php /var/www/approvals_rewrite/utility/apply_portal_user_cache_collation_fix.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Forbidden: CLI only\n");
}

// settings.inc references $_SERVER['SERVER_NAME']; provide it so CLI runs are warning-free.
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'cli';

$appRoot = dirname(__DIR__);
chdir($appRoot);

include_once($appRoot . '/include/settings.inc');
include_once($appRoot . '/include/ResultSet.class.php');
include_once($appRoot . '/include/Database.class.php');

$sqlFile = $appRoot . '/sql/fix_portal_user_cache_collation.sql';
$sqlRaw = file_get_contents($sqlFile);
if ($sqlRaw === false || trim($sqlRaw) === '') {
    fwrite(STDERR, date('c') . " apply_portal_user_cache_collation_fix: cannot read $sqlFile\n");
    exit(1);
}

// Strip the leading `--` comment lines, keeping just the SQL statement.
$lines = explode("\n", $sqlRaw);
$sqlLines = array_filter($lines, function ($line) {
    return trim($line) !== '' && substr(ltrim($line), 0, 2) !== '--';
});
$sql = trim(implode("\n", $sqlLines));
if ($sql === '') {
    fwrite(STDERR, date('c') . " apply_portal_user_cache_collation_fix: no SQL statement found in $sqlFile\n");
    exit(1);
}

$db = new Database(
    $SETTINGS['APPROVALS_SERVER'],
    $SETTINGS['APPROVALS_USERNAME'],
    $SETTINGS['APPROVALS_PASSWORD'],
    $SETTINGS['APPROVALS_DATABASE']
);
$h = $db->dbh;

/**
 * Fetch the COLLATION_NAME of a given table/column from information_schema.
 *
 * @param resource|mysqli $h Open mysqli connection handle to query against.
 * @param string $table Name of the table the column belongs to.
 * @param string $column Name of the column to look up.
 * @return string|null The column's collation (e.g. "utf8mb4_unicode_ci"), or
 *                      null if the table/column doesn't exist.
 */
function get_column_collation($h, $table, $column) {
    $table = mysqli_real_escape_string($h, $table);
    $column = mysqli_real_escape_string($h, $column);
    $result = mysqli_query($h,
        "SELECT COLLATION_NAME FROM information_schema.columns " .
        "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'");
    if (!$result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    return $row ? $row['COLLATION_NAME'] : null;
}

$usersBefore = get_column_collation($h, 'users', 'ww_number');
$cacheBefore = get_column_collation($h, 'portal_user_cache', 'ww_number');
printf("%s apply_portal_user_cache_collation_fix: BEFORE users.ww_number=%s portal_user_cache.ww_number=%s\n",
    date('c'), $usersBefore, $cacheBefore);

if (!mysqli_query($h, $sql)) {
    fwrite(STDERR, date('c') . " apply_portal_user_cache_collation_fix: FAILED - " . mysqli_error($h) . "\n");
    exit(1);
}

$usersAfter = get_column_collation($h, 'users', 'ww_number');
$cacheAfter = get_column_collation($h, 'portal_user_cache', 'ww_number');
printf("%s apply_portal_user_cache_collation_fix: AFTER users.ww_number=%s portal_user_cache.ww_number=%s\n",
    date('c'), $usersAfter, $cacheAfter);

if ($cacheAfter !== 'utf8mb4_unicode_ci') {
    fwrite(STDERR, date('c') .
        " apply_portal_user_cache_collation_fix: FAILED - portal_user_cache.ww_number collation is " .
        "'$cacheAfter', expected 'utf8mb4_unicode_ci'\n");
    exit(1);
}

printf("%s apply_portal_user_cache_collation_fix: OK\n", date('c'));
exit(0);
