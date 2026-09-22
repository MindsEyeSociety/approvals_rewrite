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
--     Expect the 13-row / 12-position list documented in the header
--     above (1486/990/36 appearing twice for the duplicate).
SELECT st.organization_id, st.venue_id, st.user_id, st.assistant, v.id AS vss_id
FROM storytellers st
JOIN vsss v
  ON v.org_id = st.organization_id
 AND v.venue_id = st.venue_id
 AND v.storyteller_id = st.user_id
WHERE st.venue_id IS NOT NULL
  AND st.assistant = 1
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
UPDATE storytellers st
JOIN vsss v
  ON v.org_id = st.organization_id
 AND v.venue_id = st.venue_id
 AND v.storyteller_id = st.user_id
SET st.assistant = 0
WHERE st.venue_id IS NOT NULL
  AND st.assistant = 1;

-- STEP 5: Decided "review" rows (national venue posts).
--
-- These are venue-scoped posts at a NATION-level org, which the STEP 4
-- rule cannot confirm on its own: the holder runs the relevant VSS under
-- a *different* org, so there is no vsss row at this exact org+venue to
-- vouch for them. Confirmed directly by the National Storyteller:
--
--   Alex Cresswell (55391) is the primary officeholder -- venue assigned,
--   NOT an assistant -- for both of his national venue posts:
--     org 673 / venue 45  COD - Society of Paranormal Investigators
--     org 673 / venue 43  VTM - Sabbat 2021: War Never Changes
--
--   Rebecca Gearhart (1486) KEEPS assistant = 1 at org 673 / venue 45.
--   She is his assistant for COD-SPI at national. No statement here: the
--   row is already assistant = 1 and must stay that way.
--
-- This pairing is what makes the conflict-of-interest rule work at Top:
-- with 55391 primary and 1486 assistant at the same org+venue, she is
-- barred from final-approving his Top applications and it is referred to
-- the org-wide NST. If BOTH were set to 0, neither would be an assistant
-- there and the Top-tier conflict would silently stop firing.
UPDATE storytellers
SET assistant = 0
WHERE organization_id = 673 AND venue_id IN (43, 45) AND user_id = 55391 AND assistant = 1;

-- The remaining 5 review rows from STEP 2d are NOT decided and must not
-- be touched here:
--   105   Kevin Tapper  org 673 nation  venue 40  (runs VSS 1226 under org 1200)
--   53738 Erin Smith    org 673 nation  venue 42  (runs VSS 1256/1261 elsewhere)
--   53738 Erin Smith    org 16  region  venue 42  (same venue, regional post)
--   2562  Sean McKeown  org 977 domain  venue 40  (runs VSS 1237 under org 1150)
--   6985  Loren Reed    org 597 globe   venue 44  (runs VSS 1322 under org 15)


-- ----------------------------------------------------------------------------
-- STEP 6: AFTER verification. Run all four, still inside the open
-- transaction, and compare against the notes below before deciding
-- whether to COMMIT or ROLLBACK.
-- ----------------------------------------------------------------------------

-- 6a. Expect exactly 14 venue-scoped primaries now: 13 flipped by the
--     STEP 4 rule minus the 1 duplicate removed by the dedup, plus the
--     2 national posts decided in STEP 5 (user 55391, venues 43 and 45).
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
--     return the same 12 distinct positions seen in STEP 2c. NOTE: the
--     2 national posts decided in STEP 5 will NOT appear here -- they
--     have no vsss row at that exact org+venue, which is precisely why
--     they needed a human decision rather than the STEP 4 rule.
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
--     exactly 14 lower than the venue-scoped / assistant = 1
--     row_count from STEP 2a (13 flipped by STEP 4, less the 1 duplicate
--     the dedup already removed from the assistant = 1 pool, plus the 2
--     national posts flipped by STEP 5).
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
