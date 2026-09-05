<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test for session_setup.inc's admin-level resolution: when a
 * storyteller row's organization_id no longer matches any real
 * `organizations` row (e.g. a deleted organization), the LEFT JOIN in
 * getStorytellerOrganizations() produces all-NULL org columns, so the
 * Organization built from that row has no globe/nation/region/domain/
 * chapter set and Organization::getLevel() legitimately returns null. The
 * page used to dereference `->organization_level` on that null value
 * directly, tripping "Attempt to read property ... on null" for both
 * admin_level and max_final_authority_level. The fix null-coalesces both
 * reads to '', matching the "no organization level" default this file
 * already uses elsewhere.
 *
 * session_setup.inc only runs when $_SESSION['user_id'] is set (db.inc
 * pulls it in automatically), so this exercises it via footerbar.inc --
 * one of the lightest pages that includes db.inc -- rather than calling it
 * directly.
 *
 * @see session_setup.inc
 */
final class SessionSetupNullOrgLevelWarningTest extends \PHPUnit\Framework\TestCase {

	public function testOrphanedStorytellerOrgDoesNotWarnAboutOrganizationLevel(): void {
		$result = PageHarness::run(
			'footerbar.inc',
			session: [ 'user_id' => '42' ],
			responses: [
				[], // UserInfoDAO::readApprovalsDBInfo(42) -- empty, so getUserInfo() takes its no-ww_number fallback path
				[], // UserInfoDAO::updateLastLoginDate(42) -- result unused
				// getStorytellerOrganizations(42): one storyteller row whose organization_id (5)
				// no longer matches a real organizations row -- the LEFT JOIN leaves every org
				// column NULL/empty, so Organization::getLevel() returns null for it.
				[ [
					'chapter' => '', 'domain' => '', 'region' => '', 'nation' => '', 'globe' => '',
					'organization_id' => '5', 'venue_id' => '0', 'assistant' => '0',
				] ],
				// Every query after this one (the admin-org-id lookup, the vss list query, the
				// VST/org-storyteller position queries, and footerbar's own cam_number lookup)
				// is left unqueued and gets an empty result automatically.
			]
		);

		$this->assertStringNotContainsString( 'Attempt to read property "organization_level" on null', $result->stderr );
	}
}
