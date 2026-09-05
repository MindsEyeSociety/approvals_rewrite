<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression tests confirming ModifyCharacterXPList3.php no longer builds its
 * lookup query from two unescaped $_GET values -- one of which (`table`) used
 * to be spliced in as the table name itself, not just a value, so no amount
 * of parameter binding alone could have fixed it. The fix binds `modify` as
 * a `?` parameter AND resolves `table` through a strict whitelist
 * ("earnedxp" or else "spentxp") before it ever reaches the SQL string.
 *
 * @see ModifyCharacterXPList3.php
 */
final class ModifyCharacterXPList3SqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED_ID = "1' OR '1'='1";
	private const TAINTED_TABLE = "spentxp' OR '1'='1";

	/** A tainted `modify` id is bound as a parameter against the legitimate "earnedxp" table. */
	public function testEarnedxpLookupBindsTaintedIdAsParameter(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPList3.php',
			get: [ 'table' => 'earnedxp', 'modify' => self::TAINTED_ID ],
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [ [ [
				'character_id' => '500',
				'eventname'    => 'Test Event',
				'earneddate'   => '2026-01-01',
				'xpearned'     => '5',
				'notes'        => 'Some notes',
			] ] ]
		);

		$lookups = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'select * from' )
		) );

		$this->assertCount( 1, $lookups );
		$this->assertSame( 'select * from earnedxp where id=?', $lookups[0]['sql'] );
		$this->assertSame( [ self::TAINTED_ID ], $lookups[0]['params'] );
	}

	/** An attempt to inject SQL via the `table` name itself is forced to the "spentxp" whitelist fallback, never spliced in raw. */
	public function testTaintedTableNameFallsBackToWhitelistedSpentxp(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPList3.php',
			get: [ 'table' => self::TAINTED_TABLE, 'modify' => '5' ],
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [ [ [
				'character_id' => '501',
				'itembought'   => 'Sword',
				'spentdate'    => '2026-02-01',
				'xpspent'      => '3',
				'notes'        => 'Bought weapon',
			] ] ]
		);

		$lookups = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'select * from' )
		) );

		$this->assertCount( 1, $lookups );
		$this->assertStringNotContainsString( self::TAINTED_TABLE, $lookups[0]['sql'], 'the table-name whitelist must reject the injected identifier entirely, not just escape it' );
		$this->assertSame( 'select * from spentxp where id=?', $lookups[0]['sql'] );
		$this->assertSame( [ '5' ], $lookups[0]['params'] );
	}
}
