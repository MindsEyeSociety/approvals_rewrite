<?php
require_once 'AppDetails_functions.php';

/**
 * Unit tests for applicationApprovalScope(), covering the legacy
 * negative-vss_id convention (a negative vss_id identifies an organization,
 * not a VSS -- see AppDetails.php's `readByID( 0 - $app_info->character->vss_id )`)
 * and the requirement that a missing/null $app_info or `character` produce an
 * all-zero scope without emitting any PHP warning or notice, since
 * `failOnNotice` in phpunit.xml makes "no notice" a genuine, enforced assertion.
 *
 * @see applicationApprovalScope()
 */
final class ApplicationApprovalScopeTest extends \PHPUnit\Framework\TestCase {

	/** A character on a real VSS contributes org_id, venue_id, and vss_id. */
	public function testCharacterOnVssContributesAllThreeFields(): void {
		$app_info = (object) array(
			'org_id' => 12,
			'venue_id' => 3,
			'character' => (object) array( 'vss_id' => 44 ),
		);
		$this->assertSame(
			array( 'org_id' => 12, 'vss_id' => 44, 'venue_id' => 3 ),
			applicationApprovalScope( $app_info )
		);
	}

	/** A null character property yields an all-zero scope with no notice. */
	public function testNullCharacterYieldsAllZeros(): void {
		$app_info = (object) array( 'org_id' => 12, 'venue_id' => 3, 'character' => null );
		$this->assertSame(
			array( 'org_id' => 12, 'vss_id' => 0, 'venue_id' => 3 ),
			applicationApprovalScope( $app_info )
		);
	}

	/** A missing character property entirely yields an all-zero vss_id with no notice. */
	public function testMissingCharacterPropertyYieldsZeroVssId(): void {
		$app_info = (object) array( 'org_id' => 12, 'venue_id' => 3 );
		$this->assertSame(
			array( 'org_id' => 12, 'vss_id' => 0, 'venue_id' => 3 ),
			applicationApprovalScope( $app_info )
		);
	}

	/** A null $app_info yields an all-zero scope with no notice. */
	public function testNullAppInfoYieldsAllZeros(): void {
		$this->assertSame(
			array( 'org_id' => 0, 'vss_id' => 0, 'venue_id' => 0 ),
			applicationApprovalScope( null )
		);
	}

	/** An explicit vss_id of 0 is not treated as an organization reference. */
	public function testZeroVssIdYieldsZeroVssIdAndNoOrgContribution(): void {
		$app_info = (object) array(
			'org_id' => 0,
			'venue_id' => 0,
			'character' => (object) array( 'vss_id' => 0 ),
		);
		$this->assertSame(
			array( 'org_id' => 0, 'vss_id' => 0, 'venue_id' => 0 ),
			applicationApprovalScope( $app_info )
		);
	}

	/**
	 * A negative vss_id with no org_id of its own means "organization -vss_id",
	 * per the legacy convention: vss_id -597 with org_id absent yields org_id 597.
	 */
	public function testNegativeVssIdWithNoOwnOrgYieldsImpliedOrgId(): void {
		$app_info = (object) array(
			'org_id' => 0,
			'venue_id' => 0,
			'character' => (object) array( 'vss_id' => -597 ),
		);
		$this->assertSame(
			array( 'org_id' => 597, 'vss_id' => 0, 'venue_id' => 0 ),
			applicationApprovalScope( $app_info )
		);
	}

	/**
	 * When the application already has its own org_id, that org_id wins over
	 * the organization implied by a negative character vss_id.
	 */
	public function testNegativeVssIdDoesNotOverrideApplicationsOwnOrgId(): void {
		$app_info = (object) array(
			'org_id' => 1212,
			'venue_id' => 0,
			'character' => (object) array( 'vss_id' => -597 ),
		);
		$this->assertSame(
			array( 'org_id' => 1212, 'vss_id' => 0, 'venue_id' => 0 ),
			applicationApprovalScope( $app_info )
		);
	}

	/** Non-numeric org_id, venue_id, and vss_id values are all treated as absent. */
	public function testNonNumericValuesAreTreatedAsAbsent(): void {
		$app_info = (object) array(
			'org_id' => 'abc',
			'venue_id' => 'def',
			'character' => (object) array( 'vss_id' => 'ghi' ),
		);
		$this->assertSame(
			array( 'org_id' => 0, 'vss_id' => 0, 'venue_id' => 0 ),
			applicationApprovalScope( $app_info )
		);
	}
}
