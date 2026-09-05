<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test for ModifyCharacterXPList3.php's "modify entry" form
 * pre-fill: it used to read the date via MySQL's UNIX_TIMESTAMP(), which
 * converts assuming the connection's (unset, so server-default UTC)
 * session time_zone into an epoch, then formats that epoch under the app's
 * fixed America/Los_Angeles timezone -- shifting the pre-filled date back
 * one calendar day. The fix reads the plain date string and formats it with
 * strtotime()+strftime(), never involving MySQL's timezone at all.
 *
 * @see ModifyCharacterXPList3.php
 */
final class ModifyCharacterXPList3DateTest extends \PHPUnit\Framework\TestCase {

	private const STORED_DATE = '2026-09-01';

	public function testEarnedDatePrefillShowsTheStoredCalendarDayNotOneEarlier(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPList3.php',
			get: [ 'modify' => '1', 'table' => 'earnedxp' ],
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [
				[ [ 'id' => '1', 'character_id' => '100', 'eventname' => 'Test Event', 'earneddate' => self::STORED_DATE, 'xpearned' => '5', 'notes' => '' ] ],
			]
		);

		$expected = strftime( '%x', strtotime( self::STORED_DATE ) );
		$wrong = strftime( '%x', strtotime( '2026-08-31' ) );

		$this->assertStringContainsString( $expected, $result->output );
		$this->assertStringNotContainsString( $wrong, $result->output );
	}

	public function testSpentDatePrefillShowsTheStoredCalendarDayNotOneEarlier(): void {
		$result = PageHarness::run(
			'ModifyCharacterXPList3.php',
			get: [ 'modify' => '2', 'table' => 'spentxp' ],
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [
				[ [ 'id' => '2', 'character_id' => '100', 'itembought' => 'Test Item', 'spentdate' => self::STORED_DATE, 'xpspent' => '3', 'notes' => '' ] ],
			]
		);

		$expected = strftime( '%x', strtotime( self::STORED_DATE ) );
		$wrong = strftime( '%x', strtotime( '2026-08-31' ) );

		$this->assertStringContainsString( $expected, $result->output );
		$this->assertStringNotContainsString( $wrong, $result->output );
	}
}
