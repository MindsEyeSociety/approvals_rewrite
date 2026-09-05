<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test for ModifyCharacterList3.php's ownership check: it
 * compares $character["user_id"] against $_SESSION['user_id'] before
 * header.inc's own login gate has run, so an unauthenticated request used
 * to trip "Undefined array key user_id". The fix wraps the read in
 * `?? null`, matching the existing "missing session key means no match"
 * semantics.
 *
 * @see ModifyCharacterList3.php
 */
final class ModifyCharacterList3SessionWarningTest extends \PHPUnit\Framework\TestCase {

	public function testNoSessionDoesNotWarnAboutUserId(): void {
		$result = PageHarness::run(
			'ModifyCharacterList3.php',
			get: [ 'modify' => '100' ],
			// No 'user_id' key at all -- simulates an anonymous visitor. The
			// admin_* lists are set only to avoid an unrelated fatal in
			// header.inc's menu builder (count() on a missing session key),
			// which would otherwise mask whether our target warning fired.
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [
				[ [
					'id' => '100', 'name' => 'Test Char', 'venue_id' => '5', 'venue' => 'Requiem',
					'subtype' => 'Ghoul', 'user_id' => '7', 'vss_id' => '3', 'org_id' => '1',
					'active' => '1', 'char_dead' => '0', 'character_sheet' => '', 'background' => '',
				] ],
				[], // VenueDAO::getVenueOptions() -- empty so no per-venue subtype queries follow
				[], // "select * from venues order by venue"
				[], // "select * from applications where character_id=...": empty avoids the numRows()>0 branch
			]
		);

		$this->assertStringNotContainsString( 'Undefined array key "user_id"', $result->stderr );
	}
}
