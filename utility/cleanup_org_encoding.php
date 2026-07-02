<?php
/**
 * cleanup_org_encoding.php — one-time, idempotent cleanup of junk characters in
 * organizations.domain / organizations.org_name.
 *
 * Fixes only genuine junk (does NOT touch legitimate accents, which are stored as
 * valid UTF-8): converts the curly right single quote U+2019 to a straight
 * apostrophe, and trims leading/trailing whitespace (spaces, tabs, newlines).
 *
 * Runs dry by default (prints a before -> after diff); pass --apply to write.
 * Idempotent: re-running after --apply changes nothing.
 *
 * Usage:
 *   php utility/cleanup_org_encoding.php           # dry run (preview only)
 *   php utility/cleanup_org_encoding.php --apply    # write the changes
 */

// db.inc uses relative includes, so run from the web root.
chdir( dirname( __DIR__ ) );
require "db.inc";

/**
 * Normalize a domain/org_name value: straighten the curly apostrophe and trim whitespace.
 *
 * @param string|null $s Raw value from the database.
 * @return string|null Cleaned value (null passes through unchanged).
 */
function clean_org_text( $s ) {
	if( $s === null ) {
		return null;
	}
	$s = str_replace( "\xE2\x80\x99", "'", $s ); // U+2019 RIGHT SINGLE QUOTATION MARK -> '
	$s = trim( $s );                              // strip leading/trailing space, tab, newline
	return $s;
}

$apply = in_array( "--apply", $argv, true );

$rs   = $db->query( "SELECT id, domain, org_name FROM organizations" );
$rows = $rs->getAllRows();

$changed = 0;
foreach( $rows as $r ) {
	$d0 = $r["domain"];
	$n0 = $r["org_name"];
	$d1 = clean_org_text( $d0 );
	$n1 = clean_org_text( $n0 );

	if( $d1 !== $d0 || $n1 !== $n0 ) {
		$changed++;
		fwrite( STDOUT, "id={$r['id']}\n" );
		if( $d1 !== $d0 ) { fwrite( STDOUT, "  domain:   [{$d0}] -> [{$d1}]\n" ); }
		if( $n1 !== $n0 ) { fwrite( STDOUT, "  org_name: [{$n0}] -> [{$n1}]\n" ); }
		if( $apply ) {
			$db->query( "UPDATE organizations SET domain=?, org_name=? WHERE id=?", array( $d1, $n1, $r["id"] ) );
		}
	}
}

if( $apply ) {
	fwrite( STDOUT, "[APPLIED] {$changed} row(s) changed.\n" );
} else {
	fwrite( STDOUT, "[DRY RUN] {$changed} row(s) would change. Re-run with --apply to write.\n" );
}
exit( 0 );
