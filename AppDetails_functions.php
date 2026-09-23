<?php

/**
 * Whether a user is barred from giving FINAL approval (status "Approved") to an application
 * AT THE TIER currently being decided.
 *
 * A user may never final-approve their own application -- that bar is absolute, at every tier,
 * for everyone including super users. Beyond that, the bar is now scoped to the *capacity* the
 * approver is acting in at that tier: per MES Handbook Ch.9 Conflict of Interest, an officer may
 * not approve an application "pending before their office" when they hold that office only as an
 * assistant to the applicant (Ch.9 Assistants). The same pair of people can be conflict-free at
 * one tier and conflicted at another, because it depends on which office governs the tier in
 * play, not on the pair alone -- see UserInfoDAO::applicantConflictOffice().
 *
 * @param $applicantUserId The application owner (applications.user_id).
 * @param $sessionUserId   The user attempting the approval ($_SESSION['user_id']).
 * @param $userInfoDAO     A UserInfoDAO, used only for the capacity-scoped conflict lookup.
 * @param $appScope        The application's approval scope, from applicationApprovalScope().
 * @param $tierRank        The tier rank (1-5, per approvalTierRank()) being decided.
 * @return bool True if this user must not finalize this application at this tier.
 * @see UserInfoDAO::applicantConflictOffice()
 * @see applicationApprovalScope()
 * @see approvalTierRank()
 *
 * Example:
 *   $blocked = finalApprovalBlocked( $app_info->user_id, $_SESSION['user_id'], $userInfoDAO,
 *                                     $appScope, $tierRank );
 *   if( !$blocked ) { $ThisStatus = "Approved"; }
 */
function finalApprovalBlocked( $applicantUserId, $sessionUserId, $userInfoDAO, $appScope, $tierRank ) {
	if( $applicantUserId == $sessionUserId ) {
		return true; // your own application, every tier, no exceptions
	}
	return $userInfoDAO->applicantConflictOffice( $sessionUserId, $applicantUserId, $appScope, $tierRank ) !== null;
}

/**
 * Maps a required-approval tier name to a numeric rank so tiers can be compared
 * with `<`/`>` instead of the fragile string equality that let an application
 * pushed past its required tier ratchet forever, because `required_approval == "<Tier>"`
 * never matched again once the tier name itself had changed underneath it.
 *
 * Comparison is case-insensitive and trims surrounding whitespace. Any value that
 * doesn't match one of the five known tier names deliberately returns 5 (Global),
 * the highest rank, rather than 1 (Low). This is a safety property, not a fallback:
 * production holds 93 applications with `required_approval = ''`, one with the
 * value `'High?'`, and one with a mojibake-corrupted `'High'`. All of these already
 * ratchet up to "Pending Global" today and are approved only there; returning 5
 * for unrecognised input reproduces that existing behaviour exactly. Returning 1
 * instead would silently make all 95 of those applications approvable by any
 * low-tier storyteller, which is a security regression, not a bug fix. Note that
 * `Global` (2706 rows in production) is a real, reachable tier in its own right,
 * not merely the sentinel for "unrecognised" — the two happen to share a rank.
 *
 * @param $tier The required-approval tier name (e.g. "Low", "Mid", "High", "Top", "Global").
 *              Accepts non-string input; it is cast to string before trimming, since
 *              `trim(null)` is deprecated on PHP 8.1+ and this project fails the test
 *              suite on notices.
 * @return int The tier's rank from 1 (Low) to 5 (Global); 5 for anything unrecognised.
 * @example approvalTierRank( "Mid" ); // 2
 * @example approvalTierRank( "" ); // 5 -- unrecognised, not Low
 * @see approvalTierName() for the inverse mapping.
 */
function approvalTierRank( $tier ) {
	$normalized = strtolower( trim( (string)$tier ) );
	$ranks = array(
		'low' => 1,
		'mid' => 2,
		'high' => 3,
		'top' => 4,
		'global' => 5,
	);
	if ( isset( $ranks[$normalized] ) ) {
		return $ranks[$normalized];
	}
	return 5;
}

