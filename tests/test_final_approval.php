<?php
/**
 * test_final_approval.php — CLI integration tests for the "no self / no assistant-of-applicant
 * final approval" rule (UserInfoDAO::isAssistantToApplicant + finalApprovalBlocked).
 *
 * The repo has no test framework, so this is a self-contained runner with a tiny assert harness.
 * It creates ISOLATED fixtures in `storytellers` + `vsss` only (the two tables the query reads),
 * using sentinel IDs far above real data, and ALWAYS cleans them up (at start and via a shutdown
 * handler), so it is idempotent and safe to run against the live DB.
 *
 * Usage:  php tests/test_final_approval.php     (exit 0 = all pass, 1 = a failure)
 */

chdir( dirname( __DIR__ ) );          // db.inc uses relative includes → run from web root
error_reporting( E_ERROR | E_PARSE ); // silence CLI notices from settings.inc/session
require "db.inc";
require_once "AppDetails_functions.php";

// --- sentinel fixture IDs (well above any real org/venue/user id) ---
const O1 = 1999000001, O2 = 1999000002, O3 = 1999000003;      // organizations
const V1 = 1999000001, V2 = 1999000002;                        // venues
const P_A = 1999000101;   // org-wide primary at O1
const ASST_A = 1999000102; // org-wide assistant at O1
const PV = 1999000103;    // venue primary at (O1,V1)
const AV = 1999000104;    // venue assistant at (O1,V1)
const P_B = 1999000105;   // VST of a VSS in (O2,V2)  [vsss.storyteller_id]
const P_B2 = 1999000106;  // second VST of (O2,V2)     [multi-VST venue]
const ASST_B = 1999000107; // aVST at (O2,V2)
const UNREL = 1999000108; // unrelated user (no positions)
const P_O3 = 1999000109;  // org-wide primary at O3

function cleanup() {
	global $db;
	$db->query( "DELETE FROM storytellers WHERE organization_id IN (?,?,?)", [ O1, O2, O3 ] );
	$db->query( "DELETE FROM vsss WHERE org_id IN (?,?,?)", [ O1, O2, O3 ] );
}

function setup() {
	global $db;
	// storytellers: (organization_id, venue_id, user_id, assistant)
	$rows = [
		[ O1, null, P_A,    0 ],  // org-wide primary
		[ O1, null, ASST_A, 1 ],  // org-wide assistant → assists P_A
		[ O1, V1,   PV,     0 ],  // venue primary
		[ O1, V1,   AV,     1 ],  // venue assistant → assists PV
		[ O2, V2,   ASST_B, 1 ],  // aVST → assists the VST(s) of (O2,V2)
		[ O3, null, P_O3,   0 ],  // unrelated primary at O3
	];
	foreach ( $rows as $r ) {
		$db->query( "INSERT INTO storytellers (organization_id, venue_id, user_id, assistant) VALUES (?,?,?,?)", $r );
	}
	// vsss: two VSSs in the same (O2,V2) with different current VSTs
	$db->query( "INSERT INTO vsss (org_id, venue_id, storyteller_id, name, vss) VALUES (?,?,?,?,?)", [ O2, V2, P_B,  'SENTINEL VSS B1', '' ] );
	$db->query( "INSERT INTO vsss (org_id, venue_id, storyteller_id, name, vss) VALUES (?,?,?,?,?)", [ O2, V2, P_B2, 'SENTINEL VSS B2', '' ] );
}

// --- tiny assert harness ---
$GLOBALS['pass'] = 0; $GLOBALS['fail'] = 0;
function check( $got, $want, $name ) {
	if ( $got === $want ) { $GLOBALS['pass']++; echo "  PASS  $name\n"; }
	else { $GLOBALS['fail']++; echo "  FAIL  $name (got " . var_export($got,true) . ", want " . var_export($want,true) . ")\n"; }
}

cleanup();                              // clear any stale sentinels from a previous crashed run
register_shutdown_function( 'cleanup' ); // guarantee cleanup even on fatal
setup();

$dao = $userInfoDAO; // from db.inc

echo "Positive — must be blocked:\n";
check( $dao->isAssistantToApplicant( ASST_A, P_A ),  true,  "org-tier: org-wide assistant of primary" );
check( $dao->isAssistantToApplicant( AV,     PV ),   true,  "org-tier: venue assistant of venue primary" );
check( $dao->isAssistantToApplicant( ASST_B, P_B ),  true,  "VST-tier: aVST of a VSS's VST" );
check( $dao->isAssistantToApplicant( ASST_B, P_B2 ), true,  "VST-tier: aVST blocked for 2nd VST of same venue" );
check( finalApprovalBlocked( P_A, P_A, $dao ),       true,  "self: approving your own application" );
check( finalApprovalBlocked( P_A, ASST_A, $dao ),    true,  "helper: assistant approving their primary's app" );

echo "Negative — must NOT be blocked:\n";
check( $dao->isAssistantToApplicant( ASST_A, UNREL ), false, "unrelated applicant" );
check( $dao->isAssistantToApplicant( ASST_A, P_O3 ),  false, "assistant, but primary is at a different org" );
check( $dao->isAssistantToApplicant( ASST_A, PV ),    false, "org-wide assistant vs venue-specific primary (venue mismatch)" );
check( $dao->isAssistantToApplicant( AV,     P_A ),   false, "venue assistant vs org-wide primary (venue mismatch)" );
check( $dao->isAssistantToApplicant( P_A,    ASST_A ),false, "reverse: primary is not their assistant's assistant" );
check( $dao->isAssistantToApplicant( ASST_B, PV ),    false, "aVST vs a primary of a different org/venue" );
check( $dao->isAssistantToApplicant( UNREL,  P_A ),   false, "approver holds no positions" );
check( finalApprovalBlocked( UNREL, ASST_A, $dao ),   false, "helper: assistant approving an unrelated user's app" );

echo "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed\n";
exit( $GLOBALS['fail'] === 0 ? 0 : 1 );
