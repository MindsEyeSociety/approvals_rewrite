<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming ModifyCharacterXPList2.php's bulk-delete branch
 * binds every id in $_POST["delete"] -- an attacker-controlled array -- as
 * its own `?` placeholder rather than splicing any of them into the SQL
 * text, including the trailing `id!=?` exclusion built from $_POST["modify"].
 *
 * @see ModifyCharacterXPList2.php
 */
final class ModifyCharacterXPList2SqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "2' OR '1'='1";

	/** Every id in the delete array -- including a tainted one -- is bound as a parameter, never spliced into the DELETE. */
	public function testBulkDeleteBindsEveryIdAsParameter(): void {
		$delete = [ '1', self::TAINTED, '3' ];

		$result = PageHarness::run(
			'ModifyCharacterXPList2.php',
			post: [ 'xptype' => 'earned', 'delete' => $delete, 'character_id' => '777' ]
		);

		$deletes = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'DELETE FROM' )
		) );

		$this->assertCount( 1, $deletes );
		$this->assertStringNotContainsString( self::TAINTED, $deletes[0]['sql'], 'the tainted id must not be spliced into the SQL text' );
		$this->assertSame( 'DELETE FROM earnedxp WHERE id IN (?,?,?) and id!=?', $deletes[0]['sql'] );
		$this->assertCount( count( $delete ) + 1, $deletes[0]['params'], 'one placeholder per deleted id, plus one for the id!=? exclusion' );
		$this->assertSame( array_merge( $delete, [ '' ] ), $deletes[0]['params'] );
	}
}
