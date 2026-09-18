<?php
require_once 'AppDetails_functions.php';

/**
 * Unit tests for resolveApprovalOutcome(), the function that decides what a single
 * "Approve" click does at one tier. It had no coverage at all before this file, despite
 * being the heart of a real production incident: a VST's Low approval was refused because
 * the old code compared `required_approval == "<Tier>"` by string equality, so once an
 * application had been pushed past its required tier it could never be approved again and
 * climbed, refusal after refusal, all the way to Pending Global. These tests pin the ratchet
 * fix (`$requiredRank <= $tierRank`) plus the self-approval and conflict-referral rules that
 * decide where an unapproved application goes next.
 *
 * @see resolveApprovalOutcome()
 */
final class ResolveApprovalOutcomeTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Builds an $app_info stdClass exposing only user_id, the sole property
	 * resolveApprovalOutcome() reads from it.
	 */
	private function makeAppInfo( int $ownerUserId ): stdClass {
		$app_info = new stdClass;
		$app_info->user_id = $ownerUserId;
		return $app_info;
	}

	/**
	 * Builds an anonymous-class UserInfoDAO stub returning fixed values from
	 * applicantConflictOffice() and highestOfficeFor(), and recording how many
	 * times each was called so self-approval short-circuiting can be asserted.
	 */
	private function makeUserInfoDAOStub( ?array $conflict, ?array $highestOffice ): object {
		return new class( $conflict, $highestOffice ) {
			public $conflict;
			public $highestOffice;
			public $conflictCalls = 0;
			public $highestOfficeCalls = 0;

			function __construct( $conflict, $highestOffice ) {
				$this->conflict = $conflict;
				$this->highestOffice = $highestOffice;
			}

			function applicantConflictOffice( $sessionUserId, $applicantUserId, $appScope, $tierRank ) {
				$this->conflictCalls++;
				return $this->conflict;
			}

			function highestOfficeFor( $userId ) {
				$this->highestOfficeCalls++;
				return $this->highestOffice;
			}
		};
	}

	/**
	 * THE RATCHET FIX: a Low application (requiredRank 1) decided at Mid (tier 2) must be
	 * Approved. The old string-equality comparison (`required_approval == "Low"`) could never
	 * match again once the application had already been referred past Low, leaving it stuck
	 * ratcheting up tier by tier to Pending Global forever.
	 */
	public function testLowApplicationApprovedWhenDecidedPastItsRequiredTierRatchetFix(): void {
		$app_info = $this->makeAppInfo( 1 );
		$dao = $this->makeUserInfoDAOStub( null, null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 1, 2 );

		$this->assertSame( array( 'status' => 'Approved', 'referredWithoutChange' => false ), $result );
	}

	/** Required rank equal to the tier being decided is also Approved. */
	public function testEqualRequiredAndTierRankIsApproved(): void {
		$app_info = $this->makeAppInfo( 1 );
		$dao = $this->makeUserInfoDAOStub( null, null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 3, 3 );

		$this->assertSame( array( 'status' => 'Approved', 'referredWithoutChange' => false ), $result );
	}

	/** A required rank above the current tier, with no conflict, refers one tier up. */
	public function testRequiredAboveTierRefersOneTierUp(): void {
		$app_info = $this->makeAppInfo( 1 );
		$dao = $this->makeUserInfoDAOStub( null, null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 4, 2 );

		$this->assertSame( array( 'status' => 'Pending High', 'referredWithoutChange' => false ), $result );
	}

	/** A domain/venue-scoped conflict (a VST) at Low refers to the VST's supervisor, the DST at Mid. */
	public function testDomainVenueScopedConflictRefersToMid(): void {
		$app_info = $this->makeAppInfo( 1 );
		$dao = $this->makeUserInfoDAOStub( array( 'org_level' => 'domain', 'venue_scoped' => true ), null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 1, 1 );

		$this->assertSame( array( 'status' => 'Pending Mid', 'referredWithoutChange' => false ), $result );
	}

	/**
	 * A nation/venue-scoped conflict (a venue's own NST) at Top refers to the org-wide NST,
	 * which sits at the SAME tier (Top): status must stay null but this was still a refusal,
	 * not a no-op, so referredWithoutChange must be true. Must never collapse with the
	 * nation/org-wide case below, which reaches a genuinely higher tier (Global).
	 */
	public function testNationVenueScopedConflictAtTopRefersWithoutChange(): void {
		$app_info = $this->makeAppInfo( 1 );
		$dao = $this->makeUserInfoDAOStub( array( 'org_level' => 'nation', 'venue_scoped' => true ), null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 1, 4 );

		$this->assertSame( array( 'status' => null, 'referredWithoutChange' => true ), $result );
	}

	/**
	 * A nation/org-wide conflict at Top refers to Global, the org-wide NST's supervisor.
	 * Distinct from the venue-scoped case above: this one actually reaches a higher tier.
	 */
	public function testNationOrgWideConflictAtTopRefersToGlobal(): void {
		$app_info = $this->makeAppInfo( 1 );
		$dao = $this->makeUserInfoDAOStub( array( 'org_level' => 'nation', 'venue_scoped' => false ), null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 1, 4 );

		$this->assertSame( array( 'status' => 'Pending Global', 'referredWithoutChange' => false ), $result );
	}

	/** A region conflict at Low jumps the clamp all the way to Top, more than one tier. */
	public function testRegionConflictAtLowJumpsToTop(): void {
		$app_info = $this->makeAppInfo( 1 );
		$dao = $this->makeUserInfoDAOStub( array( 'org_level' => 'region', 'venue_scoped' => false ), null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 1, 1 );

		$this->assertSame( array( 'status' => 'Pending Top', 'referredWithoutChange' => false ), $result );
	}

	/**
	 * A conflict whose referral rank is below the current tier: max() must prevent moving
	 * backwards, so the status stays null while still recording a refusal.
	 */
	public function testConflictReferralBelowCurrentTierCannotMoveBackwards(): void {
		$app_info = $this->makeAppInfo( 1 );
		// domain/venue-scoped refers to rank 2 (Mid); deciding at tier 4 (Top) must not regress.
		$dao = $this->makeUserInfoDAOStub( array( 'org_level' => 'domain', 'venue_scoped' => true ), null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 1, 4 );

		$this->assertSame( array( 'status' => null, 'referredWithoutChange' => true ), $result );
	}

	/** Self-approval by an officeless applicant refers one tier up. */
	public function testSelfApprovalWithNoOfficeRefersOneTierUp(): void {
		$app_info = $this->makeAppInfo( 9 );
		$dao = $this->makeUserInfoDAOStub( null, null );

		$result = resolveApprovalOutcome( $app_info, 9, $dao, array(), 1, 1 );

		$this->assertSame( array( 'status' => 'Pending Mid', 'referredWithoutChange' => false ), $result );
	}

	/** Self-approval by a domain/venue-scoped officeholder (a VST) refers to Mid, the DST. */
	public function testSelfApprovalWithDomainVenueScopedOfficeRefersToMid(): void {
		$app_info = $this->makeAppInfo( 9 );
		$dao = $this->makeUserInfoDAOStub( null, array( 'org_level' => 'domain', 'venue_scoped' => true ) );

		$result = resolveApprovalOutcome( $app_info, 9, $dao, array(), 1, 1 );

		$this->assertSame( array( 'status' => 'Pending Mid', 'referredWithoutChange' => false ), $result );
	}

	/**
	 * Self-approval by a region officeholder (an RST) refers to Top, their own supervisor's
	 * tier -- not one tier up from Low, which would wrongly refer a senior officer's own
	 * self-approval downward to someone who reports to them.
	 */
	public function testSelfApprovalWithRegionOfficeRefersToTop(): void {
		$app_info = $this->makeAppInfo( 9 );
		$dao = $this->makeUserInfoDAOStub( null, array( 'org_level' => 'region', 'venue_scoped' => false ) );

		$result = resolveApprovalOutcome( $app_info, 9, $dao, array(), 1, 1 );

		$this->assertSame( array( 'status' => 'Pending Top', 'referredWithoutChange' => false ), $result );
	}

	/** Self-approval at Global cannot move any higher, so it's refused without a status change. */
	public function testSelfApprovalAtGlobalCannotMove(): void {
		$app_info = $this->makeAppInfo( 9 );
		$dao = $this->makeUserInfoDAOStub( null, array( 'org_level' => 'globe', 'venue_scoped' => false ) );

		$result = resolveApprovalOutcome( $app_info, 9, $dao, array(), 5, 5 );

		$this->assertSame( array( 'status' => null, 'referredWithoutChange' => true ), $result );
	}

	/**
	 * Self-approval is an absolute bar decided before any conflict lookup: even when the
	 * stub's applicantConflictOffice() is wired to answer, it must never actually be called.
	 */
	public function testSelfApprovalNeverConsultsApplicantConflictOffice(): void {
		$app_info = $this->makeAppInfo( 9 );
		$dao = $this->makeUserInfoDAOStub( array( 'org_level' => 'globe', 'venue_scoped' => false ), null );

		resolveApprovalOutcome( $app_info, 9, $dao, array(), 1, 1 );

		$this->assertSame( 0, $dao->conflictCalls );
	}

	/**
	 * A globe conflict at Global has no supervisor to refer to: status stays null, matching
	 * the pre-existing "leave it for another globe ST" behaviour.
	 */
	public function testGlobeConflictAtGlobalHasNoSupervisor(): void {
		$app_info = $this->makeAppInfo( 1 );
		$dao = $this->makeUserInfoDAOStub( array( 'org_level' => 'globe', 'venue_scoped' => false ), null );

		$result = resolveApprovalOutcome( $app_info, 2, $dao, array(), 5, 5 );

		$this->assertSame( array( 'status' => null, 'referredWithoutChange' => true ), $result );
	}
}
