<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming EventsDetails.php's event lookup binds the GET
 * "id" value as a query parameter rather than splicing it directly into the
 * SQL string. The lookup only runs when "id" is non-empty, so the SQLi
 * payload used here (which is non-empty) doubles as the exercised value.
 *
 * @see EventsDetails.php
 */
final class EventsDetailsSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** The event lookup uses a "?" placeholder and binds the tainted value only as a parameter, among other unrelated queries the page issues afterward. */
	public function testEventLookupIsParameterized(): void {
		$result = PageHarness::run(
			'EventsDetails.php',
			get: [ 'id' => self::TAINTED ],
			// header.inc's generateMenus() calls count() on these two session
			// keys unconditionally; leaving them unset is a fatal TypeError
			// under PHP 8, and header.inc runs before the event query.
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ]
		);

		$eventQueries = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_contains( $q['sql'], 'e.id=?' )
		) );

		$this->assertCount( 1, $eventQueries, 'expected exactly one event lookup query' );
		$this->assertStringNotContainsString( self::TAINTED, $eventQueries[0]['sql'], 'the tainted value must not be spliced into the SQL text' );
		$this->assertContains( self::TAINTED, $eventQueries[0]['params'] );
	}
}
