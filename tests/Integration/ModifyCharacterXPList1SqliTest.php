<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression tests confirming ModifyCharacterXPList1.php's `earnedsort` and
 * `spentsort` GET values -- spliced directly into an ORDER BY clause as
 * column names, so they can never be `?`-parameterized -- are resolved
 * through a strict whitelist before use: an unrecognized value falls back to
 * the safe default column, while a real, whitelisted column name still
 * passes through untouched.
 *
 * @see ModifyCharacterXPList1.php
 */
final class ModifyCharacterXPList1SqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED_EARNEDSORT = "eventname; DROP TABLE users";
	private const TAINTED_SPENTSORT = "itembought; DROP TABLE users";

	private const CHARACTER_ROW = [
		'name'    => 'Test Character',
		'subtype' => 'Vampire',
		'venue'   => 'Test Venue',
		'user_id' => '1',
	];

	/** A non-whitelisted earnedsort/spentsort falls back to the default column, never appearing in the ORDER BY clause. */
	public function testUnrecognizedSortValuesFallBackToDefaultColumns(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPList1.php',
			get: [
				'character_id' => '123',
				'earnedsort'   => self::TAINTED_EARNEDSORT,
				'spentsort'    => self::TAINTED_SPENTSORT,
			],
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [ [ self::CHARACTER_ROW ] ]
		);

		$this->assertFalse( $result->anyQueryContains( self::TAINTED_EARNEDSORT ) );
		$this->assertFalse( $result->anyQueryContains( self::TAINTED_SPENTSORT ) );
		$this->assertTrue( $result->anyQueryContains( 'from earnedxp e where character_id=\'123\' order by earneddate desc' ) );
		$this->assertTrue( $result->anyQueryContains( 'from spentxp s where character_id=\'123\' order by spentdate desc' ) );
	}

	/** A legitimate, whitelisted sort column is honored verbatim in the ORDER BY clause -- the whitelist doesn't just always fall back. */
	public function testWhitelistedSortValuesAreHonoredVerbatim(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPList1.php',
			get: [
				'character_id' => '123',
				'earnedsort'   => 'xpearned',
				'spentsort'    => 'itembought',
			],
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [ [ self::CHARACTER_ROW ] ]
		);

		$this->assertTrue( $result->anyQueryContains( 'from earnedxp e where character_id=\'123\' order by xpearned asc' ) );
		$this->assertTrue( $result->anyQueryContains( 'from spentxp s where character_id=\'123\' order by itembought asc' ) );
	}
}
