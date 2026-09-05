<?php
require_once 'classes/UserInfoDAO.php';
require_once 'tests/Unit/support/RecordingDb.php';

/**
 * Regression tests confirming UserInfoDAO's query-building methods bind
 * caller-supplied values as parameters rather than splicing them into the
 * SQL text. Before this fix these methods used sprintf('%d'/'%s', ...) --
 * safe in practice for the %d cases and escaped for the %s cases, but not
 * this codebase's parameterized-query standard, and one missed escape() call
 * away from a real injection. Each test uses a value containing a single
 * quote to prove it cannot break out of the query if it ever reached the SQL
 * string directly.
 *
 * @see UserInfoDAO
 */
final class UserInfoDAOSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	public function testUpdateLastLoginDateBindsId(): void {
		$db = new RecordingDb();
		$dao = new UserInfoDAO( $db );

		$dao->updateLastLoginDate( self::TAINTED );

		$this->assertSame( 'UPDATE users SET last_login_date = now() WHERE id=?', $db->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $db->queries[0]['params'] );
	}

	public function testReadApprovalsDBInfoBindsId(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( $db );

		$dao->readApprovalsDBInfo( self::TAINTED );

		$this->assertStringNotContainsString( self::TAINTED, $db->queries[0]['sql'] );
		$this->assertStringContainsString( 'id=?', $db->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $db->queries[0]['params'] );
	}

	public function testReadPortalUserInfoBindsMemberNumber(): void {
		$portalDb = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( new RecordingDb(), $portalDb );

		$dao->readPortalUserInfo( self::TAINTED );

		$this->assertStringNotContainsString( self::TAINTED, $portalDb->queries[0]['sql'] );
		$this->assertStringContainsString( 'membershipNumber = ?', $portalDb->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $portalDb->queries[0]['params'] );
	}

	public function testIsMemberActiveByWwNumberBindsWwNumber(): void {
		$portalDb = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( new RecordingDb(), $portalDb );

		$dao->isMemberActiveByWwNumber( self::TAINTED );

		$this->assertStringNotContainsString( self::TAINTED, $portalDb->queries[0]['sql'] );
		$this->assertStringContainsString( 'membershipNumber = ?', $portalDb->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $portalDb->queries[0]['params'] );
	}

	public function testGetVSTPositionsBindsUserId(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( $db );

		$dao->getVSTPositions( self::TAINTED );

		$this->assertStringNotContainsString( self::TAINTED, $db->queries[0]['sql'] );
		$this->assertStringContainsString( 'storyteller_id=?', $db->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $db->queries[0]['params'] );
	}

	public function testGetOrgSTPositionsBindsUserId(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( $db );

		$dao->getOrgSTPositions( self::TAINTED );

		$this->assertStringNotContainsString( self::TAINTED, $db->queries[0]['sql'] );
		$this->assertStringContainsString( 's.user_id=?', $db->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $db->queries[0]['params'] );
	}

	public function testReadIdsByCamNumberBindsCamNumber(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( $db );

		$dao->readIdsByCamNumber( self::TAINTED );

		$this->assertStringNotContainsString( self::TAINTED, $db->queries[0]['sql'] );
		$this->assertStringContainsString( 'ww_number=?', $db->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $db->queries[0]['params'] );
	}

	public function testIsUserUnderOrganizationBindsEveryId(): void {
		$db = new RecordingDb( [ [ [ 'matching_users' => 0 ] ] ] );
		$dao = new UserInfoDAO( $db );

		$dao->isUserUnderOrganization( self::TAINTED, [ 5, self::TAINTED, 9 ] );

		$sql = $db->queries[0]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		// One placeholder for the user id, three more for the org id list.
		$this->assertSame( 4, substr_count( $sql, '?' ) );
		$this->assertSame( [ self::TAINTED, 5, self::TAINTED, 9 ], $db->queries[0]['params'] );
	}

	public function testReadIdsByOrganizationsBindsEveryId(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new UserInfoDAO( $db );

		$dao->readIdsByOrganizations( [ 5, self::TAINTED ] );

		$sql = $db->queries[0]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertSame( 2, substr_count( $sql, '?' ) );
		$this->assertSame( [ 5, self::TAINTED ], $db->queries[0]['params'] );
	}

	/** With no organization ids, both list methods must short-circuit without querying at all. */
	public function testEmptyOrganizationListsSkipTheQueryEntirely(): void {
		$db = new RecordingDb();
		$dao = new UserInfoDAO( $db );

		$this->assertFalse( $dao->isUserUnderOrganization( 1, [] ) );
		$this->assertSame( [], $dao->readIdsByOrganizations( [] ) );
		$this->assertCount( 0, $db->queries );
	}
}
