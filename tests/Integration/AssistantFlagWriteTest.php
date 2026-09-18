<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression tests for UserDisplayRecordAction.php's storyteller write: the
 * `assistant` flag used to be forced to 1 whenever a venue_id was posted,
 * regardless of whether the assistant checkbox was actually checked, making
 * it impossible to ever record a primary Venue Storyteller. The fix derives
 * the flag from the checkbox alone. The write is also now idempotent: a
 * DELETE for the same (user_id, organization_id, venue_id) precedes the
 * INSERT so re-saving a position cannot create a duplicate row. Also covers
 * a follow-on guard: `organizations.admin_user_id` is the ORG's storyteller
 * (DST/RST/NST), so the UPDATE that sets it must only fire for an org-wide
 * primary, never for a primary Venue Storyteller -- the original bug made
 * that case unreachable, and fixing the assistant flag alone would have
 * newly exposed it.
 *
 * @see UserDisplayRecordAction.php
 */
final class AssistantFlagWriteTest extends \PHPUnit\Framework\TestCase {

	private function insertQuery( array $queries ): ?array {
		foreach ( $queries as $q ) {
			if ( str_starts_with( $q['sql'], 'INSERT into storytellers' ) ) {
				return $q;
			}
		}
		return null;
	}

	private function deleteQuery( array $queries ): ?array {
		foreach ( $queries as $q ) {
			if ( str_starts_with( $q['sql'], 'DELETE from storytellers' ) ) {
				return $q;
			}
		}
		return null;
	}

	private function adminUserIdUpdateQuery( array $queries ): ?array {
		foreach ( $queries as $q ) {
			if ( str_starts_with( $q['sql'], 'UPDATE organizations SET admin_user_id' ) ) {
				return $q;
			}
		}
		return null;
	}

	/** A venue-scoped position saved with the assistant checkbox absent inserts assistant = 0. */
	public function testVenueScopedPositionWithoutCheckboxInsertsPrimary(): void {
		$result = PageHarness::run(
			'UserDisplay.php',
			post: [
				'mode' => 'doEdit',
				'id' => '10',
				'org_id' => '1',
				'admin_org_id' => '2',
				'venue_id' => '3',
			]
		);

		$insert = $this->insertQuery( $result->queries );
		$this->assertNotNull( $insert, 'expected an INSERT into storytellers query' );
		$this->assertSame(
			'INSERT into storytellers (organization_id, venue_id, user_id, assistant) values (?, ?, ?, ?)',
			$insert['sql']
		);
		$this->assertSame( [ '2', '3', '10', 0 ], $insert['params'] );
	}

	/** A venue-scoped position saved with the assistant checkbox present inserts assistant = 1. */
	public function testVenueScopedPositionWithCheckboxInsertsAssistant(): void {
		$result = PageHarness::run(
			'UserDisplay.php',
			post: [
				'mode' => 'doEdit',
				'id' => '10',
				'org_id' => '1',
				'admin_org_id' => '2',
				'venue_id' => '3',
				'assistant' => 'on',
			]
		);

		$insert = $this->insertQuery( $result->queries );
		$this->assertNotNull( $insert, 'expected an INSERT into storytellers query' );
		$this->assertSame( [ '2', '3', '10', 1 ], $insert['params'] );
	}

	/** An org-wide position (no venue_id) saved with the checkbox absent still inserts assistant = 0. */
	public function testOrgWidePositionWithoutCheckboxInsertsPrimary(): void {
		$result = PageHarness::run(
			'UserDisplay.php',
			post: [
				'mode' => 'doEdit',
				'id' => '10',
				'org_id' => '1',
				'admin_org_id' => '2',
				'venue_id' => '',
			]
		);

		$insert = $this->insertQuery( $result->queries );
		$this->assertNotNull( $insert, 'expected an INSERT into storytellers query' );
		$this->assertSame( [ '2', '', '10', 0 ], $insert['params'] );
	}

	/** Saving a position issues a DELETE for the same (user_id, organization_id, venue_id) before the INSERT. */
	public function testSavingPositionDeletesExistingRowBeforeInserting(): void {
		$result = PageHarness::run(
			'UserDisplay.php',
			post: [
				'mode' => 'doEdit',
				'id' => '10',
				'org_id' => '1',
				'admin_org_id' => '2',
				'venue_id' => '3',
			]
		);

		$delete = $this->deleteQuery( $result->queries );
		$this->assertNotNull( $delete, 'expected a DELETE from storytellers query' );
		$this->assertSame(
			'DELETE from storytellers where user_id=? and organization_id=? and venue_id=?',
			$delete['sql']
		);
		$this->assertSame( [ '10', '2', '3' ], $delete['params'] );

		$deleteIndex = array_search( $delete, $result->queries, true );
		$insertIndex = array_search( $this->insertQuery( $result->queries ), $result->queries, true );
		$this->assertLessThan( $insertIndex, $deleteIndex, 'the DELETE must run before the INSERT' );
	}

	/** A venue-scoped primary (checkbox unticked) must not overwrite the org's admin_user_id. */
	public function testVenueScopedPrimaryDoesNotUpdateOrgAdminUserId(): void {
		$result = PageHarness::run(
			'UserDisplay.php',
			post: [
				'mode' => 'doEdit',
				'id' => '10',
				'org_id' => '1',
				'admin_org_id' => '2',
				'venue_id' => '3',
			]
		);

		$this->assertNull(
			$this->adminUserIdUpdateQuery( $result->queries ),
			'a venue-scoped primary must not touch organizations.admin_user_id'
		);
	}

	/** An org-wide primary (no venue_id, checkbox unticked) still updates the org's admin_user_id. */
	public function testOrgWidePrimaryUpdatesOrgAdminUserId(): void {
		$result = PageHarness::run(
			'UserDisplay.php',
			post: [
				'mode' => 'doEdit',
				'id' => '10',
				'org_id' => '1',
				'admin_org_id' => '2',
				'venue_id' => '',
			]
		);

		$update = $this->adminUserIdUpdateQuery( $result->queries );
		$this->assertNotNull( $update, 'expected an UPDATE organizations SET admin_user_id query' );
		$this->assertSame( 'UPDATE organizations SET admin_user_id = ? WHERE id = ?', $update['sql'] );
		$this->assertSame( [ '10', '2' ], $update['params'] );
	}
}
