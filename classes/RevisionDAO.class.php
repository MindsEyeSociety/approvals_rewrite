<?php
include_once "Revision.class.php";

class RevisionDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	/**
	 * Inserts a Revision into the revisions table and sets its id from the new row.
	 * Takes the Revision by value (not by reference): only the object's `id` property
	 * is set after insert, and since objects are passed by handle in PHP, that
	 * mutation is visible to the caller without needing a reference parameter. This
	 * also lets callers pass a `new Revision(...)` expression directly without
	 * triggering the "Only variables should be passed by reference" notice.
	 *
	 * @param Revision $revision The revision to persist; its `id` is populated on return.
	 */
	function insert( $revision ) {
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