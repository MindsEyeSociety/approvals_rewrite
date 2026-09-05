<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test confirming ModifyOrgList3.php's two organization lookups
 * both bind the GET "modify" value as a query parameter rather than
 * splicing it directly into the SQL string.
 *
 * @see ModifyOrgList3.php
 */
final class ModifyOrgList3SqliTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** Both organization lookups use a "?" placeholder and bind the tainted value only as a parameter. */
	public function testOrganizationLookupsAreParameterized(): void {
		// A non-empty row is needed at both lookup sites: the page redirects
		// (and, at the second site, echoes an error and exits) if !$row.
		$orgRow = [
			'id' => 5,
			'nation' => '',
			'region' => '',
			'domain' => '',
			'chapter' => '',
			'org_name' => 'Test Org',
			'city' => '',
			'state' => '',
			'country' => '',
			'email' => '',
			'email_is_google' => 0,
			'admin_user_id' => 0,
			'active' => 1,
		];

		$result = PageHarness::run(
			'ModifyOrgList3.php',
			get: [ 'modify' => self::TAINTED ],
			session: [
				// Bypasses the "Super user OR assigned org admin" permission
				// check, which would otherwise redirect before either query runs.
				'super_user' => 1,
				// header.inc's generateMenus() calls count() on these two
				// session keys unconditionally; leaving them unset is a fatal
				// TypeError under PHP 8, and header.inc runs before the queries.
				'admin_org_list' => [],
				'admin_vss_list' => [],
			],
			responses: [ [ $orgRow ], [ $orgRow ] ]
		);

		$orgQueries = array_values( array_filter(
			$result->queries,
			fn( $q ) => str_contains( $q['sql'], 'where id=?' )
		) );

		$this->assertCount( 2, $orgQueries, 'expected both organization lookups to run' );
		foreach ( $orgQueries as $query ) {
			$this->assertStringNotContainsString( self::TAINTED, $query['sql'], 'the tainted value must not be spliced into the SQL text' );
			$this->assertContains( self::TAINTED, $query['params'] );
		}
	}
}
