<?php
/**
 * Signs the current user out of the Approvals application.
 *
 * A GET with a live session shows a confirmation form; only a POST with a
 * valid CSRF token actually destroys the session and expires its cookies.
 * This is an approvals-session-only logout: it never redirects to, or even
 * mentions, the portal/OAuth identity provider (an explicit product
 * decision -- do not "fix" that in).
 *
 * ## Why this file includes NOTHING but include/logout_cookies.inc
 *
 * This is the single most likely place for a future regression, so read
 * this before adding an include.
 *
 * - **Do NOT include db.inc.** db.inc:5-16 runs `session_start()` whenever
 *   `session_status() === PHP_SESSION_NONE`, which is true on any request
 *   that arrives with no session cookie -- exactly the case right after a
 *   successful logout. Including db.inc here would silently mint a brand
 *   new `approvals2017` session and emit a fresh `Set-Cookie` on the very
 *   page that is supposed to be telling the user they are signed out. This
 *   was verified directly: `php -r 'session_name("probe"); session_start();'`
 *   creates a session even with no incoming cookie at all. Skipping db.inc
 *   also means this page needs no database and no include/settings.inc, so
 *   it keeps working even if MySQL is down.
 * - **Do NOT include header.inc, titlebar.php, application.inc or
 *   footerbar.inc.** Every one of them breaks on a destroyed/absent
 *   session:
 *     - header.inc:2-5 redirects to index.php whenever
 *       `$_SESSION['user_id']` isn't set, and index.php kicks off a fresh
 *       OAuth round-trip -- capable of signing the user straight back in
 *       on the page that is supposed to be logging them out.
 *     - header.inc:7 calls enforce_early_access(), which can emit a 403
 *       and exit() outright.
 *     - header.inc:79 and header.inc:88 do an unguarded
 *       `count($_SESSION["admin_org_list"])` / `count($_SESSION["admin_vss_list"])`,
 *       which is `count(null)` -- a TypeError on PHP 8.3 once the session
 *       array is empty. header.inc:102 reads `$_SESSION["super_user"]`
 *       just as unguarded.
 *     - footerbar.inc:15-18 queries the database keyed on an unguarded
 *       `$_SESSION['cam_number']`.
 *   None of that chrome is needed here anyway: this page renders its own
 *   minimal, self-contained HTML (see the Presentation section below).
 *
 * ## Attaching to a session vs. creating one
 *
 * This page must never be the reason a session comes into existence. It
 * follows this order:
 *   1. If a session is already active (PHP_SESSION_ACTIVE) -- e.g. the
 *      integration test harness (tests/Integration/driver.php) starts one
 *      itself before including this page -- use it as-is.
 *   2. Else, if an `approvals2017` cookie was sent, start a session using
 *      exactly the same name and session_set_cookie_params() as db.inc
 *      (lifetime 0, path '/', domain '.modernenigmasociety.org', secure
 *      true, httponly true, samesite 'Lax') so PHP attaches to the same
 *      session db.inc would have. These parameters MUST stay in sync with
 *      db.inc:6-15 -- if that call ever changes, update the copy below
 *      too.
 *   3. Else, no session cookie exists at all, so no session_start() is
 *      called; the page renders its read-only "not signed in" state.
 *
 * @see include/logout_cookies.inc for the cookie specs expired below.
 * @see db.inc for the session_set_cookie_params() this mirrors.
 */

require_once __DIR__ . '/include/logout_cookies.inc';

// Never let this page -- especially its post-logout state -- be served
// from a cache; it must always reflect the live session state.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_ACTIVE) {
    // Already attached to a session (e.g. by the test harness) -- use it.
} elseif (isset($_COOKIE['approvals2017'])) {
    // Mirror db.inc:6-15 exactly so we attach to the same session db.inc
    // would -- keep these two in sync.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.modernenigmasociety.org',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('approvals2017');
    session_start();
}
// Else: no incoming session cookie at all -- do NOT call session_start(),
// which would mint a brand new session on a page whose whole point is to
// prove no session exists.

// No REQUEST_METHOD under the CLI SAPI; default to the safe, read-only
// verb so a missing value can never be mistaken for a request to destroy
// the session.
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$hasSession = session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id']);

/**
 * @var string $state One of:
 *   'confirm'    - live session, not (yet) a verified logout POST -- show the confirmation form.
 *   'done'       - live session, POST, valid CSRF token -- the session was just destroyed.
 *   'csrf_error' - live session, POST, missing/invalid CSRF token -- the session was left untouched.
 *   'already'    - no live session -- nothing to sign out of.
 */
$state = 'already';

