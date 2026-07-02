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
		$query = "UPDATE users SET last_login_date = now() WHERE id=%d";
		if( isset( $this->_CACHE[$id] ) ) {
			$this->_CACHE[$id]['last_login_date'] = getdate();
		}
		$this->db->query( sprintf( $query, $id ) );
	}

	function readApprovalsDBInfo( $id ) {
		$query = "SELECT ww_number, super_user, org_id, email, name, unix_timestamp(last_login_date) as last_login_date FROM users WHERE id=%d";
		$res = $this->db->query(sprintf($query,$id));
		return $res->nextRow();
	}

	/**
	 * Read user info from the Portal database (mes-portal.User)
	 * Returns empty array with default keys if portal DB is unavailable
	 */
	function readPortalUserInfo( $memnumber ) {
		if( $this->portal_db != null ) {
			$query =
				"SELECT firstName, lastName, emailAddress " .
				"FROM User " .
				"WHERE membershipNumber = '%s'";
			$res = $this->portal_db->query(sprintf($query,mysqli_escape_string($this->portal_db->dbh,$memnumber)));
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
	 * Check if a member is active by querying the Portal database
	 * Returns true if membershipExpiration is in the future (still active)
	 * Falls back to true (allow) if portal DB is unavailable
	 */
function isMemberActiveByWwNumber( $ww_number ) {
    // If the portal DB is unavailable we cannot verify membership status.
    // Treat the member as inactive to avoid sending email to possibly expired accounts.
    if( $this->portal_db == null ) {
        error_log("UserInfoDAO::isMemberActiveByWwNumber – portal DB unavailable for ww_number $ww_number");
        return false;
    }

    $query = "SELECT membershipExpiration FROM User WHERE membershipNumber = '%s'";
    $res = $this->portal_db->query(sprintf($query, mysqli_escape_string($this->portal_db->dbh, $ww_number)));
    if( $res !== false && $res->numRows() > 0 ) {
        $row = $res->nextRow();
        $expiration = $row['membershipExpiration'];
        if( !empty( $expiration ) ) {
            return strtotime( $expiration ) > time();
        }
    }

    // If we can't determine membership status, treat as inactive.
    return false;
}

	/**
	 * Check if a user is an active member by user ID
	 * Looks up the ww_number from the local users table, then checks the Portal DB
	 */
function isMemberActive( $user_id ) {
    $userInfo = $this->readApprovalsDBInfo( $user_id );
    // If we cannot retrieve a membership number, treat the user as inactive.
    if( empty( $userInfo ) || empty( $userInfo['ww_number'] ) ) {
        error_log("UserInfoDAO::isMemberActive – missing ww_number for user $user_id");
        return false;
    }
    // If the portal DB is unavailable, isMemberActiveByWwNumber will return false.
    return $this->isMemberActiveByWwNumber( $userInfo['ww_number'] );
}

	function getVSTPositions( $userID ) {
		$positions = array();
		$query = "SELECT name FROM vsss WHERE storyteller_id=%d";
		$res = $this->db->query(sprintf($query, $userID));
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
			"WHERE s.user_id=%d";
		$res = $this->db->query(sprintf($query,$userID));
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
	 * Whether the session user is a storyteller-assistant whose primary storyteller is the applicant.
	 *
	 * Enforces the handbook rule that an assistant may not resolve a decision in which their primary
	 * officer has a conflict of interest (here: the primary being the applicant). Used to block final
	 * approval of an application by an assistant of the applicant. The applicant is treated as the
	 * assistant's primary via the union of two sources:
	 *   (A) org tier — a `storytellers` primary (assistant=0) the applicant holds at the same office
	 *       (organization + venue; NULL/0 venue = org-wide) where the session user is an assistant; and
	 *   (B) VST tier — the current VST (`vsss.storyteller_id`) of any VSS in an org+venue where the
	 *       session user holds a venue-scoped assistant position (covers VSTs that exist only on `vsss`).
	 *
	 * @param $sessionUserId   The user attempting the approval.
	 * @param $applicantUserId The owner of the application (applications.user_id).
	 * @return bool True if the session user is an assistant of the applicant (block final approval).
	 * @see finalApprovalBlocked()
	 */
	function isAssistantToApplicant( $sessionUserId, $applicantUserId ) {
		// (A) org tier: session is an assistant under a storytellers-primary the applicant holds
		//     at the same office (org + venue; COALESCE treats NULL/0 venue as org-wide).
		$qA =
			"SELECT COUNT(*) AS c FROM storytellers a ".
			"JOIN storytellers p ON a.organization_id = p.organization_id ".
			"  AND COALESCE(a.venue_id,0) = COALESCE(p.venue_id,0) ".
			"WHERE a.user_id = ? AND a.assistant = 1 AND p.user_id = ? AND p.assistant = 0";
		$row = $this->db->query( $qA, [ $sessionUserId, $applicantUserId ] )->nextRow();
		if( intval( $row['c'] ) > 0 ) {
			return true;
		}
		// (B) VST tier: session holds a venue-scoped assistant position whose org+venue has a VSS
		//     the applicant currently runs (vsss.storyteller_id).
		$qB =
			"SELECT COUNT(*) AS c FROM storytellers a ".
			"JOIN vsss v ON v.org_id = a.organization_id AND v.venue_id = a.venue_id ".
			"WHERE a.user_id = ? AND a.assistant = 1 AND a.venue_id > 0 AND v.storyteller_id = ?";
		$row = $this->db->query( $qB, [ $sessionUserId, $applicantUserId ] )->nextRow();
		return intval( $row['c'] ) > 0;
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
		if( $userInfo['super_user'] ) {
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
			"WHERE u.ww_number='%s'";
		$res = $this->db->query(sprintf($query, $this->db->escape( $cam_number ) ) );
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
		$query =
			"SELECT count(*) as matching_users ".
			"FROM users ".
			"WHERE id = %d ".
			"AND org_id in (".str_repeat("%d,",count( $organization_ids )-1 ). "%d".")";
		$query = call_user_func_array( "sprintf", array_merge((array)$query,(array)$user_id, $organization_ids));
		$res = $this->db->query($query);
		$result = $this->db->nextRow();
		return $result['matching_users'] > 0;
	}

	function readIdsByOrganizations( $organization_ids ) {
		if( count($organization_ids) == 0 ) {
			return Array();
		}
		$query =
			"SELECT u.id ".
			"FROM users u ".
			"INNER JOIN `mes-portal`.User pu ON u.ww_number = pu.membershipNumber AND pu.membershipExpiration > NOW() ".
			"WHERE u.org_id in (".str_repeat("%d,",count( $organization_ids )-1 ). "%d".")";
		$query = call_user_func_array( "sprintf", array_merge((array)$query,$organization_ids));
		$res = $this->db->query($query);
		return $res->getAllRows();
	}
}
?>