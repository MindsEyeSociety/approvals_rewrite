<?php
require_once 'tests/Integration/PageHarness.php';

/**
 * Regression tests for logout.php, the standalone sign-out page.
 *
 * Guards three traps a well-meaning future edit could easily reintroduce:
 *   - **Chrome fatals on a destroyed/absent session.** logout.php includes
 *     nothing but include/logout_cookies.inc -- no db.inc, no header.inc, no
 *     titlebar.php/footerbar.inc -- because every one of those either mints a
 *     fresh session cookie on the signed-out page or throws once the session
 *     array is empty. These tests assert there is no diagnostic output and
 *     no chrome markers in the rendered HTML.
 *   - **The CSRF hole.** An unauthenticated POST (no live session) must never
 *     expire the real cookies -- that would let any third-party page log a
 *     user out via a cross-site POST. These tests assert a POST with no
 *     session renders the "not signed in" state, never "signed out".
 *   - **No database access.** logout.php must work even if MySQL is down, so
 *     it must never issue a query.
 *
 * @see logout.php
 * @see include/logout_cookies.inc
 */
final class LogoutTest extends \PHPUnit\Framework\TestCase {

	private const CONFIRM_TEXT = 'Are you sure you want to sign out of the Approvals system?';
	private const DONE_TEXT = 'You have been signed out of the Approvals system.';
	private const CSRF_ERROR_TEXT = 'Your logout request could not be verified. Please try again.';
	private const ALREADY_TEXT = 'You are not currently signed in.';

	private const DIAGNOSTIC_MARKERS = [ 'Fatal error', 'TypeError', 'Warning:', 'Deprecated:' ];

	/** Extracts the hidden csrf_token input's value from a rendered confirm form, or null if absent. */
	private function extractCsrfToken( string $output ): ?string {
		if ( preg_match( '/name="csrf_token" value="([^"]*)"/', $output, $m ) ) {
			return $m[1];
		}
		return null;
	}

	/** GET with a live session renders the confirm form and leaves the session untouched. */
	public function testGetWithLiveSessionShowsConfirmFormAndDoesNotSignOut(): void {
		$result = PageHarness::run(
			'logout.php',
			session: [ 'user_id' => '42', 'user_name' => 'Test User' ]
		);

		$this->assertStringContainsString( '<form', $result->output );
		$this->assertStringContainsString( 'method="post"', $result->output );
		$this->assertStringContainsString( 'name="csrf_token"', $result->output );
		$this->assertStringContainsString( self::CONFIRM_TEXT, $result->output );
		$this->assertStringNotContainsString( self::ALREADY_TEXT, $result->output );
		$this->assertArrayHasKey( 'user_id', $result->session );
		$this->assertSame( '42', $result->session['user_id'] );
	}

