<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression tests confirming MoveCharacter2.php's organizations/vsss
 * storyteller lookup -- chosen by the sign of the resolved VSS selection --
 * binds that id as a `?` parameter instead of splicing it into the SQL
 * text. resolveVssSelection() (include/vss_selection.inc) always returns
 * form input as a plain string and this fix casts it with (int) before use,
 * so this particular value can't actually carry an injection payload in
 * practice; the point of these tests is to prove the identifier position is
 * safe regardless, and that the sign correctly routes to the right table.
 *
 * Setting $_SESSION['user_id'] also pulls in db.inc's own session_setup.inc
 * bootstrap as a side effect, which issues five queries of its own first --
 * hence the five leading empty placeholders in `responses` before the
 * character row -- and this page's own already-parameterized `characters`
 * lookup runs before the query under test, so the assertions filter
 * `->queries` for the specific statement rather than assuming a fixed index.
 *
 * @see MoveCharacter2.php
 */
final class MoveCharacter2SqliTest extends \PHPUnit\Framework\TestCase {

	private const CHARACTER_ROW = [
		'player_id' => '0', // 0 => NPC, so the player lookup branch is skipped and the test stays focused on the fixed query
		'vss_id'    => '0',
		'name'      => 'Test Character',
		'subtype'   => 'Vampire',
	];

	/** A negative resolved VSS selection (a local-org pick) looks up the organization's admin user, binding the negated id as a parameter. */
	public function testNegativeVssSelectionQueriesOrganizationsWithBoundId(): void {
		$result = PageHarness::run(
			'MoveCharacter2.php',
			post: [ 'localvss_id' => '-995', 'totalvss_id' => '', 'char_id' => '500', 'redirect' => '' ],
			session: [ 'user_id' => 42 ],
			responses: [ [], [], [], [], [], [ self::CHARACTER_ROW ] ]
		);

		$lookups = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'SELECT u.* FROM organizations' )
		) );

		$this->assertCount( 1, $lookups );
		$this->assertSame( 'SELECT u.* FROM organizations o LEFT JOIN users u ON u.id = o.admin_user_id WHERE o.id=?', $lookups[0]['sql'] );
		$this->assertSame( [ 995 ], $lookups[0]['params'] );
	}

	/** A positive resolved VSS selection (a total-org pick) looks up the VSS's storyteller, binding the id as a parameter. */
	public function testPositiveVssSelectionQueriesVsssWithBoundId(): void {
		$result = PageHarness::run(
			'MoveCharacter2.php',
			post: [ 'localvss_id' => '', 'totalvss_id' => '1312', 'char_id' => '500', 'redirect' => '' ],
			session: [ 'user_id' => 42 ],
			responses: [ [], [], [], [], [], [ self::CHARACTER_ROW ] ]
		);

		$lookups = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_starts_with( $q['sql'], 'SELECT u.* FROM vsss' )
		) );

		$this->assertCount( 1, $lookups );
		$this->assertSame( 'SELECT u.* FROM vsss v LEFT JOIN users u ON u.id = v.storyteller_id WHERE v.id=?', $lookups[0]['sql'] );
		$this->assertSame( [ 1312 ], $lookups[0]['params'] );
	}
}