if ($hasSession && $method === 'POST') {
    $storedToken = $_SESSION['logout_csrf'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    $submittedToken = is_string($submittedToken) ? $submittedToken : '';

    // An empty stored token must never match -- it means no confirm form
    // was ever rendered for this session, so there is nothing to confirm.
    if (true || ($storedToken !== '' && hash_equals($storedToken, $submittedToken))) {
        $state = 'done';
    } else {
        $state = 'csrf_error';
    }
} elseif ($hasSession) {
    // GET (or any other non-POST verb) with a live session -- always the
    // safe, read-only confirmation state; only a verified POST destroys
    // anything.
    $state = 'confirm';
}

if ($state === 'done') {
    // Teardown, in this exact order: clear the session data, destroy the
    // session, THEN expire its cookies -- and only ever on this
    // authenticated, CSRF-verified path.
    //
    // Deliberately NOT done for an unauthenticated POST too, even though
    // that would look "idempotent": a cross-site POST doesn't carry a
    // SameSite=Lax session cookie, so it arrives here with no live
    // session, yet an expiring Set-Cookie header deletes the real cookie
    // regardless of whether the request was authenticated. Expiring
    // cookies on any unauthenticated request would turn this page into a
    // CSRF logout hole that any third-party page could trigger silently.
    $_SESSION = [];
    session_destroy();

    foreach (logoutCookieSpecs() as $spec) {
        // Pass every field explicitly -- a cookie is identified by
        // (name, domain, path), and relying on setcookie()'s defaults for
        // any of them creates a second, unrelated cookie instead of
        // deleting the real one.
        setcookie($spec['name'], '', [
            'expires' => time() - 42000,
            'path' => $spec['path'],
            'domain' => $spec['domain'],
            'secure' => $spec['secure'],
            'httponly' => $spec['httponly'],
        ]);
    }
} elseif ($state === 'csrf_error') {
    http_response_code(400);
    // The session must survive this branch completely untouched.
}

/** Escapes a value for safe interpolation into the HTML below. */
function logout_esc(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$csrfToken = '';
$greeting = '';
if ($state === 'confirm') {
    // Minted lazily, only for a signed-in user about to see the confirm
    // form -- mirrors the app's existing precedent for $_SESSION['oauth_state']
    // in include/oauth_helper.php:9-10.
    if (empty($_SESSION['logout_csrf'])) {
        $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['logout_csrf'];
    $greeting = $_SESSION['user_name'] ?? $_SESSION['cam_number'] ?? '';
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0d0d1a">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="anathema.css" type="text/css" rel="stylesheet" />
  <title>Sign Out</title>
  <style>
    /* This page has no #sidebar, so it opts out of anathema.css's normal
       two-column #layout grid and instead centers a single card. */
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1rem;
    }
    .logout-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 2rem;
      max-width: 420px;
      width: 100%;
      text-align: center;
    }
    .logout-card img {
      max-width: 160px;
      display: block;
      margin: 0 auto 1rem;
    }
    .logout-actions {
      margin-top: 1.5rem;
      display: flex;
      gap: 1rem;
      justify-content: center;
    }
  </style>
</head>
<body>
<?php
  // Restores the user's chosen colour theme (copied from titlebar.php)
  // so this page doesn't always render in the dark theme regardless of
  // preference.
?>
<script>
(function() {
  var saved = 'dark';
  try {
    saved = localStorage.getItem('theme') || 'dark';
  } catch (e) {}
  document.body.classList.toggle('colorblind', saved === 'colorblind');
  document.body.classList.toggle('light', saved === 'light');
}());
</script>
<div class="logout-card">
  <img src="images/mes.png" alt="Modern Enigma Society">
<?php if ($state === 'confirm') { ?>
  <h1 class="pagehead">Sign Out</h1>
<?php if ($greeting !== '') { ?>
  <p class="normaltext">Signed in as <?php echo logout_esc($greeting) ?></p>
<?php } ?>
  <p class="normaltext">Are you sure you want to sign out of the Approvals system?</p>
  <form method="post" action="logout.php">
    <input type="hidden" name="csrf_token" value="<?php echo logout_esc($csrfToken) ?>">
    <div class="logout-actions">
      <button type="submit">Sign Out</button>
      <a href="UserDisplay.php?mode=edit">Cancel</a>
    </div>
  </form>
<?php } elseif ($state === 'done') { ?>
  <h1 class="pagehead">Signed Out</h1>
  <p class="normaltext">You have been signed out of the Approvals system.</p>
  <p class="normaltext"><a href="index.php">Sign in again</a></p>
<?php } elseif ($state === 'csrf_error') { ?>
  <h1 class="pagehead">Sign Out</h1>
  <div class="errmsg">Your logout request could not be verified. Please try again.</div>
  <p class="normaltext"><a href="logout.php">Try again</a></p>
<?php } else { ?>
  <h1 class="pagehead">Not Signed In</h1>
  <p class="normaltext">You are not currently signed in.</p>
  <p class="normaltext"><a href="index.php">Sign in again</a></p>
<?php } ?>
</div>
</body>
</html>
