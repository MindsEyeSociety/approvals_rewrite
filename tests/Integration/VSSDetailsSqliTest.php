<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming VSSDetails.php's VSS lookup binds the GET "id"
 * value as a query parameter rather than splicing it directly into the SQL
 * string. header.inc runs before this query (unlike UserDisplay.php's
 * DeleteST action), so $IGNORE_LOGIN is what lets the request through.
 *
 * @see VSSDetails.php
 */
final class VSSDetailsSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** The VSS lookup uses a "?" placeholder and binds the tainted value only as a parameter. */
	public function testVssLookupIsParameterized(): void {
		$result = PageHarness::run(
			'VSSDetails.php',
			get: [ 'id' => self::TAINTED ],
			// header.inc's generateMenus() calls count() on these two session
			// keys unconditionally; leaving them unset is a fatal TypeError
			// under PHP 8, and header.inc runs before the VSS query.
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ]
		);

		$this->assertNotEmpty( $result->queries, 'expected at least one query to be captured' );
		$query = $result->queries[0];
		$this->assertStringContainsString( 'WHERE v.id=?', $query['sql'] );
		$this->assertStringNotContainsString( self::TAINTED, $query['sql'], 'the tainted value must not be spliced into the SQL text' );
		$this->assertContains( self::TAINTED, $query['params'] );
	}
}
