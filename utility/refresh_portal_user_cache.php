<?php
/**
 * refresh_portal_user_cache.php — Nightly maintenance job that refreshes the
 * local `portal_user_cache` table from the authoritative `mes-portal`.User data.
 *
 * The User Listing (services/UserService.class.php) reads member names and
 * membership expirations from this cache to avoid a cross-schema JOIN on every
 * page load. The cache is otherwise only updated per-user at login
 * (oauth_callback.php), so a member who renews in the portal without logging
 * into approvals drifts stale and eventually vanishes from the listing once the
 * cached expiration passes (the listing filters `membership_expiration > NOW()`).
 * Running this nightly keeps every active member present and their details current.
 *
 * It executes the committed statement in sql/prefill_portal_user_cache.sql so the
 * cache-refresh SQL lives in exactly one place. CLI-only: refuses to run under a
 * web SAPI so it cannot be triggered over HTTP. Prints a timestamped one-line
 * summary and exits non-zero on failure so cron can surface the error.
 *
 * Usage (cron, as user nta):
 *   30 3 * * * /usr/bin/php /var/www/approvals_rewrite/utility/refresh_portal_user_cache.php >> /home/nta/logs/portal_user_cache_refresh.log 2>&1
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

$sqlFile = $appRoot . '/sql/prefill_portal_user_cache.sql';
$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, date('c') . " refresh_portal_user_cache: cannot read $sqlFile\n");
    exit(1);
}

$db = new Database(
    $SETTINGS['APPROVALS_SERVER'],
    $SETTINGS['APPROVALS_USERNAME'],
    $SETTINGS['APPROVALS_PASSWORD'],
    $SETTINGS['APPROVALS_DATABASE']
);
$h = $db->dbh;

if (!mysqli_query($h, $sql)) {
    fwrite(STDERR, date('c') . " refresh_portal_user_cache: FAILED - " . mysqli_error($h) . "\n");
    exit(1);
}
$affected = mysqli_affected_rows($h);

$stat = mysqli_fetch_assoc(mysqli_query($h,
    "SELECT COUNT(*) total, SUM(membership_expiration > NOW()) visible FROM portal_user_cache"));

printf("%s refresh_portal_user_cache: OK affected=%d total=%s visible=%s\n",
    date('c'), $affected, $stat['total'], $stat['visible']);
exit(0);
