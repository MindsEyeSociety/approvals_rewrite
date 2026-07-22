<?php
/**
 * PHPUnit bootstrap for approvals_rewrite.
 *
 * Sets the working directory to the repo root so the application's cwd-relative
 * `include_once("classes/...")` paths resolve when a test requires an app class.
 * Unit tests inject stub doubles for every DAO, so neither the database nor
 * `include/settings.inc` is loaded here — tests run fully offline.
 *
 * Each test file `require_once`s the specific app file it exercises (e.g.
 * `require_once "classes/ApplicationService.php";`), keeping side-effectful
 * includes out of tests that don't need them.
 */

// App code predates PHP 8.x deprecations; keep them out of the test signal but
// still surface warnings/notices (phpunit.xml fails the run on those).
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

chdir(dirname(__DIR__));
