<?php
/**
 * Standalone child-process driver for PageHarness::run().
 *
 * Reads a JSON spec (argv[1]) describing $_GET/$_POST/$_SESSION and canned
 * DB row responses, then includes the target application page under those
 * conditions with include_path shadowing the real Database class (see
 * stub_includes/include/Database.class.php) -- every other included file
 * (db.inc, header.inc, DAOFactory, every DAO class) is the real, unmodified
 * application code. Captures every query issued to the stub $db and, via a
 * shutdown function (so it still fires if the page calls exit()/die() or
 * hits a fatal error), writes them to the spec's outFile as JSON.
 *
 * Never run directly -- always invoked by PageHarness::run() as a php -d
 * include_path=... child process with cwd set to the repo root.
 */

$specFile = $argv[1] ?? null;
if ( !$specFile || !is_file( $specFile ) ) {
    fwrite( STDERR, "driver.php: missing or unreadable spec file\n" );
    exit( 2 );
}
$spec = json_decode( file_get_contents( $specFile ), true );
if ( !is_array( $spec ) ) {
    fwrite( STDERR, "driver.php: spec file did not contain valid JSON\n" );
    exit( 2 );
}

$GLOBALS['__captured_queries'] = [];
$GLOBALS['__stub_responses'] = $spec['responses'] ?? [];

register_shutdown_function( function () use ( $spec ) {
    $out = [
        'queries' => $GLOBALS['__captured_queries'] ?? [],
        'headers' => function_exists( 'headers_list' ) ? headers_list() : [],
    ];
    file_put_contents( $spec['outFile'], json_encode( $out ) );
} );

$_GET = $spec['get'] ?? [];
$_POST = $spec['post'] ?? [];

// Start the session ourselves first so real db.inc's own
// `if (session_status() === PHP_SESSION_NONE) { session_start(); }` guard
// sees an already-active session and skips re-starting it -- otherwise
// CLI's session_start() would reset $_SESSION back to empty.
session_start();
$_SESSION = $spec['session'] ?? [];

if ( !empty( $spec['ignoreLogin'] ) ) {
    // header.inc's login gate is `!isset($_SESSION['user_id']) && !isset($IGNORE_LOGIN)`;
    // setting this lets tests exercise pages that sit behind that gate without
    // needing $_SESSION['user_id'] set (which would also pull in the much
    // heavier session_setup.inc bootstrap via db.inc).
    $GLOBALS['IGNORE_LOGIN'] = 1;
}

include getcwd() . '/' . $spec['page'];
