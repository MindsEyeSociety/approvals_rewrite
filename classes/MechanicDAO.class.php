<?php
include_once( "Mechanic.class.php");

class MechanicDAO {
	var $db;
	function __construct( $db ) {
		$this->db = $db;
	}

	function insert( &$mechanic ) {
		$query = "INSERT INTO Mechanics (venue_id, category, title, mechanics) VALUES (?, ?, ?, ?)";
		$this->db->query( $query, [
			$mechanic->venue_id,
			$mechanic->category,
			$mechanic->title,
			$mechanic->mechanics
		]);
		$mechanic->id = $this->db->getInsertID();
	}
}