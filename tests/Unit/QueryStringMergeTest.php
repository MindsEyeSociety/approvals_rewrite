<?php
require_once 'include/url.inc';

/**
 * Unit tests for queryStringMerge(), covering the "Show All Details" outage:
 * CommentShowThreads.php used to build its toggle links by concatenating a
 * hand-appended "showdetails=1&" directly onto the raw incoming query
 * string, assuming it always ended in "&". A link arriving as
 * "AppDetails.php?id=57082" (no trailing "&") became
 * "id=57082showdetails=1&", which PHP parsed as id="57082showdetails=1" —
 * "showdetails" was never set, MySQL silently coerced the id back to
 * 57082, and the page rendered normally with no visible error. A second
 * defect in the old code, `str_replace("showdetails=1&", ...)`, only
 * matched when the flag was followed by "&", so "Hide Details" failed
 * whenever the flag landed last in the string. Also covers the documented
 * parity with PHP's own $_GET parsing rules and a stochastic no-injection
 * property.
 *
 * @see queryStringMerge()
 */
final class QueryStringMergeTest extends \PHPUnit\Framework\TestCase {

	/** Appending a new param to a query string with no trailing "&" does not corrupt the preceding param. */
	public function testAppendingToQueryStringWithNoTrailingAmpersandDoesNotCorruptPrecedingParam(): void {
		$result = queryStringMerge( "id=57082", array( "showdetails" => 1 ) );
		$this->assertSame( "id=57082&showdetails=1", $result );
		$this->assertStringNotContainsString( "57082showdetails", $result );
	}

	/** A trailing "&" on the input does not produce an empty parameter. */
	public function testTrailingAmpersandProducesNoEmptyParam(): void {
		$this->assertSame( "id=57082&showdetails=1", queryStringMerge( "id=57082&", array( "showdetails" => 1 ) ) );
	}

	/** Removing a flag that is the last parameter in the string succeeds. */
	public function testRemovingFlagLastInStringSucceeds(): void {
		$this->assertSame( "id=57082", queryStringMerge( "id=57082&showdetails=1", array(), array( "showdetails" ) ) );
	}

	/** Removing a flag that sits in the middle of the string succeeds. */
	public function testRemovingFlagMidStringSucceeds(): void {
		$this->assertSame( "id=57082&mode=display", queryStringMerge( "id=57082&showdetails=1&mode=display", array(), array( "showdetails" ) ) );
	}

	/** Removing a key that is not present in the query string is a no-op. */
	public function testRemovingMissingKeyIsNoOp(): void {
		$this->assertSame( "id=57082", queryStringMerge( "id=57082", array(), array( "showdetails" ) ) );
	}

	/** Setting a key that already exists overwrites its value in place, preserving position. */
	public function testSetOverwritesExistingValueInPlace(): void {
		$this->assertSame( "id=1&showdetails=1&mode=display", queryStringMerge( "id=1&showdetails=1&mode=display", array( "showdetails" => 1 ) ) );
	}

	/** Setting one toggle while removing a mutually exclusive one leaves only the new toggle. */
	public function testMutuallyExclusiveTogglesLeaveOnlyTheSetOne(): void {
		$result = queryStringMerge( "id=57082&showrecentdetails=1", array( "showdetails" => 1 ), array( "showrecentdetails" ) );
		$this->assertSame( "id=57082&showdetails=1", $result );
	}

	/** Setting a param on an empty query string produces just that param. */
	public function testSetOnEmptyQueryStringProducesJustThatParam(): void {
		$this->assertSame( "showdetails=1", queryStringMerge( "", array( "showdetails" => 1 ) ) );
	}

	/** An empty query string with nothing to set or remove returns an empty string. */
	public function testEmptyQueryStringWithNoChangesReturnsEmptyString(): void {
		$this->assertSame( "", queryStringMerge( "", array() ) );
	}

	/** A degenerate lone "&" query string returns an empty string. */
	public function testLoneAmpersandReturnsEmptyString(): void {
		$this->assertSame( "", queryStringMerge( "&", array() ) );
	}

	/** When a name appears in both $set and $remove, $set wins (documented precedence). */
	public function testSetWinsOverRemoveForTheSameKey(): void {
		$this->assertSame( "a=1&b=2", queryStringMerge( "a=1", array( "b" => 2 ), array( "b" ) ) );
	}

	/** A duplicate key in the input keeps its last value, matching PHP's own $_GET parsing rules. */
	public function testDuplicateKeyKeepsLastValueLikeGet(): void {
		$this->assertSame( "id=2", queryStringMerge( "id=1&id=2", array() ) );
	}

	/** An encoded "&" inside a value does not split into a new parameter. */
	public function testEncodedAmpersandInValueDoesNotSplitParam(): void {
		$this->assertSame( "message=a%26b&id=5", queryStringMerge( "message=a%26b&id=5", array() ) );
	}

	/** Every real AppDetails.php parameter round-trips unchanged when nothing is set or removed. */
	public function testFullAppDetailsQueryStringRoundTripsUnchanged(): void {
		$queryString = "id=57082&mode=display&message=deletenotconfirmed&char_id=3&appuser_id=4&apporg_id=5";
		$this->assertSame( $queryString, queryStringMerge( $queryString, array() ) );
	}

	/** An integer-like key survives untouched, guarding against a future refactor to array_merge(), which would renumber it. */
	public function testIntegerLikeKeySurvives(): void {
		$this->assertSame( "7=a&id=5&showdetails=1", queryStringMerge( "7=a&id=5", array( "showdetails" => 1 ) ) );
	}

	/** A corrupt legacy link produced by the old bug self-heals once the intended params are set. */
	public function testCorruptLegacyLinkSelfHeals(): void {
		$result = queryStringMerge( "id=57082showdetails=1", array( "id" => 57082, "showdetails" => 1 ) );
		$this->assertSame( "id=57082&showdetails=1", $result );
	}

	/**
	 * Stochastic property test: an arbitrary parameter value, however hostile,
	 * must never break out of its own parameter and corrupt "id" or spawn an
	 * extra parameter — this is precisely the failure class that caused the
	 * production outage. The seed is fixed so a failure is reproducible.
	 * Keys are restricted to [A-Za-z0-9_] because parse_str() mangles "." and
	 * " " in KEYS (not values) to "_" as documented, intended behaviour;
	 * allowing those characters in a generated key would produce false
	 * failures unrelated to the property under test.
	 */
	public function testArbitraryValuesNeverBreakOutOfTheirParameter(): void {
		mt_srand( 20260917 );
		$keyAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_";
		$valueAlphabet = "&=%+[]# ?/\\\"'<>\n\tabcXYZ019";

		for ( $i = 0; $i < 200; $i++ ) {
			do {
				$key = self::randomString( $keyAlphabet, mt_rand( 1, 8 ) );
			} while ( "id" === $key );
			$value = self::randomString( $valueAlphabet, mt_rand( 0, 20 ) );

			$result = queryStringMerge( "id=57082", array( $key => $value ) );
			parse_str( $result, $back );

			$this->assertCount( 2, $back );
			$this->assertSame( "57082", $back["id"] );
			$this->assertSame( $value, $back[$key] );
		}
	}

	/** Builds a random string of the given length by picking mt_rand-indexed characters from $alphabet. */
	private static function randomString( string $alphabet, int $length ): string {
		$result = "";
		$max = strlen( $alphabet ) - 1;
		for ( $i = 0; $i < $length; $i++ ) {
			$result .= $alphabet[ mt_rand( 0, $max ) ];
		}
		return $result;
	}
}
