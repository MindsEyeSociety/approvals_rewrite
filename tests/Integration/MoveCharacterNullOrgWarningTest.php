<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test for MoveCharacter.php's handling of a session org_id that
 * no longer resolves to a real organization (e.g. a deleted org):
 * OrganizationDAO::readById() returns null in that case, but the page used
 * to dereference $userOrganization->globe/nation/region/domain/chapter
 * directly when building both VSS/org picker lists, tripping
 * "Attempt to read property ... on null" five times per call. The fix
 * null-coalesces each property read to null, which
 * VSSDAO::readLocalVenueVSSs()/OrganizationDAO::readLocalOrganizations()
 * already treat as "no location constraint".
 *
 * @see MoveCharacter.php
 */
final class MoveCharacterNullOrgWarningTest extends \PHPUnit\Framework\TestCase {

	public function testUnresolvableSessionOrgIdDoesNotWarn(): void {
		$result = PageHarness::run(
			'MoveCharacter.php',
			get: [ 'char_id' => '100' ],
			// org_id 999 deliberately matches no row, so the organizations
			// lookup below comes back empty and readById() returns null.
			// The admin_* lists are set only to avoid an unrelated fatal in
			// header.inc's menu builder (count() on a missing session key).
			session: [ 'org_id' => '999', 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [
				[ [
					'id' => '100', 'name' => 'Test Char', 'venue_id' => '5', 'subtype' => 'Ghoul',
					'user_id' => '7', 'char_type' => 'PC', 'org_id' => '1', 'vss_id' => '3',
					'background' => '', 'character_sheet' => '', 'active' => '1', 'char_dead' => '0',
					'last_updated' => '2026-01-01', 'approved_in_vss' => '1',
				] ],
				[], // OrganizationDAO::readById(999) -- empty result, so $userOrganization is null
				[], // VSSDAO::readLocalVenueVSSs()
				[], // OrganizationDAO::readLocalOrganizations()
			]
		);

		$this->assertStringNotContainsString( 'Attempt to read property', $result->stderr );
	}
}
