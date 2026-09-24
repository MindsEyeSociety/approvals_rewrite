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
	 * defaults.
	 *
	 * The live production session cookie is NOT a fixed literal: db.inc:14's
	 * `session_name("approvals2017")` is a no-op under the php8.3-fpm-legacy
	 * pool, which pins the name via `php_admin_value[session.name]` --
	 * currently `OLDDB_SESSID` -- and a `php_admin_value` always wins over
	 * any runtime `session_name()` call. The only reliable way to know the
	 * effective name is to ask PHP for it, so this function always includes
	 * an entry for `$sessionName` (or, when omitted, whatever `session_name()`
	 * reports for the current request) with db.inc's own cookie params.
	 * The rest of the list is stale cleanup: cookie names/scopes this app has
	 * used historically and may still be sitting in a returning visitor's
	 * browser, including two entries both named 'approvals_2017': one
	 * domain-scoped to legacy.modernenigmasociety.org and one host-only
	 * (empty domain). A host-only cookie and a domain-scoped cookie with the
	 * same name are different cookies that can both be present in the same
	 * browser at once, so both must be individually expired for logout to
	 * actually clear the session. Entries are deduplicated on the
	 * (name, domain, path) triple, so if the effective session name happens
	 * to collide with one of the stale entries (e.g. it really is still
	 * 'approvals2017' somewhere), it is not emitted twice.
	 *
	 * @param $sessionName The effective session cookie name to expire, e.g.
	 *        the value returned by session_name() for this request. Pass
	 *        null (the default) to have this function call session_name()
	 *        itself; callers that already captured it (as logout.php does,
	 *        once, up front) should pass it through instead so both agree.
	 * @return List of cookie specs to expire, each an associative array with
	 *         keys 'name', 'domain' (empty string means host-only), 'path',
	 *         'secure' and 'httponly', matching the arguments setcookie()
	 *         needs to delete that exact cookie.
	 *
	 * @example
	 *   foreach ( logoutCookieSpecs( session_name() ) as $spec ) {
	 *       setcookie( $spec['name'], '', time() - 3600, $spec['path'], $spec['domain'], $spec['secure'], $spec['httponly'] );
	 *   }
	 *
	 * @see db.inc for the session_set_cookie_params() this mirrors.
	 * @see logout.php for the caller that captures session_name() once and
	 *      passes it through here.
	 */
	function logoutCookieSpecs( ?string $sessionName = null ): array {
		$sessionName ??= session_name();

		$specs = [
			[
				'name'     => $sessionName,
				'domain'   => '.modernenigmasociety.org',
				'path'     => '/',
				'secure'   => true,
				'httponly' => true,
			],
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

		$seen = [];
		$deduped = [];
		foreach ( $specs as $spec ) {
			$key = $spec['name'] . "\0" . $spec['domain'] . "\0" . $spec['path'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$deduped[] = $spec;
		}

		return $deduped;
	}
}
