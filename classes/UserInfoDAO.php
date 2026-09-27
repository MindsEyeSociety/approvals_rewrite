<?php

class UserInfoDAO {
	var $db;
	var $portal_db;
	var $_CACHE = array();
	var $_POSITION_CACHE = array();

	function __construct( $db, $portal_db = null ) {
		$this->db = $db;
		$this->portal_db = $portal_db;
	}

	function updateLastLoginDate( $id ) {
		if( isset( $this->_CACHE[$id] ) ) {
			$this->_CACHE[$id]['last_login_date'] = getdate();
		}
		$this->db->query( "UPDATE users SET last_login_date = now() WHERE id=?", [$id] );
	}

	function readApprovalsDBInfo( $id ) {
		$res = $this->db->query(
			"SELECT ww_number, super_user, org_id, email, name, unix_timestamp(last_login_date) as last_login_date FROM users WHERE id=?",
			[$id]
		);
		return $res->nextRow();
	}

	/**
	 * Read user info from the Portal database (mes-portal.User)
	 * Returns empty array with default keys if portal DB is unavailable
	 */
	function readPortalUserInfo( $memnumber ) {
		if( $this->portal_db != null ) {
			$res = $this->portal_db->query(
				"SELECT firstName, lastName, emailAddress FROM User WHERE membershipNumber = ?",
				[$memnumber]
			);
			if( $res !== false && $res->numRows() > 0 ) {
				return $res->nextRow();
			}
		}

		return array(
		 "firstName" => '',
		 "lastName"  => '',
		 "emailAddress" => ''
		);
	}

	/**
	 * Membership status of a Portal member, looked up by ww_number (Portal
	 * membershipNumber). Exists so callers can tell a genuinely expired
	 * membership apart from "we couldn't find out" -- collapsing those into a
	 * single boolean is what let a three-week Portal outage silently look
	 * identical to every member's membership having expired, and every
	 * approval-notification email got dropped as a result.
	 *
	 * @param $ww_number The Portal membershipNumber to look up.
	 * @return string One of 'active', 'expired', 'unknown' (status could not
	 *         be determined, e.g. no matching Portal row or an unparseable/
	 *         blank expiration date), or 'portal_unavailable' (the Portal DB
	 *         connection or query itself failed).
	 * @see membershipStatus()
	 * @see isMemberActiveByWwNumber()
	 */
function membershipStatusByWwNumber( $ww_number ) {
    // If the portal DB is unavailable we cannot verify membership status.
    if( $this->portal_db == null ) {
        error_log("UserInfoDAO::membershipStatusByWwNumber - portal DB unavailable for ww_number $ww_number");
        return 'portal_unavailable';
    }

    $res = $this->portal_db->query(
        "SELECT membershipExpiration FROM User WHERE membershipNumber = ?",
        [$ww_number]
    );
    if( $res === false ) {
        error_log("UserInfoDAO::membershipStatusByWwNumber - portal query failed for ww_number $ww_number");
        return 'portal_unavailable';
    }
    if( $res->numRows() === 0 ) {
        error_log("UserInfoDAO::membershipStatusByWwNumber - no portal row for ww_number $ww_number");
        return 'unknown';
    }

    $row = $res->nextRow();
    $expiration = $row['membershipExpiration'] ?? '';
    if( empty( $expiration ) ) {
        error_log("UserInfoDAO::membershipStatusByWwNumber - blank membershipExpiration for ww_number $ww_number");
        return 'unknown';
    }

    $expirationTime = strtotime( $expiration );
    if( $expirationTime === false ) {
        error_log("UserInfoDAO::membershipStatusByWwNumber - unparseable membershipExpiration '$expiration' for ww_number $ww_number");
        return 'unknown';
    }

    if( $expirationTime > time() ) {
        return 'active';
    }

    error_log("UserInfoDAO::membershipStatusByWwNumber - membership EXPIRED $expiration for ww_number $ww_number");
    return 'expired';
}

