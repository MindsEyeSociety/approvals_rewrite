<?php
include_once ("vo/UserVO.class.php");

class UserService {
	function UserService() {
	}

	function getMigrationCandidate() {

	}

	function fetchMatchingUsers( $filter = array() ) {
		global $db;
		$query=
			"SELECT pu.firstName as firstname, ".
			"  pu.lastName as lastname, ".
			"  pu.emailAddress as email, ".
			"  pu.phoneNumber as phone, ".
			"  u.*, ".
			"  o.org_name as org_name " .
			"FROM users u ".
			"LEFT JOIN organizations o on u.org_id = o.id " .
			"LEFT JOIN `mes-portal`.User pu ON u.ww_number = pu.membershipNumber ";
	
		$query .= $this->buildSearchClause($filter);
	
		$query .= sprintf(
			"ORDER BY lastname, firstname ".
			"LIMIT %d, 20",
			$filter["skip"]
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
			"LEFT JOIN organizations o on u.org_id = o.id " .
			"LEFT JOIN `mes-portal`.User pu ON u.ww_number = pu.membershipNumber ";
		$countQuery .= $this->buildSearchClause($filter);
		$db->query($countQuery);
		$row=$db->nextRow();
		return $row["usercount"];
	}
	
	function buildSearchClause( $filter = array() ) {
		global $db;
		if( "" == $filter["search"] ) {
			return "WHERE pu.membershipExpiration > NOW() ";
		}
		$searchClause =
			"WHERE ( ".
			"  u.ww_number like '%s' ".
			"  or pu.lastName = '%s' ".
			"  or pu.firstName = '%s' ".
			") ".
			"AND pu.membershipExpiration > NOW() ";
		$searchClause = sprintf(
			$searchClause,
			$db->escape("%".$filter["search"]."%"),
			$db->escape($filter["search"]),
			$db->escape($filter["search"])
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
