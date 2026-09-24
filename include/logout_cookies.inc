<?php

if ( ! function_exists('logoutCookieSpecs') ) {
	/**
	 * Lists every cookie a legacy.modernenigmasociety.org logout must expire,
	 * with the exact name/domain/path each was originally set with.
	 *
	 * PHP only deletes a cookie when setcookie() is called with the same
	 * name, domain and path it was set with; any other combination silently
	 * creates or expires an unrelated cookie and leaves the original one
	 * alive. This app has set session and login cookies from several places
	 * over the years, each with slightly different scoping, so a caller must
	 * pass every field returned here to setcookie() and must not rely on its
	 * defaults. The list includes two entries both named 'approvals_2017':
	 * one domain-scoped to legacy.modernenigmasociety.org and one host-only
	 * (empty domain). A host-only cookie and a domain-scoped cookie with the
	 * same name are different cookies that can both be present in the same
	 * browser at once, so both must be individually expired for logout to
	 * actually clear the session.
	 *
	 * @return List of cookie specs to expire, each an associative array with
	 *         keys 'name', 'domain' (empty string means host-only), 'path',
	 *         'secure' and 'httponly', matching the arguments setcookie()
	 *         needs to delete that exact cookie.
	 *
	 * @example
	 *   foreach ( logoutCookieSpecs() as $spec ) {
	 *       setcookie( $spec['name'], '', time() - 3600, $spec['path'], $spec['domain'], $spec['secure'], $spec['httponly'] );
	 *   }
	 */
	function logoutCookieSpecs(): array {
		return [
			[
				'name'     => 'approvals2017',
				'domain'   => '.modernenigmasociety.org',
				'path'     => '/',
				'secure'   => true,
				'httponly' => true,
			],
			[
				'name'     => 'approvals_2017',
				'domain'   => 'legacy.modernenigmasociety.org',
				'path'     => '/',
				'secure'   => false,
				'httponly' => true,
			],
			[
				'name'     => 'approvals_2017',
				'domain'   => '',
				'path'     => '/',
				'secure'   => false,
				'httponly' => false,
			],
			[
				'name'     => 'mes_login',
				'domain'   => '.modernenigmasociety.org',
				'path'     => '/',
				'secure'   => false,
				'httponly' => false,
			],
			[
				'name'     => 'dev_camnumber',
				'domain'   => '',
				'path'     => '/',
				'secure'   => false,
				'httponly' => false,
			],
		];
	}
}
