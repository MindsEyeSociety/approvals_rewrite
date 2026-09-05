<?php
require_once 'services/UserService.class.php';
require_once 'tests/Unit/support/RecordingDb.php';

/**
 * Regression tests confirming UserService's user-search query building binds
 * the search term as a parameter rather than splicing it into the SQL text.
 * Before this fix, buildSearchClause() built its LIKE clauses via
 * sprintf('%s', ...) wrapped in $db->escape() -- safe in practice, but not
 * this codebase's parameterized-query standard, and it returned a
 * pre-escaped string rather than participating in the caller's params array.
 *
 * @see UserService::buildSearchClause()
 * @see UserService::fetchMatchingUsers()
 * @see UserService::countMatchingUsers()
 */
final class UserServiceSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "o'brien";

	protected function setUp(): void {
		unset( $GLOBALS['db'] );
	}

	public function testFetchMatchingUsersBindsSearchTerm(): void {
		$GLOBALS['db'] = $db = new RecordingDb( [ [] ] );
		$service = new UserService();

		$service->fetchMatchingUsers( [ 'search' => self::TAINTED, 'skip' => 0 ] );

		$sql = $db->queries[0]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertStringContainsString( 'LIKE ?', $sql );
		$this->assertContains( self::TAINTED . '%', $db->queries[0]['params'] );
	}

	public function testCountMatchingUsersBindsSearchTerm(): void {
		$GLOBALS['db'] = $db = new RecordingDb( [ [ [ 'usercount' => 0 ] ] ] );
		$service = new UserService();

		$service->countMatchingUsers( [ 'search' => self::TAINTED ] );

		$sql = $db->queries[0]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertStringContainsString( 'LIKE ?', $sql );
		$this->assertContains( self::TAINTED . '%', $db->queries[0]['params'] );
	}

	public function testBuildSearchClauseReturnsPlaceholdersAndParams(): void {
		$service = new UserService();

		[ $clause, $params ] = $service->buildSearchClause( [ 'search' => self::TAINTED ] );

		$this->assertStringNotContainsString( self::TAINTED, $clause );
		$this->assertStringContainsString( 'LIKE ?', $clause );
		$this->assertSame( [ self::TAINTED . '%', self::TAINTED . '%', self::TAINTED . '%' ], $params );
	}

	public function testBuildSearchClauseWithNoSearchTermReturnsNoParams(): void {
		$service = new UserService();

		[ $clause, $params ] = $service->buildSearchClause( [] );

		$this->assertSame( [], $params );
		$this->assertStringContainsString( 'membership_expiration', $clause );
	}
}
