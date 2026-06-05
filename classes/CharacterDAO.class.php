<?php
include_once("classes/Character.class.php");

class CharacterDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function readByID( $character_id ) {
		$query =
			"SELECT * ".
			"FROM characters ".
			"WHERE ID=?";
		$this->db->query($query, [$character_id]);
		if( $character_info = $this->db->nextRow() ) {
			return new Character(
				$character_info["id"],
				$character_info["name"],
				$character_info["venue_id"],
				$character_info["subtype"],
				$character_info["user_id"],
				$character_info["char_type"],
				$character_info["org_id"],
				$character_info["vss_id"],
				$character_info["background"],
				$character_info["character_sheet"],
				$character_info["active"],
				$character_info["char_dead"],
				$character_info["last_updated"],
				$character_info["approved_in_vss"]
			);
		} else {
			return null;
		}
	}

	function create( &$character ) {
		$query="SELECT id ".
			"FROM characters ".
			"WHERE name=? AND user_id=? AND active=1";
		$this->db->query($query, [$character->name, $character->user_id]);
		if( $this->db->numRows() > 0 ) {
			return 0;
		}
		$query="INSERT INTO characters (".
			"  name,".
			"  venue_id,".
			"  subtype,".
			"  user_id,".
			"  active,".
			"  char_type,".
			"  org_id,".
			"  vss_id,".
			"  approved_in_vss,".
			"  last_updated,".
			"  background,".
			"  character_sheet,".
			"  char_dead".
			") VALUES (".
			"  ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?".
			")";
		$this->db->query($query, [
			$character->name,
			$character->venue_id,
			$character->subtype,
			$character->user_id,
			$character->active,
			$character->char_type,
			$character->org_id,
			$character->vss_id,
			$character->approved_in_vss,
			$character->last_updated,
			$character->background,
			$character->character_sheet,
			$character->char_dead
		]);
		$character->id = $this->db->getInsertId();
		return true;
	}

	function update( $character ) {
		$query="UPDATE characters SET".
			"  name=?,".
			"  venue_id=?,".
			"  subtype=?,".
			"  user_id=?,".
			"  active=?,".
			"  char_type=?,".
			"  org_id=?,".
			"  vss_id=?,".
			"  approved_in_vss=?,".
			"  last_updated=?,".
			"  background=?,".
			"  character_sheet=?,".
			"  char_dead=? ".
			"WHERE ID=?";
		$this->db->query($query, [
			$character->name,
			$character->venue_id,
			$character->subtype,
			$character->user_id,
			$character->active,
			$character->char_type,
			$character->org_id,
			$character->vss_id,
			$character->approved_in_vss,
			$character->last_updated,
			$character->background,
			$character->character_sheet,
			$character->char_dead,
			$character->id
		]);
	}

	function readByVSSs( $vss_ids ) {
		if( count($vss_ids) == 0 ) {
			return array();
		}
		$placeholders = implode(",", array_fill(0, count($vss_ids), "?"));
		$query="SELECT c.id, name, venue, char_type ".
			"FROM characters c left join venues v on v.id=c.venue_id ".
			"WHERE vss_id in ($placeholders) ".
			"AND c.active = 1 ".
			"ORDER BY char_type, name ";
		$this->db->query($query, $vss_ids);
		return $this->db->getAllRows();
	}

	function readByUserID( $user_id ) {
	  	$query="SELECT c.id, name, venue, char_type ".
	  		"FROM characters c left join venues v on v.id=c.venue_id ".
	  		"WHERE user_id=? and c.active = 1 order by char_type, name ";
		$this->db->query( $query, [$user_id] );
		return $this->db->getAllRows();
	}

	function getSubtypesForVenue( $venue_id ) {
		$query = "SELECT subtype FROM character_subtypes WHERE venue_id = ? ORDER BY subtype";
		$this->db->query( $query, [$venue_id] );
		return $this->db->getAllRows();
	}
}
?>