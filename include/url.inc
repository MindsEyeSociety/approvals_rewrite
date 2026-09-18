<?php

if ( ! function_exists('queryStringMerge') ) {
	/**
	 * Rebuilds a query string with parameters added, replaced, or removed,
	 * so callers never concatenate onto a raw query string and risk
	 * corrupting the parameter that follows (e.g. turning "id=57082" plus a
	 * hand-appended "showdetails=1&" into the unparseable
	 * "id=57082showdetails=1&", which PHP silently reads as
	 * id="57082showdetails=1").
	 *
	 * $remove is applied BEFORE $set, so a name appearing in both ends up
	 * set rather than removed. The result is normalized by PHP's own
	 * query-string rules — the same rules that populate $_GET: a duplicate
	 * key keeps its last value, empty segments are dropped, a valueless
	 * `foo` becomes `foo=`, and a key containing `.` or a space is mangled
	 * to `_`. That parity with $_GET is the correctness property of this
	 * function, not a defect to work around. A surviving parameter keeps
	 * its original position in the rebuilt string, while a newly set
	 * parameter is appended at the end. Values are url-encoded in the
	 * output, so a value may safely contain `&` or `=` without corrupting
	 * neighbouring parameters.
	 *
	 * @param $queryString Untrusted query string, typically
	 *                      $_SERVER['QUERY_STRING'], with or without a
	 *                      trailing "&". May be empty.
	 * @param $set Parameter names mapped to the values they should have in
	 *             the result, overwriting any existing value for that name.
	 * @param $remove Parameter names to drop from the result before $set is
	 *                applied.
	 * @return The rebuilt, url-encoded query string, with no leading "?"
	 *         and no trailing "&".
	 *
	 * @example
	 *   queryStringMerge( "id=57082", array("showdetails" => 1) );
	 *   // => "id=57082&showdetails=1"    (not "id=57082showdetails=1")
	 *   queryStringMerge( "id=57082&showdetails=1", array(), array("showdetails") );
	 *   // => "id=57082"
	 *
	 * @see pageUrl()
	 */
	function queryStringMerge( string $queryString, array $set, array $remove = array() ): string {
		$params = array();
		parse_str( $queryString, $params );
		foreach ( $remove as $name ) {
			unset( $params[$name] );
		}
		foreach ( $set as $key => $value ) {
			$params[$key] = $value;
		}
		return http_build_query( $params );
	}
}

if ( ! function_exists('pageUrl') ) {
	/**
	 * Joins a script path and a query string into a link target, so a page
	 * with no query parameters doesn't end up with a bare trailing "?".
	 *
	 * The query string is used verbatim — callers should pass the output
	 * of queryStringMerge() rather than a hand-built string, so the "?"
	 * placement here stays the only place that decides whether it appears.
	 *
	 * @param $scriptName Path to the target script, e.g. $_SERVER['SCRIPT_NAME']
	 *                     or a literal path such as "/AppDetails.php".
	 * @param $queryString Query string to append, without a leading "?".
	 *                      Pass "" to link to the script with no query string.
	 * @return The script path with the query string appended after a "?",
	 *         or the bare script path when the query string is empty.
	 *
	 * @example
	 *   pageUrl( "/AppDetails.php", "id=57082&showdetails=1" );
	 *   // => "/AppDetails.php?id=57082&showdetails=1"
	 *   pageUrl( "/AppDetails.php", "" );
	 *   // => "/AppDetails.php"
	 *
	 * @see queryStringMerge()
	 */
	function pageUrl( string $scriptName, string $queryString ): string {
		return "" === $queryString ? $scriptName : $scriptName . "?" . $queryString;
	}
}
