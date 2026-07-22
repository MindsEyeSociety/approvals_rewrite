<?php
// Centralized session config - used by ALL pages
session_set_cookie_params([
    'lifetime' => 0,                    // expire when browser closes
    'path'     => '/',
    'domain'   => 'legacy.modernenigmasociety.org',
    'secure'   => false,                // false for HTTP testing
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_name("approvals_2017");
session_start();
?>
