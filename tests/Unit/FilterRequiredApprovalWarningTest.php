<?php
require_once 'classes/Filter.php';

/**
 * Regression test confirming Filter::mergeArray() doesn't warn when
 * required_approval arrives as a scalar empty string (from a self-generated
 * URL that flattened an empty array via implode()) rather than an array.
 * Indexing [0] into that empty string used to trip PHP 8's "Uninitialized
 * string offset 0" warning on nearly every sort-column click on
 * app_main.php, since Filter::mergeArray() runs on every request there.
 *
 * PHPUnit's failOnWarning setting (phpunit.xml) means this test needs no
 * explicit assertion on the warning itself -- a regression would fail the
 * test outright.
 *
 * @see Filter::mergeArray()
 */
final class FilterRequiredApprovalWarningTest extends \PHPUnit\Framework\TestCase {

	/** An empty-string required_approval (the real production shape) must not warn, and must not mark the filter as changed. */
	public function testEmptyStringRequiredApprovalDoesNotWarn(): void {
		$filter = new Filter();

		$changed = $filter->mergeArray( [ 'required_approval' => '' ] );

		$this->assertFalse( $changed );
	}

	/** A genuinely non-empty required_approval array is still applied correctly -- the fix doesn't just always fall back. */
	public function testNonEmptyArrayRequiredApprovalIsApplied(): void {
		$filter = new Filter();

		$changed = $filter->mergeArray( [ 'required_approval' => [ 'Low', 'Mid' ] ] );

		$this->assertTrue( $changed );
		$this->assertSame( [ 'Low', 'Mid' ], $filter->required_approval );
	}
}
