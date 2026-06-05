<?php
include_once("Plotkit.class.php");

class PlotkitDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function insert( $plotkit ) {
		$this->db->query(
			"INSERT INTO plotkits (vss_id, title, start_date, end_date, storyteller_id, url, plot_details, venue_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
			[$plotkit->vss_id, $plotkit->title, $plotkit->start_date, $plotkit->end_date, $plotkit->storyteller_id, $plotkit->url, $plotkit->plot_details, $plotkit->venue_id]
		);
	}

	function readAll( $show_all_time = true ) {
		$query = "SELECT p.id, p.title, p.end_date, v.id as vss_id, v.name as vss_name, ".
			"o.id as org_id, o.org_name, p.storyteller_id, ".
			"unix_timestamp(p.start_date) as start_date, p.url ".
			"FROM plotkits p ".
			"LEFT JOIN vsss v on v.id=p.vss_id ".
			"LEFT JOIN organizations o on o.id=-p.vss_id ".
			"LEFT JOIN users u ON u.org_id=-p.vss_id ".
			"LEFT JOIN venues ven ON ven.id=p.venue_id ";
		if( $show_all_time ) {
			$query .= "AND date_sub( now(), interval 7 DAY ) < p.start_date AND date_add( now(), interval 30 DAY ) > p.start_date ";
		}
		$query .= "WHERE COALESCE(ven.active, 1) != 0 ORDER BY p.start_date asc";

		$result = $this->db->query($query);
		return $result->getAllRows();
	}

	function readByID( $id ) {
		$query = "SELECT p.*, v.name as vss_name, o.org_name as org_name, unix_timestamp(p.start_date) as start_date ".
			"FROM plotkits p ".
			"LEFT JOIN vsss v on p.vss_id=v.id ".
			"LEFT JOIN organizations o on -p.vss_id=o.id ".
			"WHERE p.id=?";
	    $this->db->query($query, [$id]);
	    return $this->db->nextRow();
	}
}