	/**
	 * Membership status of a user, looked up by local user id via their
	 * ww_number. Same four-way result as membershipStatusByWwNumber(), for
	 * callers that only have a local users.id.
	 *
	 * @param $user_id The local users.id to check.
	 * @return string One of 'active', 'expired', 'unknown', or 'portal_unavailable'.
	 * @see membershipStatusByWwNumber()
	 * @see isMemberActive()
	 */
function membershipStatus( $user_id ) {
    $userInfo = $this->readApprovalsDBInfo( $user_id );
    // If we cannot retrieve a membership number, status cannot be determined.
    if( empty( $userInfo ) || empty( $userInfo['ww_number'] ) ) {
        error_log("UserInfoDAO::membershipStatus - missing ww_number for user $user_id");
        return 'unknown';
    }
    return $this->membershipStatusByWwNumber( $userInfo['ww_number'] );
}

	/**
	 * Check if a member is active by querying the Portal database
	 * Returns true if membershipExpiration is in the future (still active)
	 * Thin boolean wrapper over membershipStatusByWwNumber(), kept bit-identical
	 * to its pre-refactor behaviour: several email methods and other call sites
	 * depend on this exact true/false contract. Use membershipStatusByWwNumber()
	 * directly when the caller needs to distinguish "expired" from "unknown" or
	 * "portal_unavailable".
	 *
	 * @see membershipStatusByWwNumber()
	 */
function isMemberActiveByWwNumber( $ww_number ) {
    return $this->membershipStatusByWwNumber( $ww_number ) === 'active';
}

	/**
	 * Check if a user is an active member by user ID
	 * Looks up the ww_number from the local users table, then checks the Portal DB
	 * Thin boolean wrapper over membershipStatus(), kept bit-identical to its
	 * pre-refactor behaviour for the same reason as isMemberActiveByWwNumber().
	 *
	 * @see membershipStatus()
	 */
function isMemberActive( $user_id ) {
    return $this->membershipStatus( $user_id ) === 'active';
}

	function getVSTPositions( $userID ) {
		$positions = array();
		$res = $this->db->query("SELECT name FROM vsss WHERE storyteller_id=?", [$userID]);
		while( $row = $res->nextRow() ) {
			$positions[] = "VST for " . $row["name"];
		}
		return $positions;
	}

	function getOrgSTPositions( $userID ) {
		$positions = array();
		$query =
			"SELECT s.assistant, v.venue, o.chapter, o.domain, ".
			"o.region, o.nation, o.org_name ".
			"FROM storytellers s ".
			"LEFT JOIN organizations o ON s.organization_id = o.id ".
			"LEFT JOIN venues v ON s.venue_id = v.id ".
			"WHERE s.user_id=?";
		$res = $this->db->query($query, [$userID]);
		while( $row = $res->nextRow() ) {
			$position = '';
			if( $row['assistant'] ) {
				$position .= 'A';
			}
			if( $row['chapter'] ) {
				$position .= 'CST';
			} elseif( $row['domain'] ) {
				$position .= 'DST';
			} elseif( $row['region'] ) {
				$position .= 'RST';
			} elseif( $row['nation'] ) {
				$position .= 'NST';
			}
			if( $row['venue'] ) {
				$position .= " " . $row['venue'];
			}
			$position .= ' for '.$row['org_name'];
			$positions[] = $position;
		}
		return $positions;
	}

	/**
	 * SQL fragment classifying an `organizations` row (aliased `o`) into the org level of the
	 * deepest non-empty hierarchy column, folding `chapter` into `domain` since that rung of
	 * the hierarchy is dead. Shared by every query below that needs to compare a tier's office
	 * level against an actual office. Never receives caller-supplied data, so it is safe to
	 * splice into query text; only the *result* of comparing it is ever bound as a parameter.
	 */
	private const ORG_LEVEL_CASE =
		"CASE ".
		"WHEN o.chapter <> '' THEN 'domain' ".
		"WHEN o.domain  <> '' THEN 'domain' ".
		"WHEN o.region  <> '' THEN 'region' ".
		"WHEN o.nation  <> '' THEN 'nation' ".
		"ELSE 'globe' END";

