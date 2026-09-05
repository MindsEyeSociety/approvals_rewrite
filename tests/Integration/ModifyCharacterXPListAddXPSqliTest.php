<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming both branches of ModifyCharacterXPListAddXP.php
 * bind their POST fields as query parameters. The spent-XP branch previously
 * interpolated $_POST[itembought/xpspent/notes/character_id] into the SQL
 * string with no escaping at all -- confirmed causing production fatals on
 * any apostrophe in itembought/notes (e.g. a note containing "Doesn't Fit
 * the Character..."). The earned-XP branch avoided crashing via
 * sprintf()+$db->escape() but is now converted to match.
 *
 * @see ModifyCharacterXPListAddXP.php
 */
final class ModifyCharacterXPListAddXPSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "Doesn't Fit the Character";

	public function testSpentXpInsertBindsAllFields(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPListAddXP.php',
			post: [
				'table'        => 'spentxp',
				'spentdate'    => '2026-09-05',
				'itembought'   => self::TAINTED,
				'xpspent'      => '2',
				'notes'        => self::TAINTED,
				'character_id' => '36379',
			]
		);

		$this->assertCount( 1, $result->queries );
		$sql = $result->queries[0]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertStringStartsWith( 'insert into spentxp', $sql );
		$this->assertSame(
			[ self::TAINTED, '2026-09-05', '2', self::TAINTED, '36379' ],
			$result->queries[0]['params']
		);
	}

	public function testEarnedXpInsertBindsAllFields(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPListAddXP.php',
			post: [
				'table'        => 'earnedxp',
				'earneddate'   => '2026-09-05',
				'eventname'    => self::TAINTED,
				'xpearned'     => '3',
				'notes'        => self::TAINTED,
				'character_id' => '36379',
			]
		);

		$this->assertCount( 1, $result->queries );
		$sql = $result->queries[0]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertStringStartsWith( 'insert into earnedxp', $sql );
		$this->assertSame(
			[ self::TAINTED, '2026-09-05', '3', self::TAINTED, '36379' ],
			$result->queries[0]['params']
		);
	}
}
