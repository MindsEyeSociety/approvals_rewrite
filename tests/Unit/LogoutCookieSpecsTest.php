<?php
require_once 'include/logout_cookies.inc';

/**
 * Unit tests for logoutCookieSpecs(), guarding against a logout that
 * expires the wrong domain/path combination and so leaves the real
 * .modernenigmasociety.org session cookie set by db.inc alive while
 * appearing to have logged the user out.
 *
 * @see logoutCookieSpecs()
 */
final class LogoutCookieSpecsTest extends \PHPUnit\Framework\TestCase {

	/**
	 * The live production session cookie spec (approvals2017, set by
	 * db.inc:6-15) must exactly match db.inc's domain, path, secure and
	 * httponly values. If db.inc's session_set_cookie_params() call ever
	 * changes, this spec must be updated together with it.
	 */
	public function testApprovals2017MatchesDbIncSessionCookieParams(): void {
		$specs = logoutCookieSpecs();

		$liveSessionSpecs = array_values( array_filter( $specs, function ( $spec ) {
			return $spec['name'] === 'approvals2017';
		} ) );

		$this->assertSame( [
			[
				'name'     => 'approvals2017',
				'domain'   => '.modernenigmasociety.org',
				'path'     => '/',
				'secure'   => true,
				'httponly' => true,
			],
		], $liveSessionSpecs );
	}

	/** The live session cookie approvals2017 appears exactly once. */
	public function testApprovals2017AppearsExactlyOnce(): void {
		$specs = logoutCookieSpecs();

		$matches = array_filter( $specs, function ( $spec ) {
			return $spec['name'] === 'approvals2017';
		} );

		$this->assertCount( 1, $matches );
	}

	/** Both approvals_2017 variants are present, with different domains. */
	public function testApprovals2017VariantsHaveDifferentDomains(): void {
		$specs = logoutCookieSpecs();

		$matches = array_values( array_filter( $specs, function ( $spec ) {
			return $spec['name'] === 'approvals_2017';
		} ) );

		$this->assertCount( 2, $matches );
		$this->assertNotSame( $matches[0]['domain'], $matches[1]['domain'] );
	}

	/** Every spec has exactly the five expected keys, each non-empty where required. */
	public function testEverySpecHasAllFiveKeys(): void {
		foreach ( logoutCookieSpecs() as $spec ) {
			$this->assertSame( [ 'name', 'domain', 'path', 'secure', 'httponly' ], array_keys( $spec ) );
			$this->assertNotSame( '', $spec['name'] );
			$this->assertNotSame( '', $spec['path'] );
		}
	}

	/** Every secure and httponly value is a real bool, not a truthy string. */
	public function testSecureAndHttponlyAreRealBooleans(): void {
		foreach ( logoutCookieSpecs() as $spec ) {
			$this->assertIsBool( $spec['secure'] );
			$this->assertIsBool( $spec['httponly'] );
		}
	}

	/** No two specs are exact duplicates of one another. */
	public function testListContainsNoExactDuplicateSpec(): void {
		$specs = logoutCookieSpecs();

		$serialized = array_map( 'serialize', $specs );

		$this->assertSame( array_values( array_unique( $serialized ) ), array_values( $serialized ) );
	}

	/**
	 * The live session spec is read back out of db.inc itself, so this fails the day
	 * somebody changes db.inc's cookie domain, path or flags without updating the
	 * logout list. Asserting against a literal only pins the helper against itself;
	 * the real risk is db.inc drifting away from it, because a mismatched domain
	 * makes setcookie() create a second cookie and leave the live session alive --
	 * a logout that reports success and does nothing.
	 */
	public function testApprovals2017StaysInSyncWithDbInc(): void {
		$src = file_get_contents( 'db.inc' );
		$this->assertIsString( $src, 'db.inc must be readable from the repo root' );

		$start = strpos( $src, 'session_set_cookie_params(' );
		$this->assertNotFalse( $start, 'db.inc no longer calls session_set_cookie_params()' );
		$block = substr( $src, $start, strpos( $src, ']);', $start ) - $start );

		$this->assertSame( 1, preg_match( "/'domain'\s*=>\s*'([^']*)'/", $block, $d ) );
		$this->assertSame( 1, preg_match( "/'path'\s*=>\s*'([^']*)'/", $block, $p ) );
		$this->assertSame( 1, preg_match( "/'secure'\s*=>\s*(true|false)/", $block, $s ) );
		$this->assertSame( 1, preg_match( "/'httponly'\s*=>\s*(true|false)/", $block, $h ) );
		$this->assertSame( 1, preg_match( '/session_name\(\s*"([^"]+)"\s*\)/', $src, $n ) );

		$live = array_values( array_filter( logoutCookieSpecs(), function ( $spec ) use ( $n ) {
			return $spec['name'] === $n[1];
		} ) );
		$this->assertCount( 1, $live, "no logout spec covers db.inc's session name {$n[1]}" );

		$this->assertSame( $d[1], $live[0]['domain'], 'logout domain must match db.inc' );
		$this->assertSame( $p[1], $live[0]['path'], 'logout path must match db.inc' );
		$this->assertSame( $s[1] === 'true', $live[0]['secure'], 'logout secure flag must match db.inc' );
		$this->assertSame( $h[1] === 'true', $live[0]['httponly'], 'logout httponly flag must match db.inc' );
	}
}
