<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression tests for UserDisplay.php's "DeleteST" action: it used to have
 * no login check at all (header.inc, this file's login gate, isn't included
 * until well after this block runs) AND built its DELETE by interpolating
 * three raw $_GET values with no escaping -- an unauthenticated, injectable,
 * destructive DELETE. The fix adds a login check ahead of the query and
 * parameterizes it.
 *
 * @see UserDisplay.php
 */
final class UserDisplaySqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** With no session at all, the delete must not run -- zero queries issued. */
	public function testDeleteStWithNoSessionRunsNoQuery(): void {
		$result = PageHarness::run(
			'UserDisplay.php',
			get: [ 'action' => 'DeleteST', 'id' => self::TAINTED, 'organization_id' => '2', 'venue_id' => '3' ],
			session: []
		);

		$this->assertSame( [], $result->queries, 'an unauthenticated request must not reach the DELETE at all' );
	}

	/** With a session present, the delete runs, parameterized, with the tainted value only in params. */
	public function testDeleteStWithSessionBindsAllThreeValues(): void {
		$result = PageHarness::run(
			'UserDisplay.php',
			get: [ 'action' => 'DeleteST', 'id' => self::TAINTED, 'organization_id' => '2', 'venue_id' => '3' ],
			// Setting $_SESSION['user_id'] also pulls in db.inc's own
			// session_setup.inc bootstrap as a side effect, which issues a
			// few queries of its own first -- find the DELETE specifically
			// rather than assuming it's the first query captured.
			session: [ 'user_id' => '42' ]
		);

		$deletes = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'DELETE from storytellers' )
		) );

		$this->assertCount( 1, $deletes, 'expected exactly one DELETE from storytellers query' );
		$this->assertStringNotContainsString( self::TAINTED, $deletes[0]['sql'] );
		$this->assertSame( 'DELETE from storytellers where user_id=? and organization_id=? and venue_id=?', $deletes[0]['sql'] );
		$this->assertSame( [ self::TAINTED, '2', '3' ], $deletes[0]['params'] );
	}
}
