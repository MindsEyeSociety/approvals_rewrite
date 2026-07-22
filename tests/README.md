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
php tools/phpunit.phar                    # all suites (config: phpunit.xml)
php tools/phpunit.phar --testsuite unit   # unit suite only
```

## Layout

- `tests/Unit/` — fast, offline unit tests. They inject **stub doubles** for DAOs, so
  they need no database and never load `include/settings.inc`. See `tests/bootstrap.php`.
- `tests/test_final_approval.php` — a pre-existing self-contained **live-DB integration**
  script (its own assert harness). Run directly: `php tests/test_final_approval.php`.
  It is not part of the `unit` suite because it requires DB connectivity and fixtures.

## Writing a unit test

`require_once` the app file under test at the top (the bootstrap has already `chdir`'d to
the repo root), then inject anonymous-class stubs for its collaborators. Example pattern:
`tests/Unit/GetLowSTTest.php` constructs `ApplicationService` with four stub DAOs and asserts
the resolved storyteller id for each `vss_id` branch.
