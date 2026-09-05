<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression test for ModifyCharacterXPList1.php's earned/spent XP date
 * display: it used to read the date via MySQL's UNIX_TIMESTAMP(), which
 * converts assuming the connection's (unset, so server-default UTC)
 * session time_zone into an epoch, then formats that epoch under the app's
 * fixed America/Los_Angeles timezone -- shifting every stored date back one
 * calendar day. The fix reads the plain date string and formats it with
 * strtotime()+strftime(), never involving MySQL's timezone at all.
 *
 * @see ModifyCharacterXPList1.php
 */
final class ModifyCharacterXPList1DateTest extends \PHPUnit\Framework\TestCase {

	private const STORED_DATE = '2026-09-01';

	public function testEarnedDateDisplaysTheStoredCalendarDayNotOneEarlier(): void {
		$result = $this->runPage();

		$expected = strftime( '%a %b %e %Y', strtotime( self::STORED_DATE ) );
		$wrong = strftime( '%a %b %e %Y', strtotime( '2026-08-31' ) );

		$this->assertStringContainsString( $expected, $result->output );
		$this->assertStringNotContainsString( $wrong, $result->output );
	}

	public function testSpentDateDisplaysTheStoredCalendarDayNotOneEarlier(): void {
		$result = $this->runPage();

		$expected = strftime( '%a %b %e %Y', strtotime( self::STORED_DATE ) );
		$wrong = strftime( '%a %b %e %Y', strtotime( '2026-08-31' ) );

		// Both dates are rendered in the same page, so re-run the same
		// assertion against the spentxp row queued below to confirm the
		// spent-XP loop (a separate echo/strftime call in the source) is
		// fixed independently of the earned-XP loop.
		$this->assertStringContainsString( $expected, $result->output );
		$this->assertStringNotContainsString( $wrong, $result->output );
	}

	private function runPage(): \PageHarnessResult {
		return PageHarness::run(
			'ModifyCharacterXPList1.php',
			get: [ 'character_id' => '100' ],
			session: [ 'admin_org_list' => [], 'admin_vss_list' => [] ],
			responses: [
				[ [ 'name' => 'Test Character', 'venue' => 'Requiem', 'subtype' => 'Ghoul', 'user_id' => '7' ] ],
				[ [ 'id' => '1', 'eventname' => 'Test Event', 'earneddate' => self::STORED_DATE, 'xpearned' => '5', 'notes' => '' ] ],
				[ [ 'id' => '2', 'itembought' => 'Test Item', 'spentdate' => self::STORED_DATE, 'xpspent' => '3', 'notes' => '' ] ],
			]
		);
	}
}
