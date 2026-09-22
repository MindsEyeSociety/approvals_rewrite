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
-- Why it matters beyond tidiness: this VSS is the only thing that made
-- Erin Smith's storytellers row at org 597 / venue 42 look like a real VST
-- post. Without the guard added to
-- sql/fix_venue_scoped_storyteller_assistant_flag.sql, that row would have
-- been flipped to assistant = 0 -- and because org 597 is GLOBE level, that
-- would have granted globe-tier primary approval authority on the strength
-- of a test venue.
--
-- Verified before writing, all zero:
--   characters with vss_id = 1256 : 0
--   applications via those chars  : 0
--   plotkits with vss_id = 1256   : 0
--   other VSSs at org 597/venue 42: 0  (1256 is the only one)
-- STEP 1 re-checks all of this at run time -- do not skip it. The row was
-- last modified 2021-02-16 (unix 1613441277).
--
-- ############################################################################
-- READ THIS BEFORE RUNNING -- deleting the VSS does NOT remove the access.
-- ############################################################################
-- Erin Smith (53738) holds a storytellers row at org 597 / venue 42,
-- assistant = 1, which exists only to support this test VSS. Deleting the
-- VSS leaves that row orphaned -- pointing at an org+venue with no VSS at all.
--
-- That row is what actually grants access, and org 597 Global Office is
-- GLOBE level. session_setup.inc picks $top_organization as the highest org
-- from ANY storytellers row, so this row alone makes her session
-- admin_level = 'globe', which satisfies every branch of the approval ladder
-- in AppDetails.php up to and including Pending Global. Her other posts are
-- domain (org 1201), region (org 16) and nation (org 673).
--
-- Deleting the VSS does not change that. If the intent is that a test venue
-- should not confer authority, the orphaned storytellers row has to go too.
-- STEP 3 below does that, left COMMENTED OUT for a deliberate decision --
-- it is a live permissions change for a real officer, not cleanup.
-- ############################################################################
--
-- Safe to run more than once: the DELETE is a no-op once the row is gone.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- STEP 1: BEFORE. Read-only. Every count here MUST be 0 except the VSS row
-- itself. If any is non-zero, STOP -- something real has attached to this
-- VSS since this file was written.
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

-- 1c. The storytellers rows that will be left orphaned by the delete.
--     Expect one: Erin Smith (53738), assistant = 1. See the warning above --
--     this row, not the VSS, is what grants globe-level access.
SELECT s.user_id, s.organization_id, s.venue_id, s.assistant
FROM storytellers s
WHERE s.organization_id = 597 AND s.venue_id = 42;


-- ----------------------------------------------------------------------------
-- STEP 2: Delete the VSS. Pinned by id AND name together, so a renamed row
-- or a reused id matches nothing rather than deleting the wrong VSS.
-- ----------------------------------------------------------------------------
START TRANSACTION;

DELETE FROM vsss
WHERE id = 1256
  AND name = 'Erin''s test venue'
  AND org_id = 597
  AND venue_id = 42;


-- ----------------------------------------------------------------------------
-- STEP 3: OPTIONAL, and a deliberate decision -- remove the orphaned
-- storytellers row so the test venue stops conferring globe-level authority.
--
-- Leaving it in place keeps Erin Smith's session admin_level at 'globe' on
-- the strength of a VSS that no longer exists. Removing it drops her to
-- nation level (org 673), which is her highest remaining post.
--
-- This is a live permissions change for a real officer. Uncomment only if
-- that is intended.
-- ----------------------------------------------------------------------------
-- DELETE FROM storytellers
-- WHERE organization_id = 597 AND venue_id = 42 AND user_id = 53738;


-- ----------------------------------------------------------------------------
-- STEP 4: AFTER. Still inside the open transaction. Check, then COMMIT.
-- ----------------------------------------------------------------------------

-- 4a. Expect zero rows -- the VSS is gone.
SELECT COUNT(*) AS vss_1256_remaining FROM vsss WHERE id = 1256;

-- 4b. Expect zero rows -- no VSS name anywhere still looks like test data.
--     If this returns something, it is a NEW test VSS, not this one.
SELECT v.id, v.name, v.org_id, o.org_name
FROM vsss v
LEFT JOIN organizations o ON o.id = v.org_id
WHERE v.name LIKE '%test%';

-- 4c. Orphaned storytellers rows: venue-scoped posts with no VSS at that
--     org+venue at all. Expect Erin Smith's row here unless STEP 3 was run.
SELECT s.user_id, s.organization_id, s.venue_id, s.assistant
FROM storytellers s
WHERE COALESCE(s.venue_id, 0) > 0
  AND NOT EXISTS (
    SELECT 1 FROM vsss v
    WHERE v.org_id = s.organization_id AND v.venue_id = s.venue_id
  )
ORDER BY s.organization_id, s.venue_id, s.user_id;

COMMIT;

-- If STEP 4 looked wrong, run this instead of the COMMIT above (not both):
-- ROLLBACK;


-- ----------------------------------------------------------------------------
-- STEP 5: Rollback after commit. There is no soft-delete here, so recovery
-- means re-inserting the row from the backup taken before the storyteller
-- migration, or from a database snapshot:
--
--   INSERT INTO vsss (id, org_id, venue_id, storyteller_id, name, vss, modified, email)
--   VALUES (1256, 597, 42, 53738, 'Erin''s test venue', '<body text>', 1613441277,
--           'threesteprag@gmail.com');
-- ----------------------------------------------------------------------------
