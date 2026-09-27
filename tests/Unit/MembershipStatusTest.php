<?php
require_once 'classes/UserInfoDAO.php';
require_once 'tests/Unit/support/RecordingDb.php';

/**
 * Regression tests pinning UserInfoDAO's membership-status logic to a
 * four-way result (active/expired/unknown/portal_unavailable) instead of the
 * collapsed boolean it used to return. Before this fix, a Portal DB outage
 * and a genuinely expired membership both produced `false` from
 * isMemberActive(), so a three-week outage silently suppressed every
 * approval-notification email as if every member's membership had expired.
 *
 * isMemberActive() and isMemberActiveByWwNumber() are now thin wrappers over
 * membershipStatus()/membershipStatusByWwNumber(); the compatibility-pin test
 * below guards that their true/false contract stays bit-identical, since five
 * other email methods and other call sites still depend on it.
 *
 * @see UserInfoDAO::membershipStatusByWwNumber()
 * @see UserInfoDAO::membershipStatus()
 */
final class MembershipStatusTest extends \PHPUnit\Framework\TestCase {

	/** A future expiration date yields 'active'. */
	public function testFutureExpirationIsActive(): void {
		$portalDb = new RecordingDb( [ [ [ 'membershipExpiration' => date( 'Y-m-d', strtotime( '+1 year' ) ) ] ] ] );
		$dao = new UserInfoDAO( new RecordingDb(), $portalDb );

		$this->assertSame( 'active', $dao->membershipStatusByWwNumber( '12345' ) );
	}

	/** A past expiration date yields 'expired', not 'unknown' or 'portal_unavailable'. */
	public function testPastExpirationIsExpired(): void {
		$portalDb = new RecordingDb( [ [ [ 'membershipExpiration' => date( 'Y-m-d', strtotime( '-1 year' ) ) ] ] ] );
		$dao = new UserInfoDAO( new RecordingDb(), $portalDb );

		$this->assertSame( 'expired', $dao->membershipStatusByWwNumber( '12345' ) );
	}

	/** A row present with a blank membershipExpiration cannot be classified, so it is 'unknown'. */
	public function testBlankExpirationIsUnknown(): void {
		$portalDb = new RecordingDb( [ [ [ 'membershipExpiration' => '' ] ] ] );
		$dao = new UserInfoDAO( new RecordingDb(), $portalDb );

		$this->assertSame( 'unknown', $dao->membershipStatusByWwNumber( '12345' ) );
	}

	/** No matching Portal row for the membership number is 'unknown', not 'expired'. */
	public function testNoPortalRowIsUnknown(): void {
		$portalDb = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( new RecordingDb(), $portalDb );

		$this->assertSame( 'unknown', $dao->membershipStatusByWwNumber( '12345' ) );
	}

	/** An expiration date strtotime() cannot parse is 'unknown', never claimed as 'expired'. */
	public function testUnparseableExpirationIsUnknown(): void {
		$portalDb = new RecordingDb( [ [ [ 'membershipExpiration' => 'not-a-real-date' ] ] ] );
		$dao = new UserInfoDAO( new RecordingDb(), $portalDb );

		$this->assertSame( 'unknown', $dao->membershipStatusByWwNumber( '12345' ) );
	}

	/** A Portal query that fails outright (connected, but errored) is 'portal_unavailable'. */
	public function testFailedPortalQueryIsPortalUnavailable(): void {
		$portalDb = new class {
			function query( $sql, $params = [] ) {
				return false;
			}
		};
		$dao = new UserInfoDAO( new RecordingDb(), $portalDb );

		$this->assertSame( 'portal_unavailable', $dao->membershipStatusByWwNumber( '12345' ) );
	}

	/** A null portal_db (no connection at all) is 'portal_unavailable', never 'expired'. */
	public function testNullPortalDbIsPortalUnavailable(): void {
		$dao = new UserInfoDAO( new RecordingDb() );

		$this->assertSame( 'portal_unavailable', $dao->membershipStatusByWwNumber( '12345' ) );
	}

	/** A user with no ww_number on the approvals side is 'unknown'; the Portal DB is never consulted. */
	public function testMissingWwNumberIsUnknown(): void {
		$approvalsDb = new RecordingDb( [ [ [ 'ww_number' => '' ] ] ] );
		$portalDb = new RecordingDb();
		$dao = new UserInfoDAO( $approvalsDb, $portalDb );

		$this->assertSame( 'unknown', $dao->membershipStatus( 1 ) );
		$this->assertCount( 0, $portalDb->queries );
	}

	/** membershipStatus() delegates to membershipStatusByWwNumber() once a ww_number is found. */
	public function testMembershipStatusDelegatesByWwNumber(): void {
		$approvalsDb = new RecordingDb( [ [ [ 'ww_number' => '12345' ] ] ] );
		$portalDb = new RecordingDb( [ [ [ 'membershipExpiration' => date( 'Y-m-d', strtotime( '+1 year' ) ) ] ] ] );
		$dao = new UserInfoDAO( $approvalsDb, $portalDb );

		$this->assertSame( 'active', $dao->membershipStatus( 1 ) );
		$this->assertSame( [ '12345' ], $portalDb->queries[0]['params'] );
	}

	/**
	 * Compatibility pin: isMemberActive()/isMemberActiveByWwNumber() must stay
	 * true only for 'active' and false for every other status, since five
	 * other email methods still depend on that exact boolean contract.
	 */
	public function testIsMemberActiveIsTrueOnlyWhenStatusIsActive(): void {
		$activeDb = new RecordingDb( [ [ [ 'membershipExpiration' => date( 'Y-m-d', strtotime( '+1 year' ) ) ] ] ] );
		$activeDao = new UserInfoDAO( new RecordingDb(), $activeDb );
		$this->assertTrue( $activeDao->isMemberActiveByWwNumber( '12345' ) );

		$expiredDb = new RecordingDb( [ [ [ 'membershipExpiration' => date( 'Y-m-d', strtotime( '-1 year' ) ) ] ] ] );
		$expiredDao = new UserInfoDAO( new RecordingDb(), $expiredDb );
		$this->assertFalse( $expiredDao->isMemberActiveByWwNumber( '12345' ) );

		$unknownDb = new RecordingDb( [ [] ] );
		$unknownDao = new UserInfoDAO( new RecordingDb(), $unknownDb );
		$this->assertFalse( $unknownDao->isMemberActiveByWwNumber( '12345' ) );

		$unavailableDao = new UserInfoDAO( new RecordingDb() );
		$this->assertFalse( $unavailableDao->isMemberActiveByWwNumber( '12345' ) );

		$approvalsDb = new RecordingDb( [ [ [ 'ww_number' => '' ] ] ] );
		$noWwNumberDao = new UserInfoDAO( $approvalsDb, new RecordingDb() );
		$this->assertFalse( $noWwNumberDao->isMemberActive( 1 ) );
	}
}