/**
 * Maps a tier rank back to its display name, the inverse of approvalTierRank(),
 * used to build a "Pending <Name>" status string when an application is escalated.
 *
 * @param $rank A tier rank, normally 1 through 5 as produced by approvalTierRank().
 *              Any value outside that range returns 'Global', the same safe-highest
 *              behaviour as approvalTierRank()'s handling of unrecognised input.
 * @return string The tier's display name: "Low", "Mid", "High", "Top", or "Global".
 * @example approvalTierName( 3 ); // "High"
 * @example approvalTierName( 99 ); // "Global" -- out of range falls back to the highest tier
 * @see approvalTierRank() for the forward mapping.
 */
function approvalTierName( $rank ) {
	$names = array(
		1 => 'Low',
		2 => 'Mid',
		3 => 'High',
		4 => 'Top',
		5 => 'Global',
	);
	if ( isset( $names[$rank] ) ) {
		return $names[$rank];
	}
	return 'Global';
}

/**
 * Determines which offices govern an application: its organization, VSS, and venue.
 * Pure and side-effect free -- it reads only the properties it needs from $app_info
 * and never touches the database, so it can be reused by both approval-escalation
 * logic and any future auditing without a UserInfoDAO or ApplicationDAO in scope.
 *
 * Tolerates a missing or null $app_info, a missing `character` property, and a null
 * `character` -- all return all-zero scope with no PHP warning or notice, which
 * matters because 1357 production applications have no VSS at all.
 *
 * Follows the legacy convention (see AppDetails.php's `readByID( 0 - $app_info->character->vss_id )`)
 * that a *negative* character vss_id does not identify a VSS -- it identifies the
 * organization `-vss_id` instead. So a negative vss_id always yields `vss_id => 0`,
 * and only contributes to `org_id` when the application's own `org_id` was absent,
 * since the application's own organization takes precedence over one implied by its
 * character's VSS selection.
 *
 * @param $app_info An application record (typically from ApplicationDAO), expected to
 *                   optionally expose `org_id`, `venue_id`, and a `character` object with
 *                   `vss_id`. May be null or missing any of these.
 * @return array An associative array with keys 'org_id', 'vss_id', and 'venue_id', each
 *               a non-negative int; 0 means "not applicable / not scoped to this office".
 * @example applicationApprovalScope( (object)['org_id' => 12, 'venue_id' => 3, 'character' => (object)['vss_id' => 44] ] );
 *          // array( 'org_id' => 12, 'vss_id' => 44, 'venue_id' => 3 )
 * @example applicationApprovalScope( (object)['org_id' => 0, 'character' => (object)['vss_id' => -597] ] );
 *          // array( 'org_id' => 597, 'vss_id' => 0, 'venue_id' => 0 )
 * @see AppDetails.php's character-organization lookup for the same negative-vss_id convention.
 */
function applicationApprovalScope( $app_info ) {
	$org_id = 0;
	$vss_id = 0;
	$venue_id = 0;

	if ( is_object( $app_info ) ) {
		if ( isset( $app_info->org_id ) && is_numeric( $app_info->org_id ) && $app_info->org_id > 0 ) {
			$org_id = (int)$app_info->org_id;
		}
		if ( isset( $app_info->venue_id ) && is_numeric( $app_info->venue_id ) && $app_info->venue_id > 0 ) {
			$venue_id = (int)$app_info->venue_id;
		}
		if ( isset( $app_info->character ) && is_object( $app_info->character ) &&
			isset( $app_info->character->vss_id ) && is_numeric( $app_info->character->vss_id ) ) {
			$character_vss_id = (int)$app_info->character->vss_id;
			if ( $character_vss_id > 0 ) {
				$vss_id = $character_vss_id;
			} elseif ( $character_vss_id < 0 && $org_id === 0 ) {
				$org_id = 0 - $character_vss_id;
			}
		}
	}

	return array(
		'org_id' => $org_id,
		'vss_id' => $vss_id,
		'venue_id' => $venue_id,
	);
}

