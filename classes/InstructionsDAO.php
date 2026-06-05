<?php
include_once("classes/Organization.class.php");

class InstructionsDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function instructionsFromVSSID( $vss_id ) {
		$query = "SELECT i.instruction FROM instructions i ".
			"INNER JOIN vsss v ON i.vss_id = v.id ".
			"INNER JOIN venues ven ON v.venue_id = ven.id ".
			"WHERE i.vss_id = ? AND ven.active = 1";
		$this->db->query($query, [$vss_id]);
		if( $instruction = $this->db->nextRow() ) {
			return $instruction["instruction"];
		} else {
			return "";
		}
	}

	function instructionsFromOrgID( $org_id ) {
		$query = "SELECT instruction FROM instructions WHERE org_id = ?";
		$this->db->query($query, [$org_id]);
		if( $instruction = $this->db->nextRow() ) {
			return $instruction["instruction"];
		} else {
			return "";
		}
	}

	function readByOrganization( $organization ) {
		$query = "SELECT i.instruction, o.globe, o.nation, o.region, o.domain, o.chapter ".
			"from instructions i left join organizations o on i.org_id=o.id ".
			"where o.active = 1 AND (".
			"( globe=? and nation='' and region='' and domain='' and chapter='' ) ".
			"OR ( globe=? and nation=? and region='' and domain='' and chapter='' ) ".
			"OR ( globe=? and nation=? and region=? and domain='' and chapter='' ) ".
			"OR ( globe=? and nation=? and region=? and domain=? and chapter='' ) ".
			"OR ( globe=? and nation=? and region=? and domain=? and chapter=? ) ".
			") order by globe, nation, region, domain, chapter";
		$this->db->query($query, [
			$organization->globe,
			$organization->globe, $organization->nation,
			$organization->globe, $organization->nation, $organization->region,
			$organization->globe, $organization->nation, $organization->region, $organization->domain,
			$organization->globe, $organization->nation, $organization->region, $organization->domain, $organization->chapter
		]);
		return $this->db->getAllRows();
	}
}