	/** Same classification as ORG_LEVEL_CASE, but as a numeric rank (domain=1 .. globe=4) for ORDER BY. */
	private const ORG_LEVEL_RANK_CASE =
		"CASE ".
		"WHEN o.chapter <> '' THEN 1 ".
		"WHEN o.domain  <> '' THEN 1 ".
		"WHEN o.region  <> '' THEN 2 ".
		"WHEN o.nation  <> '' THEN 3 ".
		"ELSE 4 END";

	/**
	 * True hierarchy depth of an org -- chapter=1 .. globe=5 -- deliberately NOT folded the way
	 * ORG_LEVEL_CASE folds chapter into domain. Used only for ORDER BY: two offices at genuinely
	 * different depths can both satisfy the folded level filter (a chapter org and a domain org
	 * both classify as 'domain'), and the more senior one must win deterministically rather than
	 * being chosen arbitrarily by LIMIT 1.
	 */
	private const ORG_HIERARCHY_RANK_CASE =
		"CASE ".
		"WHEN o.chapter <> '' THEN 1 ".
		"WHEN o.domain  <> '' THEN 2 ".
		"WHEN o.region  <> '' THEN 3 ".
		"WHEN o.nation  <> '' THEN 4 ".
		"ELSE 5 END";

	/**
	 * Which office level and venue-scoping a given approval tier corresponds to. Whitelisted by
	 * literal `$tierRank` int keys only (1..5); a `$tierRank` that isn't one of these keys yields
	 * no match, so this array can never be influenced by a caller-supplied value reaching SQL --
	 * the *value* selected from it (a fixed 'domain'/'region'/'nation'/'globe' string) is what
	 * gets bound as a query parameter, never spliced into the SQL text.
	 */
	private const TIER_LEVELS = array(
		1 => array( 'level' => 'domain', 'venue_scope' => 'required' ),  // Low    -> VST
		2 => array( 'level' => 'domain', 'venue_scope' => 'forbidden' ), // Mid    -> DST
		3 => array( 'level' => 'region', 'venue_scope' => 'either' ),    // High   -> RST
		4 => array( 'level' => 'nation', 'venue_scope' => 'either' ),    // Top    -> NST
		5 => array( 'level' => 'globe',  'venue_scope' => 'either' ),    // Global -> --
	);

