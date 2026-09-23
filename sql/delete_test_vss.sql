-- ============================================================================
-- Delete the test VSS that lives under a real organization
-- ============================================================================
--
-- vss 1256 "Erin's test venue" sits at org 597 Global Office / venue 42
-- (WTA - Apocalypse 2021: On Frayed Threads). Its own body text reads
-- "This is a test vss. I wouldn't advise people putting anything ...".
--
-- It cannot be retired the way a test ORGANIZATION can: the `vsss` table has
-- no active column. sql/retire_test_organizations.sql handles org 1213
-- "Test Domain"; this file handles the test VSS sitting under a genuinely
-- real org, which that approach cannot reach.
--
-- SCOPE NOTE -- this is now pure cleanup.
-- When this file was first written, the storytellers row at org 597 /
-- venue 42 still existed, and because org 597 is GLOBE level it set its
-- holder's session admin_level to 'globe', satisfying every branch of the
-- approval ladder up to Pending Global on the strength of a test venue.
-- That row has since been removed by hand, so deleting this VSS no longer
-- has any permissions consequence -- it leaves nothing orphaned and changes
-- nobody's access. STEP 1c re-confirms that before the delete runs.
--
-- Verified immediately before writing, all zero:
--   characters with vss_id = 1256   : 0
--   applications via those chars    : 0
--   plotkits with vss_id = 1256     : 0
--   other VSSs at org 597/venue 42  : 0  (1256 is the only one)
--   storytellers at org 597/venue 42: 0  (the row was removed by hand)
-- STEP 1 re-checks all of this at run time -- do not skip it. The row was
-- last modified 2021-02-16 (unix 1613441277).
--
-- Safe to run more than once: the DELETE is a no-op once the row is gone.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- STEP 1: BEFORE. Read-only. Every count MUST be 0 except the VSS row itself.
-- If any is non-zero, STOP -- something has attached to this VSS since this
-- file was written.
-- ----------------------------------------------------------------------------

-- 1a. The VSS itself. Expect exactly one row, name "Erin's test venue".
SELECT v.id, v.name, v.org_id, o.org_name, v.venue_id, ve.venue, v.storyteller_id,
       FROM_UNIXTIME(v.modified) AS last_modified
FROM vsss v
LEFT JOIN organizations o ON o.id = v.org_id
LEFT JOIN venues ve       ON ve.id = v.venue_id
WHERE v.id = 1256;

-- 1b. Dependants. Every column MUST return 0.
SELECT
  ( SELECT COUNT(*) FROM characters WHERE vss_id = 1256 )              AS characters,
  ( SELECT COUNT(*) FROM applications a
      JOIN characters ch ON ch.id = a.character_id
      WHERE ch.vss_id = 1256 )                                         AS applications,
  ( SELECT COUNT(*) FROM plotkits WHERE vss_id = 1256 )                AS plotkits,
  ( SELECT COUNT(*) FROM vsss
      WHERE org_id = 597 AND venue_id = 42 AND id <> 1256 )            AS other_vsss_same_office;

-- 1c. Storytellers posted at this org+venue. Expect ZERO -- the globe-level
--     row that used to sit here was removed by hand. If this returns a row,
--     deleting the VSS will orphan it (a venue-scoped post pointing at an
--     org+venue with no VSS), and because org 597 is globe level that row
--     would keep conferring globe-tier standing. Decide what to do with it
--     before continuing.
SELECT s.user_id, s.organization_id, s.venue_id, s.assistant
FROM storytellers s
WHERE s.organization_id = 597 AND s.venue_id = 42;


-- ----------------------------------------------------------------------------
-- STEP 2: Delete the VSS. Pinned by id, name, org and venue together, so a
-- renamed row or a reused id matches nothing rather than deleting the wrong
-- VSS.
-- ----------------------------------------------------------------------------
START TRANSACTION;

DELETE FROM vsss
WHERE id = 1256
  AND name = 'Erin''s test venue'
  AND org_id = 597
  AND venue_id = 42;


-- ----------------------------------------------------------------------------
-- STEP 3: AFTER. Still inside the open transaction. Check, then COMMIT.
-- ----------------------------------------------------------------------------

-- 3a. Expect zero rows -- the VSS is gone.
SELECT COUNT(*) AS vss_1256_remaining FROM vsss WHERE id = 1256;

-- 3b. Expect zero rows -- no VSS name anywhere still looks like test data.
--     If this returns something, it is a NEW test VSS, not this one.
SELECT v.id, v.name, v.org_id, o.org_name
FROM vsss v
LEFT JOIN organizations o ON o.id = v.org_id
WHERE v.name LIKE '%test%';

-- 3c. Orphaned storytellers rows anywhere: venue-scoped posts with no VSS at
--     that org+venue at all. Reported for visibility rather than as a pass/
--     fail -- retired venues legitimately produce some of these. What matters
--     is that this delete did not ADD one at org 597 / venue 42.
SELECT s.user_id, s.organization_id, s.venue_id, s.assistant
FROM storytellers s
WHERE COALESCE(s.venue_id, 0) > 0
  AND NOT EXISTS (
    SELECT 1 FROM vsss v
    WHERE v.org_id = s.organization_id AND v.venue_id = s.venue_id
  )
ORDER BY s.organization_id, s.venue_id, s.user_id;

COMMIT;

-- If STEP 3 looked wrong, run this instead of the COMMIT above (not both):
-- ROLLBACK;


-- ----------------------------------------------------------------------------
-- STEP 4: Rollback after commit. There is no soft-delete here, so recovery
-- means re-inserting the row from a database snapshot:
--
--   INSERT INTO vsss (id, org_id, venue_id, storyteller_id, name, vss, modified, email)
--   VALUES (1256, 597, 42, 53738, 'Erin''s test venue', '<body text>', 1613441277,
--           'threesteprag@gmail.com');
-- ----------------------------------------------------------------------------
