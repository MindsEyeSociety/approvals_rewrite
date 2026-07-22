<?php

if ( ! function_exists('paginationOffset') ) {
	/**
	 * Clamps an untrusted pagination offset to a non-negative integer so it
	 * can't produce a negative SQL LIMIT offset (which throws a fatal
	 * mysqli_sql_exception).
	 *
	 * @param $raw Untrusted offset value, typically read straight from
	 *             $_GET (e.g. a "skip" or "msg" query parameter). May be a
	 *             string, int, null, or anything else int-castable.
	 * @return The offset as a non-negative integer, safe to splice into a
	 *         `LIMIT offset, count` clause.
	 *
	 * @example
	 *   paginationOffset("-20"); // => 0
	 *   paginationOffset("60");  // => 60
	 */
	function paginationOffset( $raw ): int {
		return max( 0, (int) $raw );
	}
}
