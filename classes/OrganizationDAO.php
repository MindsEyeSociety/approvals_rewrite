<?php
include_once("classes/Organization.class.php");

class OrganizationDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function readSubOrganizationIDs( $organization ) {
		$query = "SELECT id FROM organizations WHERE globe=?";
		$params = [$organization->globe];
		if( !empty( $organization->nation ) ) {
			$query .= " AND nation=?";
			$params[] = $organization->nation;
			if ( !empty( $organization->region ) ) {
				$query .= " AND region=?";
				$params[] = $organization->region;
				if ( !empty( $organization->domain ) ) {
					$query .= " AND domain=?";
					$params[] = $organization->domain;
					if ( !empty( $organization->chapter ) ) {
						$query .= " AND chapter=?";
						$params[] = $organization->chapter;
					}
				}
			}
		}
		$this->db->query($query, $params);
		$results = $this->db->getAllRows();
		$org_list = array();
		foreach ( $results as $row ) {
			$org_list[] = $row["id"];
		}
		return $org_list;
	}

	function readByID( $organizationID ) {
		$query = "SELECT * FROM organizations WHERE ID=?";
		$this->db->query($query, [$organizationID]);
		$row = $this->db->nextRow();
		if( count( $row ) > 0 ) {
			return $this->_organizationFromRow( $row );
		} else {
			return null;
		}
	}

	function _organizationFromRow( $row ) {
		return new Organization(
			$row['globe'],
			$row['nation'],
			$row['region'],
			$row['domain'],
			$row['chapter'],
			$row['city'],
			$row['state'],
			$row['country'],
			$row['admin_user_id'],
			$row['org_name'],
			$row['email'],
			$row['email_is_google'],
			$row['id'],
			$row['active']
		);
	}

	function hasDuplicate( $organization ) {
		if( strtolower($organization->domain) == 'pending'
			|| strtolower($organization->chapter == 'pending')) {
			return false;
		}
		$query = "SELECT count(*) as duplicates FROM organizations WHERE globe=? AND nation=? AND region=? AND domain=? AND chapter=?";
		$params = [$organization->globe, $organization->nation, $organization->region, $organization->domain, $organization->chapter];
		if( $organization->id !="") {
			$query .= " AND id<>?";
			$params[] = $organization->id;
		}
		$this->db->query( $query, $params );
		$row = $this->db->nextRow();
		return intval($row["duplicates"]) > 0;
	}

	function insert( $organization ) {
		$query = "INSERT INTO organizations (globe, nation, region, domain, chapter, org_name, city, state, country, email, email_is_google, admin_user_id, active) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$this->db->query( $query, [
			$organization->globe,
			$organization->nation,
			$organization->region,
			$organization->domain,
			$organization->chapter,
			$organization->org_name,
			$organization->city,
			$organization->state,
			$organization->country,
			$organization->email,
			$organization->email_is_google,
			$organization->admin_user_id,
			$organization->active
		]);
		$organization->id = $this->db->getInsertId();
		return $organization;
	}

	function getParentOrgID( $org_id ) {
		$parentQuery = "SELECT id FROM organizations WHERE globe=? AND nation=? AND region=? AND domain=? AND chapter=?";
		$this->db->query("SELECT globe,nation,region,domain,chapter FROM organizations WHERE ID=?", [$org_id]);
		$org_parents = $this->db->nextRow();
		if( $org_parents == null ) return null;
		if( $org_parents["chapter"] != "" ) {
			$this->db->query( $parentQuery, [$org_parents["globe"], $org_parents["nation"], $org_parents["region"], $org_parents["domain"], ""] );
		} else if ( $org_parents["domain"] != "" ) {
			$this->db->query( $parentQuery, [$org_parents["globe"], $org_parents["nation"], $org_parents["region"], "", ""] );
		} else if ( $org_parents["region"] != "" ) {
			$this->db->query( $parentQuery, [$org_parents["globe"], $org_parents["nation"], "", "", ""] );
		} else if ( $org_parents["nation"] != "" ) {
			$this->db->query( $parentQuery, [$org_parents["globe"], "", "", "", ""] );
		} else {
			$this->db->query( $parentQuery, ["", "", "", "", ""] );
		}
		$parent_info = $this->db->nextRow();
		if( $parent_info == null ) return null;
		return $parent_info["id"];
	}

	function readOrganizationsByIDs( $id_array ) {
		if ( count( $id_array) == 0 ) {
			return array();
		}
		$placeholders = implode(",", array_fill(0, count($id_array), "?"));
		$query = "SELECT * FROM organizations WHERE id IN ($placeholders) AND active=1 ORDER BY globe, nation, region, domain, chapter";
		$rs = $this->db->query( $query, $id_array );
		return $rs->getAllRows();
	}

	function getOrgSTID( $org_id ) {
		$query = "SELECT globe, nation, region, domain, chapter, admin_user_id FROM organizations WHERE id=?";
		$this->db->query($query, [$org_id]);
		$result = $this->db->nextRow();
		if( $result == null ) return null;
		if( $result['admin_user_id'] != "" && $result["admin_user_id"] != 0 ) {
			return $result["admin_user_id"];
		} else {
			$parent_org_id = $this->getParentOrgID( $org_id );
			return $this->getOrgSTID( $parent_org_id );
		}
	}

	function readByUserID( $user_id ) {
		$query="SELECT o.* FROM users u LEFT JOIN organizations o ON u.org_id = o.id WHERE u.id=?";
		$this->db->query( $query, [$user_id] );
		$result = $this->db->nextRow();
		return $this->_organizationFromRow( $result );
	}

	function readByVSSID( $vss_id ) {
		$query="SELECT o.* FROM vsss v LEFT JOIN organizations o ON v.org_id = o.id WHERE v.id=?";
		$this->db->query( $query, [$vss_id] );
		$result = $this->db->nextRow();
		return $this->_organizationFromRow( $result );
	}

	function readLocalOrganizations( $globe = null, $nation = null, $region = null, $domain = null, $chapter = null ) {
		$query="SELECT o.*, o.admin_user_id as storyteller_id from organizations o WHERE o.chapter='' ";
		$params = [];
		if( $domain != null ) {
			$query .= " AND o.domain=?";
			$params[] = $domain;
		} else if ( $region != null ) {
			$query .= " AND o.domain='' AND o.region=?";
			$params[] = $region;
		} else if ( $nation != null ) {
			$query .= " AND o.domain='' AND o.region='' AND o.nation=?";
			$params[] = $nation;
		} else if ( $globe != null ) {
			$query .= " AND o.domain='' AND o.region='' AND o.nation='' and o.globe=?";
			$params[] = $globe;
		}
		$query.= " ORDER by o.org_name, o.nation, o.region, o.domain, o.chapter";
		$this->db->query($query, $params);
		return $this->db->getAllRows();
	}

	/**
	 * Return the mappable organizations for the public map, as name/city/state/contact rows.
	 *
	 * This is the dataset pushed to the Google Sheet that backs the domains map
	 * (see GoogleSheetsService). Only real, physical **domain-level** orgs are included:
	 * region/nation/globe entries (empty domain) are excluded, as are retired chapters
	 * (non-empty chapter), virtual games, and test entries (matched by "virtual"/"test" in
	 * the name, or "vir"/"test" in the domain code). Ordered by name. 'region_contact' is
	 * the role-based RST contact email of the
	 * region the org sits in (the region-level org's `email`, e.g.
	 * wrrst@wr.modernenigmasociety.org), so the map always shows a way to reach someone.
	 *
	 * @return array List of associative rows with keys 'org_name', 'city', 'state', 'region_contact'.
	 * @see GoogleSheetsService::syncOrganizations()
	 */
	function getMapRows() {
		$query = "SELECT o.org_name, o.city, o.state, " .
			"(SELECT r.email FROM organizations r " .
			" WHERE r.globe=o.globe AND r.nation=o.nation AND r.region=o.region " .
			"   AND r.domain='' AND r.chapter='' AND r.region<>'' LIMIT 1) AS region_contact " .
			"FROM organizations o " .
			"WHERE o.active=1 " .
			"  AND o.domain <> '' " .
			"  AND o.chapter = '' " .
			"  AND o.org_name NOT LIKE '%virtual%' AND o.org_name NOT LIKE '%test%' " .
			"  AND o.domain   NOT LIKE '%vir%'     AND o.domain   NOT LIKE '%test%' " .
			"ORDER BY o.org_name";
		$rs = $this->db->query( $query );
		return $rs->getAllRows();
	}
}