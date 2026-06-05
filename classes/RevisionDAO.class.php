<?php
include_once "Revision.class.php";

class RevisionDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function insert( &$revision ) {
		$query = "INSERT INTO revisions (application_id, user_id, revision_date, revision) VALUES (?, ?, now(), ?)";
		$this->db->query( $query, [$revision->application_id, $revision->user_id, $revision->revision] );
		$revision->id = $this->db->getInsertID();
	}

	function readByApplicationID( $applicationID ) {
		$query="SELECT r.revision, unix_timestamp(r.revision_date) as revision_date, r.user_id FROM revisions r WHERE r.application_id=? ORDER BY revision_date asc";
		$res = $this->db->query( $query, [$applicationID] );
		return $res->getAllRows();
	}
}