<?php
/**
 * Test-only entry point for tests/Integration/DbIncSqliTest.php.
 *
 * userValidForNumber() (defined in db.inc) has no real caller anywhere in the
 * app today, so there is no existing page to point PageHarness at -- this
 * fixture just includes db.inc (which defines the function as a side effect
 * of loading) and calls it with the request's id/camnum, echoing the result.
 * It exercises the real function body, unmodified.
 */
include_once( "db.inc" );

echo userValidForNumber( $_GET['id'], $_GET['camnum'] ) ? '1' : '0';
