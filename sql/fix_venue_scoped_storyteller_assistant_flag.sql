-- ============================================================================
-- Fix venue-scoped storyteller `assistant` flag
-- ============================================================================
--
-- ROOT CAUSE: UserDisplayRecordAction.php, doEdit branch, lines 41-45:
--
--     if ( ($_POST["venue_id"] ?? null) || ($_POST["assistant"] ?? null) ) {
--       $thisassistant = 1;
--     } else {
--       $thisassistant = 0;
--     }
--
-- Any storyteller position with a venue_id was forced to assistant = 1,
-- regardless of whether the "Assistant" checkbox was actually ticked.
-- Correct behaviour: the primary Venue Storyteller (VST) of a venue
-- should be assistant = 0; only genuine assistants should carry
-- assistant = 1. Because of the bug, all 40 venue-scoped storytellers
-- rows ended up assistant = 1 and zero venue-scoped primaries exist --
-- corrupting the approval conflict-of-interest logic that reads this
-- flag.
--
-- THE CODE BUG IS FIXED SEPARATELY (not in this file). That fix MUST be
-- deployed BEFORE this data migration is applied, and in particular
-- before any venue-scoped storyteller position is next edited through
-- the UI -- if the code fix is not live first, the very next edit of
-- any venue-scoped position will re-force assistant = 1 and re-break
-- the very rows this migration corrects.
--
-- SCOPE:
--   1. Deduplicate exact-duplicate storytellers rows (the table has no
--      primary key/id column, so re-saving the same position can
--      silently insert an identical duplicate row -- confirmed for
--      user 1486 at org 990 / venue 36).
--   2. Flip assistant 1 -> 0 for the venue-scoped rows independently
--      confirmed as primaries: the user IS vsss.storyteller_id for a
--      VSS at that exact (org_id, venue_id). This matches 13 rows / 12
--      distinct positions (one of the 13 is the duplicate above).
--   3. Leave alone every org-wide row (venue_id IS NULL), and the 8
--      "review" rows: venue-scoped posts at nation/region/globe orgs
--      where the holder runs a VSS at that venue under a *different*
--      org. Those are national/regional venue posts, not VSTs, and are
--      a separate human decision. One of the 8 has already been
--      decided (user 55391, org 673, venue 45) and appears below as a
--      commented-out statement for a human to enable deliberately.
--
-- This file is authored for human review and manual execution -- it is
-- not run automatically and this agent has no database access. Read
-- every "before" SELECT's output before letting the transaction reach
-- COMMIT.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- STEP 1: Backup.
-- VERIFY this table exists and its row count matches
-- `SELECT COUNT(*) FROM storytellers` (84 rows as of 2026-09-18) before
-- proceeding to any of the steps below. This is a plain CREATE ... AS
-- SELECT (DDL), so it implicitly commits on its own and sits outside
-- the transaction used later in this file.
-- ----------------------------------------------------------------------------
CREATE TABLE storytellers_backup_20260918 AS SELECT * FROM storytellers;


-- ----------------------------------------------------------------------------
-- STEP 2: BEFORE census. Run all four and read the output before
-- touching anything.
-- ----------------------------------------------------------------------------

-- 2a. Row counts by venue-scope x assistant flag.
SELECT
  CASE WHEN venue_id IS NULL THEN 'org-wide' ELSE 'venue-scoped' END AS scope,
  assistant,
  COUNT(*) AS row_count
FROM storytellers
GROUP BY scope, assistant
ORDER BY scope, assistant;

-- 2b. Exact-duplicate rows that the dedup step (STEP 3) will collapse.
--     Expect one group with copies = 2: organization_id 990, venue_id
--     36, user_id 1486, assistant 1.
SELECT organization_id, venue_id, user_id, assistant, COUNT(*) AS copies
FROM storytellers
GROUP BY organization_id, venue_id, user_id, assistant
HAVING COUNT(*) > 1;

-- 2c. The exact rows the UPDATE (STEP 4) will flip to assistant = 0.
--     Expect 11 rows / 10 distinct positions: the 13-row list in the
--     header, less the 2 test rows excluded by STEP 4's name guard, with
--     1486/990/36 still appearing twice for the duplicate.
--     Must mirror STEP 4's WHERE clause exactly -- if you change one,
--     change the other, or this preview stops telling the truth.
SELECT st.organization_id, st.venue_id, st.user_id, st.assistant, v.id AS vss_id, v.name AS vss_name
FROM storytellers st
JOIN vsss v
  ON v.org_id = st.organization_id
 AND v.venue_id = st.venue_id
 AND v.storyteller_id = st.user_id
