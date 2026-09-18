<?php
/**
 * test_final_approval.php — CLI integration tests for the capacity-scoped conflict-of-interest
 * check (UserInfoDAO::applicantConflictOffice) and the "no self-approval" rule
 * (finalApprovalBlocked).
 *
 * The old check asked "do these two people share an assistant relationship anywhere?", which
 * wrongly blocked a VST from approving a routine Low application because, in a completely
 * unrelated office, she happens to assist the applicant. The new check asks a capacity-based
 * question instead: "at the office that governs the tier being approved, does the approver
 * assist the applicant?" The same person can be a primary officeholder at Low (no conflict) and
 * the applicant's assistant at Top (conflict) -- it depends entirely on which office and which
 * tier are in play, not on the pair of people alone.
 *
 * The repo has no test framework, so this is a self-contained runner with a tiny assert harness.
 * It creates ISOLATED fixtures in THREE tables -- `organizations`, `storytellers`, and `vsss` --
 * using sentinel IDs far above real data (organizations tagged `globe = 'SENTINEL'`, which does
 * not occur anywhere in the real org tree, and `active = 0` so they never appear on list pages),
 * and ALWAYS cleans them up (at start and via a shutdown handler), so it is idempotent and safe
 * to run against the live DB.
 *
 * Usage:  php tests/test_final_approval.php     (exit 0 = all pass, 1 = a failure)
 */

chdir( dirname( __DIR__ ) );          // db.inc uses relative includes → run from web root
error_reporting( E_ERROR | E_PARSE ); // silence CLI notices from settings.inc/session
require "db.inc";
require_once "AppDetails_functions.php";

// --- sentinel fixture IDs (well above any real org/venue/vss/user id) ---

// organizations: globe='SENTINEL' isolates these completely from the real Camarilla tree;
// active=0 keeps them off list pages (which filter active=1). Level = deepest non-empty column.
const ORG_A = 1999000201; // domain-level -- approver's own VST office (the incident's "org A")
const ORG_B = 1999000202; // domain-level -- applicant's VST office + approver's unrelated
                           // assistant office, same venue as ORG_A (the incident's "org B")
const ORG_C = 1999000203; // domain-level -- VST-bridge positive case
const ORG_D = 1999000204; // domain-level -- org-tier assistant/primary pair (governs Mid)
const ORG_R = 1999000205; // region-level -- org-tier assistant/primary pair (governs High)
const ORG_N = 1999000206; // nation-level -- org-tier assistant/primary pairs (govern Top)

// venues: plain ids. These queries never touch the `venues` table, so no row is needed there.
const V1 = 1999000301; // shared by ORG_A and ORG_B -- "the same venue" in the incident
const V3 = 1999000302; // ORG_C's venue (VST-bridge positive case)
const V6 = 1999000303; // ORG_N's venue-scoped pair

// vsss: explicit ids (int PK, so this is legal) so assertions can name a VSS.
const VSS_X = 1999000401; // ORG_A/V1 -- the approver is its VST
const VSS_Y = 1999000402; // ORG_B/V1 -- the applicant's own VSS
const VSS_Z = 1999000403; // ORG_B/V1 -- a second VSS in the applicant's org+venue, NOT run by them
const VSS_W = 1999000404; // ORG_C/V3 -- the application's own VSS for the VST-bridge case

// users
const U_APPROVER    = 1999000501; // VST of VSS_X; also a venue-scoped assistant at ORG_B/V1 and ORG_C/V3
const U_APPLICANT   = 1999000502; // VST of VSS_Y (the incident's applicant)
const U_OTHER_VST   = 1999000503; // VST of VSS_Z -- occupies that office; is never the applicant below
const U_APPLICANT_W = 1999000504; // VST of VSS_W (the applicant in the VST-bridge case)
const U_UNREL       = 1999000505; // holds no storyteller or VST position anywhere

const U_ASST_D = 1999000506; // ORG_D org-wide assistant
const U_PRIM_D = 1999000507; // ORG_D org-wide primary
const U_ASST_R = 1999000508; // ORG_R org-wide assistant
const U_PRIM_R = 1999000509; // ORG_R org-wide primary
const U_ASST_NV = 1999000510; // ORG_N venue-scoped assistant (venue V6)
const U_PRIM_NV = 1999000511; // ORG_N venue-scoped primary (venue V6)
const U_ASST_NO = 1999000512; // ORG_N org-wide assistant (no venue)
const U_PRIM_NO = 1999000513; // ORG_N org-wide primary (no venue)

const SENTINEL_ORGS = [ ORG_A, ORG_B, ORG_C, ORG_D, ORG_R, ORG_N ];

