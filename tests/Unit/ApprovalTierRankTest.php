<?php
require_once 'AppDetails_functions.php';

/**
 * Unit tests for approvalTierRank() and approvalTierName(), covering the
 * string-equality regression where `required_approval == "<Tier>"` never
 * matched once an application had been pushed past its required tier, and
 * pinning down the deliberate "unrecognised input ranks as Global" safety
 * property that keeps the 93 blank, 1 corrupted, and 1 malformed
 * `required_approval` rows in production behaving exactly as they do today.
 *
 * @see approvalTierRank()
 * @see approvalTierName()
 */
final class ApprovalTierRankTest extends \PHPUnit\Framework\TestCase {

	/** Low maps to rank 1. */
	public function testLowRanksOne(): void {
		$this->assertSame( 1, approvalTierRank( "Low" ) );
	}

	/** Mid maps to rank 2. */
	public function testMidRanksTwo(): void {
		$this->assertSame( 2, approvalTierRank( "Mid" ) );
	}

	/** High maps to rank 3. */
	public function testHighRanksThree(): void {
		$this->assertSame( 3, approvalTierRank( "High" ) );
	}

	/** Top maps to rank 4. */
	public function testTopRanksFour(): void {
		$this->assertSame( 4, approvalTierRank( "Top" ) );
	}

	/** Global maps to rank 5. */
	public function testGlobalRanksFive(): void {
		$this->assertSame( 5, approvalTierRank( "Global" ) );
	}

	/** Lowercase input is normalized. */
	public function testLowercaseIsNormalized(): void {
		$this->assertSame( 1, approvalTierRank( "low" ) );
	}

	/** Uppercase input is normalized. */
	public function testUppercaseIsNormalized(): void {
		$this->assertSame( 5, approvalTierRank( "GLOBAL" ) );
	}

	/** Surrounding whitespace is trimmed. */
	public function testSurroundingWhitespaceIsTrimmed(): void {
		$this->assertSame( 3, approvalTierRank( " High " ) );
	}

	/** An empty string, seen on 93 production rows, ranks as Global, not Low. */
	public function testEmptyStringRanksAsGlobal(): void {
		$this->assertSame( 5, approvalTierRank( "" ) );
	}

	/** A malformed value like "High?" ranks as Global rather than matching "High". */
	public function testMalformedHighQuestionMarkRanksAsGlobal(): void {
		$this->assertSame( 5, approvalTierRank( "High?" ) );
	}

	/**
	 * A mojibake-corrupted "High" with a trailing non-breaking space ranks as
	 * Global: trim() does not strip U+00A0, so this never matches "high".
	 */
	public function testMojibakeCorruptedHighRanksAsGlobal(): void {
		$this->assertSame( 5, approvalTierRank( "High\xC2\xA0" ) );
	}

	/** Null input ranks as Global without triggering a deprecation notice. */
	public function testNullRanksAsGlobal(): void {
		$this->assertSame( 5, approvalTierRank( null ) );
	}

	/** A full status string like "Pending High" ranks as Global, not High. */
	public function testPendingHighStatusStringRanksAsGlobal(): void {
		$this->assertSame( 5, approvalTierRank( "Pending High" ) );
	}

	/** A bare numeric string ranks as Global. */
	public function testNumericStringRanksAsGlobal(): void {
		$this->assertSame( 5, approvalTierRank( "3" ) );
	}

	/** "Globe" (the office-level name, not the tier name) ranks as Global. */
	public function testGlobeRanksAsGlobal(): void {
		$this->assertSame( 5, approvalTierRank( "Globe" ) );
	}

	/** Global's rank equals the rank given to unrecognised input. */
	public function testGlobalRankEqualsUnrecognisedRank(): void {
		$this->assertSame( approvalTierRank( "not a tier" ), approvalTierRank( "Global" ) );
	}

	/** Global is the maximum rank among the five canonical tiers. */
	public function testGlobalIsMaximumOfCanonicalTiers(): void {
		$ranks = array_map( 'approvalTierRank', array( "Low", "Mid", "High", "Top", "Global" ) );
		$this->assertSame( 5, max( $ranks ) );
		$this->assertSame( 5, approvalTierRank( "Global" ) );
	}

	/** approvalTierName() round-trips through approvalTierRank() for each canonical tier. */
	public function testRoundTripThroughRankAndName(): void {
		foreach ( array( "Low", "Mid", "High", "Top", "Global" ) as $tier ) {
			$this->assertSame( $tier, approvalTierName( approvalTierRank( $tier ) ) );
		}
	}

	/** An out-of-range rank returns "Global" from approvalTierName(). */
	public function testOutOfRangeRankNamesAsGlobal(): void {
		$this->assertSame( "Global", approvalTierName( 0 ) );
		$this->assertSame( "Global", approvalTierName( 6 ) );
	}
}
