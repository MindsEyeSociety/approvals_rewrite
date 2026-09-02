<?php
include_once( "classes/VSS.class.php");

class VSSDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function deleteByIDs( $vss_id ) {
		if( !is_array( $vss_id ) ) {
			$vss_id = array($vss_id);
		}
		if( count( $vss_id ) == 0 ) {
			return;
		}
		$placeholders = implode(",", array_fill(0, count($vss_id), "?"));
		$query="DELETE FROM vsss ".
			"WHERE id in ($placeholders)";
		$this->db->query( $query, $vss_id );
	}

	function getVSSSTID( $vss_id ) {
		$query = "SELECT storyteller_id FROM vsss WHERE id=?";
		$this->db->query($query, [$vss_id]);
		$result = $this->db->nextRow();
		return $result["storyteller_id"];
	}

	function readVSSsByID( $ids ) {
		if( count($ids) == 0 ) {
			return array();
		} else {
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			$query= "SELECT v.* ".
				"FROM vsss v ".
				"LEFT JOIN organizations o ON v.org_id = o.id ".
				"LEFT JOIN venues n ON v.venue_id = n.id ".
				"WHERE v.id IN ($placeholders) ".
				"AND o.active = 1 ".
				"AND n.active = 1 ".
				"ORDER BY v.name";
		  	$rs = $this->db->query($query, $ids);
		  	return $rs->getAllRows();
		}
	}

	function readVSSByID( $id ) {
		$vsss = $this->readVSSsByID( array($id) );
		return $vsss[0] ?? null;
	}

	function readByID( $vss_id ) {
		$vss = $this->readVSSsByID( array($vss_id) );
		if( count( $vss ) == 0 ) {
			return null;
		}
		return new VSS(
			$vss[0]['name'],
			$vss[0]['email'],
			$vss[0]['venue_id'],
			$vss[0]['storyteller_id'],
			$vss[0]['org_id'],
			$vss[0]['vss'],
			$vss[0]['id']
		);
	}

	function readNamesBySTID( $storyteller_id ) {
		$query = "SELECT id, name FROM vsss WHERE storyteller_id=?";
		$result = $this->db->query($query, [$storyteller_id]);
		return $result->getAllRows();
	}

	function readVSSCount( $showall, $org_id ) {
		$query="SELECT count(*) AS vssCount ".
			 " from vsss v".
			 " left join organizations o on v.org_id=o.id".
			 " left join venues n on v.venue_id=n.id";
		if ( $showall ) {
			$query.= " WHERE n.active = 1 AND o.active = 1";
			$this->db->query($query);
		} else {
			$query.= " WHERE v.org_id = ? AND n.active = 1 AND o.active = 1";
			$this->db->query($query, [$org_id]);
		}
		$row = $this->db->nextRow();
		return $row ? $row["vssCount"] : 0;
	}

	function readVSSs( $showAll, $org_id, $skip ) {
		$query="SELECT v.id, v.storyteller_id, v.org_id, v.name, v.email, v.modified, ".
			" o.nation, o.region, o.domain, o.chapter, o.org_name,o.city,o.state,o.country,".
				" n.venue ".
				" from vsss v".
				" left join organizations o on v.org_id=o.id".
				" left join venues n on v.venue_id=n.id";
		if ( !isset($showAll) || !$showAll ) {
			$query.= " WHERE v.org_id = ? AND n.active = 1 AND o.active = 1 ORDER BY o.nation, o.region, o.domain, o.chapter, n.venue";
			$res = $this->db->query($query, [$org_id]);
		} else {
			$query.= " WHERE n.active = 1 AND o.active = 1 ORDER BY o.nation, o.region, o.domain, o.chapter, n.venue";
			$res = $this->db->query($query);
		}
		$rows = array();
		while( $row = $res->nextRow() ) {
			$rows[] = $row;
		}
		return $rows;
	}

	function insert( $vss ) {
		$query =
			"INSERT INTO vsss (".
			"  venue_id,".
			"  org_id,".
			"  storyteller_id,".
			"  name,".
			"  email,".
			"  vss ,".
			"  modified ".
			") VALUES ( ".
			"  ?, ?, ?, ?, ?, ?, ?".
			")";
		$this->db->query( $query, [
			$vss->venue_id,
			$vss->organization_id,
			$vss->storyteller_id,
			$vss->name,
			$vss->email,
			$vss->vss,
			time()
		] );
		$vss->id = $this->db->getInsertId();
		return $vss;
	}

	function readVenueID( $vss_id ) {
		$rs = $this->db->query("SELECT venue_id from vsss where id = ?", [$vss_id]);
		$data = $rs->nextRow();
		return $data['venue_id'];
	}

	function readLocalVenueVSSs( $venue_id, $globe = null, $nation = null, $region = null, $domain = null, $chapter = null ) {
		$query="SELECT v.*, v.id AS id, v.name as vssname, o.nation, o.region, o.domain, o.chapter, o.org_name, n.venue ".
			" from organizations o".
			" left join vsss v on v.org_id=o.id".
			" left join venues n on v.venue_id=n.id".
			" WHERE venue_id=? AND n.active = 1";
		$params = [$venue_id];
		if ( $chapter != null ) {
			$query .= " AND o.chapter=?";
			$params[] = $chapter;
		} else if( $domain != null ) {
			$query .= " AND o.domain=?";
			$params[] = $domain;
		} else if ( $region != null ) {
			$query .= " AND o.region=?";
			$params[] = $region;
		} else if ( $nation != null ) {
			$query .= " AND o.nation=?";
			$params[] = $nation;
		} else if ( $globe != null ) {
			$query .= " AND o.globe=?";
			$params[] = $globe;
		}
		$query.= " order by o.org_name, o.nation, o.region, o.domain, o.chapter, n.venue";
		$this->db->query($query, $params);
		return $this->db->getAllRows();
	}

	function readVSSsByVenueID( $venue_id ) {
		$query="SELECT v.*, v.id as id, v.name as vssname, o.nation, o.region, o.domain, o.chapter, o.org_name, n.venue ".
			" FROM organizations o".
			" left join vsss v on v.org_id=o.id".
			" left join venues n on v.venue_id=n.id".
			" WHERE venue_id=? AND n.active = 1".
			" ORDER BY o.org_name, o.nation, o.region, o.domain, o.chapter, n.venue";
		$this->db->query($query, [$venue_id]);
		return $this->db->getAllRows();
	}
}