function cleanup() {
	global $db;
	$db->query( "DELETE FROM storytellers WHERE organization_id IN (?,?,?,?,?,?)", SENTINEL_ORGS );
	$db->query( "DELETE FROM vsss WHERE org_id IN (?,?,?,?,?,?)", SENTINEL_ORGS );
	$db->query( "DELETE FROM organizations WHERE id IN (?,?,?,?,?,?)", SENTINEL_ORGS );
	// Belt-and-braces: sweep anything a crashed prior run left behind. No real org uses this globe.
	$db->query( "DELETE FROM organizations WHERE globe = 'SENTINEL'" );
}

function setup() {
	global $db;

	// organizations: (id, org_name, globe, nation, region, domain, chapter, city, state, country,
	// admin_user_id, email, email_is_google, active) -- mirrors OrganizationDAO::insert()'s column
	// set, with an explicit id.
	$orgs = [
		[ ORG_A, 'SENTINEL Domain A', 'SENTINEL', 'SENTINEL', 'SENTINEL', 'SENTINEL', '' ],
		[ ORG_B, 'SENTINEL Domain B', 'SENTINEL', 'SENTINEL', 'SENTINEL', 'SENTINEL', '' ],
		[ ORG_C, 'SENTINEL Domain C', 'SENTINEL', 'SENTINEL', 'SENTINEL', 'SENTINEL', '' ],
		[ ORG_D, 'SENTINEL Domain D', 'SENTINEL', 'SENTINEL', 'SENTINEL', 'SENTINEL', '' ],
		[ ORG_R, 'SENTINEL Region',   'SENTINEL', 'SENTINEL', 'SENTINEL', '',         '' ], // region-level: domain empty
		[ ORG_N, 'SENTINEL Nation',   'SENTINEL', 'SENTINEL', '',         '',         '' ], // nation-level: region+domain empty
	];
	foreach ( $orgs as $o ) {
		list( $id, $name, $globe, $nation, $region, $domain, $chapter ) = $o;
		$db->query(
			"INSERT INTO organizations (id, org_name, globe, nation, region, domain, chapter, ".
			"city, state, country, admin_user_id, email, email_is_google, active) ".
			"VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
			[ $id, $name, $globe, $nation, $region, $domain, $chapter, null, null, null, null, '', 0, 0 ]
		);
	}

	// storytellers: (organization_id, venue_id, user_id, assistant)
	$rows = [
		[ ORG_B, V1,   U_APPROVER, 1 ], // approver's venue-scoped assistant office at org B/V1 --
		                                // unrelated to VSS_X, the office actually being approved
		[ ORG_C, V3,   U_APPROVER, 1 ], // approver's venue-scoped assistant office at ORG_C/V3
		[ ORG_D, null, U_ASST_D,   1 ], // org-wide domain assistant → assists U_PRIM_D
		[ ORG_D, null, U_PRIM_D,   0 ], // org-wide domain primary
		[ ORG_R, null, U_ASST_R,   1 ], // org-wide region assistant → assists U_PRIM_R
		[ ORG_R, null, U_PRIM_R,   0 ], // org-wide region primary
		[ ORG_N, V6,   U_ASST_NV,  1 ], // venue-scoped nation assistant → assists U_PRIM_NV
		[ ORG_N, V6,   U_PRIM_NV,  0 ], // venue-scoped nation primary
		[ ORG_N, null, U_ASST_NO,  1 ], // org-wide nation assistant → assists U_PRIM_NO
		[ ORG_N, null, U_PRIM_NO,  0 ], // org-wide nation primary
	];
	foreach ( $rows as $r ) {
		$db->query( "INSERT INTO storytellers (organization_id, venue_id, user_id, assistant) VALUES (?,?,?,?)", $r );
	}

	// vsss: (id, org_id, venue_id, storyteller_id, name, vss)
	$vsss = [
		[ VSS_X, ORG_A, V1, U_APPROVER,    'SENTINEL VSS X' ],
		[ VSS_Y, ORG_B, V1, U_APPLICANT,   'SENTINEL VSS Y' ],
		[ VSS_Z, ORG_B, V1, U_OTHER_VST,   'SENTINEL VSS Z' ],
		[ VSS_W, ORG_C, V3, U_APPLICANT_W, 'SENTINEL VSS W' ],
	];
	foreach ( $vsss as $v ) {
		$db->query( "INSERT INTO vsss (id, org_id, venue_id, storyteller_id, name, vss) VALUES (?,?,?,?,?,?)",
			[ $v[0], $v[1], $v[2], $v[3], $v[4], '' ] );
	}
}

/**
 * Builds a minimal application-record stand-in for applicationApprovalScope(), so these tests
 * exercise the real production scope-builder instead of hand-rolling the scope array.
 *
 * @param $org_id   The application's org_id (0 for none).
 * @param $venue_id The application's venue_id (0 for none).
 * @param $vss_id   The application character's vss_id (0 for none, or NonChar).
 * @return object A stdClass shaped like the subset of an application record that
 *                applicationApprovalScope() reads.
 * @see applicationApprovalScope()
 */
