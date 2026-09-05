<?php
require_once 'classes/UserInfoDAO.php';
require_once 'tests/Unit/support/RecordingDb.php';

/**
 * Regression test confirming UserInfoDAO::getPositions() doesn't warn when
 * getUserInfo() returns its early fallback array (no 'super_user' key) for a
 * user with no ww_number on file -- e.g. a storyteller listed for a venue on
 * MoveCharacter.php, which populates this DAO's cache via getUserInfo() for
 * every storyteller_id it displays. Reading $userInfo['super_user'] directly
 * used to trip an "Undefined array key" warning.
 *
 * PHPUnit's failOnWarning setting (phpunit.xml) means this test needs no
 * explicit assertion on the warning itself -- a regression would fail the
 * test outright.
 *
 * @see UserInfoDAO::getPositions()
 * @see UserInfoDAO::getUserInfo()
 */
final class UserInfoDAOGetPositionsWarningTest extends \PHPUnit\Framework\TestCase {

	public function testMissingSuperUserKeyDoesNotWarn(): void {
		$db = new RecordingDb( [
			[ [ 'ww_number' => '', 'name' => '' ] ], // readApprovalsDBInfo (inside getUserInfo): empty ww_number triggers the fallback array
			[], // getVSTPositions
			[], // getOrgSTPositions
		] );
		$dao = new UserInfoDAO( $db );

		$positions = $dao->getPositions( 42 );

		$this->assertSame( [], $positions );
	}
}
