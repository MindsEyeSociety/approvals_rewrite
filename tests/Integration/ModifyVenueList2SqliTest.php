<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming ModifyVenueList2.php's bulk-delete branch binds
 * every id in $_POST["delete"] -- an attacker-controlled array -- as its own
 * `?` placeholder rather than splicing any of them into the SQL text.
 *
 * @see ModifyVenueList2.php
 */
final class ModifyVenueList2SqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "20' OR '1'='1";

	/** Every id in the delete array -- including a tainted one -- is bound as a parameter, never spliced into the DELETE. */
	public function testBulkDeleteBindsEveryIdAsParameter(): void {
		$delete = [ '10', self::TAINTED, '30' ];

		$result = PageHarness::run(
			'ModifyVenueList2.php',
			post: [ 'delete' => $delete, 'modify' => '999' ]
		);

		$deletes = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'DELETE FROM venues' )
		) );

		$this->assertCount( 1, $deletes );
		$this->assertStringNotContainsString( self::TAINTED, $deletes[0]['sql'], 'the tainted id must not be spliced into the SQL text' );
		$this->assertSame( 'DELETE FROM venues WHERE id IN (?,?,?)', $deletes[0]['sql'] );
		$this->assertSame( $delete, $deletes[0]['params'] );
	}
}
