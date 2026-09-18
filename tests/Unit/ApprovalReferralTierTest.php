<?php
require_once 'AppDetails_functions.php';

/**
 * Unit tests for approvalReferralTierRank(), covering the conflict-of-interest
 * referral bug where a blocked approval was bumped one tier up from the
 * application's *current* tier instead of being sent to the conflicted
 * officeholder's actual supervisor (MES Handbook Ch.9). Pins down the single
 * "semi-primary" special case: a venue-scoped NST's supervisor is the
 * org-wide NST (Top), not Global, unlike every other office in the chain.
 *
 * @see approvalReferralTierRank()
 */
final class ApprovalReferralTierTest extends \PHPUnit\Framework\TestCase {

	/** A venue-scoped domain office is a VST; its supervisor is the DST (Mid). */
	public function testVenueScopedDomainReferralIsMid(): void {
		$this->assertSame( 2, approvalReferralTierRank( 'domain', true ) );
	}

	/** An org-wide domain office is a DST; its supervisor is the RST (High). */
	public function testOrgWideDomainReferralIsHigh(): void {
		$this->assertSame( 3, approvalReferralTierRank( 'domain', false ) );
	}

	/** A venue-scoped region office is still an RST; its supervisor is the NST (Top). */
	public function testVenueScopedRegionReferralIsTop(): void {
		$this->assertSame( 4, approvalReferralTierRank( 'region', true ) );
	}

	/** An org-wide region office is an RST; its supervisor is the NST (Top). */
	public function testOrgWideRegionReferralIsTop(): void {
		$this->assertSame( 4, approvalReferralTierRank( 'region', false ) );
	}

	/** A venue-scoped globe office still has no supervisor above Global. */
	public function testVenueScopedGlobeReferralIsGlobal(): void {
		$this->assertSame( 5, approvalReferralTierRank( 'globe', true ) );
	}

	/** An org-wide globe office has no supervisor above Global. */
	public function testOrgWideGlobeReferralIsGlobal(): void {
		$this->assertSame( 5, approvalReferralTierRank( 'globe', false ) );
	}

	/** 'chapter' folds into 'domain': venue-scoped chapter behaves like a VST. */
	public function testChapterFoldsIntoVenueScopedDomain(): void {
		$this->assertSame( 2, approvalReferralTierRank( 'chapter', true ) );
	}

	/** 'chapter' folds into 'domain': org-wide chapter behaves like a DST. */
	public function testChapterFoldsIntoOrgWideDomain(): void {
		$this->assertSame( 3, approvalReferralTierRank( 'chapter', false ) );
	}

	/** An unknown office level returns the safest rank, Global. */
	public function testUnknownOfficeLevelReferralIsGlobal(): void {
		$this->assertSame( 5, approvalReferralTierRank( 'nonsense', false ) );
	}

	/**
	 * The critical distinction this function exists to enforce: a venue-scoped
	 * NST's supervisor is the org-wide NST (Top, rank 4), while the org-wide
	 * NST's own supervisor is Global (rank 5). These two must never be
	 * collapsed into the same rank -- doing so would let a venue NST's
	 * conflicts be resolved by another venue NST instead of escalating
	 * org-wide NST conflicts all the way to Global.
	 */
	public function testVenueScopedNationMustNotCollapseWithOrgWideNation(): void {
		$venueScopedNationReferral = approvalReferralTierRank( 'nation', true );
		$orgWideNationReferral = approvalReferralTierRank( 'nation', false );

		$this->assertSame( 4, $venueScopedNationReferral );
		$this->assertSame( 5, $orgWideNationReferral );
		$this->assertNotSame( $venueScopedNationReferral, $orgWideNationReferral );
	}
}
