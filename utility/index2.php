<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => 'olddb.modernenigmasociety.org',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_name("approvals_2017");
session_start();

// Load database
include_once("db.inc");

// Load OAuth helper
include_once("include/oauth_helper.php");

// Call OAuth handler
handle_oauth_login();
?>
