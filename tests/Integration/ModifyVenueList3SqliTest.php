<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming ModifyVenueList3.php's venue lookup binds the
 * GET "modify" value as a query parameter rather than splicing it directly
 * into the SQL string.
 *
 * @see ModifyVenueList3.php
 */
final class ModifyVenueList3SqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** The venue lookup uses a "?" placeholder and binds the tainted value only as a parameter. */
	public function testVenueLookupIsParameterized(): void {
		$result = PageHarness::run(
			'ModifyVenueList3.php',
			get: [ 'modify' => self::TAINTED ],
			// header.inc's generateMenus() calls count() on these two session
			// keys unconditionally; leaving them unset is a fatal TypeError
			// under PHP 8, and header.inc runs before the venue query.
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ]
		);

		$this->assertNotEmpty( $result->queries, 'expected at least one query to be captured' );
		$query = $result->queries[0];
		$this->assertStringContainsString( 'where ID=?', $query['sql'] );
		$this->assertStringNotContainsString( self::TAINTED, $query['sql'], 'the tainted value must not be spliced into the SQL text' );
		$this->assertContains( self::TAINTED, $query['params'] );
	}
}
