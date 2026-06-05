<?php
include_once( "Category.class.php" );

class CategoryDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function readByID( $id ) {
		$query = "SELECT * FROM categories WHERE id=?";
		$this->db->query( $query, [$id] );
		return $this->db->nextRow();
	}

	function readOptions( $character_id = "" ) {
		$query="SELECT cat.category FROM categories cat ";
		if( $character_id != "" ) {
			$query .=
				"INNER JOIN category_venue cv on cv.category_id = cat.id ".
				"INNER JOIN characters c ON cv.venue_id = c.venue_id AND c.id=? ";
			$this->db->query($query, [$character_id]);
		} else {
			$query.="ORDER BY category";
			$this->db->query($query);
		}
		$rows = $this->db->getAllRows();
		$categories = array();
		foreach( $rows as $row ) {
			$categories[] = $row['category'];
		}
		return $categories;
	}

	function insert( $category ) {
		$this->db->query("INSERT INTO categories (category) VALUES (?)", [$category->category]);
		$category->id = $this->db->getInsertId();
		return $category;
	}

	function isDuplicate( $category ) {
		$query = "SELECT count(*) as duplicates FROM categories WHERE category = ?";
		$this->db->query($query, [$_POST["category"]]);
		$results = $this->db->nextRow();
		return $results['duplicates'] > 0;
	}

	function isAssignedVenue( $category, $venue_id ) {
		$query = "SELECT count(*) as assigned FROM category_venue WHERE category_id=? AND venue_id=?";
		$this->db->query( $query, [$category->id, $venue_id] );
		$results = $this->db->nextRow();
		return $results["assigned"] > 0;
	}

	function assignVenue( $category, $venue_id ) {
		$this->db->query("INSERT INTO category_venue (category_id, venue_id) VALUES (?, ?)", [$category->id, $venue_id]);
	}
}