function appInfo( $org_id, $venue_id, $vss_id ) {
	$app_info = new stdClass();
	$app_info->org_id = $org_id;
	$app_info->venue_id = $venue_id;
	$app_info->character = new stdClass();
	$app_info->character->vss_id = $vss_id;
	return $app_info;
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

// --- application scopes, built via the real production helper ---
$scopeIncidentOwnOffice  = applicationApprovalScope( appInfo( ORG_A, V1, VSS_X ) ); // VSS_X's own scope
$scopeApplicantSecondVSS = applicationApprovalScope( appInfo( ORG_B, V1, VSS_Z ) ); // VSS_Z, not the applicant's own VSS
$scopeNonChar            = applicationApprovalScope( appInfo( 0, 0, 0 ) );          // no org, no VSS
$scopeNationVenue        = applicationApprovalScope( appInfo( ORG_N, V6, 0 ) );     // ORG_N at venue V6
$scopeDomainOrgWide      = applicationApprovalScope( appInfo( ORG_D, 0, 0 ) );      // ORG_D, org-wide
$scopeRegionOrgWide      = applicationApprovalScope( appInfo( ORG_R, 0, 0 ) );      // ORG_R, org-wide
$scopeVstBridge          = applicationApprovalScope( appInfo( ORG_C, V3, VSS_W ) ); // VSS_W's own scope

echo "Must NOT be blocked:\n";
check(
	$dao->applicantConflictOffice( U_APPROVER, U_APPLICANT, $scopeIncidentOwnOffice, 1 ), null,
	"the incident: VST of VSS_X (org A/V1) is also a venue-scoped assistant at org B/V1 -- ".
	"approving VSS_X's own Low application is not a conflict (same venue, different VSS, different org)"
);
check(
	$dao->applicantConflictOffice( U_APPROVER, U_APPLICANT, $scopeApplicantSecondVSS, 1 ), null,
	"same pair, tier Low: a second VSS at the applicant's own org+venue that the applicant does not run"
);
check(
	$dao->applicantConflictOffice( U_APPROVER, U_APPLICANT, $scopeNonChar, 1 ), null,
	"NonChar application (org_id=0, vss_id=0): no org-tier relationship exists"
);
foreach ( [ 1, 2, 3, 4, 5 ] as $tier ) {
	check(
		$dao->applicantConflictOffice( U_ASST_NV, U_UNREL, $scopeNationVenue, $tier ), null,
		"unrelated applicant, tier $tier"
	);
}
check(
	$dao->applicantConflictOffice( U_ASST_D, U_PRIM_D, $scopeDomainOrgWide, 4 ), null,
	"tier mismatch: a domain-office assistant/primary pair queried at Top (domain governs Mid, not Top)"
);

echo "Must be blocked:\n";
check(
	$dao->applicantConflictOffice( U_ASST_NV, U_PRIM_NV, $scopeNationVenue, 4 ),
	array( 'org_level' => 'nation', 'venue_scoped' => true ),
	"the Top-tier case: venue-scoped nation-level assistant/primary pair -- the real incident's relationship"
);
check(
	$dao->applicantConflictOffice( U_ASST_NO, U_PRIM_NO, $scopeNationVenue, 4 ),
	array( 'org_level' => 'nation', 'venue_scoped' => false ),
	"same office pair, org-wide: venue_scoped must read false, distinct from the venue-scoped case above ".
	"(decides org-wide NST vs Global referral)"
);
check(
	$dao->applicantConflictOffice( U_APPROVER, U_APPLICANT_W, $scopeVstBridge, 1 ),
	array( 'org_level' => 'domain', 'venue_scoped' => true ),
	"VST bridge: applicant is the VST of the application's own VSS, approver is a venue-scoped ".
	"assistant at that VSS's org+venue"
);
check(
	$dao->applicantConflictOffice( U_ASST_D, U_PRIM_D, $scopeDomainOrgWide, 2 ),
	array( 'org_level' => 'domain', 'venue_scoped' => false ),
	"domain-level assistant/primary pair queried at Mid"
);
check(
	$dao->applicantConflictOffice( U_ASST_R, U_PRIM_R, $scopeRegionOrgWide, 3 ),
	array( 'org_level' => 'region', 'venue_scoped' => false ),
	"region-level assistant/primary pair queried at High"
);

echo "Self-approval (finalApprovalBlocked):\n";
check(
	finalApprovalBlocked( U_APPROVER, U_APPROVER, $dao, $scopeIncidentOwnOffice, 1 ), true,
	"self-approval blocked at Low"
);
check(
	finalApprovalBlocked( U_APPROVER, U_APPROVER, $dao, $scopeNationVenue, 4 ), true,
	"self-approval blocked at Top"
);

echo "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed\n";
exit( $GLOBALS['fail'] === 0 ? 0 : 1 );
