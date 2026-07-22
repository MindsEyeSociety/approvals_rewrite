<?php
require_once 'include/pagination.inc';

/**
 * Unit tests for paginationOffset(), covering negative, zero, positive,
 * and non-numeric inputs to confirm it always yields a non-negative int
 * safe for a SQL LIMIT offset.
 *
 * @see paginationOffset()
 */
final class PaginationOffsetTest extends \PHPUnit\Framework\TestCase {

	/** A negative int offset is clamped to zero. */
	public function testNegativeIntIsClampedToZero(): void {
		$this->assertSame( 0, paginationOffset(-20) );
	}

	/** A negative numeric string offset is clamped to zero. */
	public function testNegativeStringIsClampedToZero(): void {
		$this->assertSame( 0, paginationOffset("-20") );
	}

	/** A zero int offset passes through unchanged. */
	public function testZeroIntStaysZero(): void {
		$this->assertSame( 0, paginationOffset(0) );
	}

	/** A zero numeric string offset passes through unchanged. */
	public function testZeroStringStaysZero(): void {
		$this->assertSame( 0, paginationOffset("0") );
	}

	/** A positive int offset passes through unchanged. */
	public function testPositiveIntPassesThrough(): void {
		$this->assertSame( 40, paginationOffset(40) );
	}

	/** A positive numeric string offset is cast to int. */
	public function testPositiveStringIsCastToInt(): void {
		$this->assertSame( 60, paginationOffset("60") );
	}

	/** A non-numeric string casts to zero and is clamped. */
	public function testNonNumericStringClampsToZero(): void {
		$this->assertSame( 0, paginationOffset("abc") );
	}

	/** A null offset casts to zero and is clamped. */
	public function testNullClampsToZero(): void {
		$this->assertSame( 0, paginationOffset(null) );
	}

	/** Another positive numeric string offset is cast to int. */
	public function testAnotherPositiveStringIsCastToInt(): void {
		$this->assertSame( 13, paginationOffset("13") );
	}
}
