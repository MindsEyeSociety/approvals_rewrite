<?php
/**
 * Stub replacement for include/settings.inc, used only by the Integration
 * test harness via include_path shadowing (see stub_includes/include/Database.class.php
 * for the mechanism). Values are dummy placeholders -- the stub Database
 * class never actually connects to them.
 */

$SETTINGS = array();

$SETTINGS["APPROVALS_SERVER"]   = "stub";
$SETTINGS["APPROVALS_USERNAME"] = "stub";
$SETTINGS["APPROVALS_PASSWORD"] = "stub";
$SETTINGS["APPROVALS_DATABASE"] = "approvals_2017";

$SETTINGS["PORTAL_SERVER"]      = "stub";
$SETTINGS["PORTAL_USERNAME"]    = "stub";
$SETTINGS["PORTAL_PASSWORD"]    = "stub";
$SETTINGS["PORTAL_DATABASE"]    = "mes-portal";

$SETTINGS["OAUTH_CLIENT_ID"]     = "stub";
$SETTINGS["OAUTH_CLIENT_SECRET"] = "stub";
$SETTINGS["OAUTH_REDIRECT_URI"]  = "https://example.test/oauth_callback.php";
$SETTINGS["OAUTH_TOKEN_URL"]     = "https://example.test/oauth/v2/token";
$SETTINGS["OAUTH_API_URL"]       = "https://example.test/api/authorized/user.json";

$SETTINGS["EARLY_ACCESS_ENABLED"] = false;
$SETTINGS["GOOGLE_MAP_SYNC_ENABLED"] = false;
