<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming db.inc's userValidForNumber() binds $camnum as
 * a query parameter rather than splicing it into the SQL string.
 * userValidForNumber() has no caller anywhere in the app today, so this runs
 * it via a small test-only fixture (tests/Integration/fixtures/call_userValidForNumber.php)
 * that includes the real db.inc and calls the real function.
 *
 * @see userValidForNumber() in db.inc
 */
final class DbIncSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "x' OR '1'='1";

	public function testUserValidForNumberBindsCamNumber(): void {
		$result = PageHarness::run(
			'tests/Integration/fixtures/call_userValidForNumber.php',
			get: [ 'id' => '7', 'camnum' => self::TAINTED ],
			responses: [ [] ]
		);

		$this->assertCount( 1, $result->queries );
		$sql = $result->queries[0]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertStringContainsString( 'u.id=?', $sql );
		$this->assertStringContainsString( "u.ww_number=?", $sql );
		$this->assertSame( [ '7', self::TAINTED ], $result->queries[0]['params'] );
	}
}
