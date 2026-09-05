<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test for ModifyCharacterXPList1.php's ownership check: it reads
 * $_SESSION['user_id'] to decide whether the viewer owns the character
 * (`$this_user`) before header.inc's own login gate has run, so an
 * unauthenticated request -- mostly Googlebot, per production logs -- used
 * to trip "Undefined array key user_id". The fix wraps the read in
 * `?? null`, matching the existing "missing session key means no match"
 * semantics.
 *
 * @see ModifyCharacterXPList1.php
 */
final class ModifyCharacterXPList1SessionWarningTest extends \PHPUnit\Framework\TestCase {

	public function testNoSessionDoesNotWarnAboutUserId(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPList1.php',
			get: [ 'character_id' => '100' ],
			// No 'user_id' key at all -- simulates an anonymous visitor. The
			// admin_* lists are set only to avoid an unrelated fatal in
			// header.inc's menu builder (count() on a missing session key),
			// which would otherwise mask whether our target warning fired.
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [
				[ [ 'name' => 'Test Character', 'venue' => 'Requiem', 'subtype' => 'Ghoul', 'user_id' => '7' ] ],
				[],
				[],
			]
		);

		$this->assertStringNotContainsString( 'Undefined array key "user_id"', $result->stderr );
	}
}
