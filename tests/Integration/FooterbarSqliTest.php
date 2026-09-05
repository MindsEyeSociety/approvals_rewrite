<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming footerbar.inc's "switch user" lookup binds
 * $_SESSION['cam_number'] as a query parameter rather than splicing it
 * directly into the SQL string. footerbar.inc is included at the bottom of
 * nearly every authenticated page, so this ran on almost every page view
 * before the fix.
 *
 * @see footerbar.inc
 */
final class FooterbarSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "O'Brien123";

	public function testCamNumberLookupIsParameterized(): void {
		$result = PageHarness::run(
			'footerbar.inc',
			session: [ 'cam_number' => self::TAINTED ],
			responses: [ [] ]
		);

		$this->assertCount( 1, $result->queries );
		$sql = $result->queries[0]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql, 'the tainted value must not be spliced into the SQL text' );
		$this->assertStringContainsString( 'ww_number=?', $sql );
		$this->assertSame( [ self::TAINTED ], $result->queries[0]['params'] );
	}
}