LEFT JOIN organizations o
  ON o.id = st.organization_id
WHERE st.venue_id IS NOT NULL
  AND st.assistant = 1
  AND v.name NOT LIKE '%test%'
  AND COALESCE(o.org_name, '') NOT LIKE '%test%'
ORDER BY st.organization_id, st.venue_id, st.user_id;

-- 2c-ii. The rows the test guard EXCLUDES. Expect exactly 2: user 53738
--     at org 597 / venue 42 (vss "Erin's test venue") and user 1745522 at
--     org 1213 Test Domain / venue 45. Both would otherwise have been
--     flipped to primary, the first granting globe-tier authority.
SELECT st.organization_id, o.org_name, st.venue_id, st.user_id, v.id AS vss_id, v.name AS vss_name
FROM storytellers st
JOIN vsss v
  ON v.org_id = st.organization_id
 AND v.venue_id = st.venue_id
 AND v.storyteller_id = st.user_id
LEFT JOIN organizations o
  ON o.id = st.organization_id
WHERE st.venue_id IS NOT NULL
  AND st.assistant = 1
  AND ( v.name LIKE '%test%' OR COALESCE(o.org_name, '') LIKE '%test%' )
ORDER BY st.organization_id, st.venue_id, st.user_id;

-- 2d. The "review" rows this migration deliberately does NOT touch --
--     venue-scoped, assistant = 1, no exact-office VSS match, but the
--     holder does run a VSS at that same venue_id under a different
--     org. Expect 8 rows, including organization_id 673, venue_id 45,
--     user_id 55391 (the one already decided -- see STEP 5).
SELECT st.organization_id, st.venue_id, st.user_id, st.assistant
FROM storytellers st
WHERE st.venue_id IS NOT NULL
  AND st.assistant = 1
  AND NOT EXISTS (
    SELECT 1 FROM vsss v
    WHERE v.org_id = st.organization_id
      AND v.venue_id = st.venue_id
      AND v.storyteller_id = st.user_id
  )
  AND EXISTS (
    SELECT 1 FROM vsss v2
    WHERE v2.venue_id = st.venue_id
      AND v2.storyteller_id = st.user_id
  )
ORDER BY st.organization_id, st.venue_id, st.user_id;


-- ----------------------------------------------------------------------------
-- Mutating statements from here on. Everything below runs inside one
-- transaction. If the STEP 2 output does not match what is documented
-- above, run ROLLBACK; (at the bottom of this file) instead of
-- COMMIT; and investigate before re-attempting.
-- ----------------------------------------------------------------------------
START TRANSACTION;

-- STEP 3: Deduplicate.
-- storytellers has no primary key/id column, so identical rows cannot
-- be told apart with a self-join DELETE (there is no tie-breaking
-- column to make one copy greater/lesser than the other -- a
-- self-join on fully-equal columns matches nothing under `>` and
-- everything under `>=`). Instead, rebuild the table from its own
-- DISTINCT rows via a GROUP BY-driven temporary table. This runs
-- BEFORE the UPDATE in STEP 4 so the update never has to act on two
-- copies of the same position; CREATE/DROP TEMPORARY TABLE do not
-- cause an implicit commit in MySQL, so this stays safely inside the
-- surrounding transaction and can still be rolled back as a unit.
CREATE TEMPORARY TABLE storytellers_dedup (
  organization_id int NOT NULL DEFAULT '0',
  venue_id        int DEFAULT NULL,
  user_id         int NOT NULL DEFAULT '0',
  assistant       int DEFAULT '0'
);

INSERT INTO storytellers_dedup (organization_id, venue_id, user_id, assistant)
SELECT organization_id, venue_id, user_id, assistant
FROM storytellers
GROUP BY organization_id, venue_id, user_id, assistant;

DELETE FROM storytellers;

INSERT INTO storytellers (organization_id, venue_id, user_id, assistant)
SELECT organization_id, venue_id, user_id, assistant
FROM storytellers_dedup;

