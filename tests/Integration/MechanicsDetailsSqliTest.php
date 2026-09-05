<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming MechanicsDetails.php's mechanic lookup binds
 * the GET "id" value as a query parameter rather than splicing it directly
 * into the SQL string.
 *
 * @see MechanicsDetails.php
 */
final class MechanicsDetailsSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** The mechanic lookup uses a "?" placeholder and binds the tainted value only as a parameter, among other unrelated queries the page issues first. */
	public function testMechanicLookupIsParameterized(): void {
		$result = PageHarness::run(
			'MechanicsDetails.php',
			get: [ 'id' => self::TAINTED ],
			// header.inc's generateMenus() calls count() on these two session
			// keys unconditionally; leaving them unset is a fatal TypeError
			// under PHP 8, and header.inc runs before the mechanic query.
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ]
		);

		$mechanicQueries = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_contains( $q['sql'], 'm.id=?' )
		) );

		$this->assertCount( 1, $mechanicQueries, 'expected exactly one mechanic lookup query' );
		$this->assertStringNotContainsString( self::TAINTED, $mechanicQueries[0]['sql'], 'the tainted value must not be spliced into the SQL text' );
		$this->assertContains( self::TAINTED, $mechanicQueries[0]['params'] );
	}
}
