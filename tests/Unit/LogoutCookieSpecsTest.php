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
	 * Passing an explicit session name yields an entry for that exact name
	 * with db.inc's domain/path/secure/httponly, regardless of what
	 * session_name() happens to report in this process -- proving the spec
	 * is driven by the argument, not by a hardcoded literal.
	 */
	public function testExplicitSessionNameProducesMatchingEntry(): void {
		$specs = logoutCookieSpecs( 'X_SESSID' );

		$matches = array_values( array_filter( $specs, function ( $spec ) {
			return $spec['name'] === 'X_SESSID';
		} ) );

		$this->assertSame( [
			[
				'name'     => 'X_SESSID',
				'domain'   => '.modernenigmasociety.org',
				'path'     => '/',
				'secure'   => true,
				'httponly' => true,
			],
		], $matches );
	}

	/** With no argument, the list includes an entry named after session_name() itself. */
	public function testNoArgumentDefaultsToSessionName(): void {
		$specs = logoutCookieSpecs();

		$matches = array_filter( $specs, function ( $spec ) {
			return $spec['name'] === session_name();
		} );

		$this->assertCount( 1, $matches );
	}

	/** All five stale cleanup names are still present alongside the dynamic entry. */
	public function testStaleCleanupNamesAreStillPresent(): void {
		$names = array_column( logoutCookieSpecs( 'X_SESSID' ), 'name' );

		foreach ( [ 'approvals2017', 'approvals_2017', 'mes_login', 'dev_camnumber' ] as $stale ) {
			$this->assertContains( $stale, $names, "expected stale cleanup name '$stale' to still be listed" );
		}
	}

	/** When the effective session name collides with a stale entry, it is listed exactly once, not twice. */
	public function testCollidingSessionNameAppearsExactlyOnce(): void {
		$specs = logoutCookieSpecs( 'approvals2017' );

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
}
