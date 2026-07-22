<?php
include_once ("vo/UserVO.class.php");
include_once("include/pagination.inc");

class UserService {
	function UserService() {
	}

	function getMigrationCandidate() {

	}

	function fetchMatchingUsers( $filter = array() ) {
		global $db;
		$query =
			"SELECT puc.first_name as firstname, ".
			"  puc.last_name as lastname, ".
			"  puc.email as email, ".
			"  u.*, ".
			"  o.org_name as org_name " .
			"FROM users u ".
			"LEFT JOIN organizations o ON u.org_id = o.id " .
			"LEFT JOIN portal_user_cache puc ON u.ww_number = puc.ww_number ";

		$query .= $this->buildSearchClause($filter);

		$query .= sprintf(
			"ORDER BY lastname, firstname ".
			"LIMIT %d, 20",
			paginationOffset($filter["skip"])
		);

		$rs = $db->query($query);
		$rows = array();
		while( $row = $rs->nextRow() ) {
			$row['name'] = $this->buildName( $row['firstname'], $row['lastname'] );
			$rows[] = $row;
		}
		return $rows;
	}

	function countMatchingUsers( $filter = array() ) {
		global $db;

		$countQuery =
			"SELECT count(*) as usercount " .
			"FROM users u " .
			"LEFT JOIN organizations o ON u.org_id = o.id " .
			"LEFT JOIN portal_user_cache puc ON u.ww_number = puc.ww_number ";
		$countQuery .= $this->buildSearchClause($filter);
		$db->query($countQuery);
		$row = $db->nextRow();
		return $row["usercount"];
	}

	function buildSearchClause( $filter = array() ) {
		global $db;
		if( "" == ($filter["search"] ?? "") ) {
			return "WHERE puc.membership_expiration > NOW() ";
		}
		// Use prefix LIKE (no leading wildcard) so indexes on ww_number,
		// last_name, and first_name can be used by the query planner.
		$searchClause =
			"WHERE ( ".
			"  u.ww_number LIKE '%s' ".
			"  OR puc.last_name LIKE '%s' ".
			"  OR puc.first_name LIKE '%s' ".
			") ".
			"AND puc.membership_expiration > NOW() ";
		$searchClause = sprintf(
			$searchClause,
			$db->escape($filter["search"]."%"),
			$db->escape($filter["search"]."%"),
			$db->escape($filter["search"]."%")
		);
		return $searchClause;
	}

	function buildName ( $firstname, $lastname ) {
		if( "" == $firstname ) {
			if ( "" == $lastname ) {
				return "No Name Set";
			} else {
				return $lastname;
			}
		} else {
			if( "" == $lastname ) {
				return $firstname;
			} else {
				return $lastname . ", " . $firstname;
			}
		}
	}

}

?>