	/**
	 * Find the office, if any, at which the session user's assistant role conflicts with the
	 * applicant for the tier being approved.
	 *
	 * Handbook Ch.9 "Conflict of Interest" scopes a disqualifying relationship to the office
	 * where the decision is "pending before their office" -- not to any assistant relationship
	 * the two people share anywhere in the org tree. The same person can be a primary
	 * officeholder at one tier (no conflict) and the applicant's assistant at a different tier
	 * (a real conflict, but only at that tier), so the check must be scoped to the office that
	 * governs the tier being approved, not evaluated org-wide. This replaces the old, unscoped
	 * isAssistantToApplicant(), which blocked a VST from approving a routine Low application
	 * because she assisted the applicant in a completely different office.
	 *
	 * Two independent sources of conflict are checked, in order, short-circuiting on the first match:
	 *   (A) Office tier -- a `storytellers` primary (assistant=0) the applicant holds at an
	 *       office whose org level and venue-scoping match $tierRank (see table below), where
	 *       the session user holds an assistant (assistant=1) row at the same organization_id
	 *       and the same COALESCE(venue_id,0).
	 *   (B) VST bridge (tier 1 / Low only) -- per Handbook Ch.9 "Assistants", roughly 270 VSTs
	 *       exist only as a `vsss.storyteller_id` with no corresponding `storytellers` row, so
	 *       source (A) can never see them. This source instead checks whether the applicant is
	 *       the current VST (`vsss.storyteller_id`) of the *application's own* VSS
	 *       ($appScope['vss_id']) and the session user holds a venue-scoped assistant row at
	 *       that VSS's organization+venue. It is deliberately pinned to the application's own
	 *       VSS via `v.id = ?`: matching any VSS that merely shares the org+venue was the
	 *       original bug's shape in miniature -- it would block an approver on a VSS she runs
	 *       because the applicant happens to run a *different* VSS at the same venue. (B) is
	 *       skipped entirely when $appScope['vss_id'] is not a positive id (1357 applications
	 *       have no VSS at all).
	 *
	 * Tier -> office -> org level -> venue-scoping:
	 *   1 Low    -> VST -> domain (chapter folded in; that rung is dead) -> required (venue_id > 0)
	 *   2 Mid    -> DST -> domain                                        -> forbidden (NULL/0)
	 *   3 High   -> RST -> region                                        -> either
	 *   4 Top    -> NST -> nation                                        -> either
	 *   5 Global -> --  -> globe                                         -> either
	 *
	 * When multiple offices would conflict, the most conservative (highest org level) is
	 * reported. In practice each tier maps to exactly one org level, so this only matters for
	 * tier 1, where (A) and (B) both report 'domain' -- (A) is simply checked first.
	 *
	 * $appScope's 'org_id' and 'venue_id' are accepted for symmetry with the caller's other
	 * application-scope data but are not consulted here: the office match is keyed by the
	 * organization+venue the two parties actually share, not by the application's own office,
	 * and 'vss_id' alone is what's needed to keep source (B) pinned correctly.
	 *
	 * @param $sessionUserId   The user attempting the approval.
	 * @param $applicantUserId The owner of the application (applications.user_id).
	 * @param $appScope        array( 'org_id' => int, 'vss_id' => int, 'venue_id' => int )
	 *                         describing the application; any may be 0 meaning unknown/none.
	 * @param $tierRank        int 1..5, the tier being approved (1=Low, 2=Mid, 3=High, 4=Top, 5=Global).
	 * @return array|null null when there is no conflict, otherwise
	 *         array( 'org_level' => 'domain'|'region'|'nation'|'globe', 'venue_scoped' => bool )
	 *         describing the conflicting office, which the caller needs to work out which
	 *         officer the application must be referred to.
	 * @see highestOfficeFor()
	 */
	function applicantConflictOffice( $sessionUserId, $applicantUserId, $appScope, $tierRank ) {
		if ( !array_key_exists( $tierRank, self::TIER_LEVELS ) ) {
			return null;
		}
		$tier = self::TIER_LEVELS[$tierRank];
		$level = $tier['level'];

		$venueClause = '';
		if ( $tier['venue_scope'] === 'required' ) {
			$venueClause = " AND a.venue_id > 0";
		} elseif ( $tier['venue_scope'] === 'forbidden' ) {
			$venueClause = " AND COALESCE(a.venue_id,0) = 0";
		}

		// (A) office tier: session is an assistant under a storytellers-primary the applicant
		//     holds at an office whose level matches the tier (COALESCE treats NULL/0 venue as org-wide).
		//     Ordering is by TRUE org hierarchy depth first, then org-wide before venue-scoped, so
		//     the pick is never arbitrary: a chapter and a domain org both satisfy the folded level
		//     filter, and a pair can hold both an org-wide and a venue-scoped office at one level.
		$qA =
			"SELECT (COALESCE(a.venue_id,0) > 0) AS venue_scoped ".
			"FROM storytellers a ".
			"JOIN storytellers p ON a.organization_id = p.organization_id ".
			"  AND COALESCE(a.venue_id,0) = COALESCE(p.venue_id,0) ".
			"JOIN organizations o ON o.id = a.organization_id ".
			"WHERE a.user_id = ? AND a.assistant = 1 AND p.user_id = ? AND p.assistant = 0 ".
			"  AND ".self::ORG_LEVEL_CASE." = ?".
			$venueClause.
			" ORDER BY ".self::ORG_HIERARCHY_RANK_CASE." DESC, venue_scoped ASC LIMIT 1";
		$row = $this->db->query( $qA, [ $sessionUserId, $applicantUserId, $level ] )->nextRow();
		if ( !empty( $row ) ) {
			return array(
				'org_level'    => $level,
				'venue_scoped' => (bool)( $row['venue_scoped'] ?? false ),
			);
		}

		// (B) VST bridge, tier 1 only, pinned to the application's own VSS.
		$vssId = $appScope['vss_id'] ?? 0;
		if ( $tierRank === 1 && $vssId > 0 ) {
			$qB =
				"SELECT ".self::ORG_LEVEL_CASE." AS org_level ".
				"FROM storytellers a ".
				"JOIN vsss v ON v.org_id = a.organization_id AND v.venue_id = a.venue_id ".
				"JOIN organizations o ON o.id = v.org_id ".
				"WHERE a.user_id = ? AND a.assistant = 1 AND a.venue_id > 0 ".
				"  AND v.storyteller_id = ? AND v.id = ? ".
				"LIMIT 1";
			$row = $this->db->query( $qB, [ $sessionUserId, $applicantUserId, $vssId ] )->nextRow();
			if ( !empty( $row ) ) {
				return array(
					'org_level'    => $row['org_level'] ?? $level,
					'venue_scoped' => true,
				);
			}
		}

		return null;
	}

