<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming ModifyNPCList3.php's character lookup binds
 * the GET "modify" value as a query parameter rather than splicing it
 * directly into the SQL string. This mirrors ModifyCharacterList3.php's fix
 * -- it is the very first thing the page does, so a tainted "modify" value
 * used to reach the database unescaped on every visit to this page.
 *
 * @see ModifyNPCList3.php
 */
final class ModifyNPCList3SqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** The character lookup uses a "?" placeholder and binds the tainted value only as a parameter. */
	public function testCharacterLookupIsParameterized(): void {
		$result = PageHarness::run(
			'ModifyNPCList3.php',
			get: [ 'modify' => self::TAINTED ]
		);

		$this->assertNotEmpty( $result->queries, 'expected at least one query to be captured' );
		$query = $result->queries[0];
		$this->assertStringContainsString( 'c.ID=?', $query['sql'] );
		$this->assertStringNotContainsString( self::TAINTED, $query['sql'], 'the tainted value must not be spliced into the SQL text' );
		$this->assertContains( self::TAINTED, $query['params'] );
	}
}
