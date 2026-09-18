<?php
require_once 'include/url.inc';

/**
 * Unit tests for pageUrl(), covering the join between a script path and a
 * query string produced by queryStringMerge() — in particular that a page
 * with no query parameters never ends up with a bare trailing "?", and that
 * the query string is used verbatim regardless of whether the script path
 * is absolute or relative.
 *
 * @see pageUrl()
 */
final class PageUrlTest extends \PHPUnit\Framework\TestCase {

	/** A non-empty query string is appended after a "?". */
	public function testNonEmptyQueryStringIsAppendedAfterQuestionMark(): void {
		$this->assertSame( "/AppDetails.php?id=1", pageUrl( "/AppDetails.php", "id=1" ) );
	}

	/** An empty query string produces the bare script path with no dangling "?". */
	public function testEmptyQueryStringProducesBareScriptPath(): void {
		$this->assertSame( "/AppDetails.php", pageUrl( "/AppDetails.php", "" ) );
	}

	/** A multi-parameter query string is used verbatim. */
	public function testMultiParamQueryStringIsUsedVerbatim(): void {
		$this->assertSame( "AppDetails.php?id=1&showdetails=1", pageUrl( "AppDetails.php", "id=1&showdetails=1" ) );
	}

	/** A relative script name passes through unchanged. */
	public function testRelativeScriptNamePassesThrough(): void {
		$this->assertSame( "AppDetails.php?id=1", pageUrl( "AppDetails.php", "id=1" ) );
	}
}