/**
 * Determines the tier an application must be referred to when a conflict of
 * interest is found at a given office, per MES Handbook Ch.9: the application goes
 * to the conflicted officeholder's *supervisor*, not simply one tier up from the
 * application's current tier.
 *
 * The officer chain is VST -> DST -> RST -> NST -> Global. A VST is its own office
 * level, not a venue-scoped DST -- there is no org-wide VST -- so a VST's supervisor
 * is the DST. 'chapter' is folded into 'domain' because the CST rung no longer
 * exists (production has 0 storyteller rows and 0 VSSs at chapter-level orgs); do
 * not confuse this with $_SESSION['admin_level'] == 'chapter', which
 * session_setup.inc uses as the session's name for VST -- that is unrelated.
 *
 * THE SPECIAL CASE: a venue-scoped 'nation' office is a venue's own NST. Its
 * supervisor is the org-wide NST, which sits at the *same* level (Top), not one
 * level above (Global) -- the only office in this chain where that's true. A
 * venue's NST is "semi-primary" for that venue: it may approve on behalf of other
 * venues' NSTs at Top, but not on behalf of the org-wide NST itself, whose conflicts
 * must go all the way to Global. Do not collapse this row into the non-venue-scoped
 * 'nation' case (which does go to Global) -- that would let a venue NST's conflicts
 * be resolved by another venue NST instead of by the org-wide NST, defeating the
 * conflict-of-interest rule this function exists to enforce.
 *
 * Unknown office levels return 5 (Global), the safest / highest rank.
 *
 * @param $officeLevel The office level where the conflict was found: 'domain', 'region',
 *                      'nation', 'globe', or the legacy alias 'chapter' (treated as 'domain').
 * @param $venueScoped Whether the conflicted office is scoped to a single venue rather
 *                      than the whole organization.
 * @return int The tier rank (1-5, per approvalTierRank()) the application must be
 *             referred to.
 * @example approvalReferralTierRank( 'domain', true );  // 2 -- VST's supervisor is the DST (Mid)
 * @example approvalReferralTierRank( 'nation', true );  // 4 -- venue NST's supervisor is the org-wide NST (Top)
 * @example approvalReferralTierRank( 'nation', false ); // 5 -- org-wide NST's supervisor is Global
 * @see approvalTierRank()
 * @see approvalTierName()
 */
function approvalReferralTierRank( $officeLevel, $venueScoped ) {
	$level = strtolower( trim( (string)$officeLevel ) );
	if ( $level === 'chapter' ) {
		$level = 'domain';
	}

	if ( $level === 'domain' ) {
		return $venueScoped ? 2 : 3; // VST -> DST (Mid), or DST -> RST (High)
	}
	if ( $level === 'region' ) {
		return 4; // RST -> NST (Top), regardless of venue scoping
	}
	if ( $level === 'nation' ) {
		return $venueScoped ? 4 : 5; // venue NST -> org-wide NST (Top, the special case); org-wide NST -> Global
	}
	if ( $level === 'globe' ) {
		return 5; // Global has no supervisor
	}
	return 5;
}

