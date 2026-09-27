<?php
require_once 'classes/VSSDAO.class.php';
require_once 'tests/Unit/support/RecordingDb.php';

/**
 * Regression tests for VSSDAO's storyteller/org lookups used by the
 * escalation feature that climbs the organization hierarchy when a
 * storyteller's notification email is suppressed. Pins:
 * - getVSSOrgID() binds the vss id as a parameter rather than splicing it
 *   into the SQL text, and returns the org_id (or null, without a warning,
 *   when there is no matching row).
 * - getVSSSTID()'s null-guard: it returns null (without a warning) when
 *   there is no matching row, and is unaffected when a row does exist.
 *
 * @see VSSDAO::getVSSOrgID()
 * @see VSSDAO::getVSSSTID()
 */
final class VSSDAOOrgLookupTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** getVSSOrgID() binds the vss id as a parameter, never splicing it into the SQL text. */
	public function testGetVSSOrgIDBindsVssId(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new VSSDAO( $db );

		$dao->getVSSOrgID( self::TAINTED );

		$this->assertStringNotContainsString( self::TAINTED, $db->queries[0]['sql'] );
		$this->assertStringContainsString( 'id=?', $db->queries[0]['sql'] );
		$this->assertSame( [ self::TAINTED ], $db->queries[0]['params'] );
	}

	/** getVSSOrgID() returns the org_id when the row exists. */
	public function testGetVSSOrgIDReturnsOrgIdWhenRowExists(): void {
		$db = new RecordingDb( [ [ [ 'org_id' => 42 ] ] ] );
		$dao = new VSSDAO( $db );

		$this->assertSame( 42, $dao->getVSSOrgID( 7 ) );
	}

	/** getVSSOrgID() returns null, without a warning, when there is no such VSS. */
	public function testGetVSSOrgIDReturnsNullWhenRowMissing(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new VSSDAO( $db );

		$this->assertNull( $dao->getVSSOrgID( 7 ) );
	}

	/** getVSSSTID() returns null, without a warning, when there is no such VSS. */
	public function testGetVSSSTIDReturnsNullWhenRowMissing(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new VSSDAO( $db );

		$this->assertNull( $dao->getVSSSTID( 7 ) );
	}

	/** getVSSSTID() still returns the storyteller_id when the row exists. */
	public function testGetVSSSTIDReturnsStorytellerIdWhenRowExists(): void {
		$db = new RecordingDb( [ [ [ 'storyteller_id' => 99 ] ] ] );
		$dao = new VSSDAO( $db );

		$this->assertSame( 99, $dao->getVSSSTID( 7 ) );
	}
}