	/** The confirm form's minted CSRF token is 64 hex characters, proving random_bytes actually ran. */
	public function testGetWithLiveSessionMintsNonTrivialCsrfToken(): void {
		$result = PageHarness::run(
			'logout.php',
			session: [ 'user_id' => '42' ]
		);

		$token = $this->extractCsrfToken( $result->output );
		$this->assertNotNull( $token, 'expected a hidden csrf_token input in the confirm form' );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $token );
	}

	/** No session at all renders "not signed in" cleanly, with no chrome-fatal diagnostics anywhere. */
	public function testGetWithNoSessionShowsNotSignedInWithNoDiagnostics(): void {
		$result = PageHarness::run(
			'logout.php',
			session: []
		);

		$this->assertStringContainsString( self::ALREADY_TEXT, $result->output );
		$this->assertSame( 0, $result->exitCode );
		$this->assertStringNotContainsString( '<form', $result->output );
		foreach ( self::DIAGNOSTIC_MARKERS as $marker ) {
			$this->assertStringNotContainsString( $marker, $result->output, "unexpected \"$marker\" in output" );
			$this->assertStringNotContainsString( $marker, $result->stderr, "unexpected \"$marker\" in stderr" );
		}
	}

	/** A POST carrying the matching CSRF token destroys the session and shows the signed-out state. */
	public function testPostWithValidTokenDestroysSessionAndShowsSignedOut(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$result = PageHarness::run(
			'logout.php',
			post: [ 'csrf_token' => $token ],
			session: [ 'user_id' => '42', 'logout_csrf' => $token ],
			method: 'POST'
		);

		$this->assertStringContainsString( self::DONE_TEXT, $result->output );
		$this->assertStringContainsString( 'index.php', $result->output );
		$this->assertStringNotContainsString( '<form', $result->output );
		$this->assertSame( [], $result->session, 'a verified logout must destroy the session entirely' );
	}

	/** A POST with no token at all is refused and the session survives untouched. */
	public function testPostWithMissingTokenIsRefusedAndSessionSurvives(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$result = PageHarness::run(
			'logout.php',
			post: [],
			session: [ 'user_id' => '42', 'logout_csrf' => $token ],
			method: 'POST'
		);

		$this->assertStringContainsString( self::CSRF_ERROR_TEXT, $result->output );
		$this->assertArrayHasKey( 'user_id', $result->session );
		$this->assertSame( '42', $result->session['user_id'] );
		$this->assertSame( $token, $result->session['logout_csrf'] ?? null );
	}

	/** A POST with the wrong token is refused and the session survives untouched -- a distinct branch from a missing token. */
	public function testPostWithWrongTokenIsRefusedAndSessionSurvives(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$result = PageHarness::run(
			'logout.php',
			post: [ 'csrf_token' => 'not-the-right-token' ],
			session: [ 'user_id' => '42', 'logout_csrf' => $token ],
			method: 'POST'
		);

		$this->assertStringContainsString( self::CSRF_ERROR_TEXT, $result->output );
		$this->assertArrayHasKey( 'user_id', $result->session );
		$this->assertSame( '42', $result->session['user_id'] );
	}

	/** A supplied token can never match an empty/absent stored token -- there is nothing to confirm. */
	public function testPostWithNoStoredTokenIsRefusedEvenWithATokenSupplied(): void {
		$result = PageHarness::run(
			'logout.php',
			post: [ 'csrf_token' => 'anything-at-all' ],
			session: [ 'user_id' => '42' ],
			method: 'POST'
		);

		$this->assertStringContainsString( self::CSRF_ERROR_TEXT, $result->output );
	}

	/**
	 * A POST with no session at all must render "not signed in", never "signed out" -- the CSRF-hole
	 * guard. A cross-site POST cannot carry the SameSite=Lax session cookie, so if this branch ever
	 * expired cookies anyway, any third-party page could silently log users out.
	 */
	public function testPostWithNoSessionShowsNotSignedInNotSignedOut(): void {
		$result = PageHarness::run(
			'logout.php',
			post: [ 'csrf_token' => 'irrelevant' ],
			session: [],
			method: 'POST'
		);

		$this->assertStringContainsString( self::ALREADY_TEXT, $result->output );
		$this->assertStringNotContainsString( self::DONE_TEXT, $result->output );
	}

	/** Logout never touches the database and never renders the app's sidebar/footer chrome, in any state. */
	public function testLogoutNeverQueriesDatabaseAndRendersNoChrome(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$confirm = PageHarness::run(
			'logout.php',
			session: [ 'user_id' => '42' ]
		);
		$done = PageHarness::run(
			'logout.php',
			post: [ 'csrf_token' => $token ],
			session: [ 'user_id' => '42', 'logout_csrf' => $token ],
			method: 'POST'
		);

		foreach ( [ $confirm, $done ] as $result ) {
			$this->assertSame( [], $result->queries, 'logout.php must never issue a database query' );
			$this->assertStringNotContainsString( 'id="sidebar"', $result->output );
			$this->assertStringNotContainsString( 'id="site-footer"', $result->output );
		}
	}
}