/**
 * Decides what an "Approve" click does at a single tier: approve the application outright, refer
 * it to a higher tier, or refer it without being able to move the status at all -- the latter
 * happens when the office that must resolve the conflict sits at the SAME tier (a venue-scoped
 * NST referred to the org-wide NST, both Top) or beyond Global (which has no supervisor).
 *
 * Approval requires all three: no self-approval, no capacity-scoped conflict of interest at this
 * tier (see UserInfoDAO::applicantConflictOffice()), and the application's required tier at or
 * below the tier being decided -- the ratchet fix that replaces the old exact-match comparison
 * (`required_approval == "<Tier>"`), which could never approve an application whose requirement
 * had been raised after it was already pending past that tier.
 *
 * When approval is refused, the referral target is the conflicted office's SUPERVISOR (MES
 * Handbook Ch.9), never simply one tier up: a self-approving applicant is referred to their own
 * highest office's supervisor (or one tier up, if they hold no office at all); a conflicted
 * approver is referred to the conflicted office's supervisor; anything else (the current tier
 * doesn't yet satisfy the requirement, with no self-approval or conflict) is referred one tier up.
 * The referral target is clamped so a referral never moves the application backwards
 * (`max( $tierRank, $referralRank )`).
 *
 * @param $app_info      The application record (uses user_id and required_approval).
 * @param $sessionUserId The user attempting the approval ($_SESSION['user_id']).
 * @param $userInfoDAO   A UserInfoDAO, used for the conflict and office lookups.
 * @param $appScope      The application's approval scope, from applicationApprovalScope().
 * @param $requiredRank  The application's required-approval tier rank, from approvalTierRank().
 * @param $tierRank      The tier rank (1-5) currently being decided, e.g. 1 for "Pending Low".
 * @return array{status: ?string, referredWithoutChange: bool} 'status' is "Approved", a new
 *         "Pending <Tier>" string to write, or null when the application's status must stay
 *         exactly as it is. 'referredWithoutChange' is true only in that last case, when the
 *         application was refused approval and referred but the referral target can't move the
 *         status forward -- callers should surface a conflict-of-interest message rather than
 *         reporting success, instead of silently doing nothing (the historical Pending Global bug).
 * @example resolveApprovalOutcome( $app_info, $_SESSION['user_id'], $userInfoDAO, $appScope, 1, 1 );
 *          // array( 'status' => 'Approved', 'referredWithoutChange' => false ) -- no conflict, Low satisfied
 * @see finalApprovalBlocked()
 * @see UserInfoDAO::applicantConflictOffice()
 * @see approvalReferralTierRank()
 */
function resolveApprovalOutcome( $app_info, $sessionUserId, $userInfoDAO, $appScope, $requiredRank, $tierRank ) {
	$selfApproval = ( $app_info->user_id == $sessionUserId );
	$conflict = $selfApproval ? null : $userInfoDAO->applicantConflictOffice( $sessionUserId, $app_info->user_id, $appScope, $tierRank );

	if ( !$selfApproval && $conflict === null && $requiredRank <= $tierRank ) {
		return array( 'status' => 'Approved', 'referredWithoutChange' => false );
	}

	if ( $selfApproval ) {
		$officeHeld = $userInfoDAO->highestOfficeFor( $app_info->user_id );
		$referralRank = ( $officeHeld !== null )
			? approvalReferralTierRank( $officeHeld['org_level'], $officeHeld['venue_scoped'] )
			: $tierRank + 1;
	} elseif ( $conflict !== null ) {
		$referralRank = approvalReferralTierRank( $conflict['org_level'], $conflict['venue_scoped'] );
	} else {
		$referralRank = $tierRank + 1;
	}

	$targetRank = max( $tierRank, $referralRank );

	if ( $targetRank > $tierRank && $targetRank <= 5 ) {
		return array( 'status' => 'Pending ' . approvalTierName( $targetRank ), 'referredWithoutChange' => false );
	}

	// Target is at/below the current tier (e.g. a venue NST's conflict referred to the org-wide
	// NST, the same rank) or beyond Global (which has no supervisor): the status can't move, but
	// this was still a refusal to approve, not a plain no-op.
	return array( 'status' => null, 'referredWithoutChange' => true );
}

function addRevision( $app_id, $change ) {
	global $db;
	$query = "INSERT INTO revisions (application_id, user_id, revision_date, revision) VALUES (?, ?, now(), ?)";
	$db->query( $query, [$app_id, $_SESSION["user_id"], $change] );
}

function getPlayerOrgID( $user_id ) {
	global $db;
	$query = "SELECT org_id FROM users WHERE ID=?";
	$db->query($query, [$user_id]);
	$user_info = $db->nextRow();
	return $user_info["org_id"];
}

?>