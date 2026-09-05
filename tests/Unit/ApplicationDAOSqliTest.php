<?php
require_once 'classes/ApplicationDAO.php';
require_once 'classes/Filter.php';
require_once 'tests/Unit/support/RecordingDb.php';

/**
 * Regression test confirming ApplicationDAO::readApplicationIDsByFilters()
 * casts each $admin_org_venue_list key to (int) before splicing it into a
 * dynamic temporary-table name and join alias -- identifiers can't be bound
 * as query parameters, so this is the one spot in the method that needs an
 * explicit cast rather than a placeholder. $admin_org_venue_list is normally
 * server-computed from the session user's own permissions, not raw request
 * input, but the cast is cheap insurance against it ever carrying anything
 * else.
 *
 * @see ApplicationDAO::readApplicationIDsByFilters()
 */
final class ApplicationDAOSqliTest extends \PHPUnit\Framework\TestCase {

	public function testVenueIdKeyIsCastToIntBeforeUseAsAnIdentifier(): void {
		$db = new RecordingDb();
		$dao = new ApplicationDAO( $db );
		$filter = new Filter();
		$_SESSION['super_user'] = 0;

		// A non-numeric string key: PHP keeps this as a string array key (only
		// canonical integer-looking strings get auto-cast), so it reaches the
		// method exactly as an attacker-influenced value would.
		$maliciousVenueId = "5' OR '1'='1";
		$adminOrgVenueList = [ $maliciousVenueId => [ 1, 2, 3 ] ];

		$dao->readApplicationIDsByFilters( $filter, 1, $adminOrgVenueList, [], '', [] );

		$tempTableQueries = array_filter(
			$db->sqlStatements(),
			fn( $sql ) => str_starts_with( $sql, 'create temporary table user_orgids' )
		);
		$this->assertNotEmpty( $tempTableQueries, 'expected a CREATE TEMPORARY TABLE query to have been issued' );
		foreach ( $tempTableQueries as $sql ) {
			$this->assertStringNotContainsString( "'", $sql, 'temp table name must not carry the raw key through' );
			$this->assertMatchesRegularExpression( '/user_orgids\d+\s/', $sql, 'venue id must render as a plain integer' );
		}
	}
}
