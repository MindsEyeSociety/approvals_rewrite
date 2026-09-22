-- ============================================================================
-- Retire test organizations
-- ============================================================================
--
-- Test data must not be treated as reliable evidence of anyone's real
-- position or access. Marking test orgs active = 0 takes them out of the
-- organization pickers and lists, and lets any future "active only" rule
-- exclude them automatically instead of relying on a name match.
--
-- There is existing precedent for treating test orgs specially by name:
-- OrganizationDAO::getMapRows() already excludes org_name LIKE '%test%'
-- and domain LIKE '%test%' from the public map.
--
-- SCOPE: exactly one organization matches today --
--   org 1213  "Test Domain"  (domain OK-666-D, nation USA, region GL)
--
-- Everything beneath it was verified as test content before writing this:
--   VSSs         : 1314 "The Hellmouth", 1315 "LOTN Test Venue"
--   characters   : "Freddy Fox", "Vinny Vampire" (both owned by the author)
--   applications : 1 -- USA-C-VT-2605-057081, "I ate a vampire", Approved
--   storytellers : 1 row, the "test user" account (US2019080099)
--   sub-orgs     : none
-- No real member data sits under this org.
--
-- EFFECT, precisely:
--   * OrganizationDAO::readOrganizationsByIDs() filters active = 1, so the
--     org disappears from org lists and pickers. This is the intent.
--   * OrganizationDAO::readByID() does NOT filter active, so anything that
--     already references the org by id still resolves. Existing records and
--     the storyteller chain shown on an application will not break.
--
-- KNOWN LIMIT: this does NOT revoke the test account's permissions.
-- session_setup.inc's getStorytellerOrganizations() reads storytellers rows
-- with no active filter at all, so a storyteller row at a retired org still
-- grants access. Retiring the org is not a substitute for removing or
-- correcting that row -- it is only about keeping test structures out of
-- lists and out of any activity-based rule.
--
-- NOT COVERED HERE: test VSSs that live under a REAL org. The clearest case
-- is vss 1256 "Erin's test venue" at org 597 Global Office. The `vsss` table
-- has no active column, so it cannot be retired this way, and it is handled
-- instead by the name guard in
-- sql/fix_venue_scoped_storyteller_assistant_flag.sql.
--
-- Safe to run more than once: the UPDATE is a no-op once active is already 0.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- STEP 1: BEFORE. Read-only. Confirm the target set before changing anything.
-- ----------------------------------------------------------------------------

-- 1a. The org this migration will retire. Expect exactly one row:
--     id 1213, "Test Domain", active = 1.
SELECT id, org_name, nation, region, domain, chapter, active
FROM organizations
WHERE id = 1213;

-- 1b. Any OTHER org that looks like test data and is still active. Expect
--     zero rows today. If this returns anything, decide deliberately whether
--     to add it below -- do not widen the UPDATE into a blind pattern match,
--     because a real org could legitimately contain "test" in its name.
SELECT id, org_name, nation, region, domain, chapter, active
FROM organizations
WHERE active = 1
  AND id <> 1213
  AND ( org_name REGEXP 'test|sandbox|demo|dummy|staging'
     OR domain   REGEXP 'TEST|666' );

-- 1c. What sits under the org, so the reviewer can confirm it is all test
--     content. Expect 2 VSSs, 2 characters, 1 application, 1 storyteller row.
SELECT
  ( SELECT COUNT(*) FROM vsss WHERE org_id = 1213 )                       AS vsss,
  ( SELECT COUNT(*) FROM characters ch
      JOIN vsss v ON v.id = ch.vss_id WHERE v.org_id = 1213 )             AS characters_via_vsss,
  ( SELECT COUNT(*) FROM applications a
      JOIN characters ch ON ch.id = a.character_id
      JOIN vsss v ON v.id = ch.vss_id WHERE v.org_id = 1213 )             AS applications_via_vsss,
  ( SELECT COUNT(*) FROM storytellers WHERE organization_id = 1213 )      AS storyteller_rows,
  ( SELECT COUNT(*) FROM organizations
      WHERE domain = 'OK-666-D' AND id <> 1213 )                          AS sub_orgs;


-- ----------------------------------------------------------------------------
-- STEP 2: The change. Inspect STEP 1's output first; if anything is
-- unexpected, stop rather than running this.
-- ----------------------------------------------------------------------------

-- Pinned by id AND name together, so the statement is self-verifying: if the
-- row is ever renamed or the id reused, it matches nothing rather than
-- retiring the wrong organization.
START TRANSACTION;

UPDATE organizations
SET active = 0
WHERE id = 1213
  AND org_name = 'Test Domain';


-- ----------------------------------------------------------------------------
-- STEP 3: AFTER. Still inside the open transaction. Check, then COMMIT.
-- ----------------------------------------------------------------------------

-- 3a. Expect active = 0.
SELECT id, org_name, domain, active
FROM organizations
WHERE id = 1213;

-- 3b. Expect zero active test-looking orgs remaining.
SELECT COUNT(*) AS active_test_orgs
FROM organizations
WHERE active = 1
  AND ( org_name REGEXP 'test|sandbox|demo|dummy|staging'
     OR domain   REGEXP 'TEST|666' );

COMMIT;

-- If STEP 3 looked wrong, run this instead of the COMMIT above (not both):
-- ROLLBACK;


-- ----------------------------------------------------------------------------
-- STEP 4: Rollback after commit, if it ever proves necessary.
-- ----------------------------------------------------------------------------
--   UPDATE organizations SET active = 1 WHERE id = 1213 AND org_name = 'Test Domain';
