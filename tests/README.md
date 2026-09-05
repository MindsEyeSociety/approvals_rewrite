# Tests

This project has **no composer**. The test framework is PHPUnit, run from a
self-contained phar that is **gitignored** (it must never be deployed to the web root).

## One-time setup

Fetch the phar into `tools/phpunit.phar`:

```bash
tools/get-phpunit.sh        # Linux/macOS/Git Bash
```
```powershell
tools\get-phpunit.ps1       # Windows PowerShell
```

## Running

```bash
php tools/phpunit.phar                          # all suites (config: phpunit.xml)
php tools/phpunit.phar --testsuite unit         # unit suite only
php tools/phpunit.phar --testsuite integration  # integration suite only
```

## Layout

- `tests/Unit/` — fast, offline unit tests. They inject **stub doubles** for DAOs, so
  they need no database and never load `include/settings.inc`. See `tests/bootstrap.php`.
  `tests/Unit/support/` holds shared test doubles (not test files themselves) — e.g.
  `RecordingDb.php`, a stand-in `Database` that records every `query($sql, $params)`
  call, used by the SQL-injection regression tests to assert a query uses `?`
  placeholders and that a tainted value only ever appears in `$params`.
- `tests/Integration/` — runs a real top-level page/include (not just a class) in an
  **isolated child PHP process** via `PageHarness::run()`, against an in-memory stub
  `Database` (`tests/Integration/stub_includes/`) — still fully offline, no real
  database. This exists because many of this app's page scripts are top-level
  procedural code that calls `header()`/`exit()` directly and reads `$_GET`/`$_POST`/
  `$_SESSION` — not unit-testable in-process the way a class is. Process isolation
  means an `exit()` inside the target page only ends that child process; a shutdown
  function in the child still flushes captured queries even after a fatal error.
  The mechanism: only `include/Database.class.php` and `include/settings.inc` are
  shadowed via PHP's `include_path` (checked before a bare include's calling-script
  directory) — the real `db.inc`, `header.inc`, `DAOFactory`, and every DAO class run
  completely unmodified against the stub connection.
  **Known gap:** any page that transitively includes `application.inc` (which
  `require_once`s Smarty from a hardcoded absolute Linux path,
  `/usr/share/php/smarty3/SmartyBC.class.php`) cannot run under this harness on a
  machine without that exact path — absolute-path includes bypass `include_path`
  shadowing entirely. Those pages currently rely on the manual QA steps in their
  PR's test plan instead.
- `tests/test_final_approval.php` — a pre-existing self-contained **live-DB integration**
  script (its own assert harness). Run directly: `php tests/test_final_approval.php`.
  It is not part of either suite because it requires DB connectivity and fixtures.

## Writing a unit test

`require_once` the app file under test at the top (the bootstrap has already `chdir`'d to
the repo root), then inject anonymous-class stubs for its collaborators. Example pattern:
`tests/Unit/GetLowSTTest.php` constructs `ApplicationService` with four stub DAOs and asserts
the resolved storyteller id for each `vss_id` branch. For a query-building test, use
`tests/Unit/support/RecordingDb.php` instead of an ad hoc stub — see
`tests/Unit/UserInfoDAOSqliTest.php` for the pattern.

## Writing an integration (page-script) test

`require_once 'tests/Integration/PageHarness.php'`, then call
`PageHarness::run($page, get: [...], post: [...], session: [...], responses: [...])`
and assert on the returned `PageHarnessResult` (`->queries`, `->sqlStatements()`,
`->anyQueryContains()`, `->anyParamsContain()`, `->exitCode`). `ignoreLogin` defaults to
`true`, bypassing `header.inc`'s login gate without needing `$_SESSION['user_id']` set
(which would also trigger the heavier `session_setup.inc` bootstrap) — pass `false` only
when specifically testing login-gate behavior. `responses` supplies one row-array per
expected `$db->query()` call, in call order; a call beyond the queued responses gets an
empty result. See `tests/Integration/FooterbarSqliTest.php` for the pattern.