	/**
	 * Find the highest (most senior) primary office a user holds, so a caller can refer a
	 * self-approved application to that user's *supervising* officer rather than assuming the
	 * next tier up. Without this, an RST self-approving at Pending Low would be referred to a
	 * subordinate DST instead of their actual supervisor.
	 *
	 * Only primary positions (assistant = 0) count as an office someone supervises -- an
	 * assistant post has no subordinates of its own to hand a referral to. A user with only
	 * assistant posts (or no storyteller rows at all) returns null so the caller can fall back
	 * to its own default.
	 *
	 * A user who runs a VSS but has no `storytellers` row at all is a VST in all but name --
	 * roughly 270 VSTs exist only via `vsss.storyteller_id` -- so that is checked as a fallback
	 * when the `storytellers` lookup finds nothing, reporting a venue-scoped domain office.
	 *
	 * @param $userId The user whose highest office is being looked up.
	 * @return array|null null when the user holds no office at all, otherwise
	 *         array( 'org_level' => 'domain'|'region'|'nation'|'globe', 'venue_scoped' => bool )
	 *         describing their most senior primary office -- the same shape returned by
	 *         applicantConflictOffice(), for the caller to look up who supervises it.
	 * @see applicantConflictOffice()
	 */
	function highestOfficeFor( $userId ) {
		$qPrimary =
			"SELECT ".self::ORG_LEVEL_CASE." AS org_level, ".
			"  (COALESCE(s.venue_id,0) > 0) AS venue_scoped ".
			"FROM storytellers s ".
			"JOIN organizations o ON o.id = s.organization_id ".
			"WHERE s.user_id = ? AND s.assistant = 0 ".
			"ORDER BY ".self::ORG_HIERARCHY_RANK_CASE." DESC, (COALESCE(s.venue_id,0) > 0) ASC ".
			"LIMIT 1";
		$row = $this->db->query( $qPrimary, [ $userId ] )->nextRow();
		if ( !empty( $row ) ) {
			return array(
				'org_level'    => $row['org_level'] ?? null,
				'venue_scoped' => (bool)( $row['venue_scoped'] ?? false ),
			);
		}

		// Fallback: a VST who exists only on vsss.storyteller_id, with no storytellers row.
		$qVst =
			"SELECT ".self::ORG_LEVEL_CASE." AS org_level ".
			"FROM vsss v ".
			"JOIN organizations o ON o.id = v.org_id ".
			"WHERE v.storyteller_id = ? ".
			"LIMIT 1";
		$row = $this->db->query( $qVst, [ $userId ] )->nextRow();
		if ( !empty( $row ) ) {
			return array(
				'org_level'    => $row['org_level'] ?? null,
				'venue_scoped' => true,
			);
		}

		return null;
	}