DROP TEMPORARY TABLE storytellers_dedup;

-- STEP 4: Flip confirmed venue-scoped primaries from assistant = 1 to 0.
-- Rule: the user IS vsss.storyteller_id for a VSS at that exact
-- (org_id, venue_id). Written as a JOIN-based UPDATE against `vsss`
-- (a different table from the one being updated), not a correlated
-- subquery against storytellers itself, to avoid MySQL error 1093
-- ("You can't specify target table for update in FROM clause") --
-- that error is triggered by a subquery that reads from the same
-- table being updated, not by an ordinary multi-table UPDATE JOIN.
-- No user ids are hardcoded, so this stays correct if the underlying
-- data shifts before a human runs it.
-- Test data is excluded: a test VSS or a test org is not reliable evidence
-- of anyone's real position, and flipping such a row grants live final-
-- approval authority. Two rows are excluded by this guard:
--   53738  org 597 Global Office / venue 42  -- vss 1256 "Erin's test venue"
--          (would have granted GLOBE-tier primary authority)
--   1745522 org 1213 Test Domain / venue 45  -- org name, not vss name
-- Matched on VSS and ORGANIZATION names only, never on member names:
-- "Donny Tester" and "Sherri Teston" are real members whose surnames
-- contain "test", and must not be swept up by this.
-- LEFT JOIN + COALESCE so a storytellers row pointing at a missing
-- organizations row is still flipped rather than silently skipped.
UPDATE storytellers st
JOIN vsss v
  ON v.org_id = st.organization_id
 AND v.venue_id = st.venue_id
 AND v.storyteller_id = st.user_id
LEFT JOIN organizations o
  ON o.id = st.organization_id
SET st.assistant = 0
WHERE st.venue_id IS NOT NULL
  AND st.assistant = 1
  AND v.name NOT LIKE '%test%'
  AND COALESCE(o.org_name, '') NOT LIKE '%test%';

-- STEP 5: Decided "review" rows.
--
-- These are venue-scoped posts the STEP 4 rule cannot confirm on its own:
-- the holder runs the relevant VSS under a *different* org, so no vsss row
-- exists at this exact org+venue to vouch for them. Each was confirmed
-- directly by the National Storyteller.
--
-- 5a. Primary officeholders -- venue assigned, NOT assistants.
--
--   Alex Cresswell (55391), org 673 United States [nation]
--     venue 45  COD - Society of Paranormal Investigators
--     venue 43  VTM - Sabbat 2021: War Never Changes
--   Kevin Tapper (105), org 673 United States [nation]
--     venue 40  VTM - Masquerade 2020 : The 13th Hour
--   Erin Smith (53738), org 673 United States [nation]
--     venue 42  WTA - Apocalypse 2021: On Frayed Threads
UPDATE storytellers
SET assistant = 0
WHERE organization_id = 673 AND assistant = 1
  AND ( ( user_id = 55391 AND venue_id IN (43, 45) )
     OR ( user_id = 105   AND venue_id = 40 )
     OR ( user_id = 53738 AND venue_id = 42 ) );

-- 5b. Loren Reed (6985) -- wrong ORG, not just the wrong flag.
-- His row sits at org 597 Global Office, which would give him Global-tier
-- standing. He is the Regional Storyteller and runs "From the Ashes"
-- (VSS 1322) at org 15 Great Lakes [region] / venue 44, so the post is
-- regional. Move the row to org 15 and make it primary. After this the
-- row satisfies the STEP 4 rule on its own (vss 1322 is at org 15 /
-- venue 44 with storyteller_id 6985); it is done here only because
-- STEP 4 has already run by this point.
-- He has no existing org 15 / venue 44 row, so this creates no duplicate;
-- his separate org-wide org 15 RST row (venue NULL) is untouched.
UPDATE storytellers
SET organization_id = 15, assistant = 0
WHERE organization_id = 597 AND venue_id = 44 AND user_id = 6985 AND assistant = 1;

