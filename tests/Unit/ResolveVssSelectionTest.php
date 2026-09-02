<?php
require_once 'include/vss_selection.inc';

/**
 * Unit tests for resolveVssSelection(), covering the local-vs-total VSS
 * dropdown precedence rule, including the exact "local blank, total set"
 * scenario that caused a real production bug when this logic was inline.
 *
 * @see resolveVssSelection()
 */
final class ResolveVssSelectionTest extends \PHPUnit\Framework\TestCase {

	/** When local is blank and total is set, the total selection is used. */
	public function testBlankLocalUsesTotal(): void {
		$this->assertSame( "1312", resolveVssSelection( "", "1312" ) );
	}

	/** When local is set and total is blank, the local selection is used. */
	public function testSetLocalWithBlankTotalUsesLocal(): void {
		$this->assertSame( "-995", resolveVssSelection( "-995", "" ) );
	}

	/** When both fields are blank, no VSS was chosen. */
	public function testBothBlankReturnsZero(): void {
		$this->assertSame( 0, resolveVssSelection( "", "" ) );
	}

	/** When both fields are set, the local selection takes precedence. */
	public function testBothSetLocalTakesPrecedence(): void {
		$this->assertSame( "-995", resolveVssSelection( "-995", "1312" ) );
	}
}
