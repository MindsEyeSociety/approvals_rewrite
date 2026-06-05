<?php
include_once("classes/Venue.class.php");

class VenueDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function readByID( $id ) {
		$query = "SELECT id, venue FROM venues WHERE ID=?";
		$this->db->query( $query, [$id] );
		if( $data = $this->db->nextRow() ) {
			return new Venue( $data['id'], $data['venue']);
		} else {
			return null;
		}
	}

	function getVenueOptions() {
		$rs = $this->db->query( "SELECT id, venue FROM venues WHERE active = 1 ORDER BY venue" );
		return $rs->getAllRows();
	}

	function getVSSVenueOptions() {
		$rs = $this->db->query(
			"SELECT id, venue ".
			"FROM venues ".
			"WHERE venue <> 'Non Venue-Specific' AND active = 1 ".
			"ORDER BY venue" );
		return $rs->getAllRows();
	}

}