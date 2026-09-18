<?php
require_once 'classes/UserInfoDAO.php';
require_once 'tests/Unit/support/RecordingDb.php';

/**
 * Regression tests for the tier-scoped conflict-of-interest check that replaced
 * isAssistantToApplicant(). The old method asked "do these two people share an assistant
 * relationship anywhere?", which blocked a VST from approving a routine Low application
 * because, in a completely different office, she assisted the applicant. These tests pin
 * applicantConflictOffice() to the office matching the tier being approved, and pin its
 * VST-bridge source to the application's own VSS (`v.id = ?`) rather than any VSS that
 * merely shares an org+venue -- the shape of the original bug. They also cover
 * highestOfficeFor(), used to find a self-approving applicant's supervising officer.
 *
 * @see UserInfoDAO::applicantConflictOffice()
 * @see UserInfoDAO::highestOfficeFor()
 */
final class UserInfoDAOCoiScopeTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** Tier 1 with a VSS binds that VSS id as a parameter of the (B) query, never splices it into the SQL. */
	public function testTier1BindsVssIdAsParameterNotSql(): void {
		$db = new RecordingDb( [ [], [ [ 'org_level' => 'domain' ] ] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->applicantConflictOffice( 1486, 55391, [ 'org_id' => 0, 'vss_id' => 1312, 'venue_id' => 45 ], 1 );

		$this->assertSame( 2, count( $db->queries ) );
		$bSql = $db->queries[1]['sql'];
		$this->assertStringContainsString( 'v.id = ?', $bSql );
		$this->assertStringNotContainsString( '1312', $bSql );
		$this->assertSame( [ 1486, 55391, 1312 ], $db->queries[1]['params'] );
		$this->assertSame( [ 'org_level' => 'domain', 'venue_scoped' => true ], $result );
	}

	/** Tier 1 with vss_id = 0 must skip the (B) query entirely -- 1357 applications have no VSS. */
	public function testTier1WithNoVssSkipsBQuery(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->applicantConflictOffice( 1486, 55391, [ 'org_id' => 0, 'vss_id' => 0, 'venue_id' => 0 ], 1 );

		$this->assertSame( 1, count( $db->queries ) );
		$this->assertNull( $result );
	}

	/** Tier 4 (Top/NST) never issues the (B) query, even when the application has a VSS. */
	public function testTier4DoesNotQueryVstBridge(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->applicantConflictOffice( 1486, 55391, [ 'org_id' => 673, 'vss_id' => 999, 'venue_id' => 45 ], 4 );

		$this->assertSame( 1, count( $db->queries ) );
		$this->assertNull( $result );
	}

	/** Top tier: an (A) match at the shared national office is reported with the tier's org level. */
	public function testTier4AMatchReportsNationLevel(): void {
		$db = new RecordingDb( [ [ [ 'venue_scoped' => 1 ] ] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->applicantConflictOffice( 1486, 55391, [ 'org_id' => 673, 'vss_id' => 0, 'venue_id' => 45 ], 4 );

		$this->assertSame( [ 'org_level' => 'nation', 'venue_scoped' => true ], $result );
	}

	/** The tier's org level reaches query (A) as a bound parameter, never as a literal in the SQL text. */
	public function testTierOrgLevelBoundAsParameter(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( $db );

		$dao->applicantConflictOffice( 1, 2, [ 'org_id' => 0, 'vss_id' => 0, 'venue_id' => 0 ], 3 );

		$sql = $db->queries[0]['sql'];
		// The classification CASE always spells out every level name (it never varies with
		// input); what must never happen is the tier's *chosen* level being spliced in as the
		// comparison target -- that comparison must always be against a placeholder.
		$this->assertStringNotContainsString( "END = 'region'", $sql );
		$this->assertStringContainsString( 'END = ?', $sql );
		$this->assertSame( [ 1, 2, 'region' ], $db->queries[0]['params'] );
	}

	/** A tainted applicant id can only ever appear in bound params, never spliced into either query's SQL. */
	public function testTaintedApplicantIdOnlyInParams(): void {
		$db = new RecordingDb( [ [], [ [ 'org_level' => 'domain' ] ] ] );
		$dao = new UserInfoDAO( $db );

		$dao->applicantConflictOffice( 1486, self::TAINTED, [ 'org_id' => 0, 'vss_id' => 1312, 'venue_id' => 45 ], 1 );

		foreach ( $db->queries as $query ) {
			$this->assertStringNotContainsString( self::TAINTED, $query['sql'] );
		}
		$this->assertContains( self::TAINTED, $db->queries[0]['params'] );
		$this->assertContains( self::TAINTED, $db->queries[1]['params'] );
	}

	/** An (A) match short-circuits before the (B) query is ever issued. */
	public function testAMatchShortCircuitsBeforeBQuery(): void {
		$db = new RecordingDb( [ [ [ 'venue_scoped' => 1 ] ] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->applicantConflictOffice( 1486, 55391, [ 'org_id' => 1212, 'vss_id' => 1312, 'venue_id' => 45 ], 1 );

		$this->assertSame( 1, count( $db->queries ) );
		$this->assertSame( [ 'org_level' => 'domain', 'venue_scoped' => true ], $result );
	}

	/** An unrecognised tier rank matches nothing and issues no query at all. */
	public function testUnknownTierRankReturnsNullWithoutQuerying(): void {
		$db = new RecordingDb();
		$dao = new UserInfoDAO( $db );

		$result = $dao->applicantConflictOffice( 1486, 55391, [ 'org_id' => 0, 'vss_id' => 0, 'venue_id' => 0 ], 9 );

		$this->assertNull( $result );
		$this->assertCount( 0, $db->queries );
	}

	/** A user with no storyteller or VSS rows at all has no office to refer to. */
	public function testHighestOfficeForNoRowsReturnsNull(): void {
		$db = new RecordingDb( [ [], [] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->highestOfficeFor( 55391 );

		$this->assertSame( 2, count( $db->queries ) );
		$this->assertNull( $result );
	}

	/** The storytellers lookup binds the user id as a parameter, never splicing it into the SQL. */
	public function testHighestOfficeForBindsUserIdAsParameter(): void {
		$db = new RecordingDb( [ [ [ 'org_level' => 'nation', 'venue_scoped' => 0 ] ] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->highestOfficeFor( self::TAINTED );

		$this->assertStringNotContainsString( self::TAINTED, $db->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $db->queries[0]['params'] );
		$this->assertSame( [ 'org_level' => 'nation', 'venue_scoped' => false ], $result );
	}

	/** A primary storytellers hit skips the vsss fallback query entirely. */
	public function testHighestOfficeForSkipsVsssFallbackWhenPrimaryFound(): void {
		$db = new RecordingDb( [ [ [ 'org_level' => 'domain', 'venue_scoped' => 1 ] ] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->highestOfficeFor( 1486 );

		$this->assertSame( 1, count( $db->queries ) );
		$this->assertSame( [ 'org_level' => 'domain', 'venue_scoped' => true ], $result );
	}

	/** A VST with no storytellers row at all is found via the vsss fallback, reported venue-scoped. */
	public function testHighestOfficeForFallsBackToVsssWhenNoPrimary(): void {
		$db = new RecordingDb( [ [], [ [ 'org_level' => 'domain' ] ] ] );
		$dao = new UserInfoDAO( $db );

		$result = $dao->highestOfficeFor( 1486 );

		$this->assertSame( 2, count( $db->queries ) );
		$this->assertSame( [ 'org_level' => 'domain', 'venue_scoped' => true ], $result );
	}

	/**
	 * Rule A orders its result deterministically, so a pair holding BOTH an org-wide and a
	 * venue-scoped office at the same level always resolves the same way. Without the ORDER BY,
	 * LIMIT 1 picks arbitrarily and identical data could refer an application to Top on one run
	 * and to Global on the next -- org-wide wins, because it refers higher.
	 */
	public function testRuleAOrdersDeterministicallyPreferringOrgWide(): void {
		$db = new RecordingDb( [ [], [] ] );
		$dao = new UserInfoDAO( $db );

		$dao->applicantConflictOffice( 1486, 55391, [ 'org_id' => 673, 'vss_id' => 0, 'venue_id' => 45 ], 4 );

		$this->assertStringContainsString( 'ORDER BY venue_scoped ASC', $db->queries[0]['sql'] );
	}
}
