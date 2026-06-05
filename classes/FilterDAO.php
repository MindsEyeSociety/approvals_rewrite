<?php 
include_once("classes/Filter.php");

class FilterDAO {
	var $db;
	
	function __construct( $db ) {
		$this->db = $db;
	}

	function readByUserID( $user_id ) {
		$query = "SELECT * FROM preferences WHERE user_id=?";
		$this->db->query( $query, [$user_id] );
		if( $results = $this->db->nextRow() ) {
			$results['required_approval'] = explode(",",$results['required_approval']);
			if( $results['modinlast'] == 0 ) $results['modinlast'] = '';
		}
		return $results;
	}

	function deleteByUserID( $user_id ) {
		$query = "DELETE FROM preferences WHERE user_id=?";
		$this->db->query( $query, [$user_id] );
	}
	
	function create( $filters, $user_id ) { 
		if( is_array( $filters->required_approval ) ) {
			$required_approval = implode(',', $filters->required_approval);
		} else {
			$required_approval = '' ;
		}
		$query = "INSERT INTO preferences (user_id, venue, category, filter_user_id, status, search, org_id, modinlast, recentmod, suborgs, sortorder, inverted, required_approval, vss_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$this->db->query($query, [
			$user_id,
			$filters->venue,
			$filters->category,
			$filters->filter_user_id,
			$filters->status,
			$filters->search,
			$filters->org_id,
			$filters->modinlast,
			$filters->recentmod,
			$filters->suborgs,
			$filters->sortorder,
			$filters->inverted,
			$required_approval,
			$filters->vss_id
		]);
	}
}