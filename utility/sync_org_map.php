<?php
/**
 * sync_org_map.php — CLI one-shot full sync of active organizations to the Google map Sheet.
 *
 * Use it to seed the Sheet initially, to force a re-sync after config changes, or from cron.
 * Unlike the per-mutation hook (GoogleSheetsService::syncOrgMap), this pushes regardless of
 * the GOOGLE_MAP_SYNC_ENABLED flag, so it works for the initial seed before the flag is on.
 *
 * Usage:  php utility/sync_org_map.php
 * Exit:   0 on success, 1 on failure.
 *
 * @see GoogleSheetsService
 */

// db.inc uses relative includes, so run from the web root.
chdir( dirname( __DIR__ ) );
require "db.inc";
require_once "classes/GoogleSheetsService.php";

$dao  = $daoFactory->getOrganizationDAO();
$rows = $dao->getMapRows();

fwrite( STDOUT, "Syncing " . count( $rows ) . " active organization(s) to the Google Sheet...\n" );

try {
	$svc = new GoogleSheetsService();
	$svc->syncOrganizations( $rows );
	fwrite( STDOUT, "OK\n" );
	exit( 0 );
} catch ( \Throwable $e ) {
	fwrite( STDERR, "FAILED: " . $e->getMessage() . "\n" );
	exit( 1 );
}
