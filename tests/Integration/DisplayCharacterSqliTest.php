<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming DisplayCharacter.php binds the tainted GET
 * `char_id` as a `?` parameter in both places it drives a query: the main
 * character lookup (alongside $_SESSION['user_id']) and the activity-log
 * INSERT fired when the viewer isn't the character's owner.
 *
 * Setting $_SESSION['user_id'] also pulls in db.inc's own session_setup.inc
 * bootstrap as a side effect, which issues five queries of its own first --
 * hence the five leading empty placeholders in `responses` before the real
 * character row, and why the assertions filter `->queries` for the specific
 * statements rather than assuming a fixed index.
 *
 * @see DisplayCharacter.php
 */
final class DisplayCharacterSqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** Both the character lookup and the activity-log insert bind the tainted char_id only via params. */
	public function testCharacterLookupAndActivityLogInsertBindTaintedCharId(): void {
		$characterRow = [
			'id'               => '555',
			'name'             => 'Test Character',
			'venue'            => 'Test Venue',
			'venue_id'         => '5',
			'char_type'        => 'PC',
			'vss_id'           => '0',
			'vss_name'         => '',
			'org_name'         => '',
			'approved_in_vss'  => '0',
			'subtype'          => 'Vampire',
			'active'           => '1',
			'char_dead'        => '0',
			'character_sheet'  => 'Sheet body text',
			'background'       => 'Background body text',
			'user_id'          => '1', // deliberately different from the session's viewer id, so the activity-log insert branch fires
			'org_id'           => '0',
		];

		$result = PageHarness::run(
			'DisplayCharacter.php',
			get: [ 'char_id' => self::TAINTED ],
			session: [ 'user_id' => 99, 'admin_vss_list' => [], 'admin_org_list' => [] ],
			responses: [ [], [], [], [], [], [ $characterRow ] ]
		);

		$lookups = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'select c.*,' )
		) );
		$inserts = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'INSERT INTO activity_log' )
		) );

		$this->assertCount( 1, $lookups, 'expected exactly one character lookup query' );
		$this->assertStringNotContainsString( self::TAINTED, $lookups[0]['sql'] );
		$this->assertStringContainsString( 'c.id=?', $lookups[0]['sql'] );
		$this->assertStringContainsString( 'u.id=?', $lookups[0]['sql'] );
		$this->assertSame( [ self::TAINTED, 99 ], $lookups[0]['params'] );

		$this->assertCount( 1, $inserts, 'expected the activity-log insert to have fired' );
		$this->assertStringNotContainsString( self::TAINTED, $inserts[0]['sql'] );
		$this->assertSame( [ self::TAINTED, 99 ], $inserts[0]['params'] );
	}
}