-- 5c. Rows deliberately LEFT as assistant = 1.
--
--   Rebecca Gearhart (1486), org 673 [nation] / venue 45 COD-SPI.
--     She is Alex Cresswell's assistant for COD-SPI at national. This
--     pairing is what makes the conflict rule work at Top: with 55391
--     primary and 1486 assistant at the same org+venue, she is barred
--     from final-approving his Top applications and they are referred to
--     the org-wide NST. Setting BOTH to 0 would leave nobody as an
--     assistant there and the Top-tier conflict would silently stop
--     firing. Do not "tidy" this.
--
--   Erin Smith (53738), org 16 Northeast Region [region] / venue 42.
--     She is the assistant Regional Storyteller for Apocalypse. Primary
--     at national (5a), assistant at regional -- deliberately different.
--
--   Sean McKeown (2562), org 977 Children of the Lost Eden / venue 40.
--     Left alone: org 977 is active = 0 and venue 40 is active = 0, and
--     someone else (54321) runs the VSS at that office, so an assistant
--     row is the correct reading. Nothing live depends on it.


-- ----------------------------------------------------------------------------
-- STEP 6: AFTER verification. Run all four, still inside the open
-- transaction, and compare against the notes below before deciding
-- whether to COMMIT or ROLLBACK.
-- ----------------------------------------------------------------------------

-- 6a. Expect exactly 15 venue-scoped primaries now: 11 flipped by the
--     STEP 4 rule minus the 1 duplicate removed by the dedup (=10), plus
--     the 4 national posts in STEP 5a, plus Loren Reed's moved row (5b).
SELECT COUNT(*) AS venue_scoped_primaries
FROM storytellers
WHERE venue_id IS NOT NULL AND assistant = 0;

-- 6b. Expect zero rows -- no exact duplicates remain anywhere in the
--     table.
SELECT organization_id, venue_id, user_id, assistant, COUNT(*) AS copies
FROM storytellers
GROUP BY organization_id, venue_id, user_id, assistant
HAVING COUNT(*) > 1;

-- 6c. Expect every venue-scoped row that now has assistant = 0 to
--     still resolve to a matching vsss.storyteller_id -- i.e. the flip
--     is still correct and nothing else moved underneath it. Should
--     return 11 positions: the 10 from STEP 2c plus Loren Reed. NOTE: the
--     4 national posts decided in STEP 5a will NOT appear here -- they
--     have no vsss row at that exact org+venue, which is precisely why
--     they needed a human decision. Loren Reed's moved row (5b) WILL
--     appear, since after the move it does resolve against vss 1322.
SELECT st.organization_id, st.venue_id, st.user_id, st.assistant, v.id AS vss_id
FROM storytellers st
JOIN vsss v
  ON v.org_id = st.organization_id
 AND v.venue_id = st.venue_id
 AND v.storyteller_id = st.user_id
WHERE st.venue_id IS NOT NULL
  AND st.assistant = 0
ORDER BY st.organization_id, st.venue_id, st.user_id;

-- 6d. Expect the venue-scoped / assistant = 1 row_count here to be
--     exactly 16 lower than the venue-scoped / assistant = 1 row_count
--     from STEP 2a: 15 rows flipped in total (10 by STEP 4 after dedup and
--     the test guard, 4 in STEP 5a, 1 in STEP 5b) plus the 1 duplicate the
--     dedup removed from the assistant = 1 pool.
SELECT
  CASE WHEN venue_id IS NULL THEN 'org-wide' ELSE 'venue-scoped' END AS scope,
  assistant,
  COUNT(*) AS row_count
FROM storytellers
GROUP BY scope, assistant
ORDER BY scope, assistant;

-- If 6a-6d all match expectations, commit:
COMMIT;

-- If anything in STEP 2, STEP 5, or STEP 6 looked wrong, run this
-- instead of the COMMIT above (and do not run both):
-- ROLLBACK;


-- ----------------------------------------------------------------------------
-- STEP 7: Rollback note (for use AFTER this has already been
-- committed, if a later problem is traced back to this migration).
-- This restores the exact pre-migration data, including the
-- now-removed duplicate row:
--
--   DELETE FROM storytellers;
--   INSERT INTO storytellers (organization_id, venue_id, user_id, assistant)
--   SELECT organization_id, venue_id, user_id, assistant
--   FROM storytellers_backup_20260918;
--
-- storytellers_backup_20260918 is left in place after this migration
-- on purpose. Drop it manually only once you are confident the fix is
-- correct and no rollback will be needed:
--
--   DROP TABLE storytellers_backup_20260918;
-- ----------------------------------------------------------------------------