	function getUserInfo( $id ) {
		if( is_null( $id ) ) {
			return( array() );
		} elseif (!is_numeric( $id ) ) {
			error_log("UserInfoDAO::getUserInfo – non-numeric id: " . var_export($id, true));
			return array();
		}
		if( !isset( $this->_CACHE[$id] ) ) {
			$userInfo = $this->readApprovalsDBInfo( $id );
			if (empty($userInfo) || empty($userInfo['ww_number'])) {
				$this->_CACHE[$id] = array('name' => 'No Name Set', 'email' => '');
				return $this->_CACHE[$id];
			}
			$portal_info = $this->readPortalUserInfo( $userInfo["ww_number"] );

			if( $portal_info["lastName"] != "" or $portal_info["firstName"] != "" ) {
				$name = $portal_info['firstName'] . " " . $portal_info['lastName'];
			} else {
				// Fallback to local users table name, then to placeholder
				$name = !empty($userInfo['name']) ? $userInfo['name'] : "No Name Set";
			}
			$userInfo['name'] = $name;
			// Prefer email from portal, fallback to email from approvals users table
			$userInfo['email'] = !empty($portal_info['emailAddress']) ? $portal_info['emailAddress'] : (isset($userInfo['email']) ? $userInfo['email'] : '');

			// Now list the storytellers table positions, (A)CST,DST,RST, and NST
			$this->_CACHE[$id] = $userInfo;
		}
		return $this->_CACHE[$id];
	}

	function getCachedIDs() {
		return array_keys($this->_CACHE);
	}

	function getPositions( $userID ) {
		if( array_key_exists( $userID, $this->_POSITION_CACHE ) ) {
			return $this->_POSITION_CACHE[$userID];
		}

		$userInfo = $this->getUserInfo( $userID );
		$positions = array();
		if( $userInfo['super_user'] ?? false ) {
			$positions[] = "Super User";
		}

		$positions = array_merge( $positions, $this->getVSTPositions( $userID ) );
		$positions = array_merge( $positions, $this->getOrgSTPositions( $userID ) );

		$this->_POSITION_CACHE[$userID] = $positions;
		return $positions;
	}

	function readIdsByCamNumber( $cam_number ) {
		$swap_ids = array();
		$query="SELECT u.username, u.id ".
			"FROM users u ".
			"WHERE u.ww_number=?";
		$res = $this->db->query($query, [$cam_number]);
		if ($res->numRows() > 1) {
			while($swap_id = $res->nextRow()) {
				$swap_ids[] = $swap_id;
			}
		}
		return $swap_ids;
	}

	function isUserUnderOrganization( $user_id, $organization_ids ) {
		if( count($organization_ids) == 0 ) {
			return false;
		}
		$placeholders = implode(",", array_fill(0, count($organization_ids), "?"));
		$query =
			"SELECT count(*) as matching_users ".
			"FROM users ".
			"WHERE id = ? ".
			"AND org_id in ($placeholders)";
		$res = $this->db->query($query, array_merge([$user_id], $organization_ids));
		$result = $this->db->nextRow();
		return $result['matching_users'] > 0;
	}

	function readIdsByOrganizations( $organization_ids ) {
		if( count($organization_ids) == 0 ) {
			return Array();
		}
		$placeholders = implode(",", array_fill(0, count($organization_ids), "?"));
		$query =
			"SELECT u.id ".
			"FROM users u ".
			"INNER JOIN `mes-portal`.User pu ON u.ww_number = pu.membershipNumber AND pu.membershipExpiration > NOW() ".
			"WHERE u.org_id in ($placeholders)";
		$res = $this->db->query($query, $organization_ids);
		return $res->getAllRows();
	}
